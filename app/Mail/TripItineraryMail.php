<?php

namespace App\Mail;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Customer trip itinerary email. Queued (ShouldQueue) so the itinerary PDF is
 * rendered on the worker rather than blocking the booking-confirmation request —
 * this keeps package confirmations consistent with BookingConfirmationMail, which
 * is also queued. SerializesModels re-fetches the Trip inside the worker.
 */
class TripItineraryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Send once — renders the itinerary PDF and a retry would duplicate it.
    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public Trip $trip)
    {
        $this->trip->loadMissing(['days.events', 'booking']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Trip Itinerary — ' . ($this->trip->destination_label ?: $this->trip->trip_reference)
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trip-itinerary',
            with: ['trip' => $this->trip]
        );
    }

    /**
     * Render the itinerary PDF on the worker. Never block/kill the email if the
     * document fails to render — the itinerary body still reaches the customer.
     */
    public function attachments(): array
    {
        try {
            $bytes = app(\App\Services\PdfDocumentService::class)->customerItinerary($this->trip);

            return [
                \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $bytes, 'Itinerary-' . $this->trip->trip_reference . '.pdf')
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
