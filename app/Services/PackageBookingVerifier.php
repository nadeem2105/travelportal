<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageHotelOption;
use Illuminate\Support\Facades\Log;

/**
 * Pre-payment safety gate for package bookings that carry hotel selections.
 *
 * Re-verifies, server-side and just before the gateway order is created:
 *   1. every selected hotel option still exists, is active and available;
 *   2. API-supplied hotels still have live availability for the rooms/dates;
 *   3. the authoritative price hasn't drifted from what the customer is about to pay.
 *
 * Returns a structured result with codes ROOM_UNAVAILABLE / PRICE_CHANGED so the
 * checkout flow can react without ever silently swapping a hotel or a price.
 */
class PackageBookingVerifier
{
    public function __construct(
        protected PricingService $pricing,
        protected PackageHotelPricingService $hotelPricing,
        protected PackageFlightPricingService $flightPricing,
    ) {
    }

    /**
     * @return array{ok: bool, code: ?string, message: ?string, new_total: ?float}
     */
    public function verify(Booking $booking): array
    {
        if ($booking->product_type !== 'package') {
            return $this->ok();
        }

        $booking->loadMissing(['bookingHotels', 'packageFlights', 'packageBooking']);
        $hotels = $booking->bookingHotels;
        $flights = $booking->packageFlights;

        if ($hotels->isEmpty() && $flights->isEmpty()) {
            return $this->ok(); // land-only, nothing extra to verify
        }

        $package = Package::find($booking->packageBooking?->package_id ?? $booking->product_id);
        if (! $package) {
            return $this->ok();
        }

        $adults = (int) ($booking->packageBooking?->adults ?? 0);
        $children = (int) ($booking->packageBooking?->children ?? 0);
        $rooms = (int) ($booking->packageBooking?->room_count ?? 1);
        $date = optional($booking->packageBooking?->departure_date)->toDateString();

        // 1 + 2: each selected hotel option must still be bookable.
        $optionIds = [];
        foreach ($hotels as $line) {
            $option = $line->package_hotel_option_id
                ? PackageHotelOption::with('hotel')->find($line->package_hotel_option_id)
                : null;

            if (! $option || $option->status !== 'active') {
                return $this->fail('ROOM_UNAVAILABLE', 'The ' . $line->hotel_name_snapshot . ' room is no longer available. Please select another hotel.');
            }

            $optionIds[] = $option->id;

            if (! $this->supplierAvailable($option, $line, $date, $rooms)) {
                return $this->fail('ROOM_UNAVAILABLE', 'The ' . $line->hotel_name_snapshot . ' room is no longer available. Please select another hotel.');
            }
        }

        // Each selected flight option must still be active.
        $flightOptionId = null;
        foreach ($flights as $fline) {
            $fopt = $fline->package_flight_option_id
                ? \App\Models\PackageFlightOption::find($fline->package_flight_option_id)
                : null;

            if (! $fopt || $fopt->status !== 'active') {
                return $this->fail('ROOM_UNAVAILABLE', 'The selected flight (' . $fline->label_snapshot . ') is no longer available. Please choose another.');
            }
            $flightOptionId = $fopt->id;
        }

        // 3: authoritative reprice — compare against what's stored (pre-coupon).
        try {
            $adultPrice = $package->effectivePrice($date);
            $childPrice = (float) ($package->child_price ?? $adultPrice * 0.6);
            $base = round($adultPrice * $adults + $childPrice * $children, 2);

            $resolved = $this->hotelPricing->resolveSelection($package, $optionIds, $adults, $children, 0, $rooms, $date);
            if (! $resolved['valid']) {
                return $this->fail('ROOM_UNAVAILABLE', $resolved['error']);
            }

            $flightResolved = $this->flightPricing->resolveSelection($package, $flightOptionId, $adults, $children, $date);
            if (! $flightResolved['valid']) {
                return $this->fail('ROOM_UNAVAILABLE', $flightResolved['error']);
            }

            $fresh = $this->pricing->calculate(round($base + $resolved['upgrade_total'] + $flightResolved['total'], 2), 'package');

            // Compare to the pre-discount stored total so coupons don't trigger a false positive.
            $storedPreDiscount = round((float) $booking->total_amount + (float) $booking->discount_amount, 2);

            if (abs($fresh['total'] - $storedPreDiscount) > 1.0) {
                return [
                    'ok' => false,
                    'code' => 'PRICE_CHANGED',
                    'message' => 'The price for your selection has changed. Please review the updated total before paying.',
                    'new_total' => $fresh['total'],
                    'pricing' => $fresh,
                ];
            }
        } catch (\Throwable $e) {
            // Never block a customer because of a verifier bug — log and let it through.
            Log::warning('Package price re-verification failed; allowing checkout', [
                'booking' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->ok();
    }

    /**
     * For API-sourced hotels, confirm live availability. Manual hotels (no
     * supplier) rely on local inventory and are treated as available here.
     */
    protected function supplierAvailable(PackageHotelOption $option, $line, ?string $date, int $rooms): bool
    {
        if ($option->isManual() || ! $option->supplier_id) {
            return true;
        }

        try {
            $supplier = \App\Models\Supplier::find($option->supplier_id);
            if (! $supplier) {
                return true;
            }

            $adapter = app(\App\Services\HotelEngine::class)->adapter($supplier);
            $result = $adapter->checkAvailability(
                (string) $option->supplier_hotel_code,
                (string) $option->supplier_room_code,
                [
                    'check_in' => optional($line->check_in)->toDateString() ?? $date,
                    'check_out' => optional($line->check_out)->toDateString(),
                    'rooms' => $rooms,
                ]
            );

            // Unconfigured/demo suppliers return success=false — don't hard-block on that.
            if (($result['success'] ?? false) === true) {
                return (int) ($result['available'] ?? 0) >= $rooms;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Hotel availability check errored; allowing checkout', [
                'option' => $option->id,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    protected function ok(): array
    {
        return ['ok' => true, 'code' => null, 'message' => null, 'new_total' => null];
    }

    protected function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'new_total' => null];
    }
}
