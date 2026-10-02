<?php

namespace App\Services\Marketing\Ai\Providers;

use App\Services\Marketing\Ai\AiImageProviderInterface;
use App\Services\Marketing\Ai\AiUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI image generation (gpt-image-1). Reuses the same OpenAI API key already
 * configured for text (config services.ai.providers.openai.api_key), overlaid
 * from the encrypted integration_settings store. Returns raw bytes; the caller
 * stores them via the existing media/storage layer. Never logs the key.
 */
class OpenAiImageProvider implements AiImageProviderInterface
{
    public function __construct(protected string $apiKey, protected string $model = 'gpt-image-1')
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

    public function generateImage(string $prompt, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('OpenAI API key not configured for image generation.');
        }

        $size = $options['size'] ?? '1024x1024';
        // gpt-image-1 supports 1024x1024, 1024x1536 (portrait), 1536x1024 (landscape).
        $allowed = ['1024x1024', '1024x1536', '1536x1024'];
        if (! in_array($size, $allowed, true)) {
            $size = '1024x1024';
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'size' => $size,
            'n' => 1,
        ];
        if (! empty($options['quality'])) {
            $payload['quality'] = $options['quality']; // low|medium|high
        }

        $response = Http::withToken($this->apiKey)->acceptJson()->timeout(120)
            ->post('https://api.openai.com/v1/images/generations', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI image error: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        $b64 = (string) $response->json('data.0.b64_json', '');
        if ($b64 === '') {
            // Some responses return a URL instead of base64.
            $url = (string) $response->json('data.0.url', '');
            if ($url !== '') {
                $img = Http::timeout(60)->get($url);
                if ($img->successful()) {
                    return $this->result($img->body(), $payload['model']);
                }
            }
            throw new \RuntimeException('OpenAI image error: empty response.');
        }

        $bytes = base64_decode($b64);
        if ($bytes === false) {
            throw new \RuntimeException('OpenAI image error: could not decode image.');
        }

        return $this->result($bytes, $payload['model']);
    }

    private function result(string $bytes, string $model): array
    {
        return [
            'bytes' => $bytes,
            'mime' => 'image/png',
            'provider' => 'openai',
            'model' => $model,
            'cost' => null, // OpenAI does not return per-call cost; tracked separately if configured
        ];
    }
}
