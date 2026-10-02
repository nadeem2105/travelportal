<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Google Ads Lead Form Extensions webhook. Google POSTs a JSON payload with a
 * `google_key` that must match the key configured on the lead form. Fields
 * arrive in `user_column_data` keyed by `column_id` (FULL_NAME, PHONE_NUMBER…).
 */
class GoogleLeadWebhookController extends Controller
{
    public function __construct(private LeadService $leads)
    {
    }

    public function receive(Request $request)
    {
        $payload = $request->json()->all();

        // Auth: the webhook key must match.
        $expectedKey = config('services.google_leads.key');
        if (empty($expectedKey) || ! hash_equals((string) $expectedKey, (string) ($payload['google_key'] ?? ''))) {
            Log::warning('Google Leads webhook: invalid key');

            return response()->json(['error' => 'invalid key'], 403);
        }

        $leadId = (string) ($payload['lead_id'] ?? '');
        if ($leadId === '') {
            return response()->json(['error' => 'missing lead_id'], 422);
        }

        // Idempotency.
        if (WebhookEvent::where('provider', 'google_leads')->where('event_id', $leadId)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Google sends test submissions when validating the form — accept but skip.
        if (! empty($payload['is_test'])) {
            return response()->json(['ok' => true, 'test' => true]);
        }

        try {
            $fields = $this->mapColumns($payload['user_column_data'] ?? []);

            $this->leads->create([
                'name' => $fields['FULL_NAME'] ?? trim(($fields['FIRST_NAME'] ?? '') . ' ' . ($fields['LAST_NAME'] ?? '')) ?: 'Google Lead',
                'phone' => $fields['PHONE_NUMBER'] ?? null,
                'email' => $fields['EMAIL'] ?? null,
                'destination' => $fields['CITY'] ?? $fields['POSTAL_CODE'] ?? null,
                'product_type' => 'package',
                'source' => 'campaign',
                'source_slug' => 'google-ads',
                'external_lead_id' => $leadId,
                'external_campaign_id' => $payload['campaign_id'] ?? null,
                'form_id' => $payload['form_id'] ?? null,
                'attribution' => [
                    'utm_source' => 'google',
                    'utm_medium' => 'cpc',
                    'gclid' => $payload['gcl_id'] ?? null,
                ],
                'notes' => $this->rawNotes($fields),
            ]);

            WebhookEvent::create([
                'provider' => 'google_leads',
                'event_type' => 'lead_form',
                'event_id' => $leadId,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Google lead ingestion failed: ' . $e->getMessage(), ['lead_id' => $leadId]);

            return response()->json(['error' => 'processing error'], 500);
        }

        return response()->json(['ok' => true]);
    }

    /** Map user_column_data entries to a column_id => value array. */
    protected function mapColumns(array $columns): array
    {
        $out = [];
        foreach ($columns as $c) {
            $key = strtoupper((string) ($c['column_id'] ?? $c['column_name'] ?? ''));
            $out[$key] = $c['string_value'] ?? null;
        }

        return $out;
    }

    protected function rawNotes(array $fields): string
    {
        $skip = ['FULL_NAME', 'FIRST_NAME', 'LAST_NAME', 'PHONE_NUMBER', 'EMAIL'];
        $lines = [];
        foreach ($fields as $k => $v) {
            if (! in_array($k, $skip, true) && $v !== null && $v !== '') {
                $lines[] = ucwords(strtolower(str_replace('_', ' ', $k))) . ': ' . $v;
            }
        }

        return $lines ? "Google Ads lead form:\n" . implode("\n", $lines) : 'Google Ads lead form submission.';
    }
}
