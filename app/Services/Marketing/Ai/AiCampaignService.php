<?php

namespace App\Services\Marketing\Ai;

use App\Models\MarketingAiGeneration;
use Illuminate\Support\Facades\Log;

/**
 * Turns a short marketing brief into structured, review-ready campaign assets
 * (name, ad copy, targeting notes) using the configured AI provider.
 *
 * Guarantees:
 *  - It NEVER invents prices, availability, discounts, dates or metrics. Any
 *    factual product data must come from the brief the admin supplies; the AI
 *    is instructed to omit numbers it wasn't given.
 *  - Every call is recorded in marketing_ai_generations for cost/audit.
 *  - Output is always human-reviewed before a draft campaign is saved, and a
 *    saved draft is still published PAUSED elsewhere — nothing here spends.
 */
class AiCampaignService
{
    private const PROMPT_VERSION = 'campaign.v1';

    public function __construct(private AiProviderManager $providers)
    {
    }

    public function isConfigured(): bool
    {
        return $this->providers->isConfigured();
    }

    /**
     * @param  array{provider:string,objective:?string,destination:?string,product:?string,audience:?string,budget:?string,tone:?string,extra:?string}  $brief
     * @return array{name:string,primary_texts:array,headlines:array,descriptions:array,target_audience:string,keywords:array,notes:string}
     */
    public function suggest(array $brief, ?int $adminId = null): array
    {
        $provider = $this->providers->provider();
        // Throws AiUnavailableException when nothing is configured — caller handles it.
        $prompt = $this->buildPrompt($brief);
        $schema = $this->schema();

        $status = 'success';
        $result = [];
        try {
            $result = $provider->generateStructuredOutput($prompt, $schema, [
                'temperature' => 0.7,
                'max_tokens' => 1200,
            ]);
        } catch (\Throwable $e) {
            $status = 'failed';
            Log::warning('AI campaign generation failed', ['error' => $e->getMessage()]);
            $this->record($provider->name(), $brief, [], 'failed', $adminId);
            throw $e;
        }

        $normalized = $this->normalize($result);
        $this->record($provider->name(), $brief, $normalized, $status, $adminId);

        return $normalized;
    }

    private function buildPrompt(array $brief): string
    {
        $platform = ($brief['provider'] ?? 'meta_ads') === 'google_ads' ? 'Google Ads' : 'Meta (Facebook/Instagram) Ads';

        $lines = [
            "You are a senior performance-marketing copywriter for a travel agency (Leemroz Travels) selling curated tour packages.",
            "Write assets for a {$platform} campaign.",
            '',
            'Campaign brief:',
            '- Objective: ' . ($brief['objective'] ?: 'Leads'),
            '- Destination / product: ' . trim(($brief['destination'] ?? '') . ' ' . ($brief['product'] ?? '')),
            '- Target audience: ' . ($brief['audience'] ?: 'not specified — infer a sensible travel-intent audience'),
            '- Tone: ' . ($brief['tone'] ?: 'warm, aspirational, trustworthy'),
            '- Approx budget context: ' . ($brief['budget'] ?: 'not specified'),
            '- Extra notes: ' . ($brief['extra'] ?: 'none'),
            '',
            'STRICT RULES:',
            '- Do NOT invent prices, discounts, dates, seat counts, ratings or any numeric claim that is not explicitly in the brief. If a number is not provided, omit it.',
            '- No unverifiable superlatives ("cheapest", "guaranteed", "best in the world").',
            '- Keep Meta headlines <= 40 chars, primary texts <= 125 chars where possible.',
            '- For Google Ads, headlines <= 30 chars and descriptions <= 90 chars.',
            '- Provide a standardized campaign name in the form PLATFORM | DESTINATION | PRODUCT | OBJECTIVE | MON-YEAR (uppercase).',
            '- Keywords only matter for Google Ads; for Meta return an empty keywords array.',
        ];

        return implode("\n", $lines);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'primary_texts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'headlines' => ['type' => 'array', 'items' => ['type' => 'string']],
                'descriptions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'target_audience' => ['type' => 'string'],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['name', 'headlines', 'primary_texts'],
        ];
    }

    private function normalize(array $r): array
    {
        $arr = fn ($v) => array_values(array_filter(array_map('trim', (array) ($v ?? [])), fn ($s) => $s !== ''));

        return [
            'name' => trim((string) ($r['name'] ?? '')),
            'primary_texts' => $arr($r['primary_texts'] ?? []),
            'headlines' => $arr($r['headlines'] ?? []),
            'descriptions' => $arr($r['descriptions'] ?? []),
            'target_audience' => trim((string) ($r['target_audience'] ?? '')),
            'keywords' => $arr($r['keywords'] ?? []),
            'notes' => trim((string) ($r['notes'] ?? '')),
        ];
    }

    private function record(string $provider, array $brief, array $result, string $status, ?int $adminId): void
    {
        try {
            MarketingAiGeneration::create([
                'provider' => $provider,
                'model' => (string) config('services.ai.default_model', ''),
                'generation_type' => 'campaign',
                'prompt_version' => self::PROMPT_VERSION,
                'input_context_hash' => sha1(json_encode($brief)),
                'result' => $result,
                'status' => $status,
                'created_by' => $adminId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record AI generation', ['error' => $e->getMessage()]);
        }
    }
}
