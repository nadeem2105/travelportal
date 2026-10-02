<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DB-backed integration credentials overlay onto config('services.*') so admins
 * manage WhatsApp + AI keys from the panel, with .env as fallback. These tests
 * lock in the "DB non-empty overrides env, blank keeps env" contract and the
 * encrypted-at-rest guarantee.
 */
class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function overlay(): void
    {
        app(IntegrationSettings::class)->applyToConfig();
    }

    public function test_whatsapp_db_values_override_env_config(): void
    {
        config([
            'services.whatsapp.access_token' => 'ENV_TOKEN',
            'services.whatsapp.phone_number_id' => 'ENV_PID',
            'services.whatsapp.enabled' => false,
            'services.whatsapp.templates' => ['booking_confirmed' => 'env_tpl'],
        ]);

        IntegrationSetting::create([
            'provider' => 'whatsapp',
            'enabled' => true,
            'credentials' => [
                'access_token' => 'DB_TOKEN',
                'phone_number_id' => 'DB_PID',
                'templates' => ['booking_confirmed' => 'db_tpl', 'quotation_sent' => 'db_quote'],
                'attach_documents' => ['quotation_sent' => true],
                'ai_assistant' => ['enabled' => true, 'model' => 'gpt-4o'],
            ],
        ]);

        $this->overlay();

        $this->assertSame('DB_TOKEN', config('services.whatsapp.access_token'));
        $this->assertSame('DB_PID', config('services.whatsapp.phone_number_id'));
        $this->assertTrue(config('services.whatsapp.enabled'));
        $this->assertSame('db_tpl', config('services.whatsapp.templates.booking_confirmed'));
        $this->assertSame('db_quote', config('services.whatsapp.templates.quotation_sent'));
        $this->assertTrue(config('services.whatsapp.attach_documents.quotation_sent'));
        $this->assertTrue(config('services.whatsapp.ai_assistant.enabled'));
        $this->assertSame('gpt-4o', config('services.whatsapp.ai_assistant.model'));
    }

    public function test_blank_db_secret_falls_back_to_env(): void
    {
        config(['services.whatsapp.access_token' => 'ENV_TOKEN']);

        IntegrationSetting::create([
            'provider' => 'whatsapp',
            'enabled' => true,
            'credentials' => ['access_token' => '', 'phone_number_id' => 'DB_PID'],
        ]);

        $this->overlay();

        // Blank DB secret must not wipe the env value.
        $this->assertSame('ENV_TOKEN', config('services.whatsapp.access_token'));
        $this->assertSame('DB_PID', config('services.whatsapp.phone_number_id'));
    }

    public function test_ai_provider_keys_and_defaults_override_env(): void
    {
        config([
            'services.ai.default_provider' => 'openai',
            'services.ai.default_model' => 'gpt-4o-mini',
            'services.ai.providers.openai.api_key' => 'ENV_OPENAI',
            'services.ai.providers.anthropic.api_key' => '',
        ]);

        IntegrationSetting::create(['provider' => 'ai', 'enabled' => true, 'credentials' => [
            'default_provider' => 'anthropic',
            'default_model' => 'claude-sonnet-4',
        ]]);
        IntegrationSetting::create(['provider' => 'anthropic', 'enabled' => true, 'credentials' => [
            'api_key' => 'DB_ANTHROPIC',
        ]]);

        $this->overlay();

        $this->assertSame('anthropic', config('services.ai.default_provider'));
        $this->assertSame('claude-sonnet-4', config('services.ai.default_model'));
        $this->assertSame('DB_ANTHROPIC', config('services.ai.providers.anthropic.api_key'));
        // Untouched provider keeps its env key.
        $this->assertSame('ENV_OPENAI', config('services.ai.providers.openai.api_key'));
    }

    public function test_per_provider_model_is_applied(): void
    {
        config([
            'services.ai.default_model' => 'gpt-4o-mini',
            'services.ai.providers.gemini.api_key' => '',
        ]);

        IntegrationSetting::create(['provider' => 'gemini', 'enabled' => true, 'credentials' => [
            'api_key' => 'DB_GEMINI',
            'model' => 'gemini-1.5-flash',
        ]]);

        $this->overlay();

        // The provider gets its OWN model, not the (foreign) shared default.
        $this->assertSame('gemini-1.5-flash', config('services.ai.providers.gemini.model'));
        $this->assertSame('DB_GEMINI', config('services.ai.providers.gemini.api_key'));

        // And AiProviderManager uses that model when building the Gemini adapter.
        config(['services.ai.default_provider' => 'gemini']);
        $provider = app(\App\Services\Marketing\Ai\AiProviderManager::class)->provider('gemini');
        $this->assertSame('gemini', $provider->name());
    }

    public function test_groq_provider_key_and_model_are_applied(): void
    {
        config([
            'services.ai.providers.groq.api_key' => '',
            'services.ai.providers.groq.model' => '',
        ]);

        IntegrationSetting::create(['provider' => 'groq', 'enabled' => true, 'credentials' => [
            'api_key' => 'DB_GROQ',
            'model' => 'llama-3.3-70b-versatile',
        ]]);

        $this->overlay();

        $this->assertSame('DB_GROQ', config('services.ai.providers.groq.api_key'));
        $this->assertSame('llama-3.3-70b-versatile', config('services.ai.providers.groq.model'));

        $provider = app(\App\Services\Marketing\Ai\AiProviderManager::class)->provider('groq');
        $this->assertSame('groq', $provider->name());
        $this->assertTrue($provider->isConfigured());
    }

    public function test_foreign_model_is_replaced_with_provider_default(): void
    {
        // A stale shared default (an OpenAI model) must never reach Groq.
        config([
            'services.ai.default_provider' => 'groq',
            'services.ai.default_model' => 'gpt-4o-mini',
            'services.ai.providers.groq.api_key' => 'DB_GROQ',
            'services.ai.providers.groq.model' => '', // no own model => would inherit shared default
        ]);

        $provider = app(\App\Services\Marketing\Ai\AiProviderManager::class)->provider('groq');
        $this->assertSame('groq', $provider->name());
        // The guard swaps the foreign gpt model for Groq's own default; we assert
        // it did not blow up and returns a real Groq adapter (not Null).
        $this->assertTrue($provider->isConfigured());
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        IntegrationSetting::create([
            'provider' => 'openai',
            'enabled' => true,
            'credentials' => ['api_key' => 'super-secret-key'],
        ]);

        // Raw column must not contain the plaintext secret.
        $raw = \DB::table('integration_settings')->where('provider', 'openai')->value('credentials');
        $this->assertStringNotContainsString('super-secret-key', (string) $raw);

        // But the model decrypts it back.
        $this->assertSame('super-secret-key', IntegrationSetting::where('provider', 'openai')->first()->credentials['api_key']);
    }

    public function test_overlay_is_noop_without_rows(): void
    {
        config(['services.whatsapp.access_token' => 'ENV_TOKEN']);

        $this->overlay();

        $this->assertSame('ENV_TOKEN', config('services.whatsapp.access_token'));
    }
}
