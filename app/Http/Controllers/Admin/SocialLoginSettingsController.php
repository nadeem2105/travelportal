<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\ActivityLogger;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Http\Request;

/**
 * Social sign-in configuration for the customer website. Covers "Continue with
 * Google" (Google Identity Services) and "Continue with Facebook" (Facebook JS
 * SDK). Both live in the single integration_settings row (provider = 'social')
 * and are overlaid onto config('services.google') / config('services.facebook')
 * at boot, so .env stays a fallback.
 *
 * Google's web client ID is public by design (rendered in the browser) and has
 * no secret. Facebook, in contrast, needs a real app secret — it is stored
 * encrypted, masked in the UI, and left untouched when submitted blank (mirrors
 * the SMS / WhatsApp / AI settings screens).
 */
class SocialLoginSettingsController extends Controller
{
    public function __construct(private IntegrationSettings $settings)
    {
    }

    public function index()
    {
        $google = (array) config('services.google', []);
        $facebook = (array) config('services.facebook', []);
        $row = $this->settings->forProvider('social');

        $status = [
            'google' => [
                'enabled' => (bool) ($google['web_enabled'] ?? false),
                'web_client_id' => (string) ($google['web_client_id'] ?? ''),
            ],
            'facebook' => [
                'enabled' => (bool) ($facebook['enabled'] ?? false),
                'app_id' => (string) ($facebook['app_id'] ?? ''),
                'secret_set' => filled($facebook['app_secret'] ?? null),
                'secret_masked' => $this->mask($facebook['app_secret'] ?? null),
            ],
            'stored_in_db' => $row->exists,
        ];

        return view('admin.settings.social', compact('status'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'google_enabled' => 'sometimes|boolean',
            'google_web_client_id' => 'nullable|string|max:255',
            'facebook_enabled' => 'sometimes|boolean',
            'facebook_app_id' => 'nullable|string|max:64',
            'facebook_app_secret' => 'nullable|string|max:255',
        ]);

        $row = IntegrationSetting::firstOrNew(['provider' => 'social']);
        $creds = (array) ($row->credentials ?? []);

        // --- Google (public client ID; no secret) ---
        $google = (array) ($creds['google'] ?? []);
        $google['web_client_id'] = $data['google_web_client_id'] ?? null;
        $google['enabled'] = $request->boolean('google_enabled');
        $creds['google'] = $google;

        // --- Facebook (app ID public; app secret masked, keep when blank) ---
        $facebook = (array) ($creds['facebook'] ?? []);
        $facebook['app_id'] = $data['facebook_app_id'] ?? null;
        $facebook['enabled'] = $request->boolean('facebook_enabled');
        if (filled($data['facebook_app_secret'] ?? null)) {
            $facebook['app_secret'] = $data['facebook_app_secret'];
        }
        $creds['facebook'] = $facebook;

        $row->credentials = $creds;
        // Row-level flag reflects whether any provider is switched on.
        $row->enabled = $google['enabled'] || $facebook['enabled'];
        $row->updated_by = auth('admin')->id();
        $row->save();

        ActivityLogger::log('update', 'settings', 'Social login (Google / Facebook) settings updated');

        return back()->with('success', 'Social login settings saved. They take effect immediately.');
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
