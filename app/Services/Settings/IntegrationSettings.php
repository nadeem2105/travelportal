<?php

namespace App\Services\Settings;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Overlays DB-backed integration credentials (WhatsApp + AI providers) onto the
 * runtime config('services.*') tree at boot. This is the single mechanism that
 * lets admins manage every credential / template ID / API key from the panel
 * while keeping .env as a pure fallback:
 *
 *   effective value = DB value (if non-empty) ELSE config/.env value
 *
 * Because it mutates config() in place, ALL existing readers
 * (WhatsAppCloudClient, WhatsAppService, AiProviderManager, TravelAssistantService,
 * NotificationService, CrmController, the webhook controller, …) keep working
 * with zero call-site changes.
 *
 * Safety: guarded by Schema::hasTable + try/catch so it is a no-op before the
 * migration runs or if the DB is unavailable during early boot / console.
 */
class IntegrationSettings
{
    /** Provider slugs stored in integration_settings. */
    public const PROVIDERS = ['whatsapp', 'openai', 'anthropic', 'gemini', 'groq', 'openrouter', 'ai', 'sms', 'social', 'analytics'];

    /**
     * Read all rows once, keyed by provider. Cheap single query; not cached
     * across requests on purpose — decrypted secrets should not sit in a shared
     * cache store.
     *
     * @return array<string, IntegrationSetting>
     */
    public function rows(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        try {
            return IntegrationSetting::all()->keyBy('provider')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Fetch (or make) the row for one provider, for the admin edit screens. */
    public function forProvider(string $provider): IntegrationSetting
    {
        if ($this->tableReady()) {
            return IntegrationSetting::firstOrNew(['provider' => $provider]);
        }

        return new IntegrationSetting(['provider' => $provider]);
    }

    /**
     * Overlay DB values onto config('services.whatsapp') and config('services.ai').
     * Called from AppServiceProvider::boot(). Never throws.
     */
    public function applyToConfig(): void
    {
        try {
            $rows = $this->rows();
            if (empty($rows)) {
                return;
            }

            $this->applyWhatsApp($rows['whatsapp'] ?? null);
            $this->applyAi($rows);
            $this->applySms($rows['sms'] ?? null);
            $this->applySocial($rows['social'] ?? null);
            $this->applyFacebook($rows['social'] ?? null);
            $this->applyAnalytics($rows['analytics'] ?? null);
        } catch (\Throwable $e) {
            // Config overlay must never break the app; fall back to .env/config.
        }
    }

    // --- WhatsApp ---------------------------------------------------------

    private function applyWhatsApp(?IntegrationSetting $row): void
    {
        if (! $row || ! $row->exists) {
            return;
        }

        $base = (array) config('services.whatsapp', []);
        $c = (array) ($row->credentials ?? []);

        // Scalar credentials: DB non-empty overrides env.
        foreach ([
            'api_version', 'phone_number_id', 'waba_id', 'access_token',
            'verify_token', 'app_secret', 'app_id', 'default_template_lang',
        ] as $key) {
            $base[$key] = $this->pick($c[$key] ?? null, $base[$key] ?? null);
        }

        // The row's own enabled flag is authoritative for WhatsApp sending.
        $base['enabled'] = (bool) $row->enabled;

        // Transactional template IDs (nested map). Keep any env default when the
        // admin left a field blank.
        $base['templates'] = $this->mergeNonEmpty(
            (array) ($base['templates'] ?? []),
            (array) ($c['templates'] ?? []),
        );

        // Document-attach flags. These are explicit booleans set in the UI, so
        // when the DB row exists they take precedence (checkbox = the truth).
        if (array_key_exists('attach_documents', $c) && is_array($c['attach_documents'])) {
            $base['attach_documents'] = array_merge(
                (array) ($base['attach_documents'] ?? []),
                array_map(fn ($v) => (bool) $v, $c['attach_documents']),
            );
        }

        // AI assistant sub-block.
        if (array_key_exists('ai_assistant', $c) && is_array($c['ai_assistant'])) {
            $a = (array) ($base['ai_assistant'] ?? []);
            $in = $c['ai_assistant'];

            if (array_key_exists('enabled', $in)) {
                $a['enabled'] = (bool) $in['enabled'];
            }
            if (array_key_exists('require_verification', $in)) {
                $a['require_verification'] = (bool) $in['require_verification'];
            }
            $a['provider'] = $this->pick($in['provider'] ?? null, $a['provider'] ?? null);
            $a['model'] = $this->pick($in['model'] ?? null, $a['model'] ?? null);
            if (filled($in['max_tool_iterations'] ?? null)) {
                $a['max_tool_iterations'] = (int) $in['max_tool_iterations'];
            }
            if (filled($in['history_limit'] ?? null)) {
                $a['history_limit'] = (int) $in['history_limit'];
            }

            $base['ai_assistant'] = $a;
        }

        config(['services.whatsapp' => $base]);
    }

    // --- SMS providers ----------------------------------------------------

    /**
     * Overlay DB values onto config('sms'). Guarded like the rest: a malformed
     * or absent row simply leaves the .env/config defaults untouched.
     */
    private function applySms(?IntegrationSetting $row): void
    {
        if (! $row || ! $row->exists) {
            return;
        }

        $base = (array) config('sms', []);
        $c = (array) ($row->credentials ?? []);

        // The row's own enabled flag is authoritative for SMS sending.
        $base['enabled'] = (bool) $row->enabled;
        $base['default'] = $this->pick($c['default'] ?? null, $base['default'] ?? 'fast2sms');

        // OTP tuning (optional; blank falls back to config/.env).
        $otp = (array) ($base['otp'] ?? []);
        foreach (['length', 'ttl_minutes', 'max_attempts', 'resend_cooldown_seconds'] as $k) {
            if (filled($c['otp'][$k] ?? null)) {
                $otp[$k] = (int) $c['otp'][$k];
            }
        }
        $otp['message'] = $this->pick($c['otp']['message'] ?? null, $otp['message'] ?? null);
        $base['otp'] = $otp;

        // Per-provider credentials — DB non-empty overrides env, key by key.
        $providers = (array) ($base['providers'] ?? []);
        foreach (['fast2sms', 'msg91', 'twilio'] as $name) {
            $existing = (array) ($providers[$name] ?? []);
            $incoming = (array) ($c['providers'][$name] ?? []);
            foreach ($incoming as $k => $v) {
                $existing[$k] = $this->pick($v, $existing[$k] ?? null);
            }
            $providers[$name] = $existing;
        }
        $base['providers'] = $providers;

        config(['sms' => $base]);
    }

    // --- Social login (Google web sign-in) --------------------------------

    /**
     * Overlay DB values onto config('services.google') for the website's
     * "Continue with Google" button. Both social providers live in the single
     * 'social' row; each has its own nested enable flag under credentials.
     * (Legacy rows without a nested flag fall back to the row-level `enabled`.)
     * The button only shows when enabled AND a web client ID is present.
     */
    private function applySocial(?IntegrationSetting $row): void
    {
        if (! $row || ! $row->exists) {
            return;
        }

        $c = (array) ($row->credentials ?? []);
        $google = (array) ($c['google'] ?? []);

        $base = (array) config('services.google', []);
        $base['web_client_id'] = $this->pick($google['web_client_id'] ?? null, $base['web_client_id'] ?? null);
        // Per-provider flag when present; else the legacy row-level flag.
        $enabled = array_key_exists('enabled', $google) ? (bool) $google['enabled'] : (bool) $row->enabled;
        $base['web_enabled'] = $enabled && filled($base['web_client_id']);

        config(['services.google' => $base]);
    }

    // --- Social login (Facebook sign-in) ----------------------------------

    /**
     * Overlay DB values onto config('services.facebook'). The app secret is a
     * real secret (encrypted at rest); it never reaches the browser. The button
     * only shows when enabled AND both app ID and app secret are present.
     */
    private function applyFacebook(?IntegrationSetting $row): void
    {
        if (! $row || ! $row->exists) {
            return;
        }

        $c = (array) ($row->credentials ?? []);
        $fb = (array) ($c['facebook'] ?? []);

        $base = (array) config('services.facebook', []);
        $base['app_id'] = $this->pick($fb['app_id'] ?? null, $base['app_id'] ?? null);
        $base['app_secret'] = $this->pick($fb['app_secret'] ?? null, $base['app_secret'] ?? null);
        $base['enabled'] = ((bool) ($fb['enabled'] ?? false))
            && filled($base['app_id']) && filled($base['app_secret']);

        config(['services.facebook' => $base]);
    }

    // --- Analytics (GA4 / GTM / Meta Pixel + CAPI / Google Ads) ------------

    /**
     * Overlay admin-managed analytics config onto config('services.analytics').
     * Public IDs (measurement id, GTM, pixel, ads id) and server secrets (GA4 MP
     * api_secret, Meta CAPI token) both live in the single 'analytics' row so the
     * whole tracking stack is managed from one screen; .env stays the fallback.
     */
    private function applyAnalytics(?IntegrationSetting $row): void
    {
        if (! $row || ! $row->exists) {
            return;
        }

        $c = (array) ($row->credentials ?? []);
        $base = (array) config('services.analytics', []);

        $base['enabled'] = array_key_exists('enabled', $c) ? (bool) $c['enabled'] : ($base['enabled'] ?? true);
        $base['backend_enabled'] = array_key_exists('backend_enabled', $c) ? (bool) $c['backend_enabled'] : ($base['backend_enabled'] ?? true);

        $base['ga4']['measurement_id'] = $this->pick($c['ga4_measurement_id'] ?? null, $base['ga4']['measurement_id'] ?? null);
        $base['ga4']['api_secret'] = $this->pick($c['ga4_api_secret'] ?? null, $base['ga4']['api_secret'] ?? null);
        $base['gtm']['container_id'] = $this->pick($c['gtm_container_id'] ?? null, $base['gtm']['container_id'] ?? null);
        $base['meta']['pixel_id'] = $this->pick($c['meta_pixel_id'] ?? null, $base['meta']['pixel_id'] ?? null);
        $base['meta']['capi_token'] = $this->pick($c['meta_capi_token'] ?? null, $base['meta']['capi_token'] ?? null);
        $base['meta']['test_event_code'] = $this->pick($c['meta_test_event_code'] ?? null, $base['meta']['test_event_code'] ?? null);
        $base['google_ads']['conversion_id'] = $this->pick($c['google_ads_id'] ?? null, $base['google_ads']['conversion_id'] ?? null);
        $base['google_ads']['booking_label'] = $this->pick($c['google_ads_booking_label'] ?? null, $base['google_ads']['booking_label'] ?? null);
        $base['google_ads']['lead_label'] = $this->pick($c['google_ads_lead_label'] ?? null, $base['google_ads']['lead_label'] ?? null);
        $base['google_ads']['call_label'] = $this->pick($c['google_ads_call_label'] ?? null, $base['google_ads']['call_label'] ?? null);
        $base['google_ads']['whatsapp_label'] = $this->pick($c['google_ads_whatsapp_label'] ?? null, $base['google_ads']['whatsapp_label'] ?? null);
        $base['search_console_verification'] = $this->pick($c['search_console_verification'] ?? null, $base['search_console_verification'] ?? null);

        config(['services.analytics' => $base]);
    }

    // --- AI providers -----------------------------------------------------

    /**
     * @param array<string, IntegrationSetting> $rows
     */
    private function applyAi(array $rows): void
    {
        $base = (array) config('services.ai', []);

        // Shared defaults row (default provider / model).
        $ai = $rows['ai'] ?? null;
        if ($ai && $ai->exists) {
            $c = (array) ($ai->credentials ?? []);
            $base['default_provider'] = $this->pick($c['default_provider'] ?? null, $base['default_provider'] ?? null);
            $base['default_model'] = $this->pick($c['default_model'] ?? null, $base['default_model'] ?? null);
            // Ad Creative Studio image generation toggle + model.
            if (array_key_exists('enable_image', $c)) {
                $base['enable_image'] = (bool) $c['enable_image'];
            }
            $base['image_model'] = $this->pick($c['image_model'] ?? null, $base['image_model'] ?? null);
        }

        // Per-provider API key + model.
        $providers = (array) ($base['providers'] ?? []);
        foreach (['openai', 'anthropic', 'gemini', 'groq', 'openrouter'] as $name) {
            $row = $rows[$name] ?? null;
            if ($row && $row->exists) {
                $existing = (array) ($providers[$name] ?? []);
                $providers[$name] = array_merge($existing, [
                    'api_key' => $this->pick($row->credentials['api_key'] ?? null, $existing['api_key'] ?? null),
                    'model' => $this->pick($row->credentials['model'] ?? null, $existing['model'] ?? null),
                ]);
            }
        }
        $base['providers'] = $providers;

        config(['services.ai' => $base]);
    }

    // --- helpers ----------------------------------------------------------

    /** DB value when non-empty, else the existing (env/config) value. */
    private function pick(mixed $dbValue, mixed $fallback): mixed
    {
        return ($dbValue !== null && $dbValue !== '') ? $dbValue : $fallback;
    }

    /** Merge only non-empty overrides from $overrides into $base. */
    private function mergeNonEmpty(array $base, array $overrides): array
    {
        foreach ($overrides as $k => $v) {
            if ($v !== null && $v !== '') {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('integration_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
