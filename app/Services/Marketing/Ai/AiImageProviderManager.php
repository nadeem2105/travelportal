<?php

namespace App\Services\Marketing\Ai;

use App\Services\Marketing\Ai\Providers\NullImageProvider;
use App\Services\Marketing\Ai\Providers\OpenAiImageProvider;

/**
 * Resolves the active AI image provider from config('services.ai'). Image
 * generation is gated behind the `enable_image` flag AND a configured key; when
 * either is missing a NullImageProvider is returned so the UI can show
 * "Connect an image provider" without breaking. Credentials come from the same
 * encrypted integration_settings overlay used by the text providers.
 */
class AiImageProviderManager
{
    public function provider(?string $name = null): AiImageProviderInterface
    {
        if (! (bool) config('services.ai.enable_image', false)) {
            return new NullImageProvider();
        }

        $name = $name ?: (string) config('services.ai.image_provider', 'openai');

        return match ($name) {
            'openai' => $this->openai(),
            default => new NullImageProvider(),
        };
    }

    private function openai(): AiImageProviderInterface
    {
        $key = (string) config('services.ai.providers.openai.api_key', '');
        if ($key === '') {
            return new NullImageProvider();
        }

        $model = (string) config('services.ai.image_model', 'gpt-image-1');

        return new OpenAiImageProvider($key, $model ?: 'gpt-image-1');
    }

    public function isConfigured(?string $name = null): bool
    {
        return $this->provider($name)->isConfigured();
    }
}
