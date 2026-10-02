<?php

namespace App\Services\Marketing\Creative;

use App\Jobs\GenerateCreativeJob;
use App\Models\CreativeBrandKit;
use App\Models\CreativeTemplate;
use App\Models\MarketingCreative;
use Illuminate\Support\Str;

/**
 * Orchestrates creating a studio creative: resolves authoritative facts, applies
 * the brand kit + optional template, generates copy (AI), builds tracking/UTM,
 * persists a draft creative and queues image generation + compositing. Normal
 * and AI-command flows both go through here — one path, no duplicate logic.
 */
class CreativeStudioService
{
    public function __construct(
        private CreativeContextService $context,
        private CreativeCopyService $copy,
    ) {
    }

    /**
     * @param  array  $input  product_type, product_id, objective, audience, audience_detail,
     *                        platform, format, language, style, image_source, template_id,
     *                        brand_kit_id, variation_focus, name
     */
    public function create(array $input, ?int $adminId = null): MarketingCreative
    {
        $facts = $this->context->resolve($input['product_type'] ?? 'custom', $input['product_id'] ?? null);
        $brandKit = $this->resolveBrandKit($input['brand_kit_id'] ?? null);
        $facts = array_merge($facts, $this->brandFacts($brandKit));

        $format = $input['format'] ?? 'ig_1x1';
        $objective = $input['objective'] ?? 'lead_generation';
        $cta = CreativeFormats::OBJECTIVE_CTA[$objective] ?? ($brandKit->default_cta ?? 'Book Now');

        // Copy: AI when configured, else a safe deterministic fallback from facts.
        $copySet = $this->buildCopy($facts, [
            'objective' => $objective,
            'audience' => CreativeFormats::AUDIENCES[$input['audience'] ?? ''] ?? ($input['audience'] ?? ''),
            'audience_detail' => $input['audience_detail'] ?? '',
            'language' => $input['language'] ?? 'en',
            'focus' => $input['variation_focus'] ?? 'experience',
            'style' => $input['style'] ?? 'premium',
            'cta' => $cta,
            'template_id' => $input['template_id'] ?? null,
        ], $facts, $adminId);

        $dims = CreativeFormats::dimensions($format);
        $variationGroup = $input['variation_group'] ?? (string) Str::uuid();

        $creative = MarketingCreative::create([
            'campaign_id' => $input['campaign_id'] ?? null,
            'brand_kit_id' => $brandKit->id,
            'template_id' => $input['template_id'] ?? null,
            'name' => $input['name'] ?? $this->defaultName($facts, $format),
            'type' => 'image',
            'product_type' => $input['product_type'] ?? 'custom',
            'product_id' => $input['product_id'] ?? null,
            'destination' => $facts['destination'] ?? null,
            'objective' => $objective,
            'audience' => $input['audience'] ?? null,
            'audience_detail' => $input['audience_detail'] ?? null,
            'platform' => CreativeFormats::platformOf($format),
            'format' => $format,
            'language' => $input['language'] ?? 'en',
            'style' => $input['style'] ?? 'premium',
            'headline' => $copySet['headline'],
            'copy' => $copySet,
            'spec' => [
                'facts' => $facts,
                'brand' => $this->brandFacts($brandKit),
                'image_source' => $input['image_source'] ?? 'portal', // portal|ai|combination
                'style' => $input['style'] ?? 'premium',
            ],
            'tracking' => $this->tracking($facts, $input, $variationGroup),
            'width' => $dims['width'],
            'height' => $dims['height'],
            'ai_generated' => true,
            'status' => 'active',
            'generation_status' => 'queued',
            'approval_status' => 'draft',
            'variation_group' => $variationGroup,
            'variation_focus' => $input['variation_focus'] ?? null,
            'version' => 1,
            'created_by' => $adminId,
        ]);

        GenerateCreativeJob::dispatch($creative->id);

        return $creative;
    }

