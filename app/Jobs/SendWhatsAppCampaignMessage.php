<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaignRecipient;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a single campaign message to one recipient and records the outcome.
 * One recipient per job keeps retries isolated and rate-limiting simple.
 */
class SendWhatsAppCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $recipientId)
    {
    }

    public function handle(WhatsAppService $whatsapp, CampaignService $campaigns): void
    {
        $recipient = WhatsAppCampaignRecipient::with('campaign', 'contact')->find($this->recipientId);
        if (! $recipient || $recipient->status !== 'pending') {
            return; // already processed or gone
        }

        $campaign = $recipient->campaign;
        if (! $campaign) {
            return;
        }

        if ($campaign->status === 'cancelled') {
            $recipient->update([
                'status' => 'skipped',
                'error' => 'Campaign cancelled',
            ]);
            $campaigns->refreshProgress($campaign->fresh());
            return;
        }

        try {
            $result = $whatsapp->notifyTemplate(
                $recipient->wa_id,
                $campaign->template_name,
                $campaign->template_language,
                $campaign->template_params ?? [],
                $recipient->contact?->name,
            );

            if ($result['success'] ?? false) {
                $recipient->update([
                    'status' => 'sent',
                    'wa_message_id' => $result['wa_message_id'] ?? null,
                    'sent_at' => now(),
                    'error' => null,
                ]);
            } else {
                $recipient->update([
                    'status' => 'failed',
                    'error' => $result['error'] ?? 'Send failed',
                ]);
            }
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        } finally {
            $campaigns->refreshProgress($campaign->fresh());
        }
    }

    public function failed(\Throwable $e): void
    {
        $recipient = WhatsAppCampaignRecipient::with('campaign')->find($this->recipientId);
        if ($recipient) {
            if ($recipient->status === 'pending') {
                $recipient->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
            if ($recipient->campaign) {
                app(CampaignService::class)->refreshProgress($recipient->campaign);
            }
        }
    }
}
