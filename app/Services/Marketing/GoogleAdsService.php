<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingConnection;
use Illuminate\Support\Facades\Http;

// Uses ProviderSettingsResolver (same namespace) for DB-backed credentials.

/**
 * Google Ads platform adapter. OAuth (Google Identity) is implemented with plain
 * HTTP. Campaign write operations require the official Google Ads API (developer
 * token + googleads/google-ads-php). Until that client is wired, those methods
 * throw AdApiUnavailableException rather than faking success.
 */
class GoogleAdsService implements AdPlatformInterface
{
    public function __construct(private ProviderSettingsResolver $settings)
    {
    }

    public function provider(): string
    {
        return 'google_ads';
    }

    public function isEnabled(): bool
    {
        $c = $this->settings->config('google_ads');

        return (bool) ($c['enabled'] ?? false)
            && ! empty($c['client_id']) && ! empty($c['client_secret']) && ! empty($c['developer_token']);
    }

    public function authorizationUrl(string $state): string
    {
        $c = $this->settings->config('google_ads');
        $params = http_build_query([
            'client_id' => $c['client_id'],
            'redirect_uri' => $c['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function handleOAuthCallback(string $code): MarketingConnection
    {
        $this->assertEnabled();
        $c = $this->settings->config('google_ads');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'redirect_uri' => $c['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Google OAuth failed: ' . ($response->json('error_description') ?? ('HTTP ' . $response->status())));
        }

        $tok = $response->json();

        return MarketingConnection::create([
            'provider' => 'google_ads',
            'name' => 'Google Ads',
            'credentials' => [
                'access_token' => $tok['access_token'] ?? null,
                'refresh_token' => $tok['refresh_token'] ?? null,
                'scope' => $tok['scope'] ?? null,
            ],
            'status' => 'connected',
            'token_expires_at' => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
            'connected_by' => auth('admin')->id(),
        ]);
    }

    public function listAccounts(MarketingConnection $connection): array
    {
        // Requires Google Ads API (CustomerService.listAccessibleCustomers) with
        // the developer token + login-customer-id header.
        throw new AdApiUnavailableException('Listing Google Ads accounts requires the Google Ads API client (developer token). Configure it to enable account discovery.');
    }

    public function createCampaign(MarketingCampaign $campaign): string
    {
        throw new AdApiUnavailableException('Google Ads campaign creation requires the Google Ads API client. Not available yet.');
    }

    public function pauseCampaign(MarketingCampaign $campaign): void
    {
        throw new AdApiUnavailableException('Google Ads API client not wired.');
    }

    public function activateCampaign(MarketingCampaign $campaign): void
    {
        throw new AdApiUnavailableException('Google Ads API client not wired.');
    }

    public function updateBudget(MarketingCampaign $campaign, float $dailyBudget): void
    {
        throw new AdApiUnavailableException('Google Ads API client not wired.');
    }

    public function fetchMetrics(MarketingCampaign $campaign, string $from, string $to): array
    {
        throw new AdApiUnavailableException('Google Ads reporting requires the Google Ads API client.');
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new AdApiUnavailableException('Google Ads is not enabled/configured (GOOGLE_ADS_* in .env).');
        }
    }
}
