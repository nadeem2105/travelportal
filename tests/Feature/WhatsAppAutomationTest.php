<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppCampaignMessage;
use App\Mail\WhatsAppHandoffStaffMail;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\AutoReplyService;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppAutomationTest extends TestCase
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

    // ---- Templates sync -----------------------------------------------------

    public function test_template_sync_upserts_from_meta(): void
    {
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*/message_templates*' => Http::response([
                'data' => [
                    ['id' => '1', 'name' => 'booking_confirmed', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED',
                        'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, booking {{2}} confirmed.']]],
                    ['id' => '2', 'name' => 'promo_offer', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'APPROVED',
                        'components' => [['type' => 'BODY', 'text' => 'Special offer!']]],
                ],
                'paging' => [],
            ], 200),
        ]);

        $result = app(WhatsAppTemplateService::class)->sync();

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['synced']);
        $this->assertDatabaseHas('whatsapp_templates', ['name' => 'booking_confirmed', 'body_variable_count' => 2]);
        $this->assertDatabaseHas('whatsapp_templates', ['name' => 'promo_offer', 'body_variable_count' => 0]);

        // Re-sync updates, does not duplicate.
        app(WhatsAppTemplateService::class)->sync();
        $this->assertSame(2, WhatsAppTemplate::count());
    }

    // ---- Contact groups -----------------------------------------------------

    public function test_static_group_resolves_explicit_members(): void
    {
        $a = Contact::create(['name' => 'A', 'phone' => '919000000001', 'whatsapp_opt_in' => true]);
        $b = Contact::create(['name' => 'B', 'phone' => '919000000002', 'whatsapp_opt_in' => true]);
        Contact::create(['name' => 'C', 'phone' => '919000000003']);

        $group = ContactGroup::create(['name' => 'VIPs', 'type' => 'static']);
        $group->members()->attach([$a->id, $b->id]);

        $this->assertSame(2, $group->memberCount());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $group->resolveContacts()->pluck('id')->all());
    }

    public function test_dynamic_group_resolves_by_filter(): void
    {
        Contact::create(['name' => 'Lead1', 'phone' => '919000000010', 'lifecycle_stage' => 'lead', 'whatsapp_opt_in' => true]);
        Contact::create(['name' => 'Cust1', 'phone' => '919000000011', 'lifecycle_stage' => 'customer', 'whatsapp_opt_in' => true]);
        Contact::create(['name' => 'Cust2', 'phone' => '919000000012', 'lifecycle_stage' => 'customer', 'whatsapp_opt_in' => false]);

        $group = ContactGroup::create([
            'name' => 'Customers', 'type' => 'dynamic',
            'filters' => ['lifecycle_stage' => 'customer'],
        ]);

        $this->assertSame(2, $group->memberCount());
        // WhatsApp-reachable + opted-in narrows to 1.
        $this->assertSame(1, $group->resolveContacts(whatsappReachableOnly: true)->filter(fn ($c) => $c->whatsapp_opt_in)->count());
    }

    // ---- Campaigns ----------------------------------------------------------

    public function test_campaign_builds_recipients_and_queues_jobs(): void
    {
        $this->enableWhatsApp();
        Queue::fake();

        foreach (range(1, 3) as $i) {
            Contact::create(['name' => "C{$i}", 'phone' => '91900000010' . $i, 'lifecycle_stage' => 'customer', 'whatsapp_opt_in' => true]);
        }
        Contact::create(['name' => 'NoOptIn', 'phone' => '919000000200', 'lifecycle_stage' => 'customer', 'whatsapp_opt_in' => false]);

        $group = ContactGroup::create(['name' => 'Cust', 'type' => 'dynamic', 'filters' => ['lifecycle_stage' => 'customer']]);
        $campaign = WhatsAppCampaign::create([
            'name' => 'Promo', 'contact_group_id' => $group->id,
            'template_name' => 'promo_offer', 'template_language' => 'en_US', 'status' => 'draft',
        ]);

        app(CampaignService::class)->dispatchNow($campaign);

        $campaign->refresh();
        $this->assertSame('sending', $campaign->status);
        $this->assertSame(3, $campaign->total_recipients); // opted-in only
        Queue::assertPushed(SendWhatsAppCampaignMessage::class, 3);
    }

    public function test_campaign_job_sends_and_marks_recipient(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.CMP1']]], 200)]);

        $contact = Contact::create(['name' => 'Target', 'phone' => '919000000301', 'whatsapp_opt_in' => true]);
        $group = ContactGroup::create(['name' => 'G', 'type' => 'static']);
        $group->members()->attach($contact->id);

        $campaign = WhatsAppCampaign::create([
            'name' => 'Promo', 'contact_group_id' => $group->id,
            'template_name' => 'promo_offer', 'status' => 'draft',
        ]);
        app(CampaignService::class)->buildRecipients($campaign);
        $recipient = $campaign->recipients()->first();

        (new SendWhatsAppCampaignMessage($recipient->id))
            ->handle(app(WhatsAppService::class), app(CampaignService::class));

        $recipient->refresh();
        $this->assertSame('sent', $recipient->status);
        $this->assertSame('wamid.CMP1', $recipient->wa_message_id);
    }

    // ---- Auto-replies -------------------------------------------------------

    public function test_keyword_rule_matches_and_replies(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AR1']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Greeting', 'match_type' => 'contains', 'keywords' => ['hi', 'hello'],
            'reply_type' => 'text', 'reply_text' => 'Hello! How can we help?', 'active' => true, 'priority' => 10,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000401', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'Hi there');

        $this->assertTrue($sent);
        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id, 'direction' => 'outbound', 'body' => 'Hello! How can we help?',
        ]);
    }

    public function test_default_fallback_used_when_no_keyword_matches(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AR2']]], 200)]);

        WhatsAppAutoReply::create(['name' => 'Kw', 'match_type' => 'exact', 'keywords' => ['menu'], 'reply_type' => 'text', 'reply_text' => 'Menu', 'active' => true, 'priority' => 10]);
        WhatsAppAutoReply::create(['name' => 'Fallback', 'is_default' => true, 'reply_type' => 'text', 'reply_text' => 'Let me connect you.', 'active' => true, 'priority' => 999]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000402', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        app(AutoReplyService::class)->handle($conv, 'random gibberish');

        $this->assertDatabaseHas('whatsapp_messages', ['conversation_id' => $conv->id, 'direction' => 'outbound', 'body' => 'Let me connect you.']);
    }

    public function test_handoff_rule_pauses_bot(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AR3']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Agent', 'match_type' => 'contains', 'keywords' => ['agent', 'human'],
            'reply_type' => 'text', 'reply_text' => 'Connecting you to an agent.', 'is_handoff' => true, 'active' => true, 'priority' => 5,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000403', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        app(AutoReplyService::class)->handle($conv, 'I want an agent');
        $this->assertTrue($conv->fresh()->bot_paused);

        // Once paused, further inbound does not auto-reply.
        $before = WhatsAppMessage::where('conversation_id', $conv->id)->where('direction', 'outbound')->count();
        app(AutoReplyService::class)->handle($conv->fresh(), 'hello agent');
        $after = WhatsAppMessage::where('conversation_id', $conv->id)->where('direction', 'outbound')->count();
        $this->assertSame($before, $after);
    }

    // ---- Auto-reply Controller CRUD -----------------------------------------

    public function test_admin_can_view_auto_replies_index(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        WhatsAppTemplate::create([
            'meta_id' => 'tpl_1', 'name' => 'welcome_tpl', 'language' => 'en_US',
            'category' => 'UTILITY', 'status' => 'APPROVED',
            'body_preview' => 'Welcome to TravelQue Cashmir!',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.whatsapp-auto-replies.index'));

        $response->assertOk();
        $response->assertSee('WhatsApp Auto-replies');
        $response->assertSee('welcome_tpl');
    }

    public function test_admin_can_create_keyword_auto_reply_with_text(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Package Inquiries',
            'match_type' => 'contains',
            'keywords_raw' => "package, tour, gulmarg\npahalgam",
            'reply_type' => 'text',
            'reply_text' => 'Here are our Kashmir holiday packages.',
            'priority' => 20,
            'active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rule = WhatsAppAutoReply::where('name', 'Package Inquiries')->first();
        $this->assertNotNull($rule);
        $this->assertSame(['package', 'tour', 'gulmarg', 'pahalgam'], $rule->keywords);
        $this->assertSame('contains', $rule->match_type);
        $this->assertSame('text', $rule->reply_type);
        $this->assertSame('Here are our Kashmir holiday packages.', $rule->reply_text);
        $this->assertFalse($rule->is_default);
    }

    public function test_admin_can_create_default_fallback_rule(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Catch-all Welcome Menu',
            'match_type' => 'contains',
            'is_default' => '1',
            'reply_type' => 'text',
            'reply_text' => 'Hello! Please choose an option from our menu.',
            'priority' => 999,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rule = WhatsAppAutoReply::where('name', 'Catch-all Welcome Menu')->first();
        $this->assertNotNull($rule);
        $this->assertTrue($rule->is_default);
        $this->assertNull($rule->keywords);
    }

    public function test_admin_can_create_template_auto_reply(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Template Greeting',
            'match_type' => 'exact',
            'keywords_raw' => 'start, menu',
            'reply_type' => 'template',
            'template_name' => 'hello_world',
            'template_language' => 'en_US',
            'priority' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rule = WhatsAppAutoReply::where('name', 'Template Greeting')->first();
        $this->assertNotNull($rule);
        $this->assertSame('template', $rule->reply_type);
        $this->assertSame('hello_world', $rule->template_name);
        $this->assertSame('en_US', $rule->template_language);
    }

    public function test_admin_cannot_create_keyword_rule_without_keywords(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Invalid Rule',
            'match_type' => 'contains',
            'keywords_raw' => '',
            'is_default' => '0',
            'reply_type' => 'text',
            'reply_text' => 'Hello!',
        ]);

        $response->assertSessionHasErrors(['keywords_raw']);
        $this->assertDatabaseMissing('whatsapp_auto_replies', ['name' => 'Invalid Rule']);
    }

    public function test_admin_can_update_and_toggle_rule(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $rule = WhatsAppAutoReply::create([
            'name' => 'Original', 'match_type' => 'contains', 'keywords' => ['hi'],
            'reply_type' => 'text', 'reply_text' => 'Hello', 'active' => true, 'priority' => 10,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.whatsapp-auto-replies.update', $rule), [
            'name' => 'Updated Rule',
            'match_type' => 'exact',
            'keywords_raw' => 'hello, greetings',
            'reply_type' => 'text',
            'reply_text' => 'Welcome to Kashmir!',
            'priority' => 5,
        ]);

        $response->assertRedirect();
        $this->assertSame('Updated Rule', $rule->fresh()->name);
        $this->assertSame(['hello', 'greetings'], $rule->fresh()->keywords);

        // Toggle active status
        $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.toggle', $rule));
        $this->assertFalse($rule->fresh()->active);

        // Delete rule
        $this->actingAs($admin, 'admin')->delete(route('admin.whatsapp-auto-replies.destroy', $rule));
        $this->assertDatabaseMissing('whatsapp_auto_replies', ['id' => $rule->id]);
    }

    public function test_admin_can_create_advanced_rule_with_header_footer_and_buttons(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Interactive Welcome Menu',
            'match_type' => 'contains',
            'keywords_raw' => 'hi, hello, menu',
            'reply_type' => 'text',
            'reply_text' => 'Welcome to TravelQue Cashmir! How may we help?',
            'header_text' => 'TravelQue Cashmir',
            'footer_text' => '24/7 Helpline • Srinagar',
            'buttons' => ['Tour Packages', 'Book Cab', 'Speak to Agent'],
            'priority' => 1,
            'active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rule = WhatsAppAutoReply::where('name', 'Interactive Welcome Menu')->first();
        $this->assertNotNull($rule);
        $this->assertSame('buttons', $rule->reply_type);
        $this->assertSame('TravelQue Cashmir', $rule->header_text);
        $this->assertSame('24/7 Helpline • Srinagar', $rule->footer_text);
        $this->assertSame(['Tour Packages', 'Book Cab', 'Speak to Agent'], $rule->buttons);
    }

    public function test_auto_reply_dispatches_interactive_buttons_message(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.BTN1']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Button Menu',
            'match_type' => 'contains',
            'keywords' => ['menu', 'options'],
            'reply_type' => 'buttons',
            'reply_text' => 'Please choose an option:',
            'header_text' => 'TravelQue Cashmir',
            'footer_text' => 'Tap a button below',
            'buttons' => ['Packages', 'Cabs', 'Agent'],
            'active' => true,
            'priority' => 1,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000501', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'show menu');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return ($data['type'] ?? '') === 'interactive'
                && ($data['interactive']['type'] ?? '') === 'button'
                && ($data['interactive']['header']['text'] ?? '') === 'TravelQue Cashmir'
                && ($data['interactive']['body']['text'] ?? '') === 'Please choose an option:'
                && ($data['interactive']['footer']['text'] ?? '') === 'Tap a button below'
                && count($data['interactive']['action']['buttons'] ?? []) === 3;
        });
    }

    public function test_auto_reply_dispatches_formatted_text_with_header_and_footer(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TXT1']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Formatted Text',
            'match_type' => 'contains',
            'keywords' => ['about'],
            'reply_type' => 'text',
            'reply_text' => 'We provide top-rated tour packages across Kashmir.',
            'header_text' => 'About TravelQue Cashmir',
            'footer_text' => 'Licensed J&K Tourism Operator',
            'active' => true,
            'priority' => 1,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000502', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'tell me about you');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $body = $data['text']['body'] ?? '';
            return ($data['type'] ?? '') === 'text'
                && str_contains($body, '*About TravelQue Cashmir*')
                && str_contains($body, 'We provide top-rated tour packages')
                && str_contains($body, '_Licensed J&K Tourism Operator_');
        });
    }

    public function test_admin_can_create_auto_reply_with_image_header(): void
    {
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.whatsapp-auto-replies.store'), [
            'name' => 'Visual Kashmir Welcome',
            'match_type' => 'contains',
            'keywords_raw' => 'shikara, dal lake, welcome',
            'reply_type' => 'buttons',
            'header_type' => 'image',
            'header_image_url' => 'https://images.unsplash.com/photo-1595815771614-ade9d652a65d?w=800',
            'reply_text' => 'Experience the magic of Kashmir with TravelQue Cashmir.',
            'footer_text' => 'Direct from Srinagar',
            'buttons' => ['Shikara Ride', 'Houseboat Stay', 'Cabs'],
            'priority' => 1,
            'active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rule = WhatsAppAutoReply::where('name', 'Visual Kashmir Welcome')->first();
        $this->assertNotNull($rule);
        $this->assertSame('image', $rule->header_type);
        $this->assertSame('https://images.unsplash.com/photo-1595815771614-ade9d652a65d?w=800', $rule->header_image_url);
        $this->assertNull($rule->header_text);
        $this->assertSame(['Shikara Ride', 'Houseboat Stay', 'Cabs'], $rule->buttons);
    }

    public function test_auto_reply_dispatches_interactive_buttons_with_image_header(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.IMG_BTN1']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Image Header Menu',
            'match_type' => 'contains',
            'keywords' => ['explore'],
            'reply_type' => 'buttons',
            'header_type' => 'image',
            'header_image_url' => 'https://images.unsplash.com/photo-kashmir.jpg',
            'reply_text' => 'Choose your holiday experience:',
            'footer_text' => 'TravelQue Cashmir',
            'buttons' => ['Gulmarg', 'Pahalgam'],
            'active' => true,
            'priority' => 1,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000503', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'explore kashmir');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $interactive = $data['interactive'] ?? [];
            return ($data['type'] ?? '') === 'interactive'
                && ($interactive['type'] ?? '') === 'button'
                && ($interactive['header']['type'] ?? '') === 'image'
                && ($interactive['header']['image']['link'] ?? '') === 'https://images.unsplash.com/photo-kashmir.jpg'
                && ($interactive['body']['text'] ?? '') === 'Choose your holiday experience:'
                && ($interactive['footer']['text'] ?? '') === 'TravelQue Cashmir'
                && count($interactive['action']['buttons'] ?? []) === 2;
        });
    }

    public function test_auto_reply_dispatches_image_media_link_when_no_buttons(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.IMG_ONLY1']]], 200)]);

        WhatsAppAutoReply::create([
            'name' => 'Scenic Photo Reply',
            'match_type' => 'contains',
            'keywords' => ['photo', 'view'],
            'reply_type' => 'text',
            'header_type' => 'image',
            'header_image_url' => 'https://images.unsplash.com/photo-gulmarg.jpg',
            'reply_text' => 'Here is a recent photo of snow in Gulmarg.',
            'footer_text' => 'Taken today in Gulmarg',
            'active' => true,
            'priority' => 1,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000504', 'Guest');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'send photo');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return ($data['type'] ?? '') === 'image'
                && ($data['image']['link'] ?? '') === 'https://images.unsplash.com/photo-gulmarg.jpg'
                && str_contains($data['image']['caption'] ?? '', 'Here is a recent photo of snow in Gulmarg.')
                && str_contains($data['image']['caption'] ?? '', '_Taken today in Gulmarg_');
        });
    }

    public function test_handoff_rule_sends_email_notification_to_staff_and_logs_activity(): void
    {
        $this->enableWhatsApp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.HO1']]], 200)]);
        Mail::fake();

        $rule = WhatsAppAutoReply::create([
            'name' => 'Agent Handoff Request',
            'match_type' => 'contains',
            'keywords' => ['support', 'human'],
            'reply_type' => 'text',
            'reply_text' => 'Transferring you to a human travel consultant now...',
            'is_handoff' => true,
            'active' => true,
            'priority' => 1,
        ]);

        $svc = app(WhatsAppService::class);
        $conv = $svc->conversationFor('919000000505', 'John Doe');
        $conv->update(['window_expires_at' => now()->addHours(5)]);

        $sent = app(AutoReplyService::class)->handle($conv, 'I need support from human');

        $this->assertTrue($sent);
        $this->assertTrue($conv->fresh()->bot_paused);

        // Verify Staff Email was sent
        Mail::assertSent(WhatsAppHandoffStaffMail::class, function ($mail) use ($conv, $rule) {
            return $mail->conversation->id === $conv->id
                && $mail->rule->id === $rule->id
                && str_contains($mail->inboundText, 'support from human');
        });

        // Verify ActivityLog was created
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'whatsapp_handoff',
            'module' => 'whatsapp',
        ]);
    }
}
