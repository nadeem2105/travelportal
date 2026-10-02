<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAnalyticsBatch;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First-party analytics ingestion for the browser. Public + stateless: the
 * client sends its analytics session id (lz_sid cookie) and persistent
 * anonymous_id. Events are stored as source='client' and are NOT re-forwarded to
 * GA4/Meta server-side (the browser tags handle client-side; the backend only
 * forwards its own server-authoritative conversions), so no duplication.
 *
 * PII is stripped before storage. Batches are dispatched to a queue worker for
 * async processing so this endpoint returns in <10 ms.
 */
class AnalyticsController extends Controller
{
    use ApiResponse;

    /** Param keys that must never be stored, even if the client sends them. */
    private const PII_DENYLIST = [
        'password', 'pass', 'otp', 'pin', 'cvv', 'cvc', 'card', 'card_number',
        'cardnumber', 'card_no', 'account_number', 'account_no', 'bank_account',
        'passport', 'passport_number', 'aadhaar', 'aadhar', 'pan', 'pan_number',
        'ssn', 'token', 'access_token', 'secret',
    ];

    /** Cached result of the one-time table existence check. */
    private static ?bool $tablesReady = null;

    /**
     * Accept a single event or a batch: { events: [ {name, ...}, ... ] }.
     *
     * Validates, scrubs PII, and dispatches a ProcessAnalyticsBatch job to the
     * queue. No DB writes happen in this request cycle — the queue worker handles
     * batch inserts, deduplication, and conversion forwarding asynchronously.
     */
    public function event(Request $request)
    {
        if (! config('services.analytics.backend_enabled', true) || ! $this->tablesExist()) {
            return $this->ok(['queued' => 0]);
        }

        $events = $request->input('events');
        if (! is_array($events)) {
            $events = [$request->all()]; // single-event shape
        }
        $events = array_slice($events, 0, 25); // cap batch size

        // Validate and scrub each event before queuing.
        $cleaned = [];
        foreach ($events as $e) {
            if (! is_array($e) || empty($e['name']) || ! is_string($e['name'])) {
                continue;
            }

            $name = substr(preg_replace('/[^a-z0-9_]/i', '_', $e['name']), 0, 50);
            $params = $this->scrub((array) ($e['params'] ?? []));

            $cleaned[] = array_filter([
                'name'         => $name,
                'event_id'     => isset($e['event_id']) ? substr((string) $e['event_id'], 0, 64) : null,
                'product_type' => isset($e['product_type']) ? substr((string) $e['product_type'], 0, 30) : null,
                'product_id'   => $e['product_id'] ?? null,
                'value'        => isset($e['value']) ? (float) $e['value'] : null,
                'currency'     => isset($e['currency']) ? substr((string) $e['currency'], 0, 3) : null,
                'channel'      => isset($e['channel']) ? substr((string) $e['channel'], 0, 30) : null,
                'params'       => $params ?: null,
            ], fn ($v) => $v !== null);
        }

        if (empty($cleaned)) {
            return $this->ok(['queued' => 0]);
        }

        // Dispatch to queue — zero DB queries in this request cycle.
        ProcessAnalyticsBatch::dispatch(
            events: $cleaned,
            sessionId: $this->sid($request),
            anonymousId: $request->input('anonymous_id')
                ? substr((string) $request->input('anonymous_id'), 0, 64)
                : null,
            ip: $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 255),
            userId: auth('web')->id(),
        );

