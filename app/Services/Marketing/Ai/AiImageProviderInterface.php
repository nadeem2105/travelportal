<?php

namespace App\Services\Marketing\Ai;

/**
 * Provider-agnostic AI image generation contract (§29). Kept separate from the
 * text AiProviderInterface so image and text providers can be swapped/configured
 * independently. Implementations MUST throw (never fabricate) on failure so the
 * studio surfaces a real error and can retry.
 */
interface AiImageProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Generate an image from a prompt.
     *
     * @param  array  $options  size ('1024x1024'|'1024x1536'|'1536x1024'), quality, style
     * @return array{bytes:string, mime:string, provider:string, model:string, cost:?float}
     */
    public function generateImage(string $prompt, array $options = []): array;
}
