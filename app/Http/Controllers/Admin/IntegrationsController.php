<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\IntegrationSettings;

/**
 * Read-only "Integrations" overview: at a glance, which third-party providers are
 * configured, enabled, and whether their credentials live in the DB
 * (integration_settings) or are still falling back to .env. Each card links to
 * the provider's own settings screen for edits. Purely informational — it never
 * writes credentials itself.
 */
class IntegrationsController extends Controller
{
    public function __construct(private IntegrationSettings $settings) {}

    public function index()
    {
        $rows = $this->settings->rows();
        $inDb = fn (string $provider) => isset($rows[$provider]) && $rows[$provider]->exists;

        $wa = (array) config('services.whatsapp', []);
        $ai = (array) config('services.ai', []);
        $sms = (array) config('sms', []);
        $google = (array) config('services.google', []);
        $facebook = (array) config('services.facebook', []);

        // Effective AI default provider + whether that provider actually has a key.
        $defaultProvider = $ai['default_provider'] ?? null;
        $aiKeyed = collect(['openai', 'anthropic', 'gemini', 'groq', 'openrouter'])
            ->filter(fn ($p) => filled($ai['providers'][$p]['api_key'] ?? null))
            ->values()
            ->all();

        // SMS: only the selected driver's credentials matter for "configured".
        $smsProvider = $sms['default'] ?? null;
        $smsCreds = (array) ($sms['providers'][$smsProvider] ?? []);
        $smsConfigured = collect($smsCreds)->contains(fn ($v) => filled($v));

        $integrations = [
            [
                'name' => 'WhatsApp Cloud API',
                'desc' => 'Meta WhatsApp Business — booking, itinerary & trip messages.',
                'enabled' => (bool) ($wa['enabled'] ?? false),
                'configured' => filled($wa['access_token'] ?? null) && filled($wa['phone_number_id'] ?? null),
                'in_db' => $inDb('whatsapp'),
                'route' => 'admin.whatsapp-settings.index',
                'detail' => filled($wa['phone_number_id'] ?? null) ? 'Phone ID ' . $wa['phone_number_id'] : null,
            ],
            [
                'name' => 'AI Providers',
                'desc' => 'LLM copilot / WhatsApp assistant. Default: ' . ($defaultProvider ?: 'none') . '.',
                'enabled' => ! empty($aiKeyed),
                'configured' => ! empty($aiKeyed) && in_array($defaultProvider, $aiKeyed, true),
                'in_db' => $inDb('ai') || $inDb('gemini') || $inDb('openai') || $inDb('anthropic') || $inDb('groq') || $inDb('openrouter'),
                'route' => 'admin.ai-settings.index',
                'detail' => empty($aiKeyed) ? 'No API key set' : ('Keyed: ' . implode(', ', $aiKeyed)),
                'warn' => (! empty($aiKeyed) && ! in_array($defaultProvider, $aiKeyed, true))
                    ? "Default provider '{$defaultProvider}' has no API key — set a key or change the default."
                    : null,
            ],
            [
                'name' => 'SMS & OTP',
                'desc' => 'Transactional SMS + phone-OTP (fast2sms / msg91 / twilio).',
                'enabled' => (bool) ($sms['enabled'] ?? false),
                'configured' => $smsConfigured,
                'in_db' => $inDb('sms'),
                'route' => 'admin.sms-settings.index',
                'detail' => $smsProvider ? ('Provider: ' . $smsProvider) : null,
            ],
            [
                'name' => 'Social Login',
                'desc' => 'Google & Facebook sign-in on the customer website.',
                'enabled' => (bool) ($google['web_enabled'] ?? false) || (bool) ($facebook['enabled'] ?? false),
                'configured' => filled($google['web_client_id'] ?? null) || filled($facebook['app_id'] ?? null),
                'in_db' => $inDb('social'),
                'route' => 'admin.social-login-settings.index',
                'detail' => trim(
                    ((($google['web_enabled'] ?? false) ? 'Google on' : 'Google off') . ' · ' .
                    (($facebook['enabled'] ?? false) ? 'Facebook on' : 'Facebook off'))
                ),
            ],
        ];

        return view('admin.settings.integrations', compact('integrations'));
    }
}
