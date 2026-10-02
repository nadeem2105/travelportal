<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Contact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppRealtimeInboxTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function enableWhatsApp(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.waba_id' => 'WABA123',
        ]);
    }

    public function test_admin_can_view_realtime_inbox(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $conv = WhatsAppConversation::create([
            'wa_id' => '919876543210',
            'profile_name' => 'Aamir Khan',
            'last_message_at' => now(),
            'last_message_preview' => 'Hello Kashmir',
            'unread_count' => 1,
            'window_expires_at' => now()->addHours(20),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.whatsapp.index', ['c' => $conv->id]));

        $response->assertOk();
        $response->assertSee('WhatsApp Live Inbox');
        $response->assertSee('Aamir Khan');
        $this->assertSame(0, $conv->fresh()->unread_count); // Automatically marked read on open
    }

    public function test_conversations_sync_returns_realtime_json_list(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $contact = Contact::create(['name' => 'Zoya Akhtar', 'phone' => '919800000001']);
        $conv = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'wa_id' => '919800000001',
            'profile_name' => 'Zoya',
            'last_message_at' => now(),
            'last_message_preview' => 'Booking inquiry for Pahalgam',
            'last_message_direction' => 'inbound',
            'unread_count' => 2,
            'bot_paused' => false,
            'window_expires_at' => now()->addHours(10),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.whatsapp.conversations-sync'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'id' => $conv->id,
                'name' => 'Zoya Akhtar',
                'unread_count' => 2,
                'last_message_preview' => 'Booking inquiry for Pahalgam',
                'is_window_open' => true,
            ]);
    }

    public function test_messages_endpoint_returns_full_thread_and_clears_unread(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000002',
            'profile_name' => 'Farhan',
            'unread_count' => 3,
            'window_expires_at' => now()->addHours(12),
        ]);

        $m1 = WhatsAppMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hi, what is cab price?',
            'sent_at' => now()->subMinutes(10),
        ]);

        $m2 = WhatsAppMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'It is ₹2,500 per day for Sedan.',
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.whatsapp.messages', ['conversation' => $conv->id, 'after_id' => 0]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'conversation' => [
                    'id' => $conv->id,
                    'wa_id' => '919800000002',
                    'is_window_open' => true,
                ],
            ])
            ->assertJsonCount(2, 'messages')
            ->assertJsonFragment(['body' => 'Hi, what is cab price?'])
            ->assertJsonFragment(['body' => 'It is ₹2,500 per day for Sedan.', 'status' => 'delivered']);

        $this->assertSame(0, $conv->fresh()->unread_count);
    }

    public function test_messages_endpoint_returns_only_delta_newer_than_after_id(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000003',
            'profile_name' => 'Imran',
            'window_expires_at' => now()->addHours(12),
        ]);

        $m1 = WhatsAppMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Message 1',
        ]);

        $m2 = WhatsAppMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Message 2 - New Realtime Message',
        ]);

        // Poll with after_id = m1->id
        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.whatsapp.messages', ['conversation' => $conv->id, 'after_id' => $m1->id]));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'messages')
            ->assertJsonFragment(['id' => $m2->id, 'body' => 'Message 2 - New Realtime Message'])
            ->assertJsonMissing(['body' => 'Message 1']);
    }

    public function test_async_reply_sends_text_and_returns_json(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.REALTIME_OUT']]], 200)]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000004',
            'profile_name' => 'Meera',
            'window_expires_at' => now()->addHours(5),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp.reply', $conv), [
                'body' => 'Glad to assist you in real-time!',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                    'body' => 'Glad to assist you in real-time!',
                    'status' => 'sent',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'body' => 'Glad to assist you in real-time!',
            'wa_message_id' => 'wamid.REALTIME_OUT',
        ]);
    }

    public function test_admin_can_toggle_bot_pause_in_realtime(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);

        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000005',
            'profile_name' => 'Ravi',
            'bot_paused' => false,
        ]);

        // Toggle to pause
        $res1 = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp.toggle-bot', $conv));

        $res1->assertOk()->assertJson(['success' => true, 'bot_paused' => true]);
        $this->assertTrue($conv->fresh()->bot_paused);

        // Toggle to resume
        $res2 = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp.toggle-bot', $conv));

        $res2->assertOk()->assertJson(['success' => true, 'bot_paused' => false]);
        $this->assertFalse($conv->fresh()->bot_paused);
    }

    public function test_admin_can_send_uploaded_file_via_whatsapp_inbox(): void
    {
        $this->enableWhatsApp();
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'MEDIA_FILE_999'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.FILE_SENT_1']]], 200),
        ]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000006',
            'profile_name' => 'Tariq',
            'window_expires_at' => now()->addHours(5),
        ]);

        $file = UploadedFile::fake()->create('custom_itinerary.pdf', 150, 'application/pdf');

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp.document', $conv), [
                'doc_type' => 'file',
                'file' => $file,
                'caption' => 'Your requested travel proposal PDF',
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                    'type' => 'document',
                    'body' => 'Your requested travel proposal PDF',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'type' => 'document',
            'wa_message_id' => 'wamid.FILE_SENT_1',
        ]);
    }

    public function test_admin_can_send_uploaded_image_via_whatsapp_inbox(): void
    {
        $this->enableWhatsApp();
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.IMG_SENT_1']]], 200),
        ]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000007',
            'profile_name' => 'Sara',
            'window_expires_at' => now()->addHours(5),
        ]);

        $image = UploadedFile::fake()->image('hotel_view.jpg');

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp.document', $conv), [
                'doc_type' => 'file',
                'file' => $image,
                'caption' => 'Luxury Dal Lake Shikara view',
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                    'type' => 'image',
                    'body' => 'Luxury Dal Lake Shikara view',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'type' => 'image',
            'wa_message_id' => 'wamid.IMG_SENT_1',
        ]);
    }

    public function test_admin_can_send_template_with_variables_and_image_header(): void
    {
        $this->enableWhatsApp();
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.TPL_IMG_1']]], 200),
        ]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000008',
            'profile_name' => 'Salman',
        ]);

        $headerImg = UploadedFile::fake()->image('dal_lake.jpg');

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp.template', $conv), [
                'template' => 'kashmir_tour_promo',
                'lang' => 'en_US',
                'header_type' => 'image',
                'header_image_file' => $headerImg,
                'params' => ['Salman', '5 Days Gulmarg & Pahalgam', '₹35,000'],
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                    'type' => 'template',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'type' => 'template',
            'template_name' => 'kashmir_tour_promo',
            'wa_message_id' => 'wamid.TPL_IMG_1',
        ]);
    }

    public function test_admin_can_send_template_with_variables_and_document_header(): void
    {
        $this->enableWhatsApp();
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'MEDIA_DOC_123'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.TPL_DOC_1']]], 200),
        ]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000009',
            'profile_name' => 'Kareena',
        ]);

        $headerDoc = UploadedFile::fake()->create('custom_proposal.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp.template', $conv), [
                'template' => 'quotation',
                'lang' => 'en_US',
                'header_type' => 'document',
                'header_document_file' => $headerDoc,
                'params' => ['Kareena', 'QT-999', '₹75,000', 'https://example.com/proposal/999'],
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                    'type' => 'template',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'type' => 'template',
            'template_name' => 'quotation',
            'wa_message_id' => 'wamid.TPL_DOC_1',
        ]);
    }

    public function test_admin_can_trigger_bot_reply_from_inbox(): void
    {
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.BOT_REPLY_1']]], 200),
        ]);

        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $conv = WhatsAppConversation::create([
            'wa_id' => '919800000010',
            'profile_name' => 'Rohit',
            'window_expires_at' => now()->addHours(12),
        ]);

        $rule = \App\Models\WhatsAppAutoReply::create([
            'name' => 'Package Details Trigger',
            'keywords' => 'package, pricing, gulmarg',
            'match_type' => 'contains',
            'reply_type' => 'interactive_buttons',
            'reply_text' => 'We offer customized luxury packages starting from ₹15,000 per person!',
            'buttons' => ['Book Now', 'Talk to Agent'],
            'priority' => 1,
            'active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp.trigger-bot-reply', $conv), [
                'rule_id' => $rule->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'direction' => 'outbound',
                ],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'wa_message_id' => 'wamid.BOT_REPLY_1',
        ]);
    }
}
