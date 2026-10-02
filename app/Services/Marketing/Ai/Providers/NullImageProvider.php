<?php

namespace App\Services\Marketing\Ai\Providers;

use App\Services\Marketing\Ai\AiImageProviderInterface;
use App\Services\Marketing\Ai\AiUnavailableException;

/**
 * Safe fallback when no image provider is configured. Never fabricates an image —
 * callers check isConfigured() and show "Connect an image provider" instead.
 */
class NullImageProvider implements AiImageProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function generateImage(string $prompt, array $options = []): array
    {
        throw new AiUnavailableException('No AI image provider is configured. Connect one in AI settings.');
    }
}
