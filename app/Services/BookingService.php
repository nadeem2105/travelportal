<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingHotel;
use App\Models\BookingItem;
use App\Models\BookingTraveller;
use App\Models\FlightBooking;
use App\Models\HotelBooking;
use App\Models\PackageDeparture;
use App\Models\PackageBooking;
use App\Models\CabBooking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified booking engine for flights, hotels, cabs and packages.
 * Every booking recalculates its final price server-side via PricingService;
 * frontend-provided amounts are never persisted.
 */
class BookingService
{
    public function __construct(
        protected PricingService $pricing,
        protected CouponService $coupons,
        protected SettingsService $settings,
        protected NotificationService $notifications,
        protected PaymentManager $payments,
    ) {
    }

    public function generateReference(): string
    {
        $prefix = $this->settings->get('booking_id_prefix', 'VNH');

        do {
            $reference = $prefix . strtoupper(substr(uniqid(), -6)) . random_int(10, 99);
        } while (Booking::where('booking_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Create a booking draft (status: payment_pending) with server-calculated price.
     *
     * @param  array  $payload  product-specific payload built by the controller
     */
    public function create(array $payload): Booking
    {
        return DB::transaction(function () use ($payload) {
            $booking = Booking::create([
                'booking_reference' => $this->generateReference(),
                'user_id' => $payload['user_id'] ?? auth('web')->id(),
                'agent_id' => $payload['agent_id'] ?? null,
                'agent_commission' => $payload['agent_commission'] ?? 0,
                'product_type' => $payload['product_type'],
                'product_id' => $payload['product_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'status' => 'payment_pending',
                'supplier_cost' => $payload['pricing']['supplier_cost'],
                'subtotal' => $payload['pricing']['subtotal'],
                'markup_amount' => $payload['pricing']['markup_amount'],
                'tax_amount' => $payload['pricing']['tax_amount'],
                'total_amount' => $payload['pricing']['total'],
                'commission_amount' => $payload['pricing']['commission_amount'] ?? 0,
                'currency' => $payload['pricing']['currency'] ?? 'INR',
                'contact' => $payload['contact'] ?? null,
                'price_breakdown' => $payload['pricing'],
                'expires_at' => now()->addMinutes((int) $this->settings->get('booking_expiry_minutes', 30)),
            ]);

            foreach ($payload['items'] as $item) {
                $booking->items()->create($item);
            }

            foreach ($payload['travellers'] ?? [] as $index => $traveller) {
                $booking->travellers()->create($traveller + ['is_primary' => $index === 0]);
            }

            match ($payload['product_type']) {
                'flight' => $this->createFlightDetail($booking, $payload),
                'hotel' => $this->createHotelDetail($booking, $payload),
                'cab' => $this->createCabDetail($booking, $payload),
                'package' => $this->createPackageDetail($booking, $payload),
            };

            if (! $booking->user_id) {
                // guest checkout: remember this booking in the session
                session()->push("guest_bookings", $booking->booking_reference);
            }

            return $booking;
        });
    }

    protected function createFlightDetail(Booking $booking, array $payload): void
    {
        FlightBooking::create([
            'booking_id' => $booking->id,
            'airline_code' => $payload['flight']['airline']['code'] ?? null,
            'flight_number' => $payload['flight']['flight_number'] ?? null,
            'journey' => [
                'segments' => $payload['flight']['segments'] ?? [],
                'return_segments' => $payload['flight']['return_segments'] ?? [],
                'result_id' => $payload['flight']['result_id'] ?? null,
                'trip_type' => $payload['flight']['trip_type'] ?? 'one_way',
                'cabin_class' => $payload['flight']['cabin_class'] ?? 'economy',
                'search_params' => $payload['flight']['search_params'] ?? session('flight_search_params', []),
            ],
            'fare_details' => $payload['flight']['fare'] ?? [],
            'seat_selection' => $payload['seat_selection'] ?? null,
            'baggage_selection' => $payload['baggage_selection'] ?? null,
            'trip_type' => $payload['flight']['trip_type'] ?? 'one_way',
        ]);
    }

    protected function createHotelDetail(Booking $booking, array $payload): void
    {
        $checkIn = \Illuminate\Support\Carbon::parse($payload['hotel']['check_in']);
        $checkOut = \Illuminate\Support\Carbon::parse($payload['hotel']['check_out']);

        HotelBooking::create([
            'booking_id' => $booking->id,
            'hotel_id' => $payload['hotel']['hotel_id'] ?? null,
            'hotel_name' => $payload['hotel']['hotel_name'] ?? null,
            'room_type' => $payload['hotel']['room_type'] ?? null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => $checkIn->diffInDays($checkOut),
            'rooms' => $payload['hotel']['rooms'] ?? 1,
            'guests' => $payload['hotel']['guests'] ?? null,
            'meal_plan' => $payload['hotel']['meal_plan'] ?? 'room_only',
        ]);
    }

    protected function createCabDetail(Booking $booking, array $payload): void
    {
        CabBooking::create([
            'booking_id' => $booking->id,
            'vehicle_id' => $payload['cab']['vehicle_id'] ?? null,
            'vehicle_name' => $payload['cab']['vehicle_name'] ?? null,
            'pickup_location' => $payload['cab']['pickup_location'],
            'drop_location' => $payload['cab']['drop_location'] ?? null,
            'pickup_datetime' => $payload['cab']['pickup_datetime'],
            'trip_type' => $payload['cab']['trip_type'] ?? 'one_way',
            'distance_km' => $payload['cab']['distance_km'] ?? 0,
            'fare_breakdown' => $payload['cab']['fare_breakdown'] ?? null,
        ]);
    }

    protected function createPackageDetail(Booking $booking, array $payload): void
    {
        PackageBooking::create([
            'booking_id' => $booking->id,
            'package_id' => $payload['package']['package_id'] ?? null,
            'package_name' => $payload['package']['package_name'] ?? null,
            'departure_date' => $payload['package']['departure_date'] ?? null,
            'adults' => $payload['package']['adults'] ?? 2,
            'children' => $payload['package']['children'] ?? 0,
            'room_count' => $payload['package']['rooms'] ?? 1,
            'price_breakdown' => $payload['package']['price_breakdown'] ?? null,
            'voucher_data' => $payload['package']['voucher_data'] ?? null,
        ]);

        // Immutable hotel snapshot(s) — stays historically accurate even if the
        // hotel master/option is later edited or deleted.
        foreach ($payload['package']['hotels'] ?? [] as $line) {
            BookingHotel::create(array_merge($line, ['booking_id' => $booking->id]));
        }

        // Immutable flight snapshot(s) for MakeMyTrip-style package flights.
        foreach ($payload['package']['flights'] ?? [] as $line) {
            \App\Models\BookingPackageFlight::create(array_merge($line, ['booking_id' => $booking->id]));
        }

        if (! empty($payload['package']['departure_id'])) {
            $seats = (int) (($payload['package']['adults'] ?? 0) + ($payload['package']['children'] ?? 0));
            $departure = PackageDeparture::where('id', $payload['package']['departure_id'])->lockForUpdate()->first();

            if ($departure) {
                if (($departure->booked + $seats) > $departure->inventory) {
                    throw new \DomainException('Not enough seats available on the selected departure date.');
                }
                $departure->increment('booked', $seats);
            }
        }
    }

    public function applyCoupon(Booking $booking, string $code): array
    {
        $result = $this->coupons->validate($code, (float) $booking->subtotal, $booking->product_type, $booking->user_id);

        if (! $result['valid']) {
            return $result;
        }

        // Always calculate from the original pre-discount amounts to prevent stacking
        $originalTotal = (float) $booking->subtotal + (float) $booking->markup_amount + (float) $booking->tax_amount;
        $total = max(0, $originalTotal - $result['discount']);
        $booking->update([
            'coupon_id' => $result['coupon_id'],
            'discount_amount' => $result['discount'],
            'total_amount' => round($total, 2),
            'price_breakdown' => array_merge($booking->price_breakdown ?? [], [
                'coupon' => ['code' => $result['code'], 'discount' => $result['discount']],
                'total' => round($total, 2),
            ]),
        ]);

        return $result;
    }

    /**
     * Initiate payment: creates gateway order, returns client payload.
     */
    public function initiatePayment(Booking $booking): array
    {
        $payment = $this->payments->createOrder($booking);

        if (! $payment['success']) {
            return $payment;
        }

        return [
            'success' => true,
            'gateway' => $payment['gateway'],
            'order' => $payment['order'],
            'booking_reference' => $booking->booking_reference,
        ];
    }

    /**
     * Called after verified payment. Confirms booking with the supplier
     * (where applicable). Payment success alone NEVER confirms a booking.
     */
    public function handlePaymentSuccess(Booking $booking, Payment $payment): void
    {
        $locked = Booking::where('id', $booking->id)->lockForUpdate()->first();
        if (! $locked || $locked->status === 'confirmed') {
            return; // idempotency guard
        }

        $confirmed = $this->confirmWithSupplier($locked);

        $locked->update([
            'status' => $confirmed ? 'confirmed' : 'payment_success_booking_failed',
            'booked_at' => now(),
        ]);

        if ($confirmed) {
            $this->confirmBookingHotels($locked);

            // CRM: if this booking originated from a quotation, mark the lead
            // converted. Booking status 'confirmed' is the ONLY conversion signal.
            try {
                app(\App\Services\Crm\QuotationConversionService::class)->onBookingConfirmed($locked);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('CRM lead conversion hook failed: ' . $e->getMessage());
            }

            // Trip Operations: build the operational trip overlay + itinerary for
            // operationally-relevant bookings. Non-fatal; never blocks confirmation.
            try {
                if (\App\Models\TripOperationSetting::get('auto_generate_itinerary', true)) {
                    $trip = app(\App\Services\TripOperations\TripOperationsService::class)->syncFromBooking($locked);
                    if ($trip && \App\Models\TripOperationSetting::get('send_customer_itinerary_on_generate', false)) {
                        app(\App\Services\TripOperations\TripCommService::class)->sendCustomerItinerary($trip);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Trip Operations sync hook failed: ' . $e->getMessage());
            }
        }

        $this->notifications->sendBookingConfirmation($locked, $confirmed);

        if ($confirmed) {
            $this->notifications->notifyAdmins($locked);
            // Real purchase conversion with revenue + tax/discount. Uses
            // booking:{reference} as the shared event_id so the browser `purchase`
            // and this server forward (GA4 MP + Meta CAPI) dedupe to one conversion.
            \App\Services\AnalyticsService::trackPurchase($locked, 'booking_confirmed');
        } else {
            \App\Services\AnalyticsService::track('booking_supplier_failed', [
                'product_type' => $locked->product_type,
                'booking_reference' => $locked->booking_reference,
                'amount' => (float) $locked->total_amount,
            ]);
        }
    }

    /**
     * Mark a package booking's hotel snapshot rows as confirmed. For API-sourced
     * hotels this is where a real supplier booking id would be recorded; manual
     * hotels simply flip to confirmed.
     */
    public function confirmBookingHotels(Booking $booking): void
    {
        $booking->bookingHotels()->where('status', '!=', 'confirmed')->update(['status' => 'confirmed']);
        $booking->packageFlights()->where('status', '!=', 'confirmed')->update(['status' => 'confirmed']);
    }

    /**
     * Confirm the booking with its supplier. Manual products (packages,
     * cabs, manual hotels) confirm immediately.
     */
    public function confirmWithSupplier(Booking $booking): bool
    {
        try {
            return match ($booking->product_type) {
                'flight' => $this->confirmFlight($booking),
                'hotel' => $this->confirmHotel($booking),
                'cab', 'package' => true,
                default => false,
            };
        } catch (\Throwable $e) {
            Log::error('Supplier booking confirmation failed', [
                'booking' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function confirmFlight(Booking $booking): bool
    {
        $supplier = $booking->supplier;
        if (! $supplier) {
            return false;
        }

        $engine = app(FlightEngine::class);
        $adapter = $engine->adapter($supplier);

        $journey = $booking->flight->journey ?? [];
        $params = (!empty($journey['search_params']) ? $journey['search_params'] : session('flight_search_params', []))
            + ['result_id' => $journey['result_id'] ?? null];

        $response = $adapter->createBooking(
            $journey['result_id'] ?? '',
            $params,
            $booking->travellers->toArray(),
            $booking->contact ?? []
        );

        if ($response['success'] ?? false) {
            $ticket = $adapter->issueTicket($response['supplier_booking_id']);

            $booking->update([
                'supplier_booking_id' => $response['supplier_booking_id'],
            ]);

            $booking->flight()->update([
                'pnr' => $response['pnr'] ?? null,
                'ticket_number' => $ticket['ticket_number'] ?? null,
                'ticket_data' => $ticket,
            ]);

            return true;
        }

        return false;
    }

    protected function confirmHotel(Booking $booking): bool
    {
        $detail = $booking->hotelBooking;
        $hotel = \App\Models\Hotel::find($detail->hotel_id ?? null);

        // Manually managed hotels confirm immediately; API hotels go through supplier.
        if ($hotel && $hotel->supplier_id) {
            $supplier = \App\Models\Supplier::find($hotel->supplier_id);
            if ($supplier) {
                $adapter = app(HotelEngine::class)->adapter($supplier);
                $response = $adapter->createBooking(
                    'MANUAL-' . $hotel->id,
                    'R-0',
                    [
                        'check_in' => $detail->check_in->toDateString(),
                        'check_out' => $detail->check_out->toDateString(),
                        'rooms' => $detail->rooms,
                    ],
                    $detail->guests ?? [],
                    $booking->contact ?? []
                );

                if ($response['success'] ?? false) {
                    $detail->update(['supplier_booking_id' => $response['supplier_booking_id']]);

                    return true;
                }

                return false;
            }
        }

        $detail->update(['supplier_booking_id' => 'DIRECT-' . $booking->booking_reference]);

        return true;
    }

    public function requestCancellation(Booking $booking, string $reason, ?int $userId = null): Refund
    {
        $refund = Refund::create([
            'booking_id' => $booking->id,
            'payment_id' => $booking->successfulPayment->id ?? null,
            'amount' => 0, // set when approved
            'reason' => $reason,
            'status' => 'requested',
            'requested_by' => $userId ?? auth('web')->id(),
        ]);

        $booking->update([
            'cancellation_status' => 'requested',
            'refund_status' => 'requested',
            'notes' => trim(($booking->notes ? $booking->notes . "\n" : '') . 'Cancellation requested: ' . $reason),
        ]);

        return $refund;
    }

    public function approveCancellation(Booking $booking, Refund $refund, float $penaltyPercent, int $adminId): void
    {
        $penalty = round((float) $booking->total_amount * ($penaltyPercent / 100), 2);
        $refundAmount = round((float) $booking->total_amount - $penalty, 2);

        DB::transaction(function () use ($booking, $refund, $penalty, $refundAmount, $adminId) {
            // cancel with supplier first
            $this->cancelWithSupplier($booking);

            $refund->update([
                'amount' => $refundAmount,
                'penalty_amount' => $penalty,
                'status' => 'initiated',
                'processed_by' => $adminId,
            ]);

            $booking->update([
                'cancellation_status' => 'cancelled',
                'refund_status' => 'initiated',
                'status' => 'refund_initiated',
                'cancelled_at' => now(),
            ]);
        });

        // Negative-revenue conversion (GA4 `refund` / Meta CAPI). Distinct event_id
        // from the purchase so it doesn't collide with the booking_confirmed dedup.
        \App\Services\AnalyticsService::track('booking_cancelled', [
            'event_id' => 'refund:' . $booking->booking_reference,
            'product_type' => $booking->product_type,
            'product_id' => $booking->product_id,
            'user_id' => $booking->user_id,
            'transaction_id' => $booking->booking_reference,
            'booking_reference' => $booking->booking_reference,
            'value' => $refundAmount,
            'currency' => $booking->currency ?: 'INR',
            'penalty' => $penalty,
        ]);
    }

    protected function cancelWithSupplier(Booking $booking): bool
    {
        if (! $booking->supplier_booking_id || ! $booking->supplier) {
            return true;
        }

        try {
            $adapter = match ($booking->product_type) {
                'flight' => app(FlightEngine::class)->adapter($booking->supplier),
                'hotel' => app(HotelEngine::class)->adapter($booking->supplier),
                default => null,
            };

            $result = $adapter?->cancelBooking($booking->supplier_booking_id);

            if ($result === false || (is_array($result) && empty($result['success']))) {
                Log::warning('Supplier cancellation was rejected but local cancellation proceeding', [
                    'booking' => $booking->booking_reference,
                    'supplier' => $booking->supplier->name ?? null,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Supplier cancellation failed', [
                'booking' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function processRefund(Refund $refund, int $adminId): array
    {
        $payment = $refund->payment ?? $refund->booking->successfulPayment;

        if (! $payment) {
            return ['success' => false, 'error' => 'No successful payment found for this booking.'];
        }

        $response = $this->payments->refund($payment, $refund->amount);

        if ($response['success']) {
            $refund->update([
                'status' => 'processed',
                'gateway_refund_id' => $response['refund_id'] ?? null,
                'gateway_response' => $response,
                'processed_at' => now(),
                'processed_by' => $adminId,
            ]);

            $refund->booking->update([
                'status' => 'refunded',
                'refund_status' => 'processed',
            ]);
        } else {
            $refund->update([
                'status' => 'failed',
                'gateway_response' => $response,
            ]);
        }

        return $response;
    }
}
