<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;

/**
 * Sandbox gateway for local development / demos. Mimics the Razorpay
 * checkout handshake without touching real money. Enabled from
 * Admin → Settings → Payment by turning "Mock Gateway" on.
 */
class MockGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $config, protected string $mode = 'test')
    {
    }

    public function createOrder(Booking $booking): array
    {
        $orderId = 'mock_order_' . strtoupper(substr(md5($booking->booking_reference . microtime()), 0, 14));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'gateway' => 'mock',
            'gateway_order_id' => $orderId,
            'amount' => $booking->total_amount,
            'currency' => $booking->currency ?? 'INR',
            'status' => 'created',
            'payload' => ['mode' => 'sandbox'],
        ]);

        return [
            'success' => true,
            'gateway' => 'mock',
            'payment_id' => $payment->id,
            'order' => [
                'order_id' => $orderId,
                'amount' => (int) round($booking->total_amount * 100),
                'currency' => 'INR',
                'name' => config('app.name'),
                'description' => 'Booking ' . $booking->booking_reference,
            ],
        ];
    }

    /**
     * Sandbox signature: HMAC of order|payment with the shared sandbox secret.
     */
    public function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId . '|' . $paymentId, $this->config['secret'] ?? 'mock-secret');
    }

    public function verifyCallback(array $payload): array
    {
        $expected = $this->sign($payload['razorpay_order_id'] ?? '', $payload['razorpay_payment_id'] ?? '');

        if (! hash_equals($expected, $payload['razorpay_signature'] ?? '')) {
            return ['valid' => false, 'error' => 'Payment signature verification failed.'];
        }

        return [
            'valid' => true,
            'order_id' => $payload['razorpay_order_id'],
            'payment_id' => $payload['razorpay_payment_id'],
            'signature' => $payload['razorpay_signature'],
        ];
    }

    public function verifyWebhook(string $body, string $signature): array
    {
        $expected = hash_hmac('sha256', $body, $this->config['webhook_secret'] ?? 'mock-webhook-secret');

        if (! hash_equals($expected, $signature)) {
            return ['valid' => false, 'error' => 'Webhook signature verification failed.'];
        }

        return ['valid' => true, 'event' => json_decode($body, true)];
    }

    public function refund(Payment $payment, float $amount): array
    {
        return [
            'success' => true,
            'refund_id' => 'mock_rfnd_' . strtoupper(substr(md5(microtime()), 0, 12)),
            'status' => 'processed',
        ];
    }

    public function getPaymentStatus(Payment $payment): array
    {
        return ['success' => true, 'status' => $payment->status];
    }
}