        return $this->ok(['queued' => count($cleaned)]);
    }

    /** Enrich the visitor's session row with client-only signals (screen, anon id). */
    public function session(Request $request)
    {
        if (! config('services.analytics.backend_enabled', true) || ! $this->tablesExist()) {
            return $this->ok();
        }

        $sid = $this->sid($request);
        if (! $sid) {
            return $this->ok();
        }

        $data = array_filter([
            'anonymous_id' => $request->filled('anonymous_id') ? substr((string) $request->input('anonymous_id'), 0, 64) : null,
            'screen' => $request->filled('screen') ? substr(preg_replace('/[^0-9x]/i', '', (string) $request->input('screen')), 0, 20) : null,
            'language' => $request->filled('language') ? substr((string) $request->input('language'), 0, 20) : null,
            'user_id' => auth('web')->id(),
        ], fn ($v) => $v !== null && $v !== '');

        if ($data) {
            AnalyticsSession::where('session_id', $sid)->update($data);
        }

        return $this->ok();
    }

    /**
     * Record a page view, or patch an existing one with engagement metrics
     * (time_on_page / scroll_depth / is_exit) sent via sendBeacon on unload.
     *
     * Supports client-generated page_view_id for reliable engagement patching
     * even when the user bounces before the initial page view response returns.
     */
    public function pageView(Request $request)
    {
        if (! config('services.analytics.backend_enabled', true) || ! $this->tablesExist()) {
            return $this->ok();
        }

        $sid = $this->sid($request);

        // Patch existing page view with engagement metrics.
        // Accept either server id or client-generated page_view_id.
        $pvId = $request->input('id') ?? $request->input('page_view_id');
        if ($pvId && $request->hasAny(['time_on_page', 'scroll_depth', 'is_exit'])) {
            $query = is_numeric($pvId)
                ? AnalyticsPageView::where('id', (int) $pvId)
                : AnalyticsPageView::where('page_view_id', substr((string) $pvId, 0, 64));

            $query->where('session_id', $sid)->update(array_filter([
                'time_on_page' => $request->filled('time_on_page') ? min(86400, (int) $request->input('time_on_page')) : null,
                'scroll_depth' => $request->filled('scroll_depth') ? max(0, min(100, (int) $request->input('scroll_depth'))) : null,
                'is_exit' => $request->boolean('is_exit') ?: null,
            ], fn ($v) => $v !== null));

            return $this->ok(['id' => $pvId]);
        }

        // New page view — accept optional client-generated page_view_id.
        $clientPvId = $request->filled('page_view_id')
            ? substr((string) $request->input('page_view_id'), 0, 64)
            : null;

        $pv = AnalyticsPageView::create([
            'page_view_id' => $clientPvId,
            'session_id' => $sid,
            'anonymous_id' => $request->filled('anonymous_id') ? substr((string) $request->input('anonymous_id'), 0, 64) : null,
            'user_id' => auth('web')->id(),
            'url' => substr((string) $request->input('url'), 0, 1000) ?: null,
            'path' => substr((string) $request->input('path'), 0, 255) ?: null,
            'title' => $request->filled('title') ? substr((string) $request->input('title'), 0, 255) : null,
            'referrer' => $request->filled('referrer') ? substr((string) $request->input('referrer'), 0, 1000) : null,
            'device_type' => $request->filled('device_type') ? substr((string) $request->input('device_type'), 0, 20) : null,
            'created_at' => now(),
        ]);

        // Bump the session page-view rollup (cheap, indexed).
        if ($sid) {
            AnalyticsSession::where('session_id', $sid)->update([
                'page_views' => DB::raw('page_views + 1'),
                'last_activity_at' => now(),
            ]);
        }

        return $this->ok(['id' => $pv->id, 'page_view_id' => $clientPvId]);
    }

    /** Resolve the analytics session id: explicit payload → lz_sid cookie. */
    private function sid(Request $request, array $event = []): ?string
    {
        $sid = $event['session_id'] ?? $request->input('session_id') ?? $request->cookie('lz_sid');

        return $sid ? substr((string) $sid, 0, 100) : null;
    }

    /** Remove PII / sensitive keys from a client-supplied params bag. */
    private function scrub(array $params): array
    {
        $clean = [];
        foreach ($params as $k => $v) {
            $key = strtolower((string) $k);
            if (in_array($key, self::PII_DENYLIST, true)) {
                continue;
            }
            // Also drop anything that looks like an email/phone in a sensitive key.
            if (in_array($key, ['email', 'phone', 'mobile', 'contact'], true)) {
                continue;
            }
            if (is_scalar($v)) {
                $clean[substr($key, 0, 40)] = is_string($v) ? substr($v, 0, 255) : $v;
            } elseif (is_array($v)) {
                $clean[substr($key, 0, 40)] = $this->scrub($v);
            }
        }

        return $clean;
    }

    /**
     * Check table existence once per PHP process lifetime.
     *
     * Previously called Schema::hasTable() on every request (4x SHOW TABLES
     * queries per request). This caches the result in a static property so
     * the check runs at most once per worker process.
     */
    private function tablesExist(): bool
    {
        return self::$tablesReady ??= (
            Schema::hasTable('analytics_events') &&
            Schema::hasTable('analytics_sessions') &&
            Schema::hasTable('analytics_page_views')
        );
    }
}
