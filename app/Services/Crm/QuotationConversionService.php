<?php

namespace App\Services\Crm;

use App\Models\Booking;
use App\Models\CabBooking;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;

/**
 * Converts an accepted quotation into a real, payment-pending Booking using the
 * authoritative BookingService — the CRM never becomes a second source of truth
 * for bookings. The quote is linked to the booking (converted_booking_id) and
 * marked 'converted'; the lead is only flipped to 'converted' once the booking
 * actually reaches status 'confirmed' (handled in BookingService on payment).
 */
class QuotationConversionService
{
    public function __construct(
        private BookingService $bookings,
        private CrmActivityService $activity,
    ) {
    }

    /**
     * @throws \DomainException when the quote is not in a convertible state.
     */
    public function convert(CrmQuotation $quotation, ?int $performedBy = null): Booking
    {
        if ($quotation->converted_booking_id) {
            $existing = Booking::find($quotation->converted_booking_id);
            if ($existing) {
                return $existing; // idempotent — already converted
            }
        }

        if (! in_array($quotation->status, ['accepted', 'sent', 'viewed', 'negotiation'], true)) {
            throw new \DomainException("Quotation {$quotation->quotation_number} cannot be converted from status '{$quotation->status}'.");
        }

        $quotation->loadMissing(['lead.contact', 'contact', 'package', 'vehicle']);
        $lead = $quotation->lead;
        $contact = $quotation->contact ?? $lead?->contact;

        return DB::transaction(function () use ($quotation, $lead, $contact, $performedBy) {
            $pricing = $this->buildPricing($quotation);

            // Arrival/start date drives the rolling per-hotel check-in/out dates,
            // the package departure date and the cab pickup. Priority: the quote's
            // own travel_date → the lead's travel_start_date → today (so dates
            // always render even when neither was captured).
            $arrival = $quotation->travel_date
                ? \Illuminate\Support\Carbon::parse($quotation->travel_date)
                : ($lead?->travel_start_date
                    ? \Illuminate\Support\Carbon::parse($lead->travel_start_date)
                    : now()->startOfDay());

            // Snapshot the quote's hotels as immutable booking-hotel rows so the
            // tour voucher/itinerary shows the exact stays that were quoted.
            $hotelSnapshots = $this->buildHotelSnapshots($quotation, $arrival);

            // Carry the quote's itinerary, inclusions/exclusions and transfer info
            // onto the package booking so the voucher renders them even for custom
            // (non-package) tours where there's no linked package template.
            $voucherData = array_filter([
                'source' => 'crm_quotation',
                'quotation_number' => $quotation->quotation_number,
                'title' => $quotation->title,
                'itinerary' => $quotation->resolvedItinerary() ?: null,
                'inclusions' => $quotation->resolvedInclusions() ?: null,
                'exclusions' => $quotation->resolvedExclusions() ?: null,
                'pickup_location' => $quotation->pickup_location,
                'dropoff_location' => $quotation->dropoff_location,
                'vehicle_name' => $quotation->vehicle?->name,
            ], fn ($v) => ! empty($v));

            $booking = $this->bookings->create([
                'product_type' => 'package',
                'product_id' => $quotation->package_id,
                'user_id' => $contact?->user_id,
                'pricing' => $pricing,
                'items' => $this->buildItems($quotation),
                'contact' => $this->buildContact($lead, $contact),
                'package' => [
                    'package_id' => $quotation->package_id,
                    'package_name' => $quotation->package?->name ?? $quotation->title,
                    'adults' => $lead?->adults ?? $lead?->travellers_count ?? 2,
                    'children' => $lead?->children ?? 0,
                    'rooms' => $lead?->rooms ?? 1,
                    'departure_date' => $arrival,
                    'price_breakdown' => $pricing,
                    'hotels' => $hotelSnapshots,
                    'voucher_data' => $voucherData ?: null,
                ],
                'notes' => "Created from CRM quotation {$quotation->quotation_number}.",
            ]);

            // A private cab/transfer on the quote is attached to the package booking
            // (the unified booking engine only auto-creates a cab for product_type
            // 'cab', so package bookings need it created here).
            if ($quotation->vehicle_id || $quotation->pickup_location || $quotation->dropoff_location) {
                // cab_bookings.pickup_location and pickup_datetime are NOT NULL, so
                // guarantee sensible fallbacks (quotes may only carry a vehicle).
                CabBooking::create([
                    'booking_id' => $booking->id,
                    'vehicle_id' => $quotation->vehicle_id,
                    'vehicle_name' => $quotation->vehicle?->name,
                    'pickup_location' => $quotation->pickup_location ?: 'As per itinerary',
                    'drop_location' => $quotation->dropoff_location,
                    'pickup_datetime' => $arrival,
                    'trip_type' => 'one_way',
                ]);
            }

            // Extend the hold: CRM-created bookings shouldn't expire in the default
            // 30 minutes — give the customer until the quote's validity (min 3 days).
            $hold = $quotation->valid_until && $quotation->valid_until->isFuture()
                ? $quotation->valid_until->copy()->endOfDay()
                : now()->addDays(3);
            $booking->forceFill([
                'expires_at' => $hold,
                'discount_amount' => (float) $quotation->discount_amount,
            ])->save();

            $quotation->forceFill([
                'status' => 'converted',
                'converted_booking_id' => $booking->id,
            ])->save();

            if ($lead) {
                $this->activity->forLead($lead, 'quotation_converted',
                    "Quotation {$quotation->quotation_number} converted to booking {$booking->booking_reference}", [
                        'data' => ['booking_id' => $booking->id, 'booking_reference' => $booking->booking_reference],
                        'booking_id' => $booking->id,
                        'performed_by' => $performedBy,
                    ]);
            }

            return $booking;
        });
    }

