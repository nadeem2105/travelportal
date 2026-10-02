<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds PDF documents (invoices, e-tickets, vouchers, itineraries)
 * for booking emails and customer downloads.
 */
class PdfDocumentService
{
    /**
     * Render a view to PDF binary. Dompdf opens its own output buffer for
     * debug logging and never closes it if rendering throws — this guard
     * guarantees every buffer opened during render is closed, so callers
     * (email attachments mid-booking-flow) never leak output state.
     */
    protected function render(string $view, array $data): string
    {
        $level = ob_get_level();

        try {
            return Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->output();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * Invoice PDF — shared across all product types.
     */
    public function invoice(Booking $booking): string
    {
        $booking->loadMissing(['items', 'travellers', 'payments', 'user', 'packageBooking.package', 'bookingHotels', 'packageFlights']);

        return $this->render('pdf.invoice', ['booking' => $booking]);
    }

    /**
     * Flight e-ticket receipt.
     */
    public function flightTicket(Booking $booking): string
    {
        $booking->loadMissing(['flight', 'travellers']);

        return $this->render('pdf.flight-ticket', ['booking' => $booking]);
    }

    /**
     * Hotel reservation voucher.
     */
    public function hotelVoucher(Booking $booking): string
    {
        $booking->loadMissing(['hotelBooking.hotel', 'travellers']);

        return $this->render('pdf.hotel-voucher', ['booking' => $booking]);
    }

    /**
     * Cab booking voucher.
     */
    public function cabVoucher(Booking $booking): string
    {
        $booking->loadMissing(['cab', 'travellers']);

        return $this->render('pdf.cab-voucher', ['booking' => $booking]);
    }

    /**
     * CRM quotation PDF.
     */
    public function quotation(\App\Models\CrmQuotation $quotation): string
    {
        $quotation->loadMissing(['lead.assignee', 'package.itineraries', 'package.destination']);

        return $this->render('admin.crm.quotation_pdf', [
            'quotation' => $quotation,
            'lead' => $quotation->lead,
            'package' => $quotation->package,
            'agent' => $quotation->lead?->assignee,
        ]);
    }

    /**
     * Day-wise tour itinerary for a package booking.
     */
    public function itinerary(Booking $booking): string
    {
        $booking->loadMissing(['packageBooking.package.itineraries', 'packageBooking.package.destination', 'travellers', 'bookingHotels', 'packageFlights']);

        return $this->render('pdf.itinerary', ['booking' => $booking, 'package' => $booking->packageBooking?->package]);
    }

    /**
     * Hotel voucher for a package booking — iterates the booked hotel snapshot
     * rows (one per segment). Uses the immutable snapshot, never live master data.
     */
    public function packageHotelVoucher(Booking $booking): string
    {
        $booking->loadMissing(['bookingHotels', 'travellers', 'packageBooking.package']);

        return $this->render('pdf.package-hotel-voucher', ['booking' => $booking]);
    }

    /**
     * Operational driver sheet (A4, driver-focused). Includes internal notes,
     * pickup/drop, day-by-day plan and driver + customer contact. Optionally scopes
     * to a single assignment (day / transfer) when provided.
     */
    public function driverSheet(\App\Models\Trip $trip, ?\App\Models\DriverAssignment $assignment = null): string
    {
        $trip->loadMissing(['days.events', 'activeAssignments.driver', 'booking']);

        return $this->render('pdf.driver-sheet', [
            'trip' => $trip,
            'assignment' => $assignment,
            'assignments' => $trip->activeAssignments,
        ]);
    }

    /**
     * Customer-facing itinerary (A4). Shows ONLY customer-visible events; excludes
     * internal notes, driver/vendor details and operational markers.
     */
    public function customerItinerary(\App\Models\Trip $trip): string
    {
        $trip->loadMissing(['days.events', 'booking']);

        return $this->render('pdf.customer-itinerary', ['trip' => $trip]);
    }

    /**
     * All documents applicable to a booking, keyed by filename.
     * Packages get BOTH the tax invoice and the tour itinerary & voucher.
     *
     * @return array<string, string> filename => pdf binary
     */
    public function documentsFor(Booking $booking): array
    {
        return match ($booking->product_type) {
            'flight' => [
                'Tax Invoice ' . $booking->booking_reference . '.pdf' => $this->invoice($booking),
                'Flight E-Ticket ' . $booking->booking_reference . '.pdf' => $this->flightTicket($booking),
            ],
            'hotel' => [
                'Tax Invoice ' . $booking->booking_reference . '.pdf' => $this->invoice($booking),
                'Hotel Voucher ' . $booking->booking_reference . '.pdf' => $this->hotelVoucher($booking),
            ],
            'cab' => [
                'Tax Invoice ' . $booking->booking_reference . '.pdf' => $this->invoice($booking),
                'Cab Voucher ' . $booking->booking_reference . '.pdf' => $this->cabVoucher($booking),
            ],
            'package' => array_filter([
                'Tax Invoice ' . $booking->booking_reference . '.pdf' => $this->invoice($booking),
                'Tour Itinerary & Voucher ' . $booking->booking_reference . '.pdf' => $this->itinerary($booking),
                // Hotel voucher only when the package booking actually has hotels.
                'Hotel Voucher ' . $booking->booking_reference . '.pdf' => $booking->bookingHotels()->exists()
                    ? $this->packageHotelVoucher($booking)
                    : null,
            ]),
            default => [
                'Tax Invoice ' . $booking->booking_reference . '.pdf' => $this->invoice($booking),
            ],
        };
    }
}
