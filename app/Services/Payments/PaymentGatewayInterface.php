<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;

/**
 * Contract for every payment gateway. Secrets stay server-side;
 * the frontend only receives what the gateway's client SDK needs.
 */
interface PaymentGatewayInterface
{
    public function __construct(array $config, string $mode);

    /**
     * Create an order. Returns:
     * ['success' => bool, 'order' => [gateway-specific client payload], 'payment_id' => int]
     */
    public function createOrder(Booking $booking): array;

    /**
     * Verify a client callback (signature check). Returns verified payment data or error.
     */
    public function verifyCallback(array $payload): array;

    /**
     * Verify + decode a webhook body. Returns ['valid' => bool, 'event' => ..., 'payment' => ...]
     */
    public function verifyWebhook(string $body, string $signature): array;

    public function refund(Payment $payment, float $amount): array;

    public function getPaymentStatus(Payment $payment): array;
}
