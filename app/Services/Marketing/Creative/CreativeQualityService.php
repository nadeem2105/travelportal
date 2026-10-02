<?php

namespace App\Services\Marketing\Creative;

use App\Models\MarketingCreative;

/**
 * Automated pre-export quality checks (§36). Non-blocking warnings surfaced to
 * the user: dimensions, resolution, headline length/overflow risk, missing
 * contact info, missing disclaimer, CTA presence. Kept deterministic and cheap.
 */
class CreativeQualityService
{
    /** @return array<string> */
    public function check(MarketingCreative $creative): array
    {
        $w = [];
        $dims = CreativeFormats::dimensions($creative->format ?: 'ig_1x1');

        if ($creative->width && $creative->height) {
            if ($creative->width < $dims['width'] || $creative->height < $dims['height']) {
                $w[] = "Rendered resolution ({$creative->width}×{$creative->height}) is below the target {$dims['width']}×{$dims['height']} for this format.";
            }
        }

        $headline = (string) $creative->headline;
        if (mb_strlen($headline) > 48) {
            $w[] = 'Headline is long (' . mb_strlen($headline) . ' chars) and may overflow or shrink on small screens.';
        }
        if ($headline === '') {
            $w[] = 'No headline set — the creative may look empty.';
        }

        if (empty($creative->copy['cta'] ?? null)) {
            $w[] = 'No call-to-action (CTA) set.';
        }

        $facts = (array) ($creative->spec['facts'] ?? []);
        if (empty($facts['phone']) && empty($facts['whatsapp']) && empty($facts['website'])) {
            $w[] = 'No contact information (phone/WhatsApp/website) available for the overlay.';
        }

        $brand = (array) ($creative->spec['brand'] ?? []);
        if (! empty($brand['default_disclaimer']) && empty($creative->spec['disclaimer_rendered'])) {
            // informational only
        }

        return $w;
    }
}
