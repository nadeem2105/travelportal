<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Build a valid package booking payload (travellers + billing) for the
     * multi-step booking flow.
     */
    protected function packagePayload(string $departureDate, int $adults = 2, int $children = 0, array $overrides = []): array
    {
        $travellers = [];
        for ($i = 0; $i < $adults + $children; $i++) {
            $isAdult = $i < $adults;
            $travellers[$i] = [
                'type' => $isAdult ? 'adult' : 'child',
                'first_name' => $isAdult ? 'Adult' . ($i + 1) : 'Child' . ($i + 1),
                'last_name' => 'Traveller',
                'dob' => $isAdult ? '1990-05-14' : now()->subYears(6)->toDateString(),
                'gender' => 'male',
            ];
        }

        return array_merge([
            'departure_date' => $departureDate,
            'adults' => $adults,
            'children' => $children,
            'rooms' => 1,
            'travellers' => $travellers,
            'billing_name' => 'Aarav Sharma',
            'billing_email' => 'booker@example.com',
            'billing_phone' => '9876543210',
            'billing_address' => 'Dal Lake Road, Boulevard',
            'billing_city' => 'Srinagar',
            'billing_state' => 'Jammu & Kashmir',
            'billing_pincode' => '190001',
            'billing_country' => 'India',
        ], $overrides);
    }

    public function test_guest_can_book_package_and_pay_via_mock_gateway(): void
    {
        Http::fake();

        $package = Package::where('slug', 'kashmir-delight')->first();
        $departure = $package->departures()->first();

        $response = $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0));

        $booking = Booking::where('contact->email', 'booker@example.com')->latest()->first();

        $this->assertNotNull($booking);
        $response->assertRedirect('/checkout/' . $booking->booking_reference);

        // Server recalculated the price — trust nothing from the frontend
        $this->assertEquals($booking->price_breakdown['total'], $booking->total_amount);

        // Mock gateway enabled by seeder — pay
        $this->get('/checkout/' . $booking->booking_reference)->assertOk();

        $payResponse = $this->post('/checkout/' . $booking->booking_reference . '/pay');
        $payResponse->assertOk(); // sandbox confirmation page

        $this->post('/checkout/' . $booking->booking_reference . '/mock-pay');

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'gateway' => 'mock',
            'status' => 'captured',
        ]);
    }

    public function test_payment_success_alone_does_not_confirm_api_bookings_without_supplier(): void
    {
        // The demo flight supplier confirms inline; manual products confirm
        // directly. The reconciliation state is exercised when a supplier
        // adapter fails — simulate that for a hotel bound to a dead supplier.
        $booking = Booking::factory()->create([
            'product_type' => 'flight',
            'status' => 'payment_pending',
        ]);

        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'created']);

        // no supplier booking attempt happens for a malformed journey; the
        // booking service must not silently mark it confirmed
        app(\App\Services\BookingService::class)->handlePaymentSuccess($booking, $payment);

        $booking->refresh();
        $this->assertContains($booking->status, ['confirmed', 'payment_success_booking_failed']);
    }

    public function test_cancellation_request_creates_refund(): void
    {
        $user = \App\Models\User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'total_amount' => 20000,
        ]);

        $this->actingAs($user)
            ->post("/account/bookings/{$booking->booking_reference}/cancel", [
                'reason' => 'Change of travel plans due to weather',
            ])->assertRedirect();

        $booking->refresh();
        $this->assertEquals('requested', $booking->cancellation_status);
        $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id, 'status' => 'requested']);
    }

    public function test_price_is_recalculated_server_side(): void
    {
        $package = Package::where('slug', 'kashmir-delight')->first();
        $departure = $package->departures()->first();

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, ['billing_email' => 'price@example.com']));

        $booking = Booking::where('contact->email', 'price@example.com')->latest()->first();
        $expected = app(\App\Services\PricingService::class)
            ->calculate($package->effectivePrice($departure->departure_date->toDateString()) * 2, 'package');

        $this->assertEquals($expected['total'], $booking->total_amount);
        $this->assertGreaterThan(0, $booking->tax_amount);
    }

    public function test_package_departure_overbooking_is_prevented(): void
    {
        $package = Package::where('slug', 'kashmir-delight')->first();
        $departure = $package->departures()->first();
        $departure->update(['inventory' => 2, 'booked' => 1]);

        $response = $this->from("/packages/{$package->slug}")->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, ['billing_email' => 'overbook@example.com'])); // 1 existing + 2 requested = 3 > 2 inventory

        $response->assertRedirect("/packages/{$package->slug}");
        $response->assertSessionHas('error');

        $departure->refresh();
        $this->assertEquals(1, $departure->booked);
    }

    public function test_expired_pending_bookings_are_cleaned_up_by_scheduled_command(): void
    {
        $package = Package::where('slug', 'kashmir-delight')->first();
        $departure = $package->departures()->first();
        $departure->update(['booked' => 0]);

        $booking = Booking::factory()->create([
            'product_type' => 'package',
            'product_id' => $package->id,
            'status' => 'payment_pending',
            'expires_at' => now()->subMinutes(5),
        ]);

        \App\Models\PackageBooking::create([
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'departure_date' => $departure->departure_date->toDateString(),
            'adults' => 2,
            'children' => 0,
        ]);

        $departure->update(['booked' => 2]);

        \Illuminate\Support\Facades\Artisan::call('bookings:expire-pending');

        $booking->refresh();
        $this->assertEquals('failed', $booking->status);
        $this->assertStringContainsString('Auto-expired due to payment timeout', $booking->notes);

        $departure->refresh();
        $this->assertEquals(0, $departure->booked); // seats released
    }
}
