<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\WebhookEvent;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the active gateway from admin-managed configuration and
 * routes payment flows. Controllers never talk to gateways directly.
 */
class PaymentManager
{
    public const GATEWAYS = [
        'razorpay' => RazorpayGateway::class,
        'mock' => MockGateway::class,
    ];

    public function __construct(
        protected NotificationService $notifications,
    ) {
    }

    /**
     * Resolved lazily to avoid a circular constructor dependency
     * (BookingService ⇄ PaymentManager).
     */
    protected function bookings(): BookingService
    {
        return app(BookingService::class);
    }

    public function activeGateway(): ?PaymentGateway
    {
        return PaymentGateway::enabled()->first();
    }

    public function make(PaymentGateway $gatewayModel): PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$gatewayModel->code] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("Unknown gateway [{$gatewayModel->code}].");
        }

        return new $class($gatewayModel->config ?? [], $gatewayModel->mode);
    }

    public function createOrder(Booking $booking): array
    {
        $gatewayModel = $this->activeGateway();

        if (! $gatewayModel) {
            return ['success' => false, 'error' => 'No payment gateway is currently enabled. Please contact support.'];
        }

        try {
            return $this->make($gatewayModel)->createOrder($booking);
        } catch (\Throwable $e) {
            Log::error('Payment order creation failed', [
                'booking' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'We are unable to process payments right now. Please try again shortly.'];
        }
    }

    /**
     * Handle a verified client callback: mark payment captured, then
     * trigger supplier confirmation through the booking service.
     */
    public function handleCallback(array $payload): array
    {
        $gatewayModel = $this->activeGateway();

        if (! $gatewayModel) {
            return ['success' => false, 'error' => 'Payment gateway unavailable.'];
        }

        $verification = $this->make($gatewayModel)->verifyCallback($payload);

        if (! $verification['valid']) {
            return ['success' => false, 'error' => $verification['error'] ?? 'Payment verification failed.'];
        }

        return DB::transaction(function () use ($verification) {
            $payment = Payment::where('gateway_order_id', $verification['order_id'])->lockForUpdate()->first();

            if (! $payment) {
                return ['success' => false, 'error' => 'Payment record not found.'];
            }

            if ($payment->status === 'captured') {
                // idempotent: already processed
                return ['success' => true, 'booking_reference' => $payment->booking->booking_reference];
            }

            $payment->update([
                'gateway_payment_id' => $verification['payment_id'],
                'gateway_signature' => $verification['signature'],
                'status' => 'captured',
                'paid_at' => now(),
            ]);

            $this->bookings()->handlePaymentSuccess($payment->booking, $payment);

            return ['success' => true, 'booking_reference' => $payment->booking->booking_reference];
        });
    }

    /**
     * Secure webhook entry with signature verification and idempotency.
     */
    public function handleWebhook(string $provider, string $body, ?string $signature): array
    {
        $gatewayModel = PaymentGateway::where('code', $provider)->first();

        if (! $gatewayModel) {
            return ['success' => false, 'error' => 'Unknown provider.'];
        }

        $verification = $this->make($gatewayModel)->verifyWebhook($body, (string) $signature);

        if (! $verification['valid']) {
            Log::warning('Webhook signature verification failed', ['provider' => $provider]);

            return ['success' => false, 'error' => 'Invalid signature.'];
        }

        $event = $verification['event'] ?? [];

        $eventId = $event['id'] ?? ($event['payload']['payment']['entity']['id'] ?? ('evt_' . md5(json_encode($event)))) . '-' . ($event['event'] ?? 'unknown');

        return DB::transaction(function () use ($provider, $eventId, $event, $signature) {
            $stored = WebhookEvent::where('event_id', (string) $eventId)->lockForUpdate()->first();

            if (! $stored) {
                $stored = WebhookEvent::create([
                    'event_id' => (string) $eventId,
                    'provider' => $provider,
                    'event_type' => $event['event'] ?? 'unknown',
                    'payload' => $event,
                    'signature' => $signature,
                ]);
            } elseif ($stored->processed_at) {
                return ['success' => true, 'duplicate' => true];
            }

            $type = $event['event'] ?? '';

            if (str_starts_with($type, 'payment.captured')) {
                $entity = $event['payload']['payment']['entity'] ?? [];
                $payment = Payment::where('gateway_order_id', $entity['order_id'] ?? '')->lockForUpdate()->first();

                if ($payment && $payment->status !== 'captured') {
                    $payment->update([
                        'gateway_payment_id' => $entity['id'] ?? $payment->gateway_payment_id,
                        'status' => 'captured',
                        'paid_at' => now(),
                        'webhook_payload' => $event,
                    ]);

                    $this->bookings()->handlePaymentSuccess($payment->booking, $payment);
                }
            } elseif (str_starts_with($type, 'refund.processed')) {
                $entity = $event['payload']['refund']['entity'] ?? [];
                $payment = Payment::where('gateway_payment_id', $entity['payment_id'] ?? '')->lockForUpdate()->first();

                $payment?->refunds()->where('status', 'initiated')->update([
                    'status' => 'processed',
                    'gateway_refund_id' => $entity['id'] ?? null,
                    'processed_at' => now(),
                ]);

                $payment?->booking?->update(['status' => 'refunded', 'refund_status' => 'processed']);
            }

            $stored->update(['processed_at' => now()]);

            return ['success' => true];
        });
    }

    public function refund(Payment $payment, float $amount): array
    {
        $gatewayModel = PaymentGateway::where('code', $payment->gateway)->first();

        if (! $gatewayModel) {
            return ['success' => false, 'error' => 'Gateway not configured.'];
        }

        try {
            return $this->make($gatewayModel)->refund($payment, $amount);
        } catch (\Throwable $e) {
            Log::error('Refund failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Refund could not be processed at the gateway.'];
        }
    }
}
