<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Models\CrmLead;
use Illuminate\Database\Eloquent\Model;

/**
 * Centralised recorder for the CRM activity timeline. Every meaningful CRM event
 * funnels through here so the timeline stays consistent and queryable.
 */
class CrmActivityService
{
    public function record(string $type, string $title, array $opts = []): CrmActivity
    {
        return CrmActivity::create([
            'type' => $type,
            'title' => $title,
            'description' => $opts['description'] ?? null,
            'data' => $opts['data'] ?? null,
            'contact_id' => $opts['contact_id'] ?? null,
            'lead_id' => $opts['lead_id'] ?? null,
            'booking_id' => $opts['booking_id'] ?? null,
            'subject_type' => isset($opts['subject']) ? $opts['subject']::class : ($opts['subject_type'] ?? null),
            'subject_id' => isset($opts['subject']) ? $opts['subject']->getKey() : ($opts['subject_id'] ?? null),
            'performed_by' => $opts['performed_by'] ?? auth('admin')->id(),
            'is_internal' => (bool) ($opts['is_internal'] ?? false),
            'occurred_at' => $opts['occurred_at'] ?? now(),
        ]);
    }

    /** Convenience: record an activity tied to a lead (and its contact). */
    public function forLead(CrmLead $lead, string $type, string $title, array $opts = []): CrmActivity
    {
        return $this->record($type, $title, array_merge($opts, [
            'lead_id' => $lead->id,
            'contact_id' => $opts['contact_id'] ?? $lead->contact_id,
            'subject' => $lead,
        ]));
    }
}
