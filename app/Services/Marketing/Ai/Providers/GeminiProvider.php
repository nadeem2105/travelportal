<?php

namespace App\Services\Marketing\Ai\Providers;

use App\Services\Marketing\Ai\AiProviderInterface;
use App\Services\Marketing\Ai\AiUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (Generative Language API) adapter.
 */
class GeminiProvider implements AiProviderInterface
{
    public function __construct(private string $apiKey, private ?string $model = null)
    {
        $this->model = $model ?: 'gemini-1.5-flash';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $json = $this->generate($prompt, $options);

        return (string) data_get($json, 'candidates.0.content.parts.0.text', '');
    }

    public function generateStructuredOutput(string $prompt, array $schema, array $options = []): array
    {
        $instruction = $prompt . "\n\nRespond ONLY with valid JSON matching: " . json_encode($schema);
        $text = $this->generateText($instruction, array_merge($options, ['json' => true]));
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function generate(string $prompt, array $options): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('Gemini API key not configured.');
        }

        $model = $options['model'] ?? $this->model;
        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
        ];
        if (! empty($options['json'])) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $response = Http::acceptJson()->timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
            $body
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        return $response->json() ?? [];
    }

    /**
     * Multi-turn chat with function calling. Translates the provider-agnostic
     * message/tool shapes to Gemini's contents/functionDeclarations schema.
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('Gemini API key not configured.');
        }

        $model = $options['model'] ?? $this->model;
        $body = ['contents' => $this->toGeminiContents($messages)];

        if (! empty($options['system'])) {
            $body['systemInstruction'] = ['parts' => [['text' => (string) $options['system']]]];
        }

        if (! empty($tools)) {
            $body['tools'] = [[
                'functionDeclarations' => array_map(fn (array $t) => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
                ], $tools),
            ]];
        }

        $response = Http::acceptJson()->timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
            $body
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        $text = '';
        $toolCalls = [];
        $i = 0;
        foreach ($response->json('candidates.0.content.parts', []) ?? [] as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . $i,
                    'name' => (string) data_get($part, 'functionCall.name', ''),
                    'arguments' => is_array(data_get($part, 'functionCall.args')) ? data_get($part, 'functionCall.args') : [],
                    // Gemini 2.5+/3.x return a thoughtSignature alongside each
                    // functionCall that MUST be echoed back on the next turn, or
                    // the API rejects the follow-up with "missing thought_signature".
                    'thought_signature' => data_get($part, 'thoughtSignature'),
                ];
            }
            $i++;
        }

        return [
            'content' => $text,
            'tool_calls' => $toolCalls,
            'finish_reason' => (string) $response->json('candidates.0.finishReason', 'STOP'),
        ];
    }

    /** Convert normalized messages to Gemini's contents schema. */
    private function toGeminiContents(array $messages): array
    {
        $out = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                continue; // carried via systemInstruction
            }

            if ($role === 'tool') {
                $out[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name' => (string) ($m['name'] ?? ''),
                        'response' => ['result' => (string) ($m['content'] ?? '')],
                    ],
                ]]];
                continue;
            }

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $parts = [];
                if (! empty($m['content'])) {
                    $parts[] = ['text' => (string) $m['content']];
                }
                foreach ($m['tool_calls'] as $c) {
                    $part = ['functionCall' => [
                        'name' => $c['name'],
                        'args' => (object) ($c['arguments'] ?? []),
                    ]];
                    // Echo back the thoughtSignature Gemini 2.5+/3.x issued for this
                    // call — required or the API rejects the follow-up turn.
                    if (! empty($c['thought_signature'])) {
                        $part['thoughtSignature'] = $c['thought_signature'];
                    }
                    $parts[] = $part;
                }
                $out[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            $out[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($m['content'] ?? '')]],
            ];
        }

        return $out;
    }
}
