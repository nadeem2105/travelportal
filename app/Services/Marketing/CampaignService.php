<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignChange;
use Illuminate\Support\Str;

/**
 * Owns the campaign lifecycle on OUR side: draft creation/editing, standardized
 * naming, UTM generation, and the safety-gated publish/pause flow. All provider
 * writes go through the platform adapter and are recorded in the change log.
 */
class CampaignService
{
    public function __construct(
        private AdvertisingAccountService $accounts,
        private CampaignSafetyService $safety,
    ) {
    }

    /** Create a local draft campaign (never touches the provider). */
    public function createDraft(array $data, ?int $adminId = null): MarketingCampaign
    {
        $data['status'] = 'draft';
        $data['approval_status'] = 'draft';
        $data['created_by'] = $adminId;
        $data['utm_campaign'] = $data['utm_campaign'] ?? Str::slug($data['name'] ?? 'campaign', '_');

        return MarketingCampaign::create($data);
    }

    /** Standardized name from parts, e.g. META | KASHMIR | PACKAGE | LEADS | SEP-2026. */
    public function buildName(array $parts): string
    {
        return collect($parts)->filter()->map(fn ($p) => strtoupper((string) $p))->implode(' | ');
    }

    /** Generate UTM parameters + append to the landing page URL. */
    public function trackingUrl(MarketingCampaign $campaign): string
    {
        $base = $campaign->landing_page ?: '';
        if ($base === '') {
            return '';
        }
        $query = http_build_query(array_filter([
            'utm_source' => $campaign->provider === 'google_ads' ? 'google' : 'meta',
            'utm_medium' => 'cpc',
            'utm_campaign' => $campaign->utm_campaign,
            'utm_content' => 'ad_' . $campaign->id,
        ]));

        return $base . (str_contains($base, '?') ? '&' : '?') . $query;
    }

    /**
     * Publish a draft to the provider — created PAUSED. Requires validation to
     * pass. Does NOT activate (no spend). Throws on any issue.
     */
    public function publish(MarketingCampaign $campaign, ?int $adminId = null): void
    {
        $issues = $this->safety->prePublishIssues($campaign);
        if ($issues) {
            throw new AdApiUnavailableException('Cannot publish: ' . implode(' ', $issues));
        }

        $platform = $this->accounts->platform($campaign->provider);
        $externalId = $platform->createCampaign($campaign); // created paused; throws if API unavailable

        $campaign->forceFill([
            'external_campaign_id' => $externalId,
            'status' => 'published',
            'external_status' => 'paused',
            'approval_status' => 'published',
            'published_at' => now(),
            'approved_by' => $adminId,
        ])->save();

        $this->logChange($campaign, 'status', 'draft', 'published (paused)', 'admin', $adminId);
    }

    /** Explicit activation — this is what starts spending. Separate + gated. */
    public function activate(MarketingCampaign $campaign, ?int $adminId = null): void
    {
        $platform = $this->accounts->platform($campaign->provider);
        $platform->activateCampaign($campaign); // throws if unavailable
        $campaign->forceFill(['status' => 'active', 'external_status' => 'active'])->save();
        $this->logChange($campaign, 'status', 'paused', 'active', 'admin', $adminId);
    }

    public function pause(MarketingCampaign $campaign, ?int $adminId = null): void
    {
        $platform = $this->accounts->platform($campaign->provider);
        $platform->pauseCampaign($campaign);
        $campaign->forceFill(['status' => 'paused', 'external_status' => 'paused'])->save();
        $this->logChange($campaign, 'status', 'active', 'paused', 'admin', $adminId);
    }

    public function updateBudget(MarketingCampaign $campaign, float $dailyBudget, ?int $adminId = null): void
    {
        if (! $this->safety->budgetChangeAllowed($campaign->daily_budget, $dailyBudget)) {
            throw new AdApiUnavailableException('Budget change exceeds the configured safety limit.');
        }
        $old = $campaign->daily_budget;
        $platform = $this->accounts->platform($campaign->provider);
        $platform->updateBudget($campaign, $dailyBudget);
        $campaign->forceFill(['daily_budget' => $dailyBudget])->save();
        $this->logChange($campaign, 'budget', (string) $old, (string) $dailyBudget, 'admin', $adminId);
    }

    public function logChange(MarketingCampaign $campaign, string $field, ?string $old, ?string $new, string $source, ?int $adminId): void
    {
        MarketingCampaignChange::create([
            'campaign_id' => $campaign->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'source' => $source,
            'changed_by' => $adminId,
        ]);
    }
}
