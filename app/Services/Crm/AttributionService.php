<?php

namespace App\Services\Crm;

use Illuminate\Http\Request;

/**
 * Captures marketing attribution from the landing request and persists first-
 * and last-touch data in the session so it can be attached to a lead on submit.
 * First-touch values are NEVER overwritten once recorded.
 */
class AttributionService
{
    protected string $key;

    public function __construct()
    {
        $this->key = (string) config('crm.attribution.session_key', 'crm_attribution');
    }

    /** Call on landing pages (e.g. via middleware) to record touch data. */
    public function capture(Request $request): void
    {
        $params = (array) config('crm.attribution.params', []);
        $incoming = [];
        foreach ($params as $p) {
            if ($request->filled($p)) {
                $incoming[$p] = substr((string) $request->query($p), 0, 255);
            }
        }

        if (! $request->hasSession()) {
            return; // nothing to persist without a session store
        }

        $stored = (array) $request->session()->get($this->key, []);

        // Last touch = latest non-empty utm/click ids seen.
        if ($incoming) {
            $stored['last'] = array_merge($stored['last'] ?? [], $incoming);
            $stored['last']['landing_page'] = $request->fullUrl();
            $stored['last']['referrer_url'] = substr((string) $request->headers->get('referer', ''), 0, 1000);

            // First touch only set once.
            if (empty($stored['first'])) {
                $stored['first'] = $stored['last'];
            }
        } elseif (empty($stored['first'])) {
            // Record an organic/direct first touch with landing + referrer.
            $stored['first'] = [
                'landing_page' => $request->fullUrl(),
                'referrer_url' => substr((string) $request->headers->get('referer', ''), 0, 1000),
            ];
            $stored['last'] = $stored['first'];
        }

        $request->session()->put($this->key, $stored);
    }

    /** Build the attribution columns for a new lead, merging session + explicit input. */
    public function forLead(?Request $request = null, array $explicit = []): array
    {
        $request ??= request();
        // Leads may be created outside an HTTP request (queues, console, tests) where
        // no session store is bound — attribution is simply empty in that case.
        $stored = ($request && $request->hasSession())
            ? (array) ($request->session()->get($this->key, []) ?? [])
            : [];
        $first = $stored['first'] ?? [];
        $last = $stored['last'] ?? [];

        $pick = fn (array $bag, string $k) => $explicit[$k] ?? ($bag[$k] ?? null);

        return array_filter([
            'utm_source' => $pick($last, 'utm_source'),
            'utm_medium' => $pick($last, 'utm_medium'),
            'utm_campaign' => $pick($last, 'utm_campaign'),
            'utm_term' => $pick($last, 'utm_term'),
            'utm_content' => $pick($last, 'utm_content'),
            'gclid' => $pick($last, 'gclid'),
            'fbclid' => $pick($last, 'fbclid'),
            'landing_page' => $explicit['landing_page'] ?? ($first['landing_page'] ?? ($last['landing_page'] ?? null)),
            'referrer_url' => $explicit['referrer_url'] ?? ($first['referrer_url'] ?? null),
            'first_touch_source' => $first['utm_source'] ?? null,
            'first_touch_medium' => $first['utm_medium'] ?? null,
            'first_touch_campaign' => $first['utm_campaign'] ?? null,
            'last_touch_source' => $last['utm_source'] ?? null,
            'last_touch_medium' => $last['utm_medium'] ?? null,
            'last_touch_campaign' => $last['utm_campaign'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
