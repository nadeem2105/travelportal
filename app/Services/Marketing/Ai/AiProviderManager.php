<?php

namespace App\Services\Marketing\Ai;

use App\Services\Marketing\Ai\Providers\AnthropicProvider;
use App\Services\Marketing\Ai\Providers\GeminiProvider;
use App\Services\Marketing\Ai\Providers\GroqProvider;
use App\Services\Marketing\Ai\Providers\OpenAiProvider;
use App\Services\Marketing\Ai\Providers\OpenRouterProvider;

/**
 * Resolves the active AI provider from config (services.ai). Returns a
 * NullAiProvider when nothing is configured so callers always get a valid
 * object and can check isConfigured().
 */
class AiProviderManager
{
    public function provider(?string $name = null): AiProviderInterface
    {
        $name = $name ?: (string) config('services.ai.default_provider', 'openai');
        $key = (string) config("services.ai.providers.{$name}.api_key", '');

        if ($key === '') {
            return new NullAiProvider();
        }

        // Model resolution, per provider, so a model name is NEVER inherited
        // across providers (e.g. gpt-4o-mini must never reach Gemini):
        //   1. the provider's own configured model, else
        //   2. a sane per-provider default.
        // The shared services.ai.default_model only applies to whichever
        // provider is the configured default_provider (its historical meaning),
        // never to other providers.
        $ownModel = (string) config("services.ai.providers.{$name}.model", '');
        $sharedDefault = ($name === (string) config('services.ai.default_provider', 'openai'))
            ? (string) config('services.ai.default_model', '')
            : '';

        $perProviderDefault = [
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-3-5-sonnet-latest',
            'gemini' => 'gemini-1.5-flash',
            'groq' => 'llama-3.3-70b-versatile',
            'openrouter' => 'openai/gpt-4o-mini',
        ][$name] ?? '';

        $model = $ownModel ?: ($sharedDefault ?: $perProviderDefault);

        // Guard: if the resolved model clearly belongs to another vendor, fall
        // back to this provider's own default so a stale shared setting can't
        // send a foreign model name to the API.
        if (! $this->modelMatchesProvider($model, $name)) {
            $model = $perProviderDefault;
        }

        return match ($name) {
            'openai' => new OpenAiProvider($key, $model),
            'anthropic' => new AnthropicProvider($key, $model),
            'gemini' => new GeminiProvider($key, $model),
            'groq' => new GroqProvider($key, $model),
            'openrouter' => new OpenRouterProvider($key, $model),
            default => new NullAiProvider(),
        };
    }

    /** Loose sanity check that a model name belongs to the given provider. */
    private function modelMatchesProvider(string $model, string $provider): bool
    {
        $model = strtolower($model);

        return match ($provider) {
            'openai' => str_contains($model, 'gpt') || str_contains($model, 'o1') || str_contains($model, 'o3'),
            'anthropic' => str_contains($model, 'claude'),
            'gemini' => str_contains($model, 'gemini'),
            // Groq hosts many open models (llama, mixtral, gemma, qwen, deepseek…);
            // accept anything that isn't clearly an OpenAI/Anthropic/Gemini name.
            'groq' => ! str_contains($model, 'gpt') && ! str_contains($model, 'claude') && ! str_contains($model, 'gemini'),
            // OpenRouter proxies every vendor; model names are "vendor/model"
            // (openai/…, anthropic/…, google/…), so any name is valid here.
            'openrouter' => true,
            default => true,
        };
    }

    public function isConfigured(?string $name = null): bool
    {
        return $this->provider($name)->isConfigured();
    }
}
