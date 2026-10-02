<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Services\Marketing\Ai\Providers\OpenAiProvider;
use App\Services\WhatsApp\Assistant\CustomerResolver;
use App\Services\WhatsApp\Assistant\CustomerTools;
use App\Services\WhatsApp\Assistant\TravelAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the AI Travel Assistant's security-critical behavior (identity
 * verification + booking ownership scoping) and safe-decline paths. These do
 * not require a live LLM — authorization lives entirely in the backend.
 */
class WhatsAppAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function conversation(string $waId, ?int $contactId = null): WhatsAppConversation
    {
        return WhatsAppConversation::create([
            'wa_id' => $waId,
            'contact_id' => $contactId,
            'profile_name' => 'Test Customer',
            'window_expires_at' => now()->addHours(24),
            'status' => 'open',
            'unread_count' => 0,
            'bot_paused' => false,
        ]);
    }

    private function booking(array $overrides = []): Booking
    {
        static $n = 0;
        $n++;

        return Booking::create(array_merge([
            'booking_reference' => 'KB' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'product_type' => 'package',
            'status' => 'confirmed',
            'total_amount' => 80000,
            'currency' => 'INR',
            'contact' => ['first_name' => 'Test', 'phone' => '919811000000', 'email' => 'c@example.com'],
        ], $overrides));
    }

    // ---- Verification / ownership ------------------------------------------

    public function test_unverified_number_has_no_booking_access(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);
        $this->booking(); // exists but belongs to nobody resolvable

        $conv = $this->conversation('919999999999');
        $resolver = app(CustomerResolver::class);

        $this->assertFalse($resolver->isVerified($conv));
        $this->assertSame([], $resolver->ownedBookingIds($conv));
    }

    public function test_registered_user_auto_verifies_and_sees_only_own_bookings(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        $user = User::factory()->create(['phone' => '919811000000']);
        $mine = $this->booking(['user_id' => $user->id, 'booking_reference' => 'KBMINE']);
        $theirs = $this->booking(['user_id' => null, 'booking_reference' => 'KBOTHER', 'contact' => ['phone' => '910000000000']]);

        $conv = $this->conversation('919811000000');
        $resolver = app(CustomerResolver::class);

        $this->assertTrue($resolver->autoVerify($conv));
        $owned = $resolver->ownedBookingIds($conv->fresh());

        $this->assertContains($mine->id, $owned);
        $this->assertNotContains($theirs->id, $owned);
    }

    public function test_booking_reference_verification_requires_matching_number(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        // Booking's contact phone is a DIFFERENT number than the sender's.
        $foreign = $this->booking(['booking_reference' => 'KBFOREIGN', 'contact' => ['phone' => '910000000000']]);

        $conv = $this->conversation('919811000000');
        $resolver = app(CustomerResolver::class);

        // Knowing the reference is NOT enough when the number doesn't match.
        $this->assertFalse($resolver->verifyByBookingReference($conv, 'KBFOREIGN'));
        $this->assertFalse($conv->fresh()->isVerified());
    }

    public function test_booking_reference_verification_succeeds_for_own_number(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        $mine = $this->booking(['booking_reference' => 'KBSELF', 'contact' => ['phone' => '919811000000']]);

        $conv = $this->conversation('919811000000');
        $resolver = app(CustomerResolver::class);

        $this->assertTrue($resolver->verifyByBookingReference($conv, 'KBSELF'));
        $this->assertTrue($conv->fresh()->isVerified());
        $this->assertContains($mine->id, $resolver->ownedBookingIds($conv->fresh()));
    }

    public function test_tools_reject_foreign_booking_reference(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        $user = User::factory()->create(['phone' => '919811000000']);
        $this->booking(['user_id' => $user->id, 'booking_reference' => 'KBMINE2']);
        $this->booking(['user_id' => null, 'booking_reference' => 'KBFOREIGN2', 'contact' => ['phone' => '910000000000']]);

        $conv = $this->conversation('919811000000');
        app(CustomerResolver::class)->autoVerify($conv);

        $tools = app(CustomerTools::class)->forConversation($conv->fresh());

        // Asking for someone else's booking must NOT expose it.
        $result = $tools->getBookingDetails(['booking_reference' => 'KBFOREIGN2']);
        $this->assertArrayNotHasKey('total_amount', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_payment_status_reports_partial_without_claiming_paid(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        $user = User::factory()->create(['phone' => '919811000000']);
        $booking = $this->booking(['user_id' => $user->id, 'booking_reference' => 'KBPAY', 'total_amount' => 80000]);
        Payment::create([
            'booking_id' => $booking->id,
            'gateway' => 'razorpay',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'captured',
            'paid_at' => now(),
        ]);

        $conv = $this->conversation('919811000000');
        app(CustomerResolver::class)->autoVerify($conv);
        $tools = app(CustomerTools::class)->forConversation($conv->fresh());

        $status = $tools->getBookingPaymentStatus(['booking_reference' => 'KBPAY']);

        $this->assertSame(50000.0, $status['paid_amount']);
        $this->assertSame(30000.0, $status['pending_amount']);
        $this->assertSame('partial', $status['payment_status']);
    }

    public function test_unverified_profile_request_asks_for_verification(): void
    {
        config(['services.whatsapp.ai_assistant.require_verification' => true]);

        $conv = $this->conversation('919999999999');
        $tools = app(CustomerTools::class)->forConversation($conv);

        $result = $tools->getMyBookings();
        $this->assertTrue($result['requires_verification'] ?? false);
    }

    // ---- Safe decline -------------------------------------------------------

    public function test_assistant_declines_when_disabled(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => 'PID',
            'services.whatsapp.access_token' => 'TOKEN',
            'services.whatsapp.ai_assistant.enabled' => false,
        ]);

        $conv = $this->conversation('919811000000');
        $handled = app(TravelAssistantService::class)->handle($conv, 'meri booking dikhao');

        $this->assertFalse($handled);
    }

    public function test_assistant_declines_when_no_provider_configured(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => 'PID',
            'services.whatsapp.access_token' => 'TOKEN',
            'services.whatsapp.ai_assistant.enabled' => true,
            'services.ai.default_provider' => 'openai',
            'services.ai.providers.openai.api_key' => '', // unconfigured
        ]);

        $conv = $this->conversation('919811000000');
        $handled = app(TravelAssistantService::class)->handle($conv, 'meri booking dikhao');

        $this->assertFalse($handled);
    }

    // ---- Provider tool-call normalization ----------------------------------

    public function test_openai_provider_normalizes_tool_calls(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'get_my_bookings', 'arguments' => '{}'],
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $provider = new OpenAiProvider('test-key', 'gpt-4o');
        $result = $provider->chat(
            [['role' => 'user', 'content' => 'show my bookings']],
            [['name' => 'get_my_bookings', 'description' => 'list', 'parameters' => ['type' => 'object', 'properties' => (object) []]]],
        );

        $this->assertSame('get_my_bookings', $result['tool_calls'][0]['name']);
        $this->assertSame('call_1', $result['tool_calls'][0]['id']);
        $this->assertIsArray($result['tool_calls'][0]['arguments']);
    }
}
