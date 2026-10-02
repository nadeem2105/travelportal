<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\MockGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CouponAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_coupon_validation_rejects_expired(): void
    {
        Coupon::create([
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'ends_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $result = app(\App\Services\CouponService::class)->validate('EXPIRED', 20000, 'package');

        $this->assertFalse($result['valid']);
    }

    public function test_coupon_respects_min_amount_and_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'MINCHECK',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_booking_amount' => 50000,
            'usage_limit' => 1,
            'used_count' => 1,
            'status' => 'active',
        ]);

        $belowMin = app(\App\Services\CouponService::class)->validate('MINCHECK', 20000, 'package');
        $this->assertFalse($belowMin['valid']);

        $coupon->update(['used_count' => 1]);
        $exhausted = app(\App\Services\CouponService::class)->validate('MINCHECK', 99000, 'package');
        $this->assertFalse($exhausted['valid']);
    }

    public function test_coupon_applies_max_discount_cap(): void
    {
        Coupon::create([
            'code' => 'CAPPED',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_discount' => 1000,
            'status' => 'active',
        ]);

        $result = app(\App\Services\CouponService::class)->validate('CAPPED', 50000, 'package');

        $this->assertTrue($result['valid']);
        $this->assertEquals(1000, $result['discount']);
    }

    public function test_razorpay_webhook_rejects_bad_signature(): void
    {
        $body = json_encode(['event' => 'payment.captured', 'payload' => []]);

        $response = $this->postJson('/webhooks/razorpay', json_decode($body, true), [
            'X-Razorpay-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(400);
    }

    public function test_mock_webhook_idempotency(): void
    {
        // Configure the razorpay gateway webhook secret for this test
        \App\Models\PaymentGateway::updateOrCreate(
            ['code' => 'razorpay'],
            ['name' => 'Razorpay', 'config' => ['webhook_secret' => 'mock-webhook-secret']]
        );

        $payload = [
            'id' => 'evt_test_1',
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => ['order_id' => 'nonexistent', 'id' => 'pay_1']]],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'mock-webhook-secret');

        // valid signature (mock gateway secrets are seeded), unknown order
        $this->postJson('/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => $signature])
            ->assertOk();

        $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_test_1', 'provider' => 'razorpay']);
        $this->assertNotNull(WebhookEvent::where('event_id', 'evt_test_1')->first()->processed_at);

        // replay the same event — must be a no-op duplicate
        $response = $this->postJson('/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => $signature]);
        $response->assertOk()->assertJsonPath('duplicate', true);

        $this->assertEquals(1, WebhookEvent::count());
    }

    public function test_mock_gateway_signature_verification(): void
    {
        $gateway = new MockGateway(['secret' => 'mock-secret'], 'test');

        $signature = $gateway->sign('order_1', 'pay_1');
        $verified = $gateway->verifyCallback([
            'razorpay_order_id' => 'order_1',
            'razorpay_payment_id' => 'pay_1',
            'razorpay_signature' => $signature,
        ]);

        $this->assertTrue($verified['valid']);

        $tampered = $gateway->verifyCallback([
            'razorpay_order_id' => 'order_1',
            'razorpay_payment_id' => 'pay_2',
            'razorpay_signature' => $signature,
        ]);
        $this->assertFalse($tampered['valid']);
    }

    public function test_duplicate_webhook_events_are_stored_once(): void
    {
        $this->postJson('/webhooks/razorpay', ['event' => 'x'], ['X-Razorpay-Signature' => 'bad'])
            ->assertStatus(400);

        $this->assertEquals(0, WebhookEvent::count());
    }
}
