<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Services\Crm\LeadService;
use App\Services\Crm\QuotationConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function makeQuotation(array $overrides = []): CrmQuotation
    {
        $lead = app(LeadService::class)->create([
            'name' => 'Quote Client', 'phone' => '9876500123', 'email' => 'quote@example.com',
            'destination' => 'Kashmir', 'product_type' => 'package',
        ]);

        return CrmQuotation::create(array_merge([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'title' => 'Kashmir Delight 5D/4N',
            'subtotal' => 25000,
            'tax_amount' => 1250,
            'discount_amount' => 1000,
            'total_amount' => 25250,
            'currency' => 'INR',
            'status' => 'sent',
            'sent_at' => now(),
            'valid_until' => now()->addDays(7),
        ], $overrides));
    }

    public function test_model_auto_generates_number_uuid_and_token(): void
    {
        $q = $this->makeQuotation();

        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{6}$/', $q->quotation_number);
        $this->assertNotEmpty($q->uuid);
        $this->assertSame(48, strlen($q->public_token));
    }

    public function test_public_show_records_first_view_and_advances_status(): void
    {
        $q = $this->makeQuotation();

        $this->get(route('quote.show', $q->public_token))->assertOk()
            ->assertSee($q->quotation_number)
            ->assertSee('Kashmir Delight 5D/4N');

        $q->refresh();
        $this->assertNotNull($q->viewed_at);
        $this->assertSame('viewed', $q->status);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $q->lead_id, 'type' => 'quotation_viewed']);

        // Second view does not overwrite the original viewed_at timestamp.
        $firstViewed = $q->viewed_at;
        $this->get(route('quote.show', $q->public_token))->assertOk();
        $this->assertEquals($firstViewed->timestamp, $q->fresh()->viewed_at->timestamp);
    }

    public function test_customer_can_accept_quotation(): void
    {
        $q = $this->makeQuotation();

        $this->post(route('quote.accept', $q->public_token))->assertRedirect();

        $q->refresh();
        $this->assertSame('accepted', $q->status);
        $this->assertNotNull($q->accepted_at);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $q->lead_id, 'type' => 'quotation_accepted']);
    }

    public function test_customer_can_decline_with_reason(): void
    {
        $q = $this->makeQuotation();

        $this->post(route('quote.decline', $q->public_token), ['reason' => 'Dates changed'])->assertRedirect();

        $q->refresh();
        $this->assertSame('rejected', $q->status);
        $this->assertNotNull($q->rejected_at);
        $this->assertStringContainsString('Dates changed', (string) $q->notes);
    }

    public function test_expired_quotation_cannot_be_accepted(): void
    {
        $q = $this->makeQuotation(['valid_until' => now()->subDay(), 'status' => 'sent']);

        $this->post(route('quote.accept', $q->public_token))->assertRedirect();

        $this->assertSame('sent', $q->fresh()->status); // unchanged — still not accepted
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get(route('quote.show', 'not-a-real-token'))->assertNotFound();
    }

    // ---- Quote → booking conversion ----------------------------------------

    public function test_admin_can_convert_accepted_quotation_to_payment_pending_booking(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $q = $this->makeQuotation(['status' => 'accepted', 'accepted_at' => now()]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/crm/quotations/{$q->id}/convert")
            ->assertRedirect();

        $q->refresh();
        $this->assertSame('converted', $q->status);
        $this->assertNotNull($q->converted_booking_id);

        $this->assertDatabaseHas('bookings', [
            'id' => $q->converted_booking_id,
            'product_type' => 'package',
            'status' => 'payment_pending',
            'total_amount' => 25250,
        ]);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $q->lead_id, 'type' => 'quotation_converted']);
    }

    public function test_conversion_is_idempotent(): void
    {
        $q = $this->makeQuotation(['status' => 'accepted', 'accepted_at' => now()]);
        $converter = app(QuotationConversionService::class);

        $first = $converter->convert($q);
        $second = $converter->convert($q->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\Booking::count());
    }

    public function test_confirmed_booking_marks_lead_converted(): void
    {
        $q = $this->makeQuotation(['status' => 'accepted', 'accepted_at' => now()]);
        $converter = app(QuotationConversionService::class);

        $booking = $converter->convert($q);
        $this->assertNotSame('converted', CrmLead::find($q->lead_id)->status); // not yet

        // Simulate the authoritative confirmation signal.
        $booking->update(['status' => 'confirmed']);
        $converter->onBookingConfirmed($booking);

        $lead = CrmLead::find($q->lead_id);
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $q->lead_id, 'type' => 'lead_converted']);
    }

    public function test_rejected_quotation_cannot_be_converted(): void
    {
        $q = $this->makeQuotation(['status' => 'rejected', 'rejected_at' => now()]);

        $this->expectException(\DomainException::class);
        app(QuotationConversionService::class)->convert($q);
    }
}
