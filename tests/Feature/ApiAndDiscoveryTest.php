<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Package;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAndDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_health_check_endpoint_returns_healthy(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'services' => [
                'database' => ['status' => 'ok'],
                'cache' => ['status' => 'ok'],
                'storage' => ['status' => 'ok'],
            ],
        ]);

        $apiResponse = $this->get('/api/v1/health');
        $apiResponse->assertStatus(200);
        $apiResponse->assertJson(['status' => 'healthy']);
    }

    public function test_xml_sitemap_renders_valid_xml_with_entities(): void
    {
        $destination = Destination::firstOrCreate(
            ['slug' => 'gulmarg'],
            ['name' => 'Gulmarg', 'status' => 'active']
        );

        $package = Package::firstOrCreate(
            ['slug' => 'gulmarg-ski-adventure'],
            [
                'name' => 'Gulmarg Ski Adventure',
                'destination_id' => $destination->id,
                'package_type' => 'adventure',
                'duration_days' => 5,
                'duration_nights' => 4,
                'base_price' => 22000,
                'status' => 'active',
            ]
        );

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<urlset', $response->getContent());
        $this->assertStringContainsString(url('/packages/' . $package->slug), $response->getContent());
        $this->assertStringContainsString(url('/destinations/' . $destination->slug), $response->getContent());
    }

    public function test_robots_txt_renders_disallows_and_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Disallow: /admin/', $response->getContent());
        $this->assertStringContainsString('Disallow: /checkout/', $response->getContent());
        $this->assertStringContainsString('Sitemap: ' . url('/sitemap.xml'), $response->getContent());
    }

    public function test_hotel_api_returns_details_and_available_rooms(): void
    {
        $supplier = Supplier::firstOrCreate(
            ['slug' => 'manual-hotels'],
            ['name' => 'Manual Hotels', 'type' => 'hotel', 'adapter' => 'manual', 'status' => 'active']
        );

        $hotel = Hotel::firstOrCreate(
            ['slug' => 'kashmir-heritage-resort'],
            [
                'name' => 'Kashmir Heritage Resort',
                'city' => 'Srinagar',
                'star_rating' => 4,
                'supplier_id' => $supplier->id,
                'status' => 'active',
            ]
        );

        $room = HotelRoom::firstOrCreate(
            ['hotel_id' => $hotel->id, 'room_type' => 'Heritage Suite'],
            [
                'meal_plan' => 'half_board',
                'max_adults' => 2,
                'base_price' => 8500,
                'status' => 'active',
            ]
        );

        // Test details
        $detailRes = $this->getJson("/api/v1/hotels/{$hotel->slug}");
        $detailRes->assertStatus(200);
        $detailRes->assertJsonPath('data.name', 'Kashmir Heritage Resort');

        // Test rooms
        $roomsRes = $this->getJson("/api/v1/hotels/{$hotel->slug}/rooms");
        $roomsRes->assertStatus(200);
        $roomsRes->assertJsonStructure(['data' => [['room_id', 'room_type', 'supplier_cost', 'display_price']]]);
    }

    public function test_authenticated_user_can_create_booking_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $package = Package::firstOrCreate(
            ['slug' => 'api-kashmir-tour'],
            [
                'name' => 'API Kashmir Tour',
                'package_type' => 'group',
                'duration_days' => 4,
                'duration_nights' => 3,
                'base_price' => 18000,
                'status' => 'active',
            ]
        );

        $payload = [
            'product_type' => 'package',
            'product_id' => $package->id,
            'travellers' => [
                ['first_name' => 'John', 'last_name' => 'Doe', 'traveller_type' => 'adult'],
            ],
            'contact' => [
                'email' => 'john.doe@example.com',
                'phone' => '9876543210',
                'first_name' => 'John',
            ],
            'details' => [
                'departure_date' => now()->addDays(20)->format('Y-m-d'),
            ],
        ];

        $response = $this->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => ['reference', 'status', 'type', 'total', 'checkout_url'],
        ]);
        $this->assertEquals('package', $response->json('data.type'));
    }

    public function test_mobile_user_profile_and_saved_travellers(): void
    {
        $user = User::factory()->create(['name' => 'Sara Khan', 'phone' => '9988776655']);
        Sanctum::actingAs($user);

        // Get profile
        $profileRes = $this->getJson('/api/v1/user/profile');
        $profileRes->assertStatus(200);
        $profileRes->assertJsonPath('data.name', 'Sara Khan');

        // Store saved traveller
        $travellerPayload = [
            'title' => 'Mrs',
            'first_name' => 'Fatima',
            'last_name' => 'Khan',
            'gender' => 'female',
            'dob' => '1995-06-15',
        ];
        $storeRes = $this->postJson('/api/v1/user/saved-travellers', $travellerPayload);
        $storeRes->assertStatus(201);
        $storeRes->assertJsonPath('data.full_name', 'Fatima Khan');

        // List saved travellers
        $listRes = $this->getJson('/api/v1/user/saved-travellers');
        $listRes->assertStatus(200);
        $listRes->assertJsonCount(1, 'data');
    }

    public function test_api_auth_register_login_and_me(): void
    {
        // 1. Test registration
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Adnan Shah',
            'email' => 'adnan.api@example.com',
            'password' => 'SecurePass123!',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $regResponse->assertStatus(201);
        $regResponse->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);
        $this->assertEquals('adnan.api@example.com', $regResponse->json('user.email'));

        // 2. Test login with wrong password
        $failResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'adnan.api@example.com',
            'password' => 'WrongPassword!',
        ]);
        $failResponse->assertStatus(422);

        // 3. Test successful login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'adnan.api@example.com',
            'password' => 'SecurePass123!',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);
        $this->assertEquals('adnan.api@example.com', $loginResponse->json('user.email'));

        // 4. Test authenticated /api/v1/me with Bearer token
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/v1/me');
        $meResponse->assertStatus(200);
        $meResponse->assertJsonPath('user.email', 'adnan.api@example.com');
    }

    public function test_browser_hitting_api_shows_clean_error_responses(): void
    {
        $browserHeaders = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ];

        // 1. Browser hits POST route (/api/v1/auth/login) with GET -> 405 Method Not Allowed
        $response405 = $this->withHeaders($browserHeaders)->get('/api/v1/auth/login');
        $response405->assertStatus(405);
        $response405->assertJson([
            'success' => false,
            'error' => 'Method Not Allowed',
        ]);

        // 2. Browser hits protected route (/api/v1/me) without token -> 401 Unauthorized (no 302 redirect)
        $response401 = $this->withHeaders($browserHeaders)->get('/api/v1/me');
        $response401->assertStatus(401);
        $response401->assertJson([
            'success' => false,
            'error' => 'Unauthorized',
        ]);

        // 3. Browser hits non-existent route (/api/v1/unknown) -> 404 Not Found
        $response404 = $this->withHeaders($browserHeaders)->get('/api/v1/unknown');
        $response404->assertStatus(404);
        $response404->assertJson([
            'success' => false,
            'error' => 'Not Found',
        ]);
    }
}
