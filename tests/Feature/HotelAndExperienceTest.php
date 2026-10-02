<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\HotelEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelAndExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_correlation_id_middleware_attaches_header(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));

        // Test custom correlation ID propagation
        $customId = 'my-custom-tracing-id-12345';
        $response2 = $this->withHeaders(['X-Correlation-ID' => $customId])->get('/');
        $response2->assertStatus(200);
        $this->assertEquals($customId, $response2->headers->get('X-Correlation-ID'));
    }

    public function test_hotel_engine_deduplicates_and_aggregates_supplier_offers(): void
    {
        $supplier = \App\Models\Supplier::firstOrCreate(
            ['slug' => 'manual-hotels'],
            [
                'name' => 'Manual Hotels',
                'type' => 'hotel',
                'adapter' => 'manual',
                'status' => 'active',
                'priority' => 1,
            ]
        );

        $destination = Destination::firstOrCreate(
            ['slug' => 'srinagar'],
            [
                'name' => 'Srinagar',
                'status' => 'active',
            ]
        );

        $hotel = Hotel::firstOrCreate(
            ['slug' => 'the-lalit-grand-palace'],
            [
                'name' => 'The Lalit Grand Palace',
                'destination_id' => $destination->id,
                'supplier_id' => $supplier->id,
                'city' => 'Srinagar',
                'star_rating' => 5,
                'status' => 'active',
            ]
        );

        HotelRoom::firstOrCreate(
            ['hotel_id' => $hotel->id, 'room_type' => 'Deluxe Palace Room'],
            [
                'meal_plan' => 'breakfast',
                'max_adults' => 2,
                'base_price' => 12000,
                'status' => 'active',
            ]
        );

        $engine = app(HotelEngine::class);
        $search = $engine->search([
            'destination' => 'Srinagar',
            'check_in' => now()->addDays(5)->format('Y-m-d'),
            'check_out' => now()->addDays(7)->format('Y-m-d'),
            'rooms' => 1,
            'adults' => 2,
        ]);

        $this->assertIsArray($search);
        $this->assertGreaterThanOrEqual(1, $search['count']);
        $first = $search['results'][0];
        $this->assertArrayHasKey('supplier_offers', $first);
        $this->assertNotEmpty($first['supplier_offers']);
    }

    public function test_analytics_service_tracks_funnel_and_generates_summary(): void
    {
        AnalyticsService::track('search_flight', ['from' => 'DEL', 'to' => 'SXR']);
        AnalyticsService::track('search_hotel', ['destination' => 'Gulmarg']);
        AnalyticsService::track('view_package', ['package_id' => 10]);
        AnalyticsService::track('begin_checkout', ['amount' => 50000]);

        $summary = app(AnalyticsService::class)->funnelSummary(now()->subDay(), now()->addDay());

        $this->assertEquals(2, $summary['searches']);
        $this->assertEquals(1, $summary['product_views']);
        $this->assertEquals(1, $summary['checkouts']);
        $this->assertEquals(0, $summary['confirmed']);
    }

    public function test_customer_booking_view_renders_trip_timeline(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_reference' => 'BK-TEST-TIMELINE',
            'user_id' => $user->id,
            'product_type' => 'flight',
            'status' => 'confirmed',
            'subtotal' => 6000,
            'tax_amount' => 1080,
            'total_amount' => 7080,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('account.booking.show', $booking));

        $response->assertStatus(200);
        $response->assertSee('Trip Journey &amp; Milestones', false);
        $response->assertSee('E-Ticket Confirmed');
        $response->assertSee('Airport Check-in');
    }

    public function test_admin_reports_displays_conversion_funnel(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('E-Commerce Conversion Funnel');
        $response->assertSee('Checkout Conv:');
    }
}
