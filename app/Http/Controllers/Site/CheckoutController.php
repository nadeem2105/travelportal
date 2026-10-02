<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CouponService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentManager $payments,
        protected CouponService $coupons,
    ) {
    }

    public function show(Request $request, Booking $booking)
    {
        abort_if($booking->status === 'cancelled', 404);
        abort_unless($booking->user_id === null || $booking->user_id === auth('web')->id() || $booking->sessionOwned(), 403);

        \App\Services\AnalyticsService::track('begin_checkout', [
            'product_type' => $booking->product_type,
            'product_id' => $booking->product_id,
            'booking_reference' => $booking->booking_reference,
            'amount' => (float) $booking->total_amount,
        ]);

        $gateway = $this->payments->activeGateway();

        return view('checkout.show', [
            'seo' => ['title' => 'Secure Checkout', 'description' => ''],
            'booking' => $booking->load(['items', 'travellers', 'payments']),
            'gateway' => $gateway,
            'gatewayConfig' => $this->publicGatewayConfig($booking),
        ]);
    }

    public function applyCoupon(Request $request, Booking $booking)
    {
        abort_if($booking->status === 'cancelled', 404);
        abort_unless($booking->user_id === null || $booking->user_id === auth('web')->id() || $booking->sessionOwned(), 403);

        $request->validate(['code' => 'required|string|max:40']);

        $result = app(\App\Services\BookingService::class)->applyCoupon($booking, $request->input('code'));

        if (! $result['valid']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', "Coupon {$result['code']} applied — you saved " . money($result['discount']) . '!');
    }

    /**
     * Create a gateway order and render the hosted-checkout handoff.
     */
    public function pay(Request $request, Booking $booking)
    {
        abort_if($booking->status === 'cancelled', 404);
        abort_unless($booking->user_id === null || $booking->user_id === auth('web')->id() || $booking->sessionOwned(), 403);

        if (in_array($booking->status, ['confirmed', 'completed'])) {
            return redirect()->route('booking.confirmation', $booking);
        }

        // Terms & cancellation policy must be accepted before payment.
        if (! $request->boolean('accept_terms')) {
            return redirect()->route('checkout.show', $booking)
                ->with('error', 'Please accept the Terms & Conditions and Cancellation Policy to continue.');
        }

        // Atomic lock so rapid double-clicks / duplicate tabs can't start two payments.
        $lock = \Illuminate\Support\Facades\Cache::lock('checkout-pay:' . $booking->id, 15);
        if (! $lock->get()) {
            return redirect()->route('checkout.show', $booking)
                ->with('error', 'Your payment is already being processed — please wait a moment before trying again.');
        }

        try {
        // Pre-payment safety gate: re-verify hotel availability + price server-side.
        $check = app(\App\Services\PackageBookingVerifier::class)->verify($booking);

        if (! $check['ok']) {
            if ($check['code'] === 'PRICE_CHANGED' && ! empty($check['pricing'])) {
                // Apply the fresh price and send the customer back to confirm it — never pay a stale amount.
                $fresh = $check['pricing'];
                $booking->update([
                    'supplier_cost' => $fresh['supplier_cost'],
                    'subtotal' => $fresh['subtotal'],
                    'markup_amount' => $fresh['markup_amount'],
                    'tax_amount' => $fresh['tax_amount'],
                    'total_amount' => $fresh['total'],
                    'coupon_id' => null,
                    'discount_amount' => 0,
                    'price_breakdown' => $fresh,
                ]);

                return redirect()->route('checkout.show', $booking)
                    ->with('error', $check['message'] . ' New total: ' . money($fresh['total']) . '. Any coupon was removed — please re-apply if needed.');
            }

            return redirect()->route('checkout.show', $booking)->with('error', $check['message']);
        }

        $response = app(\App\Services\BookingService::class)->initiatePayment($booking);

        if (! $response['success']) {
            return back()->with('error', $response['error'] ?? 'Unable to start payment. Please try again.');
        }

        \App\Services\AnalyticsService::track('payment_initiated', [
            'product_type' => $booking->product_type,
            'product_id' => $booking->product_id,
            'booking_reference' => $booking->booking_reference,
            'value' => (float) $booking->total_amount,
            'currency' => $booking->currency ?: 'INR',
            'gateway' => $response['gateway'] ?? null,
        ]);

        if ($response['gateway'] === 'mock') {
            return view('checkout.mock', [
                'seo' => ['title' => 'Sandbox Payment', 'description' => ''],
                'booking' => $booking,
                'order' => $response['order'],
            ]);
        }

        return view('checkout.razorpay', [
            'seo' => ['title' => 'Secure Payment', 'description' => ''],
            'booking' => $booking,
            'order' => $response['order'],
        ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Sandbox gateway confirm: signs the payload server-side, then runs the
     * exact same verification pipeline as the live gateway.
     */
    public function mockPay(Request $request, Booking $booking)
    {
        abort_if($booking->status === 'cancelled', 404);
        abort_unless($booking->user_id === null || $booking->user_id === auth('web')->id() || $booking->sessionOwned(), 403);

        $gateway = $this->payments->activeGateway();

        if (! $gateway || $gateway->code !== 'mock') {
            abort(404);
        }

        $payment = $booking->payments()->latest()->first();

        if (! $payment || $payment->status === 'captured') {
            return redirect()->route('booking.confirmation', $booking);
        }

        // Atomic lock so a double-click can't capture the same payment twice.
        $lock = \Illuminate\Support\Facades\Cache::lock('checkout-capture:' . $booking->id, 20);
        if (! $lock->get()) {
            return redirect()->route('booking.confirmation', $booking)
                ->with('info', 'Your payment is being processed…');
        }

        try {
            // Re-read under the lock in case a parallel request already captured it.
            $payment->refresh();
            if ($payment->status === 'captured') {
                return redirect()->route('booking.confirmation', $booking->fresh());
            }

            $gatewayInstance = $this->payments->make($gateway);
            $paymentId = 'mock_pay_' . strtoupper(substr(md5(microtime()), 0, 14));

            $payload = [
                'razorpay_order_id' => $payment->gateway_order_id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $gatewayInstance->sign($payment->gateway_order_id, $paymentId),
            ];

            // Real signature verification path (identical to live flow)
            $result = $this->payments->handleCallback($payload);

            if (! $result['success']) {
                $payment->update(['status' => 'failed']);

                return redirect()->route('checkout.show', $booking)->with('error', $result['error'] ?? 'Payment failed.');
            }

            return redirect()->route('booking.confirmation', $booking->fresh());
        } finally {
            $lock->release();
        }
    }

    /**
     * Razorpay client-side handler posts here after checkout.js success.
     */
    public function callback(Request $request)
    {
        $result = $this->payments->handleCallback($request->all());

        if (! $result['success']) {
            return back()->with('error', $result['error'] ?? 'Payment verification failed.');
        }

        $booking = Booking::where('booking_reference', $result['booking_reference'] ?? '')->first();

        return redirect()->route('booking.confirmation', $booking ?? '/');
    }

    public function confirmation(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($booking);

        return view('checkout.confirmation', [
            'seo' => ['title' => 'Booking ' . $booking->booking_reference, 'description' => ''],
            'booking' => $booking->load(['items', 'travellers', 'payments', 'flight', 'hotelBooking.hotel', 'cab.vehicle', 'bookingHotels', 'packageFlights', 'packageBooking.package.itineraries', 'packageBooking.package.hotels', 'packageBooking.package.destination']),
        ]);
    }

    public function invoice(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($booking);

        $booking->load([
            'items', 'travellers', 'payments', 'user',
            'flight',
            'hotelBooking.hotel',
            'cab.vehicle',
            'packageBooking.package.itineraries',
            'packageBooking.package.hotels',
            'packageBooking.package.destination',
        ]);

        return view('account.invoice', compact('booking'));
    }

    public function itinerary(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($booking);

        $booking->load([
            'items', 'travellers', 'payments', 'user',
            'flight',
            'hotelBooking.hotel',
            'cab.vehicle',
            'bookingHotels',
            'packageFlights',
            'packageBooking.package.itineraries',
            'packageBooking.package.hotels',
            'packageBooking.package.destination',
        ]);

        return view('account.itinerary', compact('booking'));
    }

    protected function authorizeBookingAccess(Booking $booking): void
    {
        $allowed = $booking->user_id === null
            || $booking->user_id === auth('web')->id()
            || $booking->sessionOwned()
            || auth('admin')->check();

        abort_unless($allowed, 403);
    }


    protected function publicGatewayConfig(Booking $booking): array
    {
        $gateway = $this->payments->activeGateway();

        if (! $gateway) {
            return [];
        }

        // Only non-secret values are exposed to the frontend.
        $config = $gateway->config ?? [];

        return match ($gateway->code) {
            'razorpay' => ['key_id' => $config['key_id'] ?? '', 'currency' => $gateway->currency],
            'mock' => ['currency' => $gateway->currency],
            default => [],
        };
    }
}
