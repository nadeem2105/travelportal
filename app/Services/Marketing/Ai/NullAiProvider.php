<?php

namespace App\Services\Marketing\Ai;

/**
 * Safe fallback used when no AI provider is configured. It never fabricates
 * output — it signals unavailability so the UI can show "AI not configured"
 * rather than fake results.
 */
class NullAiProvider implements AiProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        throw new AiUnavailableException('No AI provider is configured. Set AI_DEFAULT_PROVIDER and the provider API key.');
    }

    public function generateStructuredOutput(string $prompt, array $schema, array $options = []): array
    {
        throw new AiUnavailableException('No AI provider is configured.');
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        throw new AiUnavailableException('No AI provider is configured.');
    }
}
