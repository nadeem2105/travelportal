<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Booking;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bAndCrmTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function superAdmin(): Admin
    {
        return Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);
    }

    public function test_admin_can_register_and_fund_b2b_agent(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->post('/admin/agents', [
            'agency_name' => 'Kashmir Discovery Travels',
            'contact_person' => 'Tariq Bhat',
            'email' => 'tariq@kashmirdiscovery.com',
            'phone' => '9906000000',
            'city' => 'Srinagar',
            'credit_limit' => 100000,
            'commission_rate' => 5,
        ]);

        $agent = Agent::where('email', 'tariq@kashmirdiscovery.com')->first();
        $this->assertNotNull($agent);
        $this->assertEquals('approved', $agent->status);
        $response->assertRedirect("/admin/agents/{$agent->id}");

        // Now fund agent wallet
        $adjustResponse = $this->actingAs($admin, 'admin')->post("/admin/agents/{$agent->id}/balance", [
            'type' => 'deposit',
            'amount' => 50000,
            'notes' => 'Advance bank deposit ref #DEP123',
        ]);

        $adjustResponse->assertRedirect();
        $agent->refresh();
        $this->assertEquals(50000, (float) $agent->wallet_balance);
        $this->assertDatabaseHas('agent_transactions', [
            'agent_id' => $agent->id,
            'type' => 'deposit',
            'amount' => 50000,
        ]);
    }

    public function test_admin_can_capture_lead_and_generate_quotation(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->post('/admin/crm/leads', [
            'name' => 'Rahul Verma',
            'phone' => '9811000000',
            'email' => 'rahul@example.com',
            'destination' => 'Gulmarg & Pahalgam',
            'product_type' => 'package',
            'budget' => 60000,
            'travellers_count' => 2,
            'source' => 'website',
        ]);

        $response->assertRedirect();
        // Phone is normalized on intake (+country code), so look the lead up by email.
        $lead = CrmLead::where('email', 'rahul@example.com')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('919811000000', $lead->phone); // normalized
        $this->assertEquals('new', $lead->status);

        // Add follow-up note
        $this->actingAs($admin, 'admin')->post("/admin/crm/leads/{$lead->id}/followup", [
            'note' => 'Spoke with customer, requested 4-star hotels in Gulmarg.',
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_follow_ups', [
            'lead_id' => $lead->id,
            'admin_id' => $admin->id,
        ]);

        // Generate Quotation
        $this->actingAs($admin, 'admin')->post("/admin/crm/leads/{$lead->id}/quotation", [
            'title' => 'Gulmarg Luxury 4N Package',
            'subtotal' => 55000,
            'tax_amount' => 2750,
            'valid_until' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $lead->refresh();
        $this->assertEquals('quotation_sent', $lead->status);
        $this->assertDatabaseHas('crm_quotations', [
            'lead_id' => $lead->id,
            'total_amount' => 57750,
        ]);
    }

    public function test_admin_can_manage_affiliate_payout(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'affiliate_code' => 'KASHMIR10',
            'commission_percent' => 5,
            'status' => 'active',
            'total_earnings' => 2500,
            'paid_earnings' => 0,
        ]);

        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $commission = AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'booking_id' => $booking->id,
            'booking_amount' => 50000,
            'commission_amount' => 2500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->post("/admin/affiliates/commissions/{$commission->id}/pay");
        $response->assertRedirect();

        $commission->refresh();
        $this->assertEquals('paid', $commission->status);

        $affiliate->refresh();
        $this->assertEquals(2500, (float) $affiliate->paid_earnings);
        $this->assertEquals(0, $affiliate->pendingEarnings());
    }

    public function test_admin_can_manually_add_a_contact(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->post('/admin/contacts', [
            'name' => 'Manual Contact',
            'phone' => '+91 98765-11122',
            'email' => 'manual@example.com',
            'city' => 'Srinagar',
            'lifecycle_stage' => 'prospect',
            'whatsapp_opt_in' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', [
            'name' => 'Manual Contact',
            'phone' => '919876511122',      // normalized
            'email' => 'manual@example.com',
            'lifecycle_stage' => 'prospect',
        ]);
    }

    public function test_manual_add_contact_dedupes_by_phone(): void
    {
        $admin = $this->superAdmin();

        \App\Models\Contact::create(['name' => 'Existing', 'phone' => '919876511122', 'lifecycle_stage' => 'lead']);

        $this->actingAs($admin, 'admin')->post('/admin/contacts', [
            'name' => 'Duplicate Attempt',
            'phone' => '098765 11122', // same normalized number
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\Contact::where('phone', '919876511122')->count());
        $this->assertDatabaseMissing('contacts', ['name' => 'Duplicate Attempt']);
    }

    public function test_manual_add_contact_requires_phone_or_email(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->from('/admin/contacts')
            ->post('/admin/contacts', ['name' => 'No Channel'])
            ->assertRedirect('/admin/contacts');

        $this->assertDatabaseMissing('contacts', ['name' => 'No Channel']);
    }
}
