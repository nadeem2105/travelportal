<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Send exactly once. This mail renders several PDFs and goes over SMTP that
     * can be slow, so a retry (from a timeout or a re-released job) would deliver
     * a duplicate invoice/itinerary to the customer. $timeout must stay below the
     * queue connection's retry_after so the worker kills a hung send rather than
     * letting the queue re-run it.
     */
    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing([
            'user', 'items', 'travellers', 'payments', 'supplier',
            'flight', 'hotelBooking.hotel', 'cab.vehicle', 'packageBooking.package', 'bookingHotels', 'packageFlights'
        ]);
    }

    public function envelope(): Envelope
    {
        $ref = $this->booking->booking_reference;
        $subject = match ($this->booking->product_type) {
            'package' => "Package Booking Confirmed: " . ($this->booking->packageBooking?->package_name ?? 'Tour') . " [{$ref}]",
            'hotel' => "Hotel Reservation Confirmed: " . ($this->booking->hotelBooking?->hotel_name ?? 'Hotel') . " [{$ref}]",
            'cab' => "Cab Booking Confirmed: " . ($this->booking->cab?->pickup_location ?? 'City') . " → " . ($this->booking->cab?->drop_location ?? 'Destination') . " [{$ref}]",
            'flight' => "Flight E-Ticket Confirmed: " . ($this->booking->flight?->journey['segments'][0]['from']['city'] ?? 'Origin') . " → " . ($this->booking->flight?->journey['segments'][0]['to']['city'] ?? 'Destination') . " [{$ref}]",
            default => "Booking Confirmed: [{$ref}]",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = match ($this->booking->product_type) {
            'package' => 'emails.booking_package',
            'hotel' => 'emails.booking_hotel',
            'cab' => 'emails.booking_cab',
            'flight' => 'emails.booking_flight',
            default => 'emails.booking_package',
        };

        return new Content(
            view: $view,
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->contact['first_name'] ?? ($this->booking->user?->name ?? 'Valued Traveller'),
            ]
        );
    }

    /**
     * Attach the booking documents as PDFs:
     * invoice for every booking, plus an e-ticket (flight), voucher (hotel/cab),
     * or the day-wise itinerary (package) alongside the invoice.
     */
    public function attachments(): array
    {
        try {
            $documents = app(\App\Services\PdfDocumentService::class)->documentsFor($this->booking);

            return collect($documents)->map(fn ($binary, $filename) => (
                \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $binary, $filename)
                    ->withMime("application/pdf")
            ))->values()->all();
        } catch (\Throwable $e) {
            // never block the confirmation email because a document failed to render
            report($e);

            return [];
        }
    }
}
