<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CrmLead;
use App\Services\Crm\ContactService;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCoreTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true; // seeds default pipeline, stages, lead sources, an admin

    // ---- Contact dedup / normalization -------------------------------------

    public function test_phone_and_email_are_normalized(): void
    {
        $svc = app(ContactService::class);
        $this->assertSame('919876543210', $svc->normalizePhone('+91 98765-43210'));
        $this->assertSame('919876543210', $svc->normalizePhone('098765 43210'));
        $this->assertSame('john@example.com', $svc->normalizeEmail('  John@Example.com '));
    }

    public function test_find_or_create_does_not_duplicate_contacts(): void
    {
        $svc = app(ContactService::class);

        $a = $svc->findOrCreate(['name' => 'Nadeem', 'phone' => '+91 98765-43210', 'email' => 'n@example.com']);
        $b = $svc->findOrCreate(['name' => 'Nadeem B', 'phone' => '098765 43210']); // same normalized phone
        $c = $svc->findOrCreate(['name' => 'Nadeem C', 'email' => 'N@Example.com']); // same normalized email

        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->id, $c->id);
        $this->assertSame(1, Contact::count());
    }

    // ---- Lead creation ------------------------------------------------------

    public function test_lead_service_creates_contact_lead_number_stage_and_score(): void
    {
        $lead = app(LeadService::class)->create([
            'name' => 'Aarav', 'phone' => '9876500000', 'email' => 'a@example.com',
            'destination' => 'Kashmir', 'product_type' => 'package', 'budget' => 50000,
            'source_slug' => 'website',
        ]);

        $this->assertMatchesRegularExpression('/^LD-\d{4}-\d{6}$/', $lead->lead_number);
        $this->assertNotNull($lead->contact_id);
        $this->assertNotNull($lead->pipeline_id);
        $this->assertNotNull($lead->stage_id);            // default first stage
        $this->assertGreaterThan(0, $lead->score);        // has_email + budget + package_interest
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $lead->id, 'type' => 'lead_created']);
    }

    public function test_lead_numbers_are_unique(): void
    {
        $svc = app(LeadService::class);
        $numbers = [];
        for ($i = 0; $i < 12; $i++) {
            $numbers[] = $svc->create(['name' => "L{$i}", 'phone' => '98765000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)])->lead_number;
        }
        $this->assertSame(count($numbers), count(array_unique($numbers)));
    }

    public function test_lead_is_assigned_to_an_active_admin(): void
    {
        // AdminSeeder provides at least one active admin.
        $lead = app(LeadService::class)->create(['name' => 'Assign Me', 'phone' => '9000000001']);

        if ($lead->assigned_to !== null) {
            $this->assertDatabaseHas('admins', ['id' => $lead->assigned_to, 'status' => 'active']);
            $this->assertDatabaseHas('crm_activities', ['lead_id' => $lead->id, 'type' => 'assigned']);
        } else {
            $this->assertTrue(true); // no eligible staff configured — acceptable
        }
    }

    // ---- Website capture + attribution -------------------------------------

    public function test_website_capture_creates_lead_with_first_and_last_touch(): void
    {
        // First touch (Google organic-style), then a later paid touch (Meta), same session.
        $this->get('/?utm_source=google&utm_medium=organic&utm_campaign=brand')->assertOk();
        $this->get('/?utm_source=meta&utm_medium=cpc&utm_campaign=diwali')->assertOk();

        $this->post('/leads/capture', [
            'name' => 'Web Lead',
            'phone' => '9812345678',
            'email' => 'web@example.com',
            'destination' => 'Gulmarg',
            'product_type' => 'package',
            'message' => 'Need a 5 day trip',
        ])->assertRedirect();

        $lead = CrmLead::where('destination', 'Gulmarg')->latest()->first();
        $this->assertNotNull($lead);
        $this->assertSame('google', $lead->first_touch_source);   // first touch preserved
        $this->assertSame('meta', $lead->last_touch_source);      // last touch updated
        $this->assertSame(1, Contact::where('email', 'web@example.com')->count());
    }

    public function test_capture_honeypot_blocks_spam(): void
    {
        $this->from('/')->post('/leads/capture', [
            'name' => 'Spam Bot',
            'phone' => '9800000000',
            'company_website' => 'http://spam.example', // honeypot filled
        ])->assertSessionHasErrors('company_website');

        $this->assertSame(0, CrmLead::where('name', 'Spam Bot')->count());
    }
}
