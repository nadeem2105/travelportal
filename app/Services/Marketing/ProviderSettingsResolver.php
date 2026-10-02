<?php

namespace App\Services\Marketing;

use App\Models\MarketingProviderSetting;

/**
 * Single source of truth for provider OAuth *app* credentials. Reads from the
 * DB-backed marketing_provider_settings table first (decrypted), falling back to
 * the legacy config/.env values only for keys not present in the DB. This lets
 * admins manage credentials in the UI without touching .env.
 */
class ProviderSettingsResolver
{
    /** @var array<string,array> in-request cache */
    private array $cache = [];

    /**
     * Effective config for a provider, same shape callers already expect from
     * config('services.google_ads') / config('services.meta_ads').
     */
    public function config(string $provider): array
    {
        if (isset($this->cache[$provider])) {
            return $this->cache[$provider];
        }

        $envConfig = (array) config("services.{$provider}", []);
        $setting = $this->setting($provider);

        if (! $setting) {
            return $this->cache[$provider] = $envConfig;
        }

        // DB credentials (non-empty) override env; enabled flag comes from DB.
        $dbCreds = array_filter(
            (array) ($setting->credentials ?? []),
            fn ($v) => $v !== null && $v !== ''
        );

        $merged = array_merge($envConfig, $dbCreds);
        $merged['enabled'] = (bool) $setting->enabled;

        return $this->cache[$provider] = $merged;
    }

    public function setting(string $provider): ?MarketingProviderSetting
    {
        return MarketingProviderSetting::where('provider', $provider)->first();
    }

    public function forget(?string $provider = null): void
    {
        if ($provider) {
            unset($this->cache[$provider]);
        } else {
            $this->cache = [];
        }
    }
}
