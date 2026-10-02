<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\ActivityLogger;
use App\Services\Settings\IntegrationSettings;
use App\Services\WhatsApp\WhatsAppCloudClient;
use Illuminate\Http\Request;

/**
 * WhatsApp Cloud API configuration — now DB-backed and editable. Credentials,
 * transactional template IDs, document-attach flags and the AI-assistant options
 * are stored (encrypted) in integration_settings and overlaid onto
 * config('services.whatsapp') at boot, so .env remains only a fallback. Secrets
 * are masked in the UI and left untouched when the field is submitted blank.
 */
class WhatsAppSettingsController extends Controller
{
    public function __construct(private IntegrationSettings $settings)
    {
    }

    public function index()
    {
        // Effective (post-overlay) config drives the display so admins see what
        // is actually in force, regardless of whether it came from DB or .env.
        $cfg = config('services.whatsapp');
        $row = $this->settings->forProvider('whatsapp');

        $status = [
            'enabled' => (bool) ($cfg['enabled'] ?? false),
            'api_version' => $cfg['api_version'] ?? 'v21.0',
            'phone_number_id' => $cfg['phone_number_id'] ?? null,
            'waba_id' => $cfg['waba_id'] ?? null,
            'access_token' => $this->mask($cfg['access_token'] ?? null),
            'has_access_token' => ! empty($cfg['access_token']),
            'app_secret_set' => ! empty($cfg['app_secret']),
            'verify_token' => $cfg['verify_token'] ?? null,
            'app_id' => $cfg['app_id'] ?? null,
            'default_template_lang' => $cfg['default_template_lang'] ?? 'en_US',
        ];

        $templates = (array) ($cfg['templates'] ?? []);
        $attach = (array) ($cfg['attach_documents'] ?? []);
        $assistant = (array) ($cfg['ai_assistant'] ?? []);
        $storedInDb = $row->exists;

        $webhookUrl = url('/webhooks/whatsapp');

        return view('admin.crm.whatsapp.settings', compact(
            'status', 'templates', 'attach', 'assistant', 'storedInDb', 'webhookUrl'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'api_version' => 'nullable|string|max:10',
            'phone_number_id' => 'nullable|string|max:64',
            'waba_id' => 'nullable|string|max:64',
            'access_token' => 'nullable|string',
            'app_secret' => 'nullable|string',
            'verify_token' => 'nullable|string|max:255',
            'app_id' => 'nullable|string|max:64',
            'default_template_lang' => 'nullable|string|max:10',

            'templates' => 'array',
            'templates.*' => 'nullable|string|max:128',

            'attach' => 'array',

            'assistant_enabled' => 'sometimes|boolean',
            'assistant_require_verification' => 'sometimes|boolean',
            'assistant_provider' => 'nullable|in:openai,anthropic,gemini,groq,openrouter',
            'assistant_model' => 'nullable|string|max:64',
            'assistant_max_tool_iterations' => 'nullable|integer|min:1|max:15',
            'assistant_history_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $row = IntegrationSetting::firstOrNew(['provider' => 'whatsapp']);
        $creds = (array) ($row->credentials ?? []);

        // Non-secret scalars: store the submitted value (blank clears to fall
        // back on .env — except secrets, handled below).
        foreach (['api_version', 'phone_number_id', 'waba_id', 'verify_token', 'app_id', 'default_template_lang'] as $key) {
            $creds[$key] = $data[$key] ?? null;
        }

        // Secrets: only overwrite when a new non-blank value is provided, so a
        // masked/blank submit keeps the stored secret.
        foreach (['access_token', 'app_secret'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $creds[$secret] = $data[$secret];
            }
        }

        // Template IDs (blank = skip WhatsApp for that event).
        $creds['templates'] = array_map(
            fn ($v) => ($v === '' ? null : $v),
            (array) ($data['templates'] ?? [])
        );

        // Document-attach flags — unchecked boxes are absent, so coerce the
        // known keys to explicit booleans.
        $attachIn = (array) ($request->input('attach', []));
        $creds['attach_documents'] = [
            'quotation_sent' => (bool) ($attachIn['quotation_sent'] ?? false),
            'booking_confirmed' => (bool) ($attachIn['booking_confirmed'] ?? false),
            'booking_itinerary' => (bool) ($attachIn['booking_itinerary'] ?? false),
            'booking_invoice' => (bool) ($attachIn['booking_invoice'] ?? false),
            'hotel_voucher' => (bool) ($attachIn['hotel_voucher'] ?? false),
            'cab_voucher' => (bool) ($attachIn['cab_voucher'] ?? false),
            'trip_customer_itinerary' => (bool) ($attachIn['trip_customer_itinerary'] ?? false),
            'trip_driver_sheet' => (bool) ($attachIn['trip_driver_sheet'] ?? false),
        ];

        // AI assistant sub-block.
        $creds['ai_assistant'] = [
            'enabled' => $request->boolean('assistant_enabled'),
            'require_verification' => $request->boolean('assistant_require_verification'),
            'provider' => $data['assistant_provider'] ?? null,
            'model' => $data['assistant_model'] ?? null,
            'max_tool_iterations' => $data['assistant_max_tool_iterations'] ?? null,
            'history_limit' => $data['assistant_history_limit'] ?? null,
        ];

        $row->credentials = $creds;
        $row->enabled = $request->boolean('enabled');
        $row->updated_by = auth('admin')->id();
        $row->save();

        ActivityLogger::log('update', 'settings', 'WhatsApp integration settings updated');

        return back()->with('success', 'WhatsApp settings saved. They take effect immediately.');
    }

    public function test(WhatsAppCloudClient $client)
    {
        $result = $client->verifyConnection();

        $row = IntegrationSetting::firstOrNew(['provider' => 'whatsapp']);

        if ($result['success']) {
            $d = $result['data'] ?? [];
            $summary = trim(($d['verified_name'] ?? '') . ' ' . (isset($d['display_phone_number']) ? '(' . $d['display_phone_number'] . ')' : ''));
            $quality = $d['quality_rating'] ?? null;

            if ($row->exists) {
                $row->forceFill(['last_tested_at' => now(), 'last_test_status' => 'ok', 'last_test_message' => $summary])->save();
            }

            return back()->with('success', 'Connection OK. ' . ($summary ?: 'Phone number reachable.') . ($quality ? " Quality rating: {$quality}." : ''));
        }

        if ($row->exists) {
            $row->forceFill(['last_tested_at' => now(), 'last_test_status' => 'failed', 'last_test_message' => $result['error'] ?? 'unknown'])->save();
        }

        return back()->with('error', 'Connection failed: ' . ($result['error'] ?? 'unknown error'));
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
