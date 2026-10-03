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

        $queueName = config('services.whatsapp.campaign_queue', 'whatsapp');
        $delay = 0;
        $pendingCount = 0;

        foreach ($campaign->recipients()->where('status', 'pending')->get() as $recipient) {
            SendWhatsAppCampaignMessage::dispatch($recipient->id)
                ->onQueue($queueName)
                ->delay(now()->addSeconds($delay));
            $delay += self::SEND_SPACING_SECONDS;
            $pendingCount++;
        }

        // Empty campaign or no reachable recipients — nothing to send.
        if ($campaign->total_recipients === 0 || $pendingCount === 0) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    /** Mark a campaign to run at a future time. */
    public function schedule(WhatsAppCampaign $campaign, \DateTimeInterface $when): void
    {
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $when]);
    }

    /** Cancel a campaign and mark any un-sent recipients as skipped. */
    public function cancel(WhatsAppCampaign $campaign): void
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $campaign->recipients()->where('status', 'pending')->update([
            'status' => 'skipped',
            'error' => 'Campaign cancelled by admin',
        ]);

        $this->refreshProgress($campaign);
        $campaign->update(['status' => 'cancelled']);
    }

    /** Re-dispatch failed or stuck pending recipients for a campaign. */
    public function retryPendingOrFailed(WhatsAppCampaign $campaign): int
    {
        $recipients = $campaign->recipients()->whereIn('status', ['pending', 'failed'])->get();
        if ($recipients->isEmpty()) {
            return 0;
        }

        $campaign->update(['status' => 'sending', 'completed_at' => null]);

        $queueName = config('services.whatsapp.campaign_queue', 'whatsapp');
        $delay = 0;

        foreach ($recipients as $recipient) {
            $recipient->update(['status' => 'pending', 'error' => null]);
            SendWhatsAppCampaignMessage::dispatch($recipient->id)
                ->onQueue($queueName)
                ->delay(now()->addSeconds($delay));
            $delay += self::SEND_SPACING_SECONDS;
        }

        return $recipients->count();
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
        $failed = $counts['failed'] ?? 0;
        $pending = $counts['pending'] ?? 0;

        $campaign->update([
            'sent_count' => $sent,
            'delivered_count' => ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0),
            'read_count' => $counts['read'] ?? 0,
            'failed_count' => $failed,
        ]);

        if ($pending === 0 && $campaign->status === 'sending') {
            $finalStatus = ($sent > 0 || $campaign->total_recipients === 0) ? 'completed' : 'failed';
            $campaign->update(['status' => $finalStatus, 'completed_at' => now()]);
        }
    }

    /**
     * Inspect and reconcile any campaigns stuck in 'sending'.
     * Recalculates progress, and marks stale pending recipients (staleMinutes) as failed.
     */
    public function reconcileStuckCampaigns(int $staleMinutes = 30): int
    {
        $sending = WhatsAppCampaign::where('status', 'sending')->get();
        $reconciled = 0;

        foreach ($sending as $campaign) {
            $this->refreshProgress($campaign);
            $campaign->refresh();

            if ($campaign->status !== 'sending') {
                $reconciled++;
                continue;
            }

            // If campaign has been sending for longer than stale threshold, check for stuck recipients
            if ($campaign->started_at && $campaign->started_at->lt(now()->subMinutes($staleMinutes))) {
                $updated = $campaign->recipients()
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'failed',
                        'error' => 'Job timed out or queue worker was interrupted during dispatch',
                    ]);

                if ($updated > 0) {
                    $this->refreshProgress($campaign);
                    $reconciled++;
                }
            }
        }

        return $reconciled;
    }
}
