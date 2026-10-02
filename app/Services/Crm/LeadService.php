<?php

namespace App\Services\Crm;

use App\Events\Crm\LeadCreated;
use App\Models\CrmLead;
use App\Models\LeadSource;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates lead intake from any channel (website, phone, manual, API,
 * marketing webhooks). Ensures a single contact, attaches attribution, assigns
 * an agent, seeds the pipeline stage, scores, and records the timeline — all in
 * one transaction. Idempotency (for webhook sources) is handled by the caller
 * via external_lead_id before calling create().
 */
class LeadService
{
    public function __construct(
        protected ContactService $contacts,
        protected AttributionService $attribution,
        protected LeadAssignmentService $assignment,
        protected LeadScoringService $scoring,
        protected CrmActivityService $activity,
    ) {
    }

    protected array $legacySources = ['website', 'referral', 'phone', 'social', 'campaign'];

    public function create(array $data, ?Request $request = null): CrmLead
    {
        return DB::transaction(function () use ($data, $request) {
            $contact = $this->contacts->findOrCreate($data);

            $sourceId = $this->resolveSourceId($data);
            $attribution = $this->attribution->forLead($request, $data['attribution'] ?? []);

            $pipeline = Pipeline::default();
            $stageId = $pipeline?->stages->first()?->id;

            $assignedTo = $data['assigned_to']
                ?? $this->assignment->resolve(['source_id' => $sourceId]);

            $lead = CrmLead::create([
                // legacy columns (kept working)
                'name' => $data['name'] ?? $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone ?? ($data['phone'] ?? null),
                'destination' => $data['destination'] ?? null,
                'product_type' => $data['product_type'] ?? 'package',
                'budget' => $data['budget'] ?? null,
                'travellers_count' => $data['travellers_count'] ?? (($data['adults'] ?? 1) + ($data['children'] ?? 0)),
                'travel_date' => $data['travel_date'] ?? ($data['travel_start_date'] ?? null),
                'source' => in_array($data['source'] ?? null, $this->legacySources, true) ? $data['source'] : 'website',
                'status' => 'new',
                'assigned_to' => $assignedTo,
                'notes' => $data['notes'] ?? null,
                // CRM layer
                'contact_id' => $contact->id,
                'company_id' => $data['company_id'] ?? $contact->company_id,
                'source_id' => $sourceId,
                'source_detail' => $data['source_detail'] ?? null,
                'pipeline_id' => $pipeline?->id,
                'stage_id' => $stageId,
                'priority' => $data['priority'] ?? 'medium',
                'travel_start_date' => $data['travel_start_date'] ?? null,
                'travel_end_date' => $data['travel_end_date'] ?? null,
                'adults' => $data['adults'] ?? null,
                'children' => $data['children'] ?? null,
                'infants' => $data['infants'] ?? null,
                'rooms' => $data['rooms'] ?? null,
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'service_type' => $data['service_type'] ?? null,
                'external_lead_id' => $data['external_lead_id'] ?? null,
                'external_campaign_id' => $data['external_campaign_id'] ?? null,
                'external_adset_id' => $data['external_adset_id'] ?? null,
                'external_ad_id' => $data['external_ad_id'] ?? null,
                'form_id' => $data['form_id'] ?? null,
            ] + $attribution);

            // Keep contact lifecycle + source in sync.
            $contact->forceFill([
                'last_activity_at' => now(),
                'source_id' => $contact->source_id ?: $sourceId,
            ])->save();

            $this->scoring->apply($lead);

            $this->activity->forLead($lead, 'lead_created', 'Lead created', [
                'data' => ['source_id' => $sourceId, 'channel' => $data['source'] ?? 'website'],
                'performed_by' => $data['performed_by'] ?? auth('admin')->id(),
            ]);

            if ($assignedTo) {
                $this->activity->forLead($lead, 'assigned', 'Lead assigned', [
                    'data' => ['assigned_to' => $assignedTo],
                    'performed_by' => $data['performed_by'] ?? auth('admin')->id(),
                ]);
            }

            event(new LeadCreated($lead));

            // Fire automation workflows registered for lead creation.
            app(\App\Services\Crm\AutomationEngine::class)->dispatch('lead_created', $lead);

            // Single choke point for the lead conversion across ALL channels
            // (website forms, mobile app, Meta/Google lead webhooks). Forwards to
            // GA4 + Meta CAPI; event_id = lead:{uuid} dedupes with the browser event.
            \App\Services\AnalyticsService::track('generate_lead', [
                'event_id' => 'lead:' . $lead->uuid,
                'product_type' => $lead->product_type,
                'user_id' => $lead->contact?->user_id,
                'lead_type' => $data['source_slug'] ?? ($data['source'] ?? 'website'),
                'destination' => $lead->destination,
                'value' => $lead->budget ?: null,
                'currency' => $lead->budget ? 'INR' : null,
                'forward' => true,
                // Hashed in the CAPI provider — raw values never leave the server.
                'user_data' => array_filter([
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                ]),
            ]);

            return $lead;
        });
    }

    /** Resolve a lead_sources.id from an explicit id, slug, or platform hint. */
    protected function resolveSourceId(array $data): ?int
    {
        if (! empty($data['source_id'])) {
            return (int) $data['source_id'];
        }
        if (! empty($data['source_slug'])) {
            return LeadSource::where('slug', $data['source_slug'])->value('id');
        }
        // Map legacy channel word to a source slug if one exists.
        if (! empty($data['source'])) {
            return LeadSource::where('slug', $data['source'])->value('id');
        }

        return null;
    }
}
