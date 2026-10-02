<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;

/**
 * Native in-app payments for the mobile app. Wraps the existing server-side
 * payment pipeline (BookingService::initiatePayment → PaymentManager) so the
 * app can open the Razorpay SDK with a server-created order and confirm with a
 * signature-verified callback. Amounts are ALWAYS taken from the booking on the
 * server — the client never supplies or influences the charge amount.
 *
 * Every endpoint is ownership-scoped: a user can only pay for their own booking.
 */
class PaymentController extends Controller
{
    use ApiResponse;

    /** Statuses from which a booking may still be paid. */
    private const PAYABLE = ['pending', 'payment_pending', 'failed'];

    /**
     * Create a gateway order for a booking. Returns the public order details the
     * native Razorpay SDK needs (order_id, amount in paise, currency, public
     * key_id, prefill). No secret keys are ever returned.
     */
    public function createOrder(Request $request, Booking $booking, BookingService $bookings)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        if ($booking->status === 'confirmed' || $booking->status === 'completed') {
            return $this->fail('This booking is already paid.', 409);
        }
        if (! in_array($booking->status, self::PAYABLE, true)) {
            return $this->fail('This booking cannot be paid in its current state.', 422);
        }

        $result = $bookings->initiatePayment($booking);

        if (! ($result['success'] ?? false)) {
            return $this->fail($result['error'] ?? 'Could not start payment.', 422);
        }

        return $this->ok([
            'gateway' => $result['gateway'],
            'order' => $result['order'],
            'booking_reference' => $result['booking_reference'],
        ], 'Payment order created.');
    }

    /**
     * Verify the payment result returned by the Razorpay SDK. On a valid
     * signature the booking is confirmed server-side (idempotent).
     */
    public function verify(Request $request, Booking $booking, PaymentManager $payments)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        // Guard: the order must belong to this booking.
        $ownsOrder = $booking->payments()
            ->where('gateway_order_id', $data['razorpay_order_id'])
            ->exists();

        if (! $ownsOrder) {
            return $this->fail('Payment does not match this booking.', 422);
        }

        $result = $payments->handleCallback($data);

        if (! ($result['success'] ?? false)) {
            return $this->fail($result['error'] ?? 'Payment verification failed.', 422);
        }

        $booking->refresh();

        return $this->ok([
            'booking_reference' => $result['booking_reference'] ?? $booking->booking_reference,
            'status' => $booking->status,
            'confirmed' => $booking->status === 'confirmed',
        ], $booking->status === 'confirmed'
            ? 'Payment successful and booking confirmed.'
            : 'Payment received. Your booking is being finalised.');
    }

    /** Poll payment/booking status (e.g. after backgrounding the app mid-payment). */
    public function status(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $latest = $booking->payments()->latest('id')->first();

        return $this->ok([
            'booking_reference' => $booking->booking_reference,
            'booking_status' => $booking->status,
            'payment_status' => $latest?->status,
            'paid_at' => $latest?->paid_at?->toIso8601String(),
            'total' => ['amount' => (float) $booking->total_amount, 'currency' => $booking->currency],
        ]);
    }
}
