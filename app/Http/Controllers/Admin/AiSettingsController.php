<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\ActivityLogger;
use App\Services\Marketing\Ai\AiProviderManager;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Http\Request;

/**
 * AI provider credentials (OpenAI / Anthropic / Gemini) + shared defaults,
 * managed from the admin panel. Keys are stored encrypted in integration_settings
 * and overlaid onto config('services.ai') at boot, so .env is only a fallback.
 * API keys are masked and left untouched when submitted blank.
 */
class AiSettingsController extends Controller
{
    /** Providers exposed on this screen. */
    private const KEYED = ['openai', 'anthropic', 'gemini', 'groq', 'openrouter'];

    public function __construct(private IntegrationSettings $settings)
    {
    }

    public function index()
    {
        $ai = config('services.ai');

        $providers = [];
        foreach (self::KEYED as $name) {
            $key = data_get($ai, "providers.{$name}.api_key");
            $providers[$name] = [
                'set' => ! empty($key),
                'masked' => $this->mask($key),
                'model' => (string) data_get($ai, "providers.{$name}.model", ''),
                'stored_in_db' => $this->settings->forProvider($name)->exists,
            ];
        }

        $defaults = [
            'default_provider' => $ai['default_provider'] ?? 'openai',
            'default_model' => $ai['default_model'] ?? 'gpt-4o-mini',
            'enable_image' => (bool) ($ai['enable_image'] ?? false),
            'image_model' => $ai['image_model'] ?? 'gpt-image-1',
        ];

        return view('admin.settings.ai', compact('providers', 'defaults'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_provider' => 'nullable|in:openai,anthropic,gemini,groq,openrouter',
            'default_model' => 'nullable|string|max:96',
            'enable_image' => 'nullable|boolean',
            'image_model' => 'nullable|string|max:64',
            'keys' => 'array',
            'keys.openai' => 'nullable|string',
            'keys.anthropic' => 'nullable|string',
            'keys.gemini' => 'nullable|string',
            'keys.groq' => 'nullable|string',
            'keys.openrouter' => 'nullable|string',
            'models' => 'array',
            'models.openai' => 'nullable|string|max:96',
            'models.anthropic' => 'nullable|string|max:96',
            'models.gemini' => 'nullable|string|max:96',
            'models.groq' => 'nullable|string|max:96',
            'models.openrouter' => 'nullable|string|max:96',
        ]);

        // Shared defaults row.
        $aiRow = IntegrationSetting::firstOrNew(['provider' => 'ai']);
        $aiCreds = (array) ($aiRow->credentials ?? []);
        $aiCreds['default_provider'] = $data['default_provider'] ?? null;
        $aiCreds['default_model'] = $data['default_model'] ?? null;
        $aiCreds['enable_image'] = $request->boolean('enable_image');
        $aiCreds['image_model'] = $data['image_model'] ?? null;
        $aiRow->credentials = $aiCreds;
        $aiRow->enabled = true;
        $aiRow->updated_by = auth('admin')->id();
        $aiRow->save();

        // Per-provider API key + model. The key is only overwritten when a new
        // non-blank value is given (blank keeps the stored key); the model is
        // saved independently so it can change without re-entering the key.
        foreach (self::KEYED as $name) {
            $incomingKey = $data['keys'][$name] ?? null;
            $incomingModel = $data['models'][$name] ?? null;

            $row = IntegrationSetting::firstOrNew(['provider' => $name]);
            $creds = (array) ($row->credentials ?? []);

            if (filled($incomingKey)) {
                $creds['api_key'] = $incomingKey;
            }
            $creds['model'] = $incomingModel; // blank => provider falls back to default model

            // Skip creating an empty row for a provider that has neither a key
            // (stored or incoming) nor a model.
            if (! $row->exists && ! filled($creds['api_key'] ?? null) && ! filled($creds['model'])) {
                continue;
            }

            $row->credentials = $creds;
            $row->enabled = true;
            $row->updated_by = auth('admin')->id();
            $row->save();
        }

        ActivityLogger::log('update', 'settings', 'AI provider settings updated');

        return back()->with('success', 'AI provider settings saved. They take effect immediately.');
    }

    /** Live check that the configured default provider is reachable. */
    public function test(AiProviderManager $manager)
    {
        if (! $manager->isConfigured()) {
            return back()->with('error', 'No API key set for the default provider. Save a key first.');
        }

        try {
            $provider = $manager->provider();
            $reply = $provider->generateText('Reply with the single word: OK');

            if (stripos($reply, 'ok') !== false || filled($reply)) {
                return back()->with('success', 'AI provider responded successfully.');
            }

            return back()->with('error', 'AI provider returned an empty response.');
        } catch (\Throwable $e) {
            return back()->with('error', 'AI test failed: ' . $e->getMessage());
        }
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