    /**
     * Called from the booking confirmation path. If a confirmed booking originated
     * from a quotation, mark the lead converted (booking status 'confirmed' is the
     * ONLY authoritative conversion signal — never payment capture alone).
     */
    public function onBookingConfirmed(Booking $booking): void
    {
        $quotation = CrmQuotation::where('converted_booking_id', $booking->id)->first();
        if (! $quotation) {
            return;
        }

        $lead = $quotation->lead;
        if ($lead && $lead->status !== 'converted') {
            $lead->forceFill([
                'status' => 'converted',
                'converted_at' => now(),
            ])->save();

            $this->activity->forLead($lead, 'lead_converted',
                "Lead converted — booking {$booking->booking_reference} confirmed", [
                    'booking_id' => $booking->id,
                    'performed_by' => null,
                ]);

            app(\App\Services\Crm\AutomationEngine::class)->dispatch('booking_confirmed', $lead);
        }
    }

    private function buildPricing(CrmQuotation $q): array
    {
        $subtotal = (float) $q->subtotal;
        $tax = (float) $q->tax_amount;
        $discount = (float) $q->discount_amount;
        $total = (float) $q->total_amount;

        return [
            'supplier_cost' => $subtotal,   // unknown at quote time — mirror subtotal
            'subtotal' => $subtotal,
            'markup_amount' => 0.0,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'commission_amount' => 0.0,
            'currency' => $q->currency ?: 'INR',
            'source' => 'crm_quotation',
            'quotation_number' => $q->quotation_number,
        ];
    }

    /**
     * Map the quotation's resolved hotel stays into immutable BookingHotel rows.
     * Names/cities are already snapshotted on the quote, so the voucher stays
     * accurate even if the hotel master is later edited. When an arrival date is
     * known, check-in/check-out dates roll forward across the stays by nights.
     */
    private function buildHotelSnapshots(CrmQuotation $q, ?\Illuminate\Support\Carbon $arrival = null): array
    {
        $cursor = $arrival?->copy();

        return collect($q->resolvedHotels())
            ->map(function ($stay) use (&$cursor) {
                $nights = (int) ($stay['nights'] ?? 0);
                $checkIn = $cursor?->copy();
                $checkOut = ($cursor && $nights > 0) ? $cursor->copy()->addDays($nights) : $checkIn;

                // Advance the cursor for the next stay.
                if ($cursor && $nights > 0) {
                    $cursor->addDays($nights);
                }

                return array_filter([
                    'hotel_id' => $stay['hotel_id'] ?? null,
                    'segment_label' => $stay['location'] ?? $stay['city'] ?? null,
                    'hotel_name_snapshot' => $stay['hotel_name'] ?? null,
                    'address_snapshot' => $stay['city'] ?? null,
                    'nights' => $stay['nights'] ?? null,
                    'rooms' => 1,
                    'check_in' => $checkIn?->toDateString(),
                    'check_out' => $checkOut?->toDateString(),
                    'is_included' => true,
                    'status' => 'pending',
                ], fn ($v) => $v !== null && $v !== '');
            })
            ->filter(fn ($row) => ! empty($row['hotel_name_snapshot']) || ! empty($row['segment_label']))
            ->values()
            ->all();
    }

    private function buildItems(CrmQuotation $q): array
    {
        $lines = [];

        if (! empty($q->items) && is_array($q->items)) {
            foreach ($q->items as $item) {
                $amount = (float) ($item['amount'] ?? 0);
                $lines[] = [
                    'item_type' => 'package',
                    'name' => $item['label'] ?? $q->title,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total_price' => $amount,
                ];
            }
        }

        // Fallback / guarantee at least one line covering the quoted total.
        if (empty($lines)) {
            $lines[] = [
                'item_type' => 'package',
                'name' => $q->title,
                'quantity' => 1,
                'unit_price' => (float) $q->subtotal,
                'total_price' => (float) $q->subtotal,
            ];
        }

        return $lines;
    }

    private function buildContact(?CrmLead $lead, $contact): array
    {
        $name = $contact->name ?? $lead?->name ?? 'Guest';
        $email = $contact->email ?? $lead?->email;
        $phone = $contact->phone ?? $lead?->phone;

        return [
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => str_contains($name, ' ') ? trim(substr($name, strpos($name, ' '))) : '',
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }
}
