<?php

namespace App\Services\Marketing\Ai\Providers;

/**
 * OpenRouter adapter. OpenRouter exposes an OpenAI-compatible Chat Completions
 * API (same request/response shape, tool calling and JSON mode) that proxies to
 * many upstream models, so this reuses OpenAiProvider's logic and only points it
 * at OpenRouter's endpoint. Model names use the "vendor/model" form, e.g.
 * openai/gpt-4o-mini, anthropic/claude-3.5-sonnet, google/gemini-flash-1.5,
 * meta-llama/llama-3.3-70b-instruct.
 */
class OpenRouterProvider extends OpenAiProvider
{
    public function __construct(string $apiKey, string $model = 'openai/gpt-4o-mini')
    {
        parent::__construct($apiKey, $model);
    }

    public function name(): string
    {
        return 'openrouter';
    }

    protected function endpoint(): string
    {
        return 'https://openrouter.ai/api/v1/chat/completions';
    }

    protected function errorLabel(): string
    {
        return 'OpenRouter';
    }
}
