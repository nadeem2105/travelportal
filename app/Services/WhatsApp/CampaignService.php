<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppCampaignMessage;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use Illuminate\Support\Facades\DB;

/**
 * Builds and dispatches WhatsApp broadcast campaigns. Recipients are drawn from
 * the campaign's contact group, filtered to WhatsApp-reachable + opted-in
 * contacts (marketing compliance), and each send is queued so large lists stay
 * within Meta rate limits.
 */
class CampaignService
{
    /** Spacing between queued sends (seconds) to smooth throughput. */
    private const SEND_SPACING_SECONDS = 1;

    /**
     * Materialize recipients from the campaign's group (idempotent — safe to
     * re-run; only adds missing rows). Returns the number of recipients.
     */
    public function buildRecipients(WhatsAppCampaign $campaign): int
    {
        $group = $campaign->group;
        if (! $group) {
            return 0;
        }

        // WhatsApp-reachable + opted-in only.
        $contacts = $group->resolveContacts(whatsappReachableOnly: true)
            ->filter(fn ($c) => (bool) $c->whatsapp_opt_in);

        DB::transaction(function () use ($campaign, $contacts) {
            foreach ($contacts as $contact) {
                $waId = preg_replace('/\D+/', '', (string) $contact->phone);
                if (! $waId) {
                    continue;
                }

                WhatsAppCampaignRecipient::firstOrCreate(
                    ['campaign_id' => $campaign->id, 'wa_id' => $waId],
                    ['contact_id' => $contact->id, 'status' => 'pending']
                );
            }

            $campaign->update(['total_recipients' => $campaign->recipients()->count()]);
        });

        return $campaign->total_recipients;
    }

    /** Queue an immediate send. */
    public function dispatchNow(WhatsAppCampaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return;
        }

        $this->buildRecipients($campaign);

        $campaign->update(['status' => 'sending', 'started_at' => now(), 'scheduled_at' => null]);

        $delay = 0;
        foreach ($campaign->recipients()->where('status', 'pending')->get() as $recipient) {
            SendWhatsAppCampaignMessage::dispatch($recipient->id)
                ->onQueue('whatsapp')
                ->delay(now()->addSeconds($delay));
            $delay += self::SEND_SPACING_SECONDS;
        }

        // Empty campaign — nothing to send.
        if ($campaign->total_recipients === 0) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    /** Mark a campaign to run at a future time. */
    public function schedule(WhatsAppCampaign $campaign, \DateTimeInterface $when): void
    {
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $when]);
    }

    /** Dispatch any scheduled campaigns whose time has come (called by the scheduler). */
    public function processDue(): int
    {
        $due = WhatsAppCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $this->dispatchNow($campaign);
        }

        return $due->count();
    }

    /** Recompute counters and close the campaign when all recipients are processed. */
    public function refreshProgress(WhatsAppCampaign $campaign): void
    {
        $counts = $campaign->recipients()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $sent = ($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0);
        $pending = $counts['pending'] ?? 0;

        $campaign->update([
            'sent_count' => $sent,
            'delivered_count' => ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0),
            'read_count' => $counts['read'] ?? 0,
            'failed_count' => $counts['failed'] ?? 0,
        ]);

        if ($pending === 0 && $campaign->status === 'sending') {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
