<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPackageFlight;
use App\Models\Package;
use App\Models\PackageFlightOption;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageFlightBookingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

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
        ], $overrides);
    }

    protected function seedFlights(Package $package, string $mode = 'optional'): PackageFlightOption
    {
        $package->update(['flight_mode' => $mode]);

        return PackageFlightOption::create([
            'package_id' => $package->id,
            'label' => 'Ex-Delhi Round Trip',
            'origin_city' => 'Delhi',
            'origin_airport_code' => 'DEL',
            'destination_airport_code' => 'SXR',
            'airline' => 'IndiGo',
            'trip_type' => 'round_trip',
            'cabin_class' => 'economy',
            'baggage' => '15kg + 7kg',
            'price_basis' => 'per_person',
            'price' => 8000,
            'is_default' => true,
            'refundable' => false,
            'cancellation_policy' => 'Airline rules apply.',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    protected function package(): Package
    {
        return Package::where('slug', 'kashmir-delight')->firstOrFail();
    }

    public function test_optional_flight_can_be_skipped(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $this->seedFlights($package, 'optional');

        // No flight_option_id → "without flights".
        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, ['billing_email' => 'noflight@example.com']))
            ->assertRedirect();

        $booking = Booking::where('contact->email', 'noflight@example.com')->latest()->first();
        $this->assertNotNull($booking);
        $this->assertSame(0, $booking->packageFlights()->count());

        $expected = app(PricingService::class)
            ->calculate($package->effectivePrice($departure->departure_date->toDateString()) * 2, 'package');
        $this->assertEquals($expected['total'], $booking->total_amount);
    }

    public function test_flight_adds_per_person_cost_and_snapshot(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $flight = $this->seedFlights($package, 'optional');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'withflight@example.com',
                'flight_option_id' => $flight->id,
            ]))->assertRedirect();

        $booking = Booking::where('contact->email', 'withflight@example.com')->latest()->first();

        // per_person × 2 travellers = 16000 add-on.
        $base = $package->effectivePrice($departure->departure_date->toDateString()) * 2;
        $expected = app(PricingService::class)->calculate($base + 16000, 'package');
        $this->assertEquals($expected['total'], $booking->total_amount);

        $snap = $booking->packageFlights()->first();
        $this->assertNotNull($snap);
        $this->assertEquals('Ex-Delhi Round Trip', $snap->label_snapshot);
        $this->assertEquals(16000, (float) $snap->price);
        $this->assertEquals(2, $snap->travellers);
        $this->assertDatabaseHas('booking_items', ['booking_id' => $booking->id, 'item_type' => 'package_flight']);
    }

    public function test_required_flight_is_enforced(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $this->seedFlights($package, 'required');

        $this->from("/packages/{$package->slug}/book")
            ->post("/packages/{$package->slug}/book",
                $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                    'billing_email' => 'needflight@example.com',
                ]))
            ->assertRedirect("/packages/{$package->slug}/book")
            ->assertSessionHas('error');

        $this->assertNull(Booking::where('contact->email', 'needflight@example.com')->first());
    }

    public function test_quote_endpoint_includes_flight(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $flight = $this->seedFlights($package, 'optional');

        $response = $this->postJson("/packages/{$package->slug}/quote", [
            'departure_date' => $departure->departure_date->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1,
            'flight_option_id' => $flight->id,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertEquals(16000, $response->json('flight_total'));
    }

    public function test_flight_snapshot_is_immutable(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $flight = $this->seedFlights($package, 'optional');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'immutableflight@example.com',
                'flight_option_id' => $flight->id,
            ]))->assertRedirect();

        $snap = BookingPackageFlight::where('label_snapshot', 'Ex-Delhi Round Trip')->latest()->first();
        $this->assertNotNull($snap);

        $flight->update(['label' => 'Renamed Fare', 'price' => 99999]);

        $snap->refresh();
        $this->assertEquals('Ex-Delhi Round Trip', $snap->label_snapshot);
        $this->assertEquals(16000, (float) $snap->price);
    }
}
