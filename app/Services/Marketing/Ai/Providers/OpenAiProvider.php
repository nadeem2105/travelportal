<?php

namespace App\Services\Marketing\Ai\Providers;

use App\Services\Marketing\Ai\AiProviderInterface;
use App\Services\Marketing\Ai\AiUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Chat Completions adapter. Structured output uses JSON response format.
 * Keys come from config (never logged). Failures throw — callers surface them
 * instead of fabricating content.
 */
class OpenAiProvider implements AiProviderInterface
{
    public function __construct(protected string $apiKey, protected string $model = 'gpt-4o-mini')
    {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function name(): string
    {
        return 'openai';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $json = $this->completion($prompt, $options);

        return (string) data_get($json, 'choices.0.message.content', '');
    }

    public function generateStructuredOutput(string $prompt, array $schema, array $options = []): array
    {
        $instruction = $prompt . "\n\nRespond ONLY with valid JSON matching this shape: " . json_encode($schema);
        $json = $this->completion($instruction, array_merge($options, ['json' => true]));
        $content = (string) data_get($json, 'choices.0.message.content', '');
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function completion(string $prompt, array $options): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('OpenAI API key not configured.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $options['system'] ?? 'You are a precise travel marketing assistant. Never invent prices, availability, discounts or metrics; if data is missing, say so.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
        ];
        if (! empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey)->acceptJson()->timeout(60)
            ->post($this->endpoint(), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorLabel() . ' error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        return $response->json() ?? [];
    }

    /** Chat Completions endpoint — overridden by OpenAI-compatible providers (e.g. Groq). */
    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    /** Human label used in error messages. */
    protected function errorLabel(): string
    {
        return 'OpenAI';
    }

    /**
     * Multi-turn chat with native tool calling (function calling). Translates
     * the provider-agnostic message/tool shapes to the OpenAI schema and back.
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException($this->errorLabel() . ' API key not configured.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->toOpenAiMessages($messages, $options['system'] ?? null),
            'temperature' => $options['temperature'] ?? 0.3,
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn (array $t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
                ],
            ], $tools);
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($this->apiKey)->acceptJson()->timeout(60)
            ->post($this->endpoint(), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorLabel() . ' error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        $choice = $response->json('choices.0') ?? [];
        $toolCalls = [];
        foreach (data_get($choice, 'message.tool_calls', []) ?? [] as $call) {
            $args = json_decode((string) data_get($call, 'function.arguments', '{}'), true);
            $toolCalls[] = [
                'id' => (string) data_get($call, 'id', ''),
                'name' => (string) data_get($call, 'function.name', ''),
                'arguments' => is_array($args) ? $args : [],
            ];
        }

        return [
            'content' => (string) data_get($choice, 'message.content', ''),
            'tool_calls' => $toolCalls,
            'finish_reason' => (string) data_get($choice, 'finish_reason', 'stop'),
        ];
    }

    /** Convert normalized messages to the OpenAI Chat Completions schema. */
    private function toOpenAiMessages(array $messages, ?string $system): array
    {
        $out = [];
        if ($system) {
            $out[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($m['tool_call_id'] ?? ''),
                    'content' => (string) ($m['content'] ?? ''),
                ];
                continue;
            }

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $out[] = [
                    'role' => 'assistant',
                    'content' => $m['content'] ?? null,
                    'tool_calls' => array_map(fn (array $c) => [
                        'id' => $c['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $c['name'],
                            'arguments' => json_encode($c['arguments'] ?? []),
                        ],
                    ], $m['tool_calls']),
                ];
                continue;
            }

            $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        return $out;
    }
}
