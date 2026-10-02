<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function superAdmin(): Admin
    {
        return Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);
    }

    public function test_security_headers_middleware_attaches_defensive_headers(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }

    public function test_admin_reports_displays_financial_profitability_and_margin(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        Booking::create([
            'booking_reference' => 'BK-FIN-TEST-001',
            'user_id' => $user->id,
            'product_type' => 'package',
            'status' => 'confirmed',
            'supplier_cost' => 20000,
            'subtotal' => 25000,
            'markup_amount' => 5000,
            'tax_amount' => 2500,
            'total_amount' => 27500,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Financial Profitability &amp; Merchant Ledger', false);
        $response->assertSee('Gross Volume');
        $response->assertSee('Supplier Cost');
        $response->assertSee('Gateway Fees');
        $response->assertSee('Net Platform Margin');
        $response->assertSee('Export Financial Ledger');
    }

    public function test_admin_can_export_financial_ledger_csv(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create(['name' => 'Farooq Ahmed']);
        Booking::create([
            'booking_reference' => 'BK-CSV-LEDGER-01',
            'user_id' => $user->id,
            'product_type' => 'hotel',
            'status' => 'confirmed',
            'supplier_cost' => 8000,
            'subtotal' => 10000,
            'markup_amount' => 2000,
            'tax_amount' => 1200,
            'total_amount' => 11200,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.financial-export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Reference,Date,Product,Customer,Status', $content);
        $this->assertStringContainsString('"Customer Paid (INR)"', $content);
        $this->assertStringContainsString('"Supplier Cost (INR)"', $content);
        $this->assertStringContainsString('"Net Platform Margin (INR)"', $content);
        $this->assertStringContainsString('BK-CSV-LEDGER-01', $content);
        $this->assertStringContainsString('Farooq Ahmed', $content);
        $this->assertStringContainsString('11200.00', $content);
        $this->assertStringContainsString('8000.00', $content);
    }

    public function test_sms_service_normalizes_numbers_and_dispatches_successfully(): void
    {
        $sms = app(SmsService::class);

        // Standard 10-digit Indian mobile
        $this->assertEquals('+919906123456', $sms->normalizePhone('9906123456'));

        // Already formatted with country code
        $this->assertEquals('+919906123456', $sms->normalizePhone('+919906123456'));

        // Formatted with spaces and dashes
        $this->assertEquals('+919906123456', $sms->normalizePhone('+91 9906-123456'));

        // Dispatch via log driver
        $result = $sms->send('9906123456', 'Your Leemroz Travels booking is confirmed!');
        $this->assertTrue($result);
    }

    public function test_admin_can_assign_driver_to_cab_booking(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $booking = Booking::create([
            'booking_reference' => 'BK-CAB-DRIVER-01',
            'user_id' => $user->id,
            'product_type' => 'cab',
            'status' => 'confirmed',
            'subtotal' => 3500,
            'tax_amount' => 175,
            'total_amount' => 3675,
        ]);

        \App\Models\CabBooking::create([
            'booking_id' => $booking->id,
            'vehicle_name' => 'Innova Crysta',
            'pickup_location' => 'Srinagar Airport',
            'drop_location' => 'Gulmarg',
            'pickup_datetime' => now()->addDays(2),
            'distance_km' => 52,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.assign-driver', $booking), [
            'driver_name' => 'Ghulam Nabi',
            'driver_phone' => '9906112233',
            'vehicle_number' => 'JK-01-AB-9999',
            'vendor_name' => 'Royal Kashmir Fleet',
        ]);

        $response->assertSessionHas('success');

        $cab = $booking->fresh()->cab;
        $this->assertNotNull($cab->driver_details);
        $this->assertEquals('Ghulam Nabi', $cab->driver_details['driver_name']);
        $this->assertEquals('9906112233', $cab->driver_details['driver_phone']);

        // Check customer can view assigned driver details
        $customerResponse = $this->actingAs($user, 'web')->get(route('account.booking.show', $booking));
        $customerResponse->assertStatus(200);
        $customerResponse->assertSee('Ghulam Nabi');
        $customerResponse->assertSee('JK-01-AB-9999');
    }
}
