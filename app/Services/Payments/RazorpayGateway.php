<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class RazorpayGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $config, protected string $mode = 'test')
    {
    }

    protected function baseUrl(): string
    {
        return 'https://api.razorpay.com/v1';
    }

    protected function auth(): array
    {
        return [
            ($this->config['key_id'] ?? ''),
            ($this->config['key_secret'] ?? ''),
        ];
    }

    public function publicKey(): string
    {
        return $this->config['key_id'] ?? '';
    }

    public function createOrder(Booking $booking): array
    {
        $amountInPaise = (int) round($booking->total_amount * 100);

        $response = Http::withBasicAuth(...$this->auth())
            ->timeout(30)
            ->post($this->baseUrl() . '/orders', [
                'amount' => $amountInPaise,
                'currency' => $booking->currency ?? 'INR',
                'receipt' => $booking->booking_reference,
                'notes' => [
                    'booking_reference' => $booking->booking_reference,
                    'product_type' => $booking->product_type,
                ],
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Unable to create payment order. Please try again.'];
        }

        $order = $response->json();

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $order['id'],
            'amount' => $booking->total_amount,
            'currency' => $booking->currency ?? 'INR',
            'status' => 'created',
        ]);

        return [
            'success' => true,
            'gateway' => 'razorpay',
            'payment_id' => $payment->id,
            'order' => [
                'order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency'],
                'key_id' => $this->publicKey(), // public key only
                'name' => config('app.name'),
                'description' => 'Booking ' . $booking->booking_reference,
                'prefill' => [
                    'name' => $booking->contact['first_name'] ?? '',
                    'email' => $booking->contact['email'] ?? '',
                    'contact' => $booking->contact['phone'] ?? '',
                ],
                'theme' => ['color' => '#2563eb'],
            ],
        ];
    }

    public function verifyCallback(array $payload): array
    {
        $expected = hash_hmac(
            'sha256',
            ($payload['razorpay_order_id'] ?? '') . '|' . ($payload['razorpay_payment_id'] ?? ''),
            $this->config['key_secret'] ?? ''
        );

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
        $secret = $this->config['webhook_secret'] ?? '';

        if ($secret === '') {
            return ['valid' => false, 'error' => 'Webhook secret not configured.'];
        }

        $expected = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($expected, $signature)) {
            return ['valid' => false, 'error' => 'Webhook signature verification failed.'];
        }

        return ['valid' => true, 'event' => json_decode($body, true)];
    }

    public function refund(Payment $payment, float $amount): array
    {
        $response = Http::withBasicAuth(...$this->auth())
            ->timeout(30)
            ->post($this->baseUrl() . '/payments/' . $payment->gateway_payment_id . '/refund', [
                'amount' => (int) round($amount * 100),
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => $response->json('error.description') ?? 'Refund failed at gateway.'];
        }

        $data = $response->json();

        return [
            'success' => true,
            'refund_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    public function getPaymentStatus(Payment $payment): array
    {
        $response = Http::withBasicAuth(...$this->auth())
            ->timeout(30)
            ->get($this->baseUrl() . '/payments/' . $payment->gateway_payment_id);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Unable to fetch payment status.'];
        }

        return ['success' => true, 'status' => $response->json('status')];
    }
}
