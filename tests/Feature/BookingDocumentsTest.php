<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Package;
use App\Services\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_invoice_pdf_generates_for_every_product_type(): void
    {
        Http::fake();

        foreach (['flight', 'hotel', 'cab', 'package'] as $type) {
            $booking = Booking::factory()->create([
                'product_type' => $type,
                'status' => 'confirmed',
                'contact' => ['email' => 'pdf@example.com', 'phone' => '999', 'first_name' => 'Test'],
            ]);

            $pdf = app(PdfDocumentService::class)->invoice($booking);

            $this->assertNotEmpty($pdf);
            $this->assertEquals('%PDF', substr($pdf, 0, 4), "Invoice for {$type} is not a valid PDF");
        }
    }

    public function test_package_documents_include_invoice_and_itinerary(): void
    {
        $package = Package::where('slug', 'kashmir-delight')->first();

        $booking = Booking::factory()->create([
            'product_type' => 'package',
            'status' => 'confirmed',
            'product_id' => $package->id,
        ]);
        $booking->packageBooking()->create([
            'package_id' => $package->id,
            'package_name' => $package->name,
            'departure_date' => now()->addDays(20),
            'adults' => 2,
        ]);

        $documents = app(PdfDocumentService::class)->documentsFor($booking);

        $this->assertCount(2, $documents, 'Package bookings must include BOTH invoice and itinerary PDFs');
        $this->assertStringContainsString('Tax Invoice ', array_key_first($documents));
        $this->assertStringContainsString('Tour Itinerary & Voucher ', array_keys($documents)[1]);
        $this->assertEquals('%PDF', substr($documents['Tour Itinerary & Voucher ' . $booking->booking_reference . '.pdf'], 0, 4));
    }

    public function test_confirmation_email_carries_pdf_attachments(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'product_type' => 'flight',
            'status' => 'confirmed',
            'contact' => ['email' => 'attach@example.com', 'phone' => '999', 'first_name' => 'Attach'],
        ]);

        Mail::to('attach@example.com')->send(new BookingConfirmationMail($booking));

        Mail::assertSent(BookingConfirmationMail::class, function (BookingConfirmationMail $mail) {
            return count($mail->attachments()) >= 2; // invoice + e-ticket PDFs
        });
    }

    public function test_customer_can_download_booking_pdf(): void
    {
        $user = \App\Models\User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'product_type' => 'package',
            'status' => 'confirmed',
            'product_id' => Package::first()->id,
        ]);
        $booking->packageBooking()->create([
            'package_id' => $package->id ?? Package::first()->id,
            'package_name' => 'Test',
            'adults' => 2,
        ]);

        $this->actingAs($user)
            ->get("/account/bookings/{$booking->booking_reference}/invoice-pdf?type=invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_guest_can_download_via_session(): void
    {
        $booking = Booking::factory()->create([
            'product_type' => 'cab',
            'status' => 'confirmed',
        ]);
        session()->push('guest_bookings', $booking->booking_reference);

        $this->withSession(['guest_bookings' => [$booking->booking_reference]])
            ->get("/booking/{$booking->booking_reference}/pdf?type=voucher")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_stranger_cannot_download_booking_pdf(): void
    {
        $booking = Booking::factory()->create(['product_type' => 'cab', 'status' => 'confirmed']);

        $this->get("/booking/{$booking->booking_reference}/pdf")
            ->assertForbidden(); // null-owner bookings are never public
    }
}
