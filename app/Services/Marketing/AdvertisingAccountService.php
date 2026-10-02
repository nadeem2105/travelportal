<?php

namespace App\Services\Marketing;

use App\Models\MarketingAccount;
use App\Models\MarketingConnection;

/**
 * Resolves the correct platform adapter and manages connections/accounts.
 * The single place controllers go to for OAuth + account discovery.
 */
class AdvertisingAccountService
{
    public function __construct(
        private GoogleAdsService $google,
        private MetaAdsService $meta,
    ) {
    }

    /** Get the platform adapter for a provider slug. */
    public function platform(string $provider): AdPlatformInterface
    {
        return match ($provider) {
            'google_ads' => $this->google,
            'meta_ads' => $this->meta,
            default => throw new AdApiUnavailableException("Unknown provider: {$provider}"),
        };
    }

    /** Provider config/enable status for the Accounts UI. */
    public function providerStatuses(): array
    {
        return [
            'google_ads' => [
                'label' => 'Google Ads',
                'enabled' => $this->google->isEnabled(),
                'connections' => MarketingConnection::where('provider', 'google_ads')->with('accounts')->latest()->get(),
            ],
            'meta_ads' => [
                'label' => 'Meta Ads',
                'enabled' => $this->meta->isEnabled(),
                'connections' => MarketingConnection::where('provider', 'meta_ads')->with('accounts')->latest()->get(),
            ],
        ];
    }

    /**
     * Verify a stored connection is currently usable.
     * - Meta: makes a real lightweight Graph call (me/adaccounts).
     * - Google: checks token presence/expiry (a full call needs the Ads API SDK).
     * Never fakes success — returns the real outcome.
     *
     * @return array{ok:bool,message:string}
     */
    public function verifyConnection(MarketingConnection $connection): array
    {
        if ($connection->status === 'disconnected') {
            return ['ok' => false, 'message' => 'This connection is disconnected. Reconnect it.'];
        }

        $token = data_get($connection->credentials, 'access_token');
        if (! $token) {
            return ['ok' => false, 'message' => 'No access token stored — reconnect the account.'];
        }

        if ($connection->provider === 'meta_ads') {
            try {
                $accounts = $this->meta->listAccounts($connection);

                return ['ok' => true, 'message' => 'Meta connection is live. ' . count($accounts) . ' ad account(s) visible.'];
            } catch (AdApiUnavailableException $e) {
                return ['ok' => false, 'message' => 'Meta check failed: ' . $e->getMessage()];
            }
        }

        // google_ads: without the Ads API SDK we validate token freshness only.
        $expires = $connection->token_expires_at;
        $hasRefresh = filled(data_get($connection->credentials, 'refresh_token'));

        if ($expires && $expires->isPast() && ! $hasRefresh) {
            return ['ok' => false, 'message' => 'Google token expired and no refresh token is stored — reconnect.'];
        }

        return [
            'ok' => true,
            'message' => 'Google connection has valid credentials' . ($hasRefresh ? ' (refresh token stored).' : '.')
                . ' Full account listing needs the Google Ads API client.',
        ];
    }

    /** Discover and persist accounts under a connection (official API). */
    public function syncAccounts(MarketingConnection $connection): int
    {
        $platform = $this->platform($connection->provider);
        $accounts = $platform->listAccounts($connection); // throws if unavailable

        $count = 0;
        foreach ($accounts as $a) {
            $externalId = (string) ($a['account_id'] ?? $a['id'] ?? $a['customer_id'] ?? '');
            if ($externalId === '') {
                continue;
            }
            MarketingAccount::updateOrCreate(
                ['provider' => $connection->provider, 'external_account_id' => $externalId],
                [
                    'connection_id' => $connection->id,
                    'account_name' => $a['name'] ?? $a['descriptive_name'] ?? $externalId,
                    'currency' => $a['currency'] ?? null,
                    'timezone' => $a['timezone_name'] ?? null,
                    'business_id' => data_get($a, 'business.id'),
                    'last_synced_at' => now(),
                    'last_sync_status' => 'success',
                ]
            );
            $count++;
        }

        $connection->forceFill(['last_synced_at' => now(), 'last_sync_status' => 'success'])->save();

        return $count;
    }

    public function disconnect(MarketingConnection $connection): void
    {
        $connection->update(['status' => 'disconnected', 'credentials' => null]);
    }
}
