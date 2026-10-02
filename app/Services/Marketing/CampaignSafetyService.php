<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;

/**
 * Guardrails that keep the platform from spending money accidentally:
 *  - new campaigns are always created locally as 'draft' and remotely 'paused'
 *  - pre-publish validation must pass before any provider write
 *  - budget changes are bounded by configurable global limits
 * These are advisory checks the controllers/services MUST consult before writes.
 */
class CampaignSafetyService
{
    /** Global caps (currency units). Configurable via settings later. */
    public function dailySpendLimit(): float
    {
        return (float) (settings('marketing_daily_spend_limit', 50000));
    }

    public function maxBudgetChangePercent(): float
    {
        return (float) (settings('marketing_max_budget_change_percent', 50));
    }

    /**
     * Validate a campaign before it may be published to a provider.
     *
     * @return array<int,string> list of blocking issues (empty = ok)
     */
    public function prePublishIssues(MarketingCampaign $campaign): array
    {
        $issues = [];

        if (! $campaign->account_id) {
            $issues[] = 'No ad account connected/selected.';
        }
        if (! $campaign->name) {
            $issues[] = 'Campaign name is required.';
        }
        if (! $campaign->objective) {
            $issues[] = 'Objective is required.';
        }
        if (($campaign->budget_type === 'daily' && ! $campaign->daily_budget)
            || ($campaign->budget_type === 'lifetime' && ! $campaign->lifetime_budget)
            || ! $campaign->budget_type) {
            $issues[] = 'A valid budget is required.';
        }
        if (! $campaign->landing_page) {
            $issues[] = 'A landing page URL is required.';
        }
        if ($campaign->start_at && $campaign->end_at && $campaign->end_at->lt($campaign->start_at)) {
            $issues[] = 'End date is before the start date.';
        }
        if ($campaign->daily_budget && $campaign->daily_budget > $this->dailySpendLimit()) {
            $issues[] = 'Daily budget exceeds the global safety limit of ' . number_format($this->dailySpendLimit()) . '.';
        }

        return $issues;
    }

    /** Is a proposed daily-budget change within the allowed percentage swing? */
    public function budgetChangeAllowed(?float $current, float $proposed): bool
    {
        if (! $current || $current <= 0) {
            return $proposed <= $this->dailySpendLimit();
        }
        $pct = abs($proposed - $current) / $current * 100;

        return $pct <= $this->maxBudgetChangePercent() && $proposed <= $this->dailySpendLimit();
    }
}
