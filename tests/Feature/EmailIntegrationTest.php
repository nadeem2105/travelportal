<?php

namespace Tests\Feature;

use App\Mail\BookingCancellationMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\CrmQuotationMail;
use App\Mail\TestEmailMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\CabBooking;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\FlightBooking;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\Package;
use App\Models\PackageBooking;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Database\Seeders\SettingSeeder::class;
        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    public function test_admin_can_save_email_settings(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->put('/admin/settings', [
            'settings' => [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.mailtrap.io',
                'mail_port' => 2525,
                'mail_encryption' => 'tls',
                'mail_username' => 'testuser123',
                'mail_password' => 'secretpass',
                'mail_from_name' => 'Leemroz Travels Support',
                'mail_from_address' => 'support@travelquecashmir.test',
                'mail_notifications_enabled' => '1',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', ['key' => 'mail_host', 'value' => 'smtp.mailtrap.io']);
        $this->assertDatabaseHas('settings', ['key' => 'mail_port', 'value' => '2525']);
        $this->assertDatabaseHas('settings', ['key' => 'mail_from_address', 'value' => 'support@travelquecashmir.test']);
    }

    public function test_admin_can_send_test_email(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post('/admin/settings/test-email', [
            'test_email' => 'diagnostics@example.com',
        ]);

        $response->assertSessionHas('success');
        Mail::assertSent(TestEmailMail::class, function ($mail) {
            return $mail->hasTo('diagnostics@example.com');
        });
    }

    public function test_package_booking_confirmation_sends_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $package = Package::create([
            'name' => 'Super Kashmir Holiday',
            'slug' => 'super-kashmir-holiday',
            'duration_days' => 5,
            'duration_nights' => 4,
            'base_price' => 20000,
            'starting_price' => 20000,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'VNH-PKG-TEST-001',
            'user_id' => $user->id,
            'product_type' => 'package',
            'product_id' => $package->id,
            'status' => 'pending',
            'total_amount' => 25000,
            'contact' => ['first_name' => 'Sara', 'email' => 'customer@example.com', 'phone' => '9876543210'],
        ]);

        PackageBooking::create([
            'booking_id' => $booking->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'departure_date' => now()->addDays(15),
            'adults' => 2,
            'children' => 0,
        ]);

        app(NotificationService::class)->sendBookingConfirmation($booking, true);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) use ($booking) {
            return $mail->hasTo('customer@example.com') &&
                   $mail->booking->booking_reference === $booking->booking_reference;
        });
    }

    public function test_guest_checkout_email_is_used_when_user_is_null(): void
    {
        Mail::fake();

        $booking = Booking::create([
            'booking_reference' => 'VNH-GST-TEST-002',
            'user_id' => null, // Guest checkout without account
            'product_type' => 'package',
            'status' => 'pending',
            'total_amount' => 15000,
            'contact' => ['first_name' => 'Farooq', 'email' => 'guest.traveller@example.com', 'phone' => '9988776655'],
        ]);

        app(NotificationService::class)->sendBookingConfirmation($booking, true);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('guest.traveller@example.com');
        });
    }

    public function test_hotel_booking_confirmation_sends_email(): void
    {
        Mail::fake();
        $hotel = Hotel::create([
            'name' => 'The Grand Lalit Srinagar',
            'slug' => 'the-grand-lalit-srinagar',
            'city' => 'Srinagar',
            'starting_price' => 5000,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'VNH-HTL-TEST-003',
            'user_id' => null,
            'product_type' => 'hotel',
            'product_id' => $hotel->id,
            'status' => 'confirmed',
            'total_amount' => 12000,
            'contact' => ['first_name' => 'Aisha', 'email' => 'aisha@example.com'],
        ]);

        HotelBooking::create([
            'booking_id' => $booking->id,
            'hotel_id' => $hotel->id,
            'hotel_name' => $hotel->name,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(8),
            'rooms_count' => 1,
            'room_type' => 'Deluxe Heritage Room',
            'meal_plan' => 'MAP',
            'nights' => 3,
            'guests' => 2,
        ]);

        app(NotificationService::class)->sendBookingConfirmation($booking, true);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('aisha@example.com');
        });
    }

    public function test_cab_booking_confirmation_and_driver_assignment_sends_email(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $type = \App\Models\VehicleType::create(['name' => 'SUV', 'status' => 'active']);
        $vehicle = Vehicle::create([
            'name' => 'Toyota Innova Crysta',
            'vehicle_type_id' => $type->id,
            'passenger_capacity' => 6,
            'luggage_capacity' => 4,
            'is_ac' => true,
            'base_price' => 2000,
            'per_km_rate' => 15,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'VNH-CAB-TEST-004',
            'user_id' => null,
            'product_type' => 'cab',
            'status' => 'confirmed',
            'total_amount' => 3500,
            'contact' => ['first_name' => 'Zubair', 'email' => 'zubair@example.com', 'phone' => '9797000000'],
        ]);

        $cabBooking = CabBooking::create([
            'booking_id' => $booking->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Srinagar Airport (SXR)',
            'drop_location' => 'Gulmarg Gondola Base',
            'pickup_datetime' => now()->addDays(2),
            'trip_type' => 'one_way',
            'passengers' => 3,
        ]);

        // 1. Initial confirmation
        app(NotificationService::class)->sendBookingConfirmation($booking, true);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('zubair@example.com');
        });

        // 2. Admin assigns driver
        $assignResponse = $this->actingAs($admin, 'admin')->post("/admin/bookings/{$booking->id}/assign-driver", [
            'driver_name' => 'Bashir Ahmad',
            'driver_phone' => '9419012345',
            'vehicle_number' => 'JK-01-AB-1234',
        ]);

        $assignResponse->assertRedirect();
        Mail::assertSent(BookingConfirmationMail::class, 2);
    }

    public function test_flight_booking_confirmation_sends_email(): void
    {
        Mail::fake();

        $booking = Booking::create([
            'booking_reference' => 'VNH-FLT-TEST-005',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'total_amount' => 8500,
            'contact' => ['first_name' => 'Tariq', 'email' => 'tariq@example.com'],
        ]);

        FlightBooking::create([
            'booking_id' => $booking->id,
            'pnr' => 'AI842K',
            'journey' => [
                'type' => 'one_way',
                'segments' => [
                    [
                        'from' => ['code' => 'DEL', 'city' => 'Delhi', 'date' => now()->addDays(7)->format('Y-m-d'), 'time' => '10:30'],
                        'to' => ['code' => 'SXR', 'city' => 'Srinagar', 'date' => now()->addDays(7)->format('Y-m-d'), 'time' => '12:00'],
                        'airline' => ['name' => 'Air India', 'code' => 'AI'],
                        'flight_number' => 'AI-825',
                    ],
                ],
            ],
            'supplier_fare' => 7500,
            'published_fare' => 8500,
        ]);

        app(NotificationService::class)->sendBookingConfirmation($booking, true);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('tariq@example.com');
        });
    }

    public function test_crm_quotation_creation_and_resend_sends_email(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $lead = CrmLead::create([
            'name' => 'Naveen Kumar',
            'email' => 'naveen.travel@example.com',
            'phone' => '9812345678',
            'destination' => 'Pahalgam & Sonmarg',
            'product_type' => 'package',
            'source' => 'website',
            'status' => 'contacted',
            'assigned_to' => $admin->id,
        ]);

        // 1. Create quotation via CRM -> automatically emails lead
        $createResponse = $this->actingAs($admin, 'admin')->post("/admin/crm/leads/{$lead->id}/quotation", [
            'title' => 'Customized Kashmir Family Tour',
            'subtotal' => 45000,
            'tax_amount' => 2250,
            'valid_until' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $createResponse->assertRedirect();
        Mail::assertSent(CrmQuotationMail::class, function ($mail) {
            return $mail->hasTo('naveen.travel@example.com') &&
                   $mail->quotation->title === 'Customized Kashmir Family Tour';
        });

        // 2. Resend quotation email
        $quotation = CrmQuotation::where('lead_id', $lead->id)->first();
        $resendResponse = $this->actingAs($admin, 'admin')->post("/admin/crm/quotations/{$quotation->id}/send");

        $resendResponse->assertRedirect();
        Mail::assertSent(CrmQuotationMail::class, 2);
    }

    public function test_admin_can_resend_booking_confirmation_email(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $booking = Booking::create([
            'booking_reference' => 'VNH-RESEND-001',
            'product_type' => 'package',
            'status' => 'confirmed',
            'total_amount' => 19999,
            'contact' => ['first_name' => 'Rohit', 'email' => 'rohit@example.com'],
        ]);

        $response = $this->actingAs($admin, 'admin')->post("/admin/bookings/{$booking->id}/resend-email");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('rohit@example.com');
        });
    }

    public function test_admin_booking_status_change_to_confirmed_triggers_email(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $booking = Booking::create([
            'booking_reference' => 'VNH-STATUS-CHANGE-001',
            'product_type' => 'hotel',
            'status' => 'payment_pending',
            'total_amount' => 8000,
            'contact' => ['first_name' => 'Meera', 'email' => 'meera@example.com'],
        ]);

        $response = $this->actingAs($admin, 'admin')->post("/admin/bookings/{$booking->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertRedirect();
        $this->assertEquals('confirmed', $booking->fresh()->status);
        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('meera@example.com');
        });
    }

    public function test_booking_cancellation_triggers_cancellation_email(): void
    {
        Mail::fake();

        $booking = Booking::create([
            'booking_reference' => 'VNH-CANCEL-001',
            'product_type' => 'package',
            'status' => 'cancelled',
            'total_amount' => 20000,
            'contact' => ['first_name' => 'Karan', 'email' => 'karan@example.com'],
        ]);

        app(NotificationService::class)->sendCancellation($booking, 18000);

        Mail::assertSent(BookingCancellationMail::class, function ($mail) {
            return $mail->hasTo('karan@example.com') && $mail->refundAmount == 18000;
        });
    }

    public function test_admin_can_view_quotation_pdf(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $lead = CrmLead::create([
            'name' => 'Aditi Sharma',
            'email' => 'aditi@example.com',
            'phone' => '9876543210',
            'destination' => 'Srinagar & Gulmarg',
            'product_type' => 'package',
            'source' => 'website',
            'status' => 'new',
        ]);

        $quotation = CrmQuotation::create([
            'lead_id' => $lead->id,
            'quotation_number' => 'QUO-PDF-TEST',
            'title' => 'Gulmarg Winter Wonderland',
            'subtotal' => 35000,
            'tax_amount' => 1750,
            'total_amount' => 36750,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($admin, 'admin')->get("/admin/crm/quotations/{$quotation->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_can_download_quotation_pdf(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $lead = CrmLead::create([
            'name' => 'Sameer Bhat',
            'email' => 'sameer@example.com',
            'phone' => '9419000000',
            'destination' => 'Pahalgam',
            'product_type' => 'package',
            'source' => 'phone',
            'status' => 'new',
        ]);

        $quotation = CrmQuotation::create([
            'lead_id' => $lead->id,
            'quotation_number' => 'QUO-DL-TEST',
            'title' => 'Pahalgam Valley Tour',
            'subtotal' => 22000,
            'tax_amount' => 1100,
            'total_amount' => 23100,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($admin, 'admin')->get("/admin/crm/quotations/{$quotation->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=Quotation-QUO-DL-TEST.pdf');
    }

    public function test_crm_quotation_email_has_pdf_attachment(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $lead = CrmLead::create([
            'name' => 'Fayaz Wani',
            'email' => 'fayaz@example.com',
            'phone' => '9797111222',
            'destination' => 'Sonmarg',
            'product_type' => 'package',
            'source' => 'website',
            'status' => 'new',
        ]);

        $quotation = CrmQuotation::create([
            'lead_id' => $lead->id,
            'quotation_number' => 'QUO-ATT-TEST',
            'title' => 'Golden Meadow Sonmarg Expedition',
            'subtotal' => 18000,
            'tax_amount' => 900,
            'total_amount' => 18900,
            'status' => 'sent',
        ]);

        $mailable = new CrmQuotationMail($quotation);
        $attachments = $mailable->attachments();

        $this->assertCount(1, $attachments);
    }
}
