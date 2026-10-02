<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\PdfDocumentService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends one transactional WhatsApp template message off the request cycle.
 *
 * Both the (potentially slow) PDF rendering and the Graph API upload/send happen
 * inside handle() on the worker, so booking confirmation/cancellation flows never
 * block on WhatsApp. WhatsApp remains a best-effort channel: email/SMS are the
 * guaranteed ones, so a disabled integration or a missing template is a silent
 * no-op here (WhatsAppService::notifyEvent already guards those cases).
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * @param  string       $event         Template event key (config services.whatsapp.templates.<event>).
     * @param  ?string      $phone         Recipient phone (any format; normalized downstream).
     * @param  array<int,string> $params   Ordered template body variables.
     * @param  ?string      $name          Contact display name.
     * @param  ?int         $bookingId     Booking to render a document from (optional).
     * @param  ?string      $documentType  'itinerary' | 'invoice' | null (no attachment).
     */
    public function __construct(
        public string $event,
        public ?string $phone,
        public array $params = [],
        public ?string $name = null,
        public ?int $bookingId = null,
        public ?string $documentType = null,
    ) {
    }

    public function handle(WhatsAppService $whatsapp, PdfDocumentService $pdf): void
    {
        if (empty($this->phone) || ! $whatsapp->isEnabled()) {
            return; // nothing to send, or WhatsApp is turned off
        }

        $document = $this->resolveDocument($pdf);

        $whatsapp->notifyEvent($this->event, $this->phone, $this->params, $this->name, $document);
    }

    /**
     * Render the attachment PDF for this event, if one was requested. Returns the
     * ['bytes' => ..., 'filename' => ...] shape notifyEvent expects, or null.
     */
    private function resolveDocument(PdfDocumentService $pdf): ?array
    {
        if (! $this->documentType || ! $this->bookingId) {
            return null;
        }

        $booking = Booking::find($this->bookingId);
        if (! $booking) {
            return null;
        }

        $ref = $booking->booking_reference;

        try {
            if ($this->documentType === 'invoice') {
                return ['bytes' => $pdf->invoice($booking), 'filename' => "Invoice-{$ref}.pdf"];
            }

            // 'itinerary' → the product-appropriate travel document.
            return match ($booking->product_type) {
                'package' => ['bytes' => $pdf->itinerary($booking), 'filename' => "Itinerary-{$ref}.pdf"],
                'flight' => ['bytes' => $pdf->flightTicket($booking), 'filename' => "FlightTicket-{$ref}.pdf"],
                'hotel' => ['bytes' => $pdf->hotelVoucher($booking), 'filename' => "HotelVoucher-{$ref}.pdf"],
                'cab' => ['bytes' => $pdf->cabVoucher($booking), 'filename' => "CabVoucher-{$ref}.pdf"],
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning("WhatsApp document ({$this->documentType}) render failed for booking {$this->bookingId}: " . $e->getMessage());

            return null;
        }
    }
}
