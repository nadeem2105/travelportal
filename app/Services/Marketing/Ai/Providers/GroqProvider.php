<?php

namespace App\Services\Marketing\Ai\Providers;

/**
 * Groq adapter. Groq exposes an OpenAI-compatible Chat Completions API
 * (same request/response shape, tool calling and JSON mode), so this simply
 * reuses OpenAiProvider's logic and points it at Groq's endpoint. Groq hosts
 * open models such as llama-3.3-70b-versatile, mixtral, gemma and qwen.
 */
class GroqProvider extends OpenAiProvider
{
    public function __construct(string $apiKey, string $model = 'llama-3.3-70b-versatile')
    {
        parent::__construct($apiKey, $model);
    }

    public function name(): string
    {
        return 'groq';
    }

    protected function endpoint(): string
    {
        return 'https://api.groq.com/openai/v1/chat/completions';
    }

    protected function errorLabel(): string
    {
        return 'Groq';
    }
}
