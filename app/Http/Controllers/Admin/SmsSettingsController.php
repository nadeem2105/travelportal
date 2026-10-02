<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\ActivityLogger;
use App\Services\Settings\IntegrationSettings;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;

/**
 * SMS provider configuration (Fast2SMS / MSG91 / Twilio) for transactional SMS
 * and phone-OTP. DB-backed and editable: credentials are stored encrypted in
 * integration_settings and overlaid onto config('sms') at boot, so .env is only
 * a fallback. Secrets are masked in the UI and left untouched when submitted
 * blank — mirrors the WhatsApp / AI settings screens.
 */
class SmsSettingsController extends Controller
{
    /** Secret credential keys per provider (masked, only overwritten when filled). */
    private const SECRETS = [
        'fast2sms' => ['api_key'],
        'msg91' => ['auth_key'],
        'twilio' => ['auth_token'],
    ];

    /** Non-secret scalar keys per provider (stored as-is; blank clears to .env). */
    private const SCALARS = [
        'fast2sms' => ['route', 'sender_id', 'message_id'],
        'msg91' => ['sender_id', 'template_id', 'default_country'],
        'twilio' => ['sid', 'from', 'messaging_service_sid'],
    ];

    public function __construct(private IntegrationSettings $settings)
    {
    }

    public function index()
    {
        $cfg = config('sms');
        $row = $this->settings->forProvider('sms');

        $providers = [];
        foreach (self::SECRETS as $name => $secretKeys) {
            $p = (array) data_get($cfg, "providers.{$name}", []);
            $secrets = [];
            foreach ($secretKeys as $sk) {
                $secrets[$sk] = ['set' => filled($p[$sk] ?? null), 'masked' => $this->mask($p[$sk] ?? null)];
            }
            $scalars = [];
            foreach (self::SCALARS[$name] as $k) {
                $scalars[$k] = (string) ($p[$k] ?? '');
            }
            $providers[$name] = ['secrets' => $secrets, 'scalars' => $scalars];
        }

        $status = [
            'enabled' => (bool) ($cfg['enabled'] ?? false),
            'default' => $cfg['default'] ?? 'fast2sms',
            'otp' => (array) ($cfg['otp'] ?? []),
            'stored_in_db' => $row->exists,
            'last_tested_at' => $row->last_tested_at,
            'last_test_status' => $row->last_test_status,
            'last_test_message' => $row->last_test_message,
        ];

        return view('admin.settings.sms', compact('providers', 'status'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'default' => 'nullable|in:fast2sms,msg91,twilio',

            'otp_length' => 'nullable|integer|min:4|max:8',
            'otp_ttl_minutes' => 'nullable|integer|min:1|max:60',
            'otp_max_attempts' => 'nullable|integer|min:1|max:10',
            'otp_resend_cooldown_seconds' => 'nullable|integer|min:15|max:600',
            'otp_message' => 'nullable|string|max:320',

            'providers' => 'array',
        ]);

        $row = IntegrationSetting::firstOrNew(['provider' => 'sms']);
        $creds = (array) ($row->credentials ?? []);

        $creds['default'] = $data['default'] ?? null;
        $creds['otp'] = [
            'length' => $data['otp_length'] ?? null,
            'ttl_minutes' => $data['otp_ttl_minutes'] ?? null,
            'max_attempts' => $data['otp_max_attempts'] ?? null,
            'resend_cooldown_seconds' => $data['otp_resend_cooldown_seconds'] ?? null,
            'message' => $data['otp_message'] ?? null,
        ];

        $input = (array) $request->input('providers', []);
        $providers = (array) ($creds['providers'] ?? []);

        foreach (self::SCALARS as $name => $scalarKeys) {
            $existing = (array) ($providers[$name] ?? []);

            // Non-secret scalars: store submitted value (blank clears to .env).
            foreach ($scalarKeys as $k) {
                $existing[$k] = $input[$name][$k] ?? null;
            }

            // Secrets: only overwrite when a new non-blank value is provided.
            foreach (self::SECRETS[$name] as $sk) {
                if (filled($input[$name][$sk] ?? null)) {
                    $existing[$sk] = $input[$name][$sk];
                }
            }

            $providers[$name] = $existing;
        }
        $creds['providers'] = $providers;

        $row->credentials = $creds;
        $row->enabled = $request->boolean('enabled');
        $row->updated_by = auth('admin')->id();
        $row->save();

        ActivityLogger::log('update', 'settings', 'SMS provider settings updated');

        return back()->with('success', 'SMS settings saved. They take effect immediately.');
    }

    /** Send a live test SMS to a supplied number using the active provider. */
    public function test(Request $request, SmsManager $sms)
    {
        $request->validate(['test_phone' => 'required|string|max:20']);

        // Ensure the just-saved config is in force for this request.
        $this->settings->applyToConfig();

        if (! $sms->enabled()) {
            return back()->with('error', 'SMS is disabled. Enable it and save before testing.');
        }
        if (! $sms->isConfigured()) {
            return back()->with('error', 'The active SMS provider is not fully configured.');
        }

        // Use a sample code so DLT templates (whose variable is the OTP) render
        // with a real value — otherwise the {#var#} arrives blank. This mirrors
        // exactly what sendOtp() does in production.
        $sample = (string) random_int(100000, 999999);

        $result = $sms->send(
            $request->input('test_phone'),
            "Leemroz Travels: your test verification code is {$sample}.",
            ['otp' => $sample]
        );

        $row = IntegrationSetting::firstOrNew(['provider' => 'sms']);
        if ($row->exists) {
            $row->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $result['success'] ? 'ok' : 'failed',
                'last_test_message' => $result['success'] ? ('Sent via ' . $result['provider']) : ($result['error'] ?? 'unknown'),
            ])->save();
        }

        return $result['success']
            ? back()->with('success', 'Test SMS sent via ' . $result['provider'] . '.')
            : back()->with('error', 'Test failed: ' . ($result['error'] ?? 'unknown error'));
    }

    private function mask(?string $secret): ?string
    {
        if (empty($secret)) {
            return null;
        }
        $len = strlen($secret);

        return $len <= 8 ? str_repeat('•', $len) : ('••••••••' . substr($secret, -4));
    }
}
