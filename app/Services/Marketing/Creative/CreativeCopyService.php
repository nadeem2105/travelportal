<?php

namespace App\Services\Marketing\Creative;

use App\Models\MarketingAiGeneration;
use App\Services\Marketing\Ai\AiProviderManager;
use Illuminate\Support\Facades\Log;

/**
 * Generates ad copy (headline/primary text/description/hook/CTA) for a creative
 * using the configured TEXT provider. Given ONLY the authoritative facts from
 * CreativeContextService; strictly forbidden from inventing prices, dates,
 * discounts, ratings, seat counts or any number not supplied. Every call is
 * logged to marketing_ai_generations for cost/audit.
 */
class CreativeCopyService
{
    private const PROMPT_VERSION = 'creative_copy.v1';

    public function __construct(private AiProviderManager $providers)
    {
    }

    public function isConfigured(): bool
    {
        return $this->providers->isConfigured();
    }

    /**
     * @param  array  $facts     resolved authoritative facts
     * @param  array  $opts      objective, audience, audience_detail, language, focus, style, cta
     * @return array{headline:string,primary_text:string,description:string,hook:string,cta:string,alt_headlines:array}
     */
    public function generate(array $facts, array $opts, ?int $adminId = null): array
    {
        $provider = $this->providers->provider();
        $prompt = $this->buildPrompt($facts, $opts);
        $schema = $this->schema();

        $status = 'success';
        $result = [];
        try {
            $result = $provider->generateStructuredOutput($prompt, $schema, [
                'temperature' => 0.75,
                'max_tokens' => 900,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Creative copy generation failed', ['error' => $e->getMessage()]);
            $this->record($provider->name(), $facts, $opts, [], 'failed', $adminId);
            throw $e;
        }

        $normalized = $this->normalize($result, $opts);
        $this->record($provider->name(), $facts, $opts, $normalized, $status, $adminId);

        return $normalized;
    }

    private function buildPrompt(array $facts, array $opts): string
    {
        $lang = $this->languageLabel($opts['language'] ?? 'en');
        $focus = $opts['focus'] ?? 'experience';
        $objective = $opts['objective'] ?? 'lead_generation';
        $audience = trim(($opts['audience'] ?? '') . ' ' . ($opts['audience_detail'] ?? '')) ?: 'travel-intent audience';

        // Only pass facts that actually exist — the model must not fill gaps.
        $known = [];
        foreach ([
            'package_name' => 'Package', 'destination' => 'Destination', 'duration' => 'Duration',
            'price_formatted' => 'Price', 'old_price_formatted' => 'Original price', 'discount' => 'Discount',
            'hotel_name' => 'Hotel', 'best_time' => 'Best time',
        ] as $key => $label) {
            if (! empty($facts[$key])) {
                $known[] = "- {$label}: {$facts[$key]}";
            }
        }
        if (! empty($facts['highlights'])) {
            $known[] = '- Highlights: ' . implode(', ', array_slice((array) $facts['highlights'], 0, 6));
        }
        if (! empty($facts['inclusions'])) {
            $known[] = '- Inclusions: ' . implode(', ', array_slice((array) $facts['inclusions'], 0, 6));
        }

        $lines = [
            "You are a senior travel-advertising copywriter for {$facts['company']}.",
            "Write ad copy in {$lang} for a social/paid ad.",
            "Creative angle/focus: {$focus}. Campaign objective: {$objective}. Target audience: {$audience}.",
            '',
            'AUTHORITATIVE FACTS (the ONLY facts you may state):',
            ...$known,
            '',
            'STRICT RULES:',
            '- NEVER invent or alter prices, discounts, durations, dates, ratings, seat counts or any number not listed above. If not listed, do not mention it.',
            '- No false scarcity ("only 2 seats left") unless it appears in the facts above.',
            '- Keep the headline punchy (<= 40 chars). Primary text <= 125 chars. Description <= 90 chars. Hook <= 60 chars.',
            '- The CTA must be a short action phrase suitable for the objective.',
            '- Match the tone to the audience and focus. Output ' . $lang . ' only.',
        ];

        return implode("\n", array_filter($lines, fn ($l) => $l !== null));
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'primary_text' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'hook' => ['type' => 'string'],
                'cta' => ['type' => 'string'],
                'alt_headlines' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['headline', 'primary_text', 'cta'],
        ];
    }

    private function normalize(array $r, array $opts): array
    {
        $s = fn ($v) => trim((string) ($v ?? ''));
        $arr = fn ($v) => array_values(array_filter(array_map('trim', (array) ($v ?? [])), fn ($x) => $x !== ''));

        return [
            'headline' => $s($r['headline'] ?? ''),
            'primary_text' => $s($r['primary_text'] ?? ''),
            'description' => $s($r['description'] ?? ''),
            'hook' => $s($r['hook'] ?? ''),
            'cta' => $s($r['cta'] ?? '') ?: ($opts['cta'] ?? 'Book Now'),
            'alt_headlines' => $arr($r['alt_headlines'] ?? []),
        ];
    }

    private function languageLabel(string $code): string
    {
        return [
            'en' => 'English', 'hi' => 'Hindi', 'ur' => 'Urdu', 'hinglish' => 'Hinglish (Roman Hindi + English mix)',
        ][$code] ?? 'English';
    }

    private function record(string $provider, array $facts, array $opts, array $result, string $status, ?int $adminId): void
    {
        try {
            MarketingAiGeneration::create([
                'provider' => $provider,
                'model' => (string) config('services.ai.default_model', ''),
                'generation_type' => 'copy',
                'prompt_version' => self::PROMPT_VERSION,
                'input_context_hash' => sha1(json_encode([$facts['product_id'] ?? null, $opts])),
                'result' => $result,
                'status' => $status,
                'created_by' => $adminId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record creative copy generation', ['error' => $e->getMessage()]);
        }
    }
}