    /** Produce N sibling variations with different creative focuses. */
    public function generateVariations(MarketingCreative $base, array $focuses, ?int $adminId = null): array
    {
        $created = [];
        foreach ($focuses as $focus) {
            $created[] = $this->create([
                'product_type' => $base->product_type,
                'product_id' => $base->product_id,
                'campaign_id' => $base->campaign_id,
                'brand_kit_id' => $base->brand_kit_id,
                'template_id' => $base->template_id,
                'objective' => $base->objective,
                'audience' => $base->audience,
                'audience_detail' => $base->audience_detail,
                'platform' => $base->platform,
                'format' => $base->format,
                'language' => $base->language,
                'style' => $base->style,
                'image_source' => $base->spec['image_source'] ?? 'portal',
                'variation_group' => $base->variation_group,
                'variation_focus' => $focus,
                'name' => $base->name . ' — ' . (CreativeFormats::VARIATION_FOCUS[$focus] ?? $focus),
            ], $adminId);
        }

        return $created;
    }

    private function buildCopy(array $facts, array $opts, array $factsForTemplate, ?int $adminId): array
    {
        // Template-driven copy takes precedence when a template with text is chosen.
        $template = ! empty($opts['template_id']) ? CreativeTemplate::find($opts['template_id']) : null;

        if ($this->copy->isConfigured()) {
            try {
                $set = $this->copy->generate($facts, $opts, $adminId);
                $set['cta'] = $set['cta'] ?: ($opts['cta'] ?? 'Book Now');

                return $set;
            } catch (\Throwable $e) {
                // fall through to deterministic copy
            }
        }

        // Deterministic fallback (no invented facts) — uses template or facts only.
        $headline = $template
            ? CreativeTemplate::render($template->headline_template, $factsForTemplate)
            : trim(($facts['destination'] ?? $facts['name'] ?? 'Your Next Trip'));
        $primary = $template
            ? CreativeTemplate::render($template->primary_text_template, $factsForTemplate)
            : trim(($facts['short_description'] ?? ''));

        return [
            'headline' => $headline ?: ($facts['name'] ?? 'Plan Your Trip'),
            'primary_text' => $primary,
            'description' => $facts['duration'] ?? '',
            'hook' => '',
            'cta' => $opts['cta'] ?? 'Book Now',
            'alt_headlines' => [],
        ];
    }

    private function tracking(array $facts, array $input, string $variationGroup): array
    {
        $platform = CreativeFormats::platformOf($input['format'] ?? 'ig_1x1');
        $campaignSlug = Str::slug($facts['name'] ?? ($facts['destination'] ?? 'campaign')) ?: 'campaign';
        $content = 'creative_' . substr($variationGroup, 0, 8);

        $utm = [
            'utm_source' => $platform,
            'utm_medium' => in_array($platform, ['instagram', 'facebook'], true) ? 'paid_social' : $platform,
            'utm_campaign' => $campaignSlug,
            'utm_content' => $content,
        ];

        $target = $facts['booking_url'] ?? ($facts['website'] ?? config('app.url'));
        $sep = str_contains((string) $target, '?') ? '&' : '?';
        $trackedUrl = $target . $sep . http_build_query($utm);

        return [
            'creative_code' => strtoupper(Str::random(8)),
            'platform' => $platform,
            'utm' => $utm,
            'target_url' => $target,
            'tracked_url' => $trackedUrl,
        ];
    }

    private function resolveBrandKit(?int $id): CreativeBrandKit
    {
        if ($id) {
            $kit = CreativeBrandKit::find($id);
            if ($kit) {
                return $kit;
            }
        }

        return CreativeBrandKit::active();
    }

    private function brandFacts(CreativeBrandKit $kit): array
    {
        return [
            'brand_name' => $kit->brand_name,
            'logo_path' => $kit->logo_path,
            'primary_color' => $kit->primary_color,
            'secondary_color' => $kit->secondary_color,
            'accent_color' => $kit->accent_color,
            'text_color' => $kit->text_color,
            'phone' => $kit->phone ?: (settings('company_phone') ?? ''),
            'whatsapp' => $kit->whatsapp ?: (settings('company_whatsapp') ?? ''),
            'website' => $kit->website ?: (settings('company_website') ?? ''),
            'default_disclaimer' => $kit->default_disclaimer,
        ];
    }

    private function defaultName(array $facts, string $format): string
    {
        $name = $facts['name'] ?? ($facts['destination'] ?? 'Creative');

        return trim($name . ' — ' . CreativeFormats::label($format));
    }
}
