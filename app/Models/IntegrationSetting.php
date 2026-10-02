<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-backed integration credentials (WhatsApp + AI providers), managed from the
 * admin panel. The `credentials` array is encrypted at rest via the
 * `encrypted:array` cast, so access tokens / app secrets / API keys are never
 * stored in plain text and never leave the DB unencrypted.
 *
 * These values are overlaid onto config('services.*') at boot by
 * App\Services\Settings\IntegrationSettings, so .env stays a fallback and every
 * existing config() call site keeps working unchanged.
 */
class IntegrationSetting extends Model
{
    protected $table = 'integration_settings';

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

    /** True when the required secrets for this provider are present. */
    public function isConfigured(): bool
    {
        $c = $this->credentials ?? [];

        return match ($this->provider) {
            'whatsapp' => filled($c['access_token'] ?? null) && filled($c['phone_number_id'] ?? null),
            'openai', 'anthropic', 'gemini', 'groq', 'openrouter' => filled($c['api_key'] ?? null),
            default => false,
        };
    }
}
