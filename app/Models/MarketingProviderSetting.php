<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-provider OAuth app credentials, DB-backed (replaces .env for these secrets).
 * The credentials array is encrypted at rest via the encrypted:array cast, so
 * client secrets / app secrets / developer tokens are never stored in plain text.
 */
class MarketingProviderSetting extends Model
{
    protected $table = 'marketing_provider_settings';

    protected $fillable = [
        'provider',
        'enabled',
        'credentials',
        'meta',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'meta' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    /** True when every required secret for this provider is present. */
    public function isConfigured(): bool
    {
        $c = $this->credentials ?? [];

        return match ($this->provider) {
            'google_ads' => filled($c['client_id'] ?? null) && filled($c['client_secret'] ?? null) && filled($c['developer_token'] ?? null),
            'meta_ads' => filled($c['app_id'] ?? null) && filled($c['app_secret'] ?? null),
            default => false,
        };
    }
}
