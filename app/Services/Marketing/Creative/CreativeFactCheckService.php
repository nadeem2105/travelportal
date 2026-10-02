<?php

namespace App\Services\Marketing\Creative;

use App\Models\MarketingCreative;

/**
 * Validates a generated creative's copy against the authoritative facts stored
 * on the creative (spec). Flags potential mismatches (e.g. a duration or price
 * in the copy that doesn't match the DB) so a human reviews before publishing.
 * Never auto-publishes on mismatch (§35).
 */
class CreativeFactCheckService
{
    /** @return array<string> human-readable warnings (empty = clean) */
    public function check(MarketingCreative $creative): array
    {
        $warnings = [];
        $facts = (array) ($creative->spec['facts'] ?? []);
        $copyText = strtolower(trim(implode(' ', array_filter([
            $creative->headline,
            $creative->copy['primary_text'] ?? null,
            $creative->copy['description'] ?? null,
            $creative->copy['hook'] ?? null,
        ]))));

        if ($copyText === '') {
            return $warnings;
        }

        // Duration: if the copy names a nights/days count, it must match the DB.
        if (! empty($facts['duration_nights'])) {
            if (preg_match_all('/(\d+)\s*nights?/', $copyText, $m)) {
                foreach ($m[1] as $n) {
                    if ((int) $n !== (int) $facts['duration_nights']) {
                        $warnings[] = "Copy mentions {$n} nights but the package has {$facts['duration_nights']}.";
                    }
                }
            }
        }
        if (! empty($facts['duration_days'])) {
            if (preg_match_all('/(\d+)\s*days?/', $copyText, $m)) {
                foreach ($m[1] as $n) {
                    if ((int) $n !== (int) $facts['duration_days']) {
                        $warnings[] = "Copy mentions {$n} days but the package has {$facts['duration_days']}.";
                    }
                }
            }
        }

        // False scarcity: block "only N seats/rooms left" unless facts confirm it.
        if (preg_match('/only\s+\d+\s+(seats?|rooms?|left|spots?)/', $copyText) && empty($facts['inventory_confirmed'])) {
            $warnings[] = 'Copy implies scarcity ("only N left") that is not confirmed by inventory data.';
        }

        // Unverifiable superlatives.
        foreach (['cheapest', 'guaranteed', 'lowest price ever', 'best in the world'] as $bad) {
            if (str_contains($copyText, $bad)) {
                $warnings[] = "Copy uses an unverifiable claim: \"{$bad}\".";
            }
        }

        return $warnings;
    }
}
