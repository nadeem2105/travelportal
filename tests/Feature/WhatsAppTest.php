<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function inboundPayload(string $from, string $text, string $msgId, string $name = 'Aisha'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [['wa_id' => $from, 'profile' => ['name' => $name]]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $msgId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    // ---- Verification handshake --------------------------------------------

    public function test_webhook_verification_succeeds_with_correct_token(): void
    {
        config(['services.whatsapp.verify_token' => 'secret-verify']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=secret-verify&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_webhook_verification_fails_with_wrong_token(): void
    {
        config(['services.whatsapp.verify_token' => 'secret-verify']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
            ->assertForbidden();
    }

    // ---- Inbound messages ---------------------------------------------------

    public function test_inbound_message_creates_contact_conversation_and_opens_window(): void
    {
        config(['services.whatsapp.app_secret' => null]); // skip signature in this test

        $this->postJson('/webhooks/whatsapp', $this->inboundPayload('919811000000', 'Hi, need a Kashmir package', 'wamid.AAA'))
            ->assertOk();

        $this->assertSame(1, Contact::where('phone', '919811000000')->count());
        $conv = WhatsAppConversation::where('wa_id', '919811000000')->first();
        $this->assertNotNull($conv);
        $this->assertTrue($conv->isWindowOpen());
        $this->assertSame(1, $conv->unread_count);
        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.AAA',
            'direction' => 'inbound',
            'body' => 'Hi, need a Kashmir package',
        ]);
    }

    public function test_inbound_message_is_idempotent(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $payload = $this->inboundPayload('919811000000', 'Duplicate', 'wamid.DUP');
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame(1, WhatsAppMessage::where('wa_message_id', 'wamid.DUP')->count());
    }

    public function test_inbound_rejected_when_signature_invalid(): void
    {
        config(['services.whatsapp.app_secret' => 'app-secret-123']);

        $this->call('POST', '/webhooks/whatsapp', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Hub-Signature-256' => 'sha256=deadbeef'],
            json_encode($this->inboundPayload('919811000000', 'x', 'wamid.SIG'))
        )->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_inbound_accepted_with_valid_signature(): void
    {
        config(['services.whatsapp.app_secret' => 'app-secret-123']);

        $body = json_encode($this->inboundPayload('919811000000', 'Signed hello', 'wamid.OK'));
        $sig = 'sha256=' . hash_hmac('sha256', $body, 'app-secret-123');

        $this->call('POST', '/webhooks/whatsapp', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Hub-Signature-256' => $sig],
            $body
        )->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'wamid.OK', 'direction' => 'inbound']);
    }

    // ---- Outbound sending ---------------------------------------------------

    public function test_outbound_text_send_persists_message(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]], 200),
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919811000000', 'Aisha');
        $conv->update(['window_expires_at' => now()->addHours(5)]); // window open

        $result = $svc->sendText($conv, 'Thanks for reaching out!');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.OUT1',
            'direction' => 'outbound',
            'body' => 'Thanks for reaching out!',
            'status' => 'sent',
        ]);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/messages'));
    }

    public function test_outbound_text_blocked_when_window_closed(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);
        Http::fake();

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919811000000', 'Aisha');
        $conv->update(['window_expires_at' => now()->subHour()]); // closed

        $result = $svc->sendText($conv, 'Too late');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::where('direction', 'outbound')->count());
    }

    public function test_notify_template_sends_and_persists(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TPL']]], 200),
        ]);

        $result = app(WhatsAppService::class)->notifyTemplate(
            '919811000000',
            'booking_confirmed',
            'en_US',
            ['Aisha', 'TQC-ABC123', 'Kashmir', '20 Oct 2026', '₹57,750.00'],
            'Aisha',
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.TPL',
            'direction' => 'outbound',
            'type' => 'template',
            'template_name' => 'booking_confirmed',
        ]);
        Http::assertSent(fn ($req) => str_contains(json_encode($req->data()), 'TQC-ABC123'));
    }

    public function test_notify_template_skipped_when_disabled(): void
    {
        config(['services.whatsapp.enabled' => false]);
        Http::fake();

        $result = app(WhatsAppService::class)->notifyTemplate('919811000000', 'booking_confirmed', null, ['x']);

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_notify_event_uses_configured_template(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.templates.quotation_sent' => 'quote_ready',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.EV1']]], 200)]);

        $result = app(WhatsAppService::class)->notifyEvent('quotation_sent', '919811000000', ['Aisha', 'QT-2026-000001', '₹25,000'], 'Aisha');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'wamid.EV1', 'template_name' => 'quote_ready', 'direction' => 'outbound']);
    }

    public function test_notify_event_skipped_when_template_not_configured(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.templates.booking_cancelled' => null,
        ]);
        Http::fake();

        $result = app(WhatsAppService::class)->notifyEvent('booking_cancelled', '919811000000', ['x']);

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_send_document_uploads_and_persists(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'MEDIA123'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.DOC1']]], 200),
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919811000000', 'Aisha');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $result = $svc->sendDocumentPdf($conv, '%PDF-1.4 fake bytes', 'Quotation-QT-1.pdf', 'Your quotation');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id, 'direction' => 'outbound', 'type' => 'document',
            'wa_message_id' => 'wamid.DOC1',
        ]);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/media'));
    }

    public function test_send_document_blocked_when_window_closed(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);
        Http::fake();

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919811000000', 'Aisha');
        $conv->update(['window_expires_at' => now()->subHour()]);

        $result = $svc->sendDocumentPdf($conv, 'bytes', 'x.pdf');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_delivery_status_updates_message(): void
    {
        config(['services.whatsapp.app_secret' => null]);
        $this->postJson('/webhooks/whatsapp', $this->inboundPayload('919811000000', 'hi', 'wamid.STAT'))->assertOk();

        // Create an outbound message to receive a status update.
        $conv = WhatsAppConversation::where('wa_id', '919811000000')->first();
        WhatsAppMessage::create([
            'conversation_id' => $conv->id, 'contact_id' => $conv->contact_id,
            'wa_message_id' => 'wamid.SENT', 'direction' => 'outbound', 'type' => 'text',
            'body' => 'hello', 'status' => 'sent',
        ]);

        $statusPayload = [
            'entry' => [['changes' => [['value' => [
                'statuses' => [['id' => 'wamid.SENT', 'status' => 'delivered']],
            ]]]]],
        ];
        $this->postJson('/webhooks/whatsapp', $statusPayload)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'wamid.SENT', 'status' => 'delivered']);
    }

    public function test_booking_confirmation_sends_whatsapp_with_itinerary_attached(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.templates.booking_confirmed' => 'booking_confirmed_itinerary',
            'services.whatsapp.attach_documents.booking_itinerary' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'MEDIA_ITIN_123'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.BOOK1']]], 200),
        ]);

        $package = \App\Models\Package::first();

        $booking = \App\Models\Booking::factory()->create([
            'product_type' => 'package',
            'status' => 'confirmed',
            'total_amount' => 57750,
            'contact' => [
                'first_name' => 'Aisha',
                'phone' => '919811000000',
                'email' => 'aisha@example.com',
            ],
            'product_id' => $package->id,
        ]);
        $booking->packageBooking()->create([
            'package_id' => $package->id,
            'package_name' => $package->name,
            'departure_date' => now()->addDays(20),
            'adults' => 2,
        ]);

        app(\App\Services\NotificationService::class)->sendBookingConfirmation($booking, true);

        // Assert media was uploaded to Meta
        Http::assertSent(function ($req) use ($booking) {
            return str_contains($req->url(), '/media')
                && str_contains($req->body(), "Itinerary-{$booking->booking_reference}.pdf");
        });

        // Assert message template sent with DOCUMENT header and correct body variables
        Http::assertSent(function ($req) use ($booking) {
            if (! str_contains($req->url(), '/messages')) {
                return false;
            }
            $data = $req->data();
            $header = collect($data['template']['components'])->firstWhere('type', 'header');
            $body = collect($data['template']['components'])->firstWhere('type', 'body');

            return $data['template']['name'] === 'booking_confirmed_itinerary'
                && isset($header['parameters'][0]['document'])
                && $header['parameters'][0]['document']['id'] === 'MEDIA_ITIN_123'
                && $header['parameters'][0]['document']['filename'] === "Itinerary-{$booking->booking_reference}.pdf"
                && count($body['parameters']) === 5
                && $body['parameters'][0]['text'] === 'Aisha'
                && $body['parameters'][1]['text'] === $booking->booking_reference;
        });
    }

    public function test_booking_confirmation_sends_both_itinerary_and_invoice_whatsapp_templates(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.templates.booking_confirmed' => 'booking_confirmed_itinerary',
            'services.whatsapp.templates.booking_invoice' => 'booking_invoice',
            'services.whatsapp.attach_documents.booking_itinerary' => true,
            'services.whatsapp.attach_documents.booking_invoice' => true,
        ]);

        $mediaCount = 0;
        Http::fake([
            'graph.facebook.com/*/media' => function () use (&$mediaCount) {
                $mediaCount++;

                return Http::response(['id' => "MEDIA_{$mediaCount}"], 200);
            },
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.MSG1']]], 200),
        ]);

        $package = \App\Models\Package::first();

        $booking = \App\Models\Booking::factory()->create([
            'product_type' => 'package',
            'status' => 'confirmed',
            'total_amount' => 57750,
            'contact' => [
                'first_name' => 'Aisha',
                'phone' => '919811000000',
                'email' => 'aisha@example.com',
            ],
            'product_id' => $package->id,
        ]);
        $booking->packageBooking()->create([
            'package_id' => $package->id,
            'package_name' => $package->name,
            'departure_date' => now()->addDays(20),
            'adults' => 2,
        ]);

        app(\App\Services\NotificationService::class)->sendBookingConfirmation($booking, true);

        // Assert two templates were dispatched
        Http::assertSent(function ($req) use ($booking) {
            return str_contains($req->url(), '/messages')
                && ($req->data()['template']['name'] ?? '') === 'booking_confirmed_itinerary';
        });

        Http::assertSent(function ($req) use ($booking) {
            if (! str_contains($req->url(), '/messages')) {
                return false;
            }
            $data = $req->data();
            $header = collect($data['template']['components'])->firstWhere('type', 'header');

            return ($data['template']['name'] ?? '') === 'booking_invoice'
                && isset($header['parameters'][0]['document'])
                && $header['parameters'][0]['document']['filename'] === "Invoice-{$booking->booking_reference}.pdf";
        });
    }

    public function test_admin_can_send_whatsapp_invoice(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.templates.booking_invoice' => 'booking_invoice',
            'services.whatsapp.attach_documents.booking_invoice' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'MEDIA_INV_ADMIN'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.ADMIN_INV']]], 200),
        ]);

        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $booking = \App\Models\Booking::factory()->create([
            'product_type' => 'hotel',
            'status' => 'confirmed',
            'total_amount' => 12500,
            'contact' => [
                'first_name' => 'Rahul',
                'phone' => '919811000000',
                'email' => 'rahul@example.com',
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.bookings.send-whatsapp-invoice', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($req) use ($booking) {
            if (! str_contains($req->url(), '/messages')) {
                return false;
            }
            $data = $req->data();
            $header = collect($data['template']['components'])->firstWhere('type', 'header');

            return ($data['template']['name'] ?? '') === 'booking_invoice'
                && isset($header['parameters'][0]['document'])
                && $header['parameters'][0]['document']['id'] === 'MEDIA_INV_ADMIN'
                && $header['parameters'][0]['document']['filename'] === "Invoice-{$booking->booking_reference}.pdf";
        });
    }
}
