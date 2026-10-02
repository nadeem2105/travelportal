<?php

namespace Tests\Feature;

use App\Models\CrmLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdLeadIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true; // seeds lead sources (meta-ads / google-ads)

    // ---- Meta Lead Ads ------------------------------------------------------

    public function test_meta_webhook_verification(): void
    {
        config(['services.meta_leads.verify_token' => 'meta-verify']);

        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=meta-verify&hub_challenge=42')
            ->assertOk()->assertSee('42');

        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')
            ->assertForbidden();
    }

    public function test_meta_leadgen_creates_lead_with_attribution(): void
    {
        config([
            'services.meta_leads.app_secret' => null, // skip signature in test
            'services.meta_leads.page_access_token' => 'page-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'LEAD1',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Aisha Khan']],
                    ['name' => 'phone_number', 'values' => ['+91 98765-43210']],
                    ['name' => 'email', 'values' => ['aisha@example.com']],
                    ['name' => 'destination', 'values' => ['Gulmarg']],
                ],
                'campaign_id' => 'C1', 'ad_id' => 'A1', 'form_id' => 'F1',
            ], 200),
        ]);

        $payload = ['entry' => [['changes' => [['field' => 'leadgen', 'value' => [
            'leadgen_id' => 'LEAD1', 'form_id' => 'F1', 'ad_id' => 'A1', 'campaign_id' => 'C1', 'adgroup_id' => 'AG1',
        ]]]]]];

        $this->postJson('/webhooks/meta', $payload)->assertOk();

        $lead = CrmLead::where('external_lead_id', 'LEAD1')->first();
        $this->assertNotNull($lead);
        $this->assertSame('Aisha Khan', $lead->name);
        $this->assertSame('919876543210', $lead->phone);      // normalized
        $this->assertSame('Gulmarg', $lead->destination);
        $this->assertSame('C1', $lead->external_campaign_id);
        $this->assertSame('meta', $lead->utm_source);
    }

    public function test_meta_leadgen_is_idempotent(): void
    {
        config(['services.meta_leads.app_secret' => null, 'services.meta_leads.page_access_token' => 'x']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'DUP', 'field_data' => [['name' => 'full_name', 'values' => ['Dup']], ['name' => 'phone_number', 'values' => ['9811111111']]]], 200)]);

        $payload = ['entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => 'DUP']]]]]];
        $this->postJson('/webhooks/meta', $payload)->assertOk();
        $this->postJson('/webhooks/meta', $payload)->assertOk();

        $this->assertSame(1, CrmLead::where('external_lead_id', 'DUP')->count());
    }

    // ---- Google Ads Lead Forms ---------------------------------------------

    public function test_google_lead_created_with_valid_key(): void
    {
        config(['services.google_leads.key' => 'g-secret']);

        $this->postJson('/webhooks/google-leads', [
            'lead_id' => 'GL1', 'google_key' => 'g-secret', 'campaign_id' => 'GC1', 'gcl_id' => 'gclid123',
            'user_column_data' => [
                ['column_id' => 'FULL_NAME', 'string_value' => 'Ravi Verma'],
                ['column_id' => 'PHONE_NUMBER', 'string_value' => '+91 90000 11111'],
                ['column_id' => 'EMAIL', 'string_value' => 'ravi@example.com'],
                ['column_id' => 'CITY', 'string_value' => 'Srinagar'],
            ],
        ])->assertOk();

        $lead = CrmLead::where('external_lead_id', 'GL1')->first();
        $this->assertNotNull($lead);
        $this->assertSame('Ravi Verma', $lead->name);
        $this->assertSame('919000011111', $lead->phone);
        $this->assertSame('gclid123', $lead->gclid);
        $this->assertSame('google', $lead->utm_source);
    }

    public function test_google_rejects_bad_key(): void
    {
        config(['services.google_leads.key' => 'g-secret']);

        $this->postJson('/webhooks/google-leads', ['lead_id' => 'X', 'google_key' => 'wrong'])
            ->assertForbidden();

        $this->assertSame(0, CrmLead::where('external_lead_id', 'X')->count());
    }

    public function test_google_test_submission_is_skipped(): void
    {
        config(['services.google_leads.key' => 'g-secret']);

        $this->postJson('/webhooks/google-leads', [
            'lead_id' => 'TEST1', 'google_key' => 'g-secret', 'is_test' => true,
            'user_column_data' => [['column_id' => 'FULL_NAME', 'string_value' => 'Tester']],
        ])->assertOk();

        $this->assertSame(0, CrmLead::where('external_lead_id', 'TEST1')->count());
    }
}
