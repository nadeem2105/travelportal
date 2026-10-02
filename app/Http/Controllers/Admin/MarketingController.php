<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\MarketingConnection;
use App\Models\MarketingDailyMetric;
use App\Models\MarketingProviderSetting;
use App\Services\Marketing\AdApiUnavailableException;
use App\Services\Marketing\AdvertisingAccountService;
use App\Services\Marketing\ProviderSettingsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin → Marketing → Advertising. Overview dashboard + ad-account connection
 * management (OAuth) + DB-backed provider credential settings. Thin controller;
 * logic lives in the marketing services.
 */
class MarketingController extends Controller
{
    public function __construct(
        private AdvertisingAccountService $accounts,
        private ProviderSettingsResolver $settings,
    ) {
    }

    public function overview(Request $request)
    {
        $range = (int) $request->query('days', 30);
        $since = now()->subDays($range)->startOfDay();

        $totals = MarketingDailyMetric::where('date', '>=', $since)
            ->selectRaw('COALESCE(SUM(spend),0) spend, COALESCE(SUM(impressions),0) impressions, COALESCE(SUM(clicks),0) clicks, COALESCE(SUM(leads),0) leads, COALESCE(SUM(conversion_value),0) revenue')
            ->first();

        $campaigns = MarketingCampaign::latest()->limit(10)->get();
        $connectionCount = MarketingConnection::where('status', 'connected')->count();

        return view('admin.marketing.overview', compact('totals', 'campaigns', 'connectionCount', 'range'));
    }

    public function accounts()
    {
        return view('admin.marketing.accounts', ['providers' => $this->accounts->providerStatuses()]);
    }

    /** Begin OAuth for a provider. */
    public function connect(string $provider)
    {
        try {
            $platform = $this->accounts->platform($provider);
            if (! $platform->isEnabled()) {
                return back()->with('error', ucfirst(str_replace('_', ' ', $provider)) . ' is not configured. Add its credentials to .env and set its *_ENABLED flag.');
            }
            $state = Str::random(32);
            session(['marketing_oauth_state' => $state, 'marketing_oauth_provider' => $provider]);

            return redirect()->away($platform->authorizationUrl($state));
        } catch (AdApiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** OAuth callback (redirect URI). */
    public function callback(Request $request, string $provider)
    {
        if ($request->query('state') !== session('marketing_oauth_state')) {
            return redirect()->route('admin.marketing.accounts')->with('error', 'OAuth state mismatch. Please retry.');
        }
        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect()->route('admin.marketing.accounts')->with('error', 'Authorization was cancelled or failed.');
        }

        try {
            $platform = $this->accounts->platform($provider);
            $connection = $platform->handleOAuthCallback($code);
            $synced = 0;
            try {
                $synced = $this->accounts->syncAccounts($connection);
            } catch (AdApiUnavailableException $e) {
                // Connected, but account listing needs further API setup — that's OK.
            }

            return redirect()->route('admin.marketing.accounts')
                ->with('success', ucfirst(str_replace('_', ' ', $provider)) . " connected." . ($synced ? " {$synced} account(s) imported." : ''));
        } catch (AdApiUnavailableException $e) {
            return redirect()->route('admin.marketing.accounts')->with('error', $e->getMessage());
        }
    }

    public function syncAccounts(MarketingConnection $connection)
    {
        try {
            $count = $this->accounts->syncAccounts($connection);

            return back()->with('success', "Imported/updated {$count} account(s).");
        } catch (AdApiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function disconnect(MarketingConnection $connection)
    {
        $this->accounts->disconnect($connection);

        return back()->with('success', 'Connection disconnected.');
    }

    // ---- Provider credential settings (DB-backed, no .env) -----------------

    /** Credential management screen for both providers. */
    public function settings()
    {
        $providers = [];
        foreach (['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads'] as $slug => $label) {
            $setting = MarketingProviderSetting::firstOrNew(['provider' => $slug]);
            $providers[$slug] = [
                'slug' => $slug,
                'label' => $label,
                'setting' => $setting,
                'creds' => $setting->credentials ?? [],
                'configured' => $setting->exists ? $setting->isConfigured() : false,
                'default_redirect' => url("/admin/marketing/{$this->callbackSegment($slug)}/callback"),
            ];
        }

        return view('admin.marketing.settings', compact('providers'));
    }

    /** Persist credentials for a provider (secrets encrypted at rest). */
    public function updateSettings(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['google_ads', 'meta_ads'], true), 404);

        if ($provider === 'google_ads') {
            $data = $request->validate([
                'client_id' => 'nullable|string|max:255',
                'client_secret' => 'nullable|string|max:255',
                'developer_token' => 'nullable|string|max:255',
                'login_customer_id' => 'nullable|string|max:32',
                'redirect_uri' => 'nullable|url|max:500',
                'api_version' => 'nullable|string|max:10',
                'enabled' => 'nullable|boolean',
            ]);
            $keys = ['client_id', 'client_secret', 'developer_token', 'login_customer_id', 'redirect_uri', 'api_version'];
        } else {
            $data = $request->validate([
                'app_id' => 'nullable|string|max:255',
                'app_secret' => 'nullable|string|max:255',
                'redirect_uri' => 'nullable|url|max:500',
                'api_version' => 'nullable|string|max:10',
                'webhook_verify_token' => 'nullable|string|max:255',
                'enabled' => 'nullable|boolean',
            ]);
            $keys = ['app_id', 'app_secret', 'redirect_uri', 'api_version', 'webhook_verify_token'];
        }

        $setting = MarketingProviderSetting::firstOrNew(['provider' => $provider]);
        $existing = $setting->credentials ?? [];

        // Merge: only overwrite a secret when a new non-empty value is provided,
        // so blank secret fields don't wipe stored values.
        $creds = $existing;
        foreach ($keys as $k) {
            $val = $data[$k] ?? null;
            if ($val !== null && $val !== '') {
                $creds[$k] = $val;
            }
        }

        $setting->fill([
            'credentials' => $creds,
            'enabled' => (bool) ($data['enabled'] ?? false),
            'updated_by' => auth('admin')->id(),
        ])->save();

        $this->settings->forget($provider);

        return redirect()->route('admin.marketing.settings')
            ->with('success', ucfirst(str_replace('_', ' ', $provider)) . ' credentials saved (encrypted).');
    }

    /** Live check: is a stored connection actually usable right now? */
    public function checkConnection(MarketingConnection $connection)
    {
        $result = $this->accounts->verifyConnection($connection);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function callbackSegment(string $provider): string
    {
        return $provider === 'google_ads' ? 'google' : 'meta';
    }
}
