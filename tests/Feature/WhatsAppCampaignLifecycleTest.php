<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppCampaignMessage;
use App\Models\Admin;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppCampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Admin $admin;
    private ContactGroup $group;
    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.waba_id' => 'WABA123',
        ]);

        $this->admin = Admin::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->group = ContactGroup::create([
            'name' => 'VIP Travelers',
            'type' => 'static',
        ]);

        $contact1 = Contact::create([
            'name' => 'Alice Wonder',
            'phone' => '+919876543210',
            'whatsapp_opt_in' => true,
        ]);

        $contact2 = Contact::create([
            'name' => 'Bob Builder',
            'phone' => '+919876543211',
            'whatsapp_opt_in' => true,
        ]);

        $this->group->members()->attach([$contact1->id, $contact2->id]);

        $this->template = WhatsAppTemplate::create([
            'name' => 'kashmir_season_offer',
            'language' => 'en_US',
            'category' => 'MARKETING',
            'status' => 'APPROVED',
            'components' => [
                ['type' => 'BODY', 'text' => 'Hi {{1}}, special offer for you!'],
            ],
            'body_variable_count' => 1,
        ]);
    }

    public function test_campaign_dispatch_sends_messages_and_completes(): void
    {
        Http::fake([
            'graph.facebook.com/*' => fn () => Http::response([
                'messages' => [['id' => 'wamid.' . uniqid()]],
            ], 200),
        ]);

        $campaign = WhatsAppCampaign::create([
            'name' => 'Summer Launch',
            'contact_group_id' => $this->group->id,
            'template_name' => $this->template->name,
            'template_language' => 'en_US',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $service = app(CampaignService::class);
        $service->dispatchNow($campaign);

        $campaign->refresh();
        $this->assertSame(2, $campaign->total_recipients);

        // Run the dispatched jobs
        foreach ($campaign->recipients as $recipient) {
            $job = new SendWhatsAppCampaignMessage($recipient->id);
            $job->handle(app(WhatsAppService::class), $service);
        }

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertNotNull($campaign->completed_at);
    }

    public function test_campaign_with_failed_sends_marks_recipients_failed_and_does_not_hang(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Message template not approved'],
            ], 400),
        ]);

        $campaign = WhatsAppCampaign::create([
            'name' => 'Winter Special',
            'contact_group_id' => $this->group->id,
            'template_name' => $this->template->name,
            'template_language' => 'en_US',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $service = app(CampaignService::class);
        $service->dispatchNow($campaign);

        foreach ($campaign->recipients as $recipient) {
            $job = new SendWhatsAppCampaignMessage($recipient->id);
            $job->handle(app(WhatsAppService::class), $service);
        }

        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
        $this->assertSame(0, $campaign->sent_count);
        $this->assertSame(2, $campaign->failed_count);
        $this->assertNotNull($campaign->completed_at);
    }

    public function test_cancelling_campaign_marks_pending_recipients_as_skipped(): void
    {
        $campaign = WhatsAppCampaign::create([
            'name' => 'Cancel Me',
            'contact_group_id' => $this->group->id,
            'template_name' => $this->template->name,
            'template_language' => 'en_US',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $service = app(CampaignService::class);
        $service->buildRecipients($campaign);

        $service->cancel($campaign);

        $campaign->refresh();
        $this->assertSame('cancelled', $campaign->status);

        foreach ($campaign->recipients as $recipient) {
            $this->assertSame('skipped', $recipient->status);
        }
    }

    public function test_reconcile_stuck_campaigns_auto_resolves(): void
    {
        $campaign = WhatsAppCampaign::create([
            'name' => 'Stuck Campaign',
            'contact_group_id' => $this->group->id,
            'template_name' => $this->template->name,
            'template_language' => 'en_US',
            'status' => 'sending',
            'started_at' => now()->subHours(2),
            'total_recipients' => 2,
            'created_by' => $this->admin->id,
        ]);

        // Create recipients stuck in pending
        WhatsAppCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'wa_id' => '919876543210',
            'status' => 'pending',
            'created_at' => now()->subHours(2),
        ]);
        WhatsAppCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'wa_id' => '919876543211',
            'status' => 'pending',
            'created_at' => now()->subHours(2),
        ]);

        $service = app(CampaignService::class);
        $reconciled = $service->reconcileStuckCampaigns(staleMinutes: 30);

        $this->assertGreaterThan(0, $reconciled);

        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
        $this->assertSame(2, $campaign->failed_count);
    }
}
