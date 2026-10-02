<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Lead Ads webhook. GET verifies the subscription; POST receives leadgen
 * notifications, fetches the full lead via the Graph API, and creates a CRM
 * lead with full ad attribution. Authenticated by X-Hub-Signature-256 + a
 * WebhookEvent idempotency guard on the leadgen id.
 */
class MetaLeadWebhookController extends Controller
{
    public function __construct(private LeadService $leads)
    {
    }

    public function verify(Request $request)
    {
        $expected = config('services.meta_leads.verify_token');
        if ($request->query('hub_mode') === 'subscribe'
            && $expected && hash_equals((string) $expected, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (! $this->signatureValid($request)) {
            Log::warning('Meta Leads webhook: invalid signature');

            return response()->json(['error' => 'invalid signature'], 403);
        }

        foreach (data_get($request->json()->all(), 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }
                $this->handleLeadgen($change['value'] ?? []);
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function handleLeadgen(array $value): void
    {
        $leadgenId = $value['leadgen_id'] ?? null;
        if (! $leadgenId) {
            return;
        }

        // Idempotency — skip if this leadgen id was already processed.
        if (WebhookEvent::where('provider', 'meta_leads')->where('event_id', $leadgenId)->exists()) {
            return;
        }

        try {
            $lead = $this->fetchLead($leadgenId);
            if (! $lead) {
                return;
            }

            $fields = $this->flattenFieldData($lead['field_data'] ?? []);

            $this->leads->create([
                'name' => $fields['full_name'] ?? trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? '')) ?: 'Meta Lead',
                'phone' => $fields['phone_number'] ?? $fields['phone'] ?? null,
                'email' => $fields['email'] ?? null,
                'destination' => $fields['destination'] ?? $fields['city'] ?? null,
                'product_type' => 'package',
                'source' => 'campaign',
                'source_slug' => 'meta-ads',
                'external_lead_id' => $leadgenId,
                'external_campaign_id' => $value['campaign_id'] ?? ($lead['campaign_id'] ?? null),
                'external_adset_id' => $value['adgroup_id'] ?? ($lead['adset_id'] ?? null),
                'external_ad_id' => $value['ad_id'] ?? ($lead['ad_id'] ?? null),
                'form_id' => $value['form_id'] ?? ($lead['form_id'] ?? null),
                'attribution' => [
                    'utm_source' => 'meta',
                    'utm_medium' => 'paid_social',
                    'utm_campaign' => $value['campaign_id'] ?? null,
                ],
                'notes' => $this->rawNotes($fields),
            ]);

            WebhookEvent::create([
                'provider' => 'meta_leads',
                'event_type' => 'leadgen',
                'event_id' => $leadgenId,
                'payload' => $value,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Meta lead ingestion failed: ' . $e->getMessage(), ['leadgen_id' => $leadgenId]);
        }
    }

    /** Fetch the full lead record from the Graph API. */
    protected function fetchLead(string $leadgenId): ?array
    {
        $token = config('services.meta_leads.page_access_token');
        $version = config('services.meta_leads.api_version', 'v21.0');
        if (empty($token)) {
            Log::warning('Meta Leads: page access token not configured.');

            return null;
        }

        $response = Http::withToken($token)->acceptJson()->timeout(20)
            ->get("https://graph.facebook.com/{$version}/{$leadgenId}", [
                'fields' => 'id,created_time,field_data,ad_id,campaign_id,adset_id,form_id',
            ]);

        return $response->successful() ? $response->json() : null;
    }

    /** Turn Meta's [{name, values:[...]}] into a flat name => value map. */
    protected function flattenFieldData(array $fieldData): array
    {
        $out = [];
        foreach ($fieldData as $f) {
            $name = strtolower((string) ($f['name'] ?? ''));
            $out[$name] = is_array($f['values'] ?? null) ? implode(', ', $f['values']) : ($f['values'] ?? null);
        }

        return $out;
    }

    protected function rawNotes(array $fields): string
    {
        $lines = [];
        foreach ($fields as $k => $v) {
            if (! in_array($k, ['full_name', 'first_name', 'last_name', 'phone_number', 'phone', 'email'], true) && $v !== null && $v !== '') {
                $lines[] = ucwords(str_replace('_', ' ', $k)) . ': ' . $v;
            }
        }

        return $lines ? "Meta Lead Ads submission:\n" . implode("\n", $lines) : 'Meta Lead Ads submission.';
    }

    protected function signatureValid(Request $request): bool
    {
        $secret = config('services.meta_leads.app_secret');
        if (empty($secret)) {
            return true; // dev fallback
        }
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $header);
    }
}
