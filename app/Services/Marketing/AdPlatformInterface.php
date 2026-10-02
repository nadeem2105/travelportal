<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingConnection;

/**
 * Common contract implemented by GoogleAdsService and MetaAdsService. Every
 * method maps to an OFFICIAL provider API operation. When an operation is not
 * available (not connected, not enabled, or unsupported by the account), the
 * implementation must throw AdApiUnavailableException — never simulate success.
 *
 * Campaigns are created in a PAUSED/DRAFT state where the provider supports it;
 * activation is a separate, explicitly-authorized call.
 */
interface AdPlatformInterface
{
    public function provider(): string;

    public function isEnabled(): bool;

    /** Build the OAuth authorization URL for connecting an account. */
    public function authorizationUrl(string $state): string;

    /** Exchange an OAuth code for tokens and persist an encrypted connection. */
    public function handleOAuthCallback(string $code): MarketingConnection;

    /** List ad accounts accessible under a connection (official API). */
    public function listAccounts(MarketingConnection $connection): array;

    /** Create the campaign remotely in PAUSED state; returns external id. */
    public function createCampaign(MarketingCampaign $campaign): string;

    public function pauseCampaign(MarketingCampaign $campaign): void;

    /** Activate a previously-created (paused) campaign — spends money. */
    public function activateCampaign(MarketingCampaign $campaign): void;

    public function updateBudget(MarketingCampaign $campaign, float $dailyBudget): void;

    /** Pull metrics for a date range (incremental where supported). */
    public function fetchMetrics(MarketingCampaign $campaign, string $from, string $to): array;
}
