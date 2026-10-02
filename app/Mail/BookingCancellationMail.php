<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancellationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Send once — avoid a retry re-delivering the cancellation/refund notice.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Booking $booking,
        public float $refundAmount,
        public string $refundDays = '5-7'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking Cancelled & Refund Notice: [{$this->booking->booking_reference}] - Leemroz Travels"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_cancellation',
            with: [
                'booking' => $this->booking,
                'refundAmount' => $this->refundAmount,
                'refundDays' => $this->refundDays,
                'customerName' => $this->booking->contact['first_name'] ?? ($this->booking->user?->name ?? 'Valued Traveller'),
            ]
        );
    }
}
