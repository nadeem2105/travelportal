<?php

namespace App\Services\Marketing\Ai\Providers;

use App\Services\Marketing\Ai\AiProviderInterface;
use App\Services\Marketing\Ai\AiUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API adapter.
 */
class AnthropicProvider implements AiProviderInterface
{
    public function __construct(private string $apiKey, private ?string $model = null)
    {
        $this->model = $model ?: 'claude-3-5-sonnet-latest';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $json = $this->messages($prompt, $options);

        return (string) data_get($json, 'content.0.text', '');
    }

    public function generateStructuredOutput(string $prompt, array $schema, array $options = []): array
    {
        $instruction = $prompt . "\n\nRespond ONLY with valid JSON matching: " . json_encode($schema);
        $text = $this->generateText($instruction, $options);
        // Strip code fences if present.
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function messages(string $prompt, array $options): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('Anthropic API key not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->acceptJson()->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system' => $options['system'] ?? 'You are a precise travel marketing assistant. Never invent prices, availability, discounts or metrics.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        return $response->json() ?? [];
    }

    /**
     * Multi-turn chat with native tool use. Translates the provider-agnostic
     * message/tool shapes to Anthropic's content-block schema and back.
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('Anthropic API key not configured.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system' => $options['system'] ?? 'You are a precise travel assistant. Never invent prices, availability, bookings or metrics.',
            'messages' => $this->toAnthropicMessages($messages),
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn (array $t) => [
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'input_schema' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], $tools);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->acceptJson()->timeout(60)->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        $text = '';
        $toolCalls = [];
        foreach ($response->json('content', []) ?? [] as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return [
            'content' => $text,
            'tool_calls' => $toolCalls,
            'finish_reason' => (string) $response->json('stop_reason', 'end_turn'),
        ];
    }

    /** Convert normalized messages to Anthropic's content-block schema. */
    private function toAnthropicMessages(array $messages): array
    {
        $out = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            // Anthropic has no separate system role in the messages array.
            if ($role === 'system') {
                continue;
            }

            if ($role === 'tool') {
                $out[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => (string) ($m['tool_call_id'] ?? ''),
                        'content' => (string) ($m['content'] ?? ''),
                    ]],
                ];
                continue;
            }

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $blocks = [];
                if (! empty($m['content'])) {
                    $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];
                }
                foreach ($m['tool_calls'] as $c) {
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $c['id'],
                        'name' => $c['name'],
                        'input' => (object) ($c['arguments'] ?? []),
                    ];
                }
                $out[] = ['role' => 'assistant', 'content' => $blocks];
                continue;
            }

            $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        return $out;
    }
}
