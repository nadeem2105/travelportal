<?php

namespace App\Services\Crm;

use App\Models\CrmLead;
use App\Models\LeadSource;
use Illuminate\Support\Carbon;

/**
 * Config-driven lead scoring. Points come from config/crm.php so nothing is
 * hardcoded. Extra runtime signals (e.g. whatsapp_conversation, payment_initiated)
 * can be passed in as booleans to bump the score at any lifecycle point.
 */
class LeadScoringService
{
    public function score(CrmLead $lead, array $signals = []): int
    {
        $rules = (array) config('crm.scoring', []);
        $score = 0;

        if (! empty($lead->email)) {
            $score += (int) ($rules['has_email'] ?? 0);
        }
        if ($lead->budget || $lead->budget_min || $lead->budget_max) {
            $score += (int) ($rules['has_budget'] ?? 0);
        }
        if (($lead->product_type ?? null) === 'package' || ($lead->service_type ?? null) === 'package') {
            $score += (int) ($rules['package_interest'] ?? 0);
        }

        $travel = $lead->travel_start_date ?? $lead->travel_date;
        if ($travel && Carbon::parse($travel)->lessThanOrEqualTo(now()->addDays(30))) {
            $score += (int) ($rules['travel_within_30_days'] ?? 0);
        }

        foreach ([
            'whatsapp_conversation', 'quotation_requested', 'checkout_started',
            'payment_initiated', 'booking_confirmed',
        ] as $signal) {
            if (! empty($signals[$signal])) {
                $score += (int) ($rules[$signal] ?? 0);
            }
        }

        // Source bonus
        if ($lead->source_id) {
            $slug = LeadSource::whereKey($lead->source_id)->value('slug');
            if ($slug) {
                $score += (int) (($rules['source_bonus'] ?? [])[$slug] ?? 0);
            }
        }

        return max(0, $score);
    }

    /** Recompute and persist the lead's score. */
    public function apply(CrmLead $lead, array $signals = []): int
    {
        $score = $this->score($lead, $signals);
        if ($lead->score !== $score) {
            $lead->forceFill(['score' => $score])->save();
        }

        return $score;
    }
}
