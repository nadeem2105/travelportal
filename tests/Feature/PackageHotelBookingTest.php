<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingHotel;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Package;
use App\Models\PackageHotelOption;
use App\Models\PackageHotelSegment;
use App\Services\PdfDocumentService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PackageHotelBookingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ---- helpers ------------------------------------------------------------

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

    /** Attach a segment with an included + an upgrade hotel option to a package. */
    protected function seedHotelOptions(Package $package, string $mode = 'optional'): array
    {
        $package->update(['hotel_mode' => $mode]);

        $hotelA = Hotel::create([
            'name' => 'Lake View Inn', 'slug' => 'lake-view-inn-' . uniqid(),
            'city' => 'Srinagar', 'address' => 'Boulevard Road', 'star_rating' => 4,
            'status' => 'active',
        ]);
        $hotelB = Hotel::create([
            'name' => 'Grand Palace', 'slug' => 'grand-palace-' . uniqid(),
            'city' => 'Srinagar', 'address' => 'Gupkar Road', 'star_rating' => 5,
            'status' => 'active',
        ]);
        HotelRoom::create(['hotel_id' => $hotelA->id, 'room_type' => 'Deluxe', 'max_adults' => 3, 'max_children' => 2, 'base_price' => 3000, 'meal_plan' => 'breakfast', 'total_rooms' => 10, 'status' => 'active']);
        HotelRoom::create(['hotel_id' => $hotelB->id, 'room_type' => 'Premium', 'max_adults' => 3, 'max_children' => 2, 'base_price' => 6000, 'meal_plan' => 'half_board', 'total_rooms' => 10, 'status' => 'active']);

        $segment = PackageHotelSegment::create([
            'package_id' => $package->id, 'label' => 'Srinagar Stay', 'city' => 'Srinagar', 'nights' => 2, 'sort_order' => 0,
        ]);

        $included = PackageHotelOption::create([
            'package_id' => $package->id, 'segment_id' => $segment->id,
            'hotel_id' => $hotelA->id, 'room_type' => 'Deluxe', 'meal_plan' => 'breakfast',
            'star_rating' => 4, 'base_adults' => 2, 'max_adults' => 3, 'max_children' => 2,
            'is_default' => true, 'price_basis' => 'per_booking', 'upgrade_price' => 0,
            'refundable' => true, 'cancellation_policy' => 'Free cancellation up to 7 days.',
            'status' => 'active', 'sort_order' => 0,
        ]);

        $upgrade = PackageHotelOption::create([
            'package_id' => $package->id, 'segment_id' => $segment->id,
            'hotel_id' => $hotelB->id, 'room_type' => 'Premium', 'meal_plan' => 'half_board',
            'star_rating' => 5, 'base_adults' => 2, 'max_adults' => 3, 'max_children' => 2,
            'is_default' => false, 'price_basis' => 'per_booking', 'upgrade_price' => 5000,
            'refundable' => false, 'cancellation_policy' => 'Non-refundable.',
            'status' => 'active', 'sort_order' => 1,
        ]);

        return compact('segment', 'included', 'upgrade', 'hotelA', 'hotelB');
    }

    protected function package(): Package
    {
        return Package::where('slug', 'kashmir-delight')->firstOrFail();
    }

    // ---- tests --------------------------------------------------------------

    public function test_existing_package_without_hotels_still_books(): void
    {
        $package = $this->package(); // hotel_mode defaults to 'none'
        $departure = $package->departures()->first();

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, ['billing_email' => 'nohotel@example.com']))
            ->assertRedirect();

        $booking = Booking::where('contact->email', 'nohotel@example.com')->latest()->first();
        $this->assertNotNull($booking);
        $this->assertSame(0, $booking->bookingHotels()->count());
    }

    public function test_included_hotel_adds_snapshot_without_upgrade_cost(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package, 'required');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'included@example.com',
                'hotel_option_ids' => [$opts['included']->id],
            ]))->assertRedirect();

        $booking = Booking::where('contact->email', 'included@example.com')->latest()->first();

        $expected = app(PricingService::class)
            ->calculate($package->effectivePrice($departure->departure_date->toDateString()) * 2, 'package');

        $this->assertEquals($expected['total'], $booking->total_amount);
        $this->assertSame(1, $booking->bookingHotels()->count());

        $snap = $booking->bookingHotels()->first();
        $this->assertEquals('Lake View Inn', $snap->hotel_name_snapshot);
        $this->assertTrue((bool) $snap->is_included);
        $this->assertEquals(0, (float) $snap->total);
    }

    public function test_upgrade_hotel_increases_total_and_stores_snapshot(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package, 'required');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'upgrade@example.com',
                'hotel_option_ids' => [$opts['upgrade']->id],
            ]))->assertRedirect();

        $booking = Booking::where('contact->email', 'upgrade@example.com')->latest()->first();

        $base = $package->effectivePrice($departure->departure_date->toDateString()) * 2;
        $expected = app(PricingService::class)->calculate($base + 5000, 'package');

        $this->assertEquals($expected['total'], $booking->total_amount);

        $snap = $booking->bookingHotels()->first();
        $this->assertEquals('Grand Palace', $snap->hotel_name_snapshot);
        $this->assertEquals(5000, (float) $snap->total);
        $this->assertFalse((bool) $snap->refundable);
        $this->assertDatabaseHas('booking_items', ['booking_id' => $booking->id, 'item_type' => 'package_hotel']);
    }

    public function test_required_hotel_selection_is_enforced(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $this->seedHotelOptions($package, 'required');

        $this->from("/packages/{$package->slug}/book")
            ->post("/packages/{$package->slug}/book",
                $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                    'billing_email' => 'missing@example.com',
                    // no hotel_option_ids
                ]))
            ->assertRedirect("/packages/{$package->slug}/book")
            ->assertSessionHas('error');

        $this->assertNull(Booking::where('contact->email', 'missing@example.com')->first());
    }

    public function test_quote_endpoint_reflects_upgrade_price(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package);

        $base = app(PricingService::class)
            ->calculate($package->effectivePrice($departure->departure_date->toDateString()) * 2, 'package');
        $withUpgrade = app(PricingService::class)
            ->calculate($package->effectivePrice($departure->departure_date->toDateString()) * 2 + 5000, 'package');

        $response = $this->postJson("/packages/{$package->slug}/quote", [
            'departure_date' => $departure->departure_date->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1,
            'hotel_option_ids' => [$opts['upgrade']->id],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertEquals($withUpgrade['total'], $response->json('total'));
        $this->assertGreaterThan($base['total'], $response->json('total'));
        $this->assertEquals(5000, $response->json('hotel_upgrade'));
    }

    public function test_hotel_snapshot_is_immutable_after_booking(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package, 'required');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'immutable@example.com',
                'hotel_option_ids' => [$opts['upgrade']->id],
            ]))->assertRedirect();

        $snap = BookingHotel::where('hotel_name_snapshot', 'Grand Palace')->latest()->first();
        $this->assertNotNull($snap);

        // Admin later renames the hotel + option.
        $opts['hotelB']->update(['name' => 'Renamed Hotel']);
        $opts['upgrade']->update(['room_type' => 'Changed Room', 'upgrade_price' => 99999]);

        $snap->refresh();
        $this->assertEquals('Grand Palace', $snap->hotel_name_snapshot); // unchanged
        $this->assertEquals(5000, (float) $snap->total);                  // unchanged
    }

    public function test_package_hotel_voucher_pdf_generates(): void
    {
        Http::fake();
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package, 'required');

        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 0, [
                'billing_email' => 'voucher@example.com',
                'hotel_option_ids' => [$opts['upgrade']->id],
            ]));

        $booking = Booking::where('contact->email', 'voucher@example.com')->latest()->first();

        $pdf = app(PdfDocumentService::class)->packageHotelVoucher($booking);
        $this->assertStringStartsWith('%PDF', $pdf);

        $docs = app(PdfDocumentService::class)->documentsFor($booking);
        $this->assertTrue(collect($docs)->keys()->contains(fn ($k) => str_contains($k, 'Hotel Voucher')));
    }

    public function test_child_occupancy_is_not_priced_as_adult(): void
    {
        $package = $this->package();
        $departure = $package->departures()->first();
        $opts = $this->seedHotelOptions($package, 'required');

        // 2 adults + 1 child, included hotel (no upgrade). Child uses child_price, not adult.
        $this->post("/packages/{$package->slug}/book",
            $this->packagePayload($departure->departure_date->toDateString(), 2, 1, [
                'billing_email' => 'child@example.com',
                'hotel_option_ids' => [$opts['included']->id],
            ]))->assertRedirect();

        $booking = Booking::where('contact->email', 'child@example.com')->latest()->first();

        $adultPrice = $package->effectivePrice($departure->departure_date->toDateString());
        $childPrice = (float) ($package->child_price ?? $adultPrice * 0.6);
        $expected = app(PricingService::class)->calculate($adultPrice * 2 + $childPrice * 1, 'package');

        $this->assertEquals($expected['total'], $booking->total_amount);
    }
}
