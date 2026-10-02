<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes a batch of client-submitted analytics events off the HTTP cycle.
 *
 * The ingestion API validates and dispatches this job immediately so the browser
 * gets a <10 ms response. This worker does the real work: dedup checking, batch
 * insert, session rollup, and — for any conversion events — dispatching the
 * ForwardAnalyticsEvent job for server-side GA4/Meta forwarding.
 *
 * Retry: 3 attempts with [10 s, 30 s, 120 s] backoff. On permanent failure the
 * batch is logged to the failed_jobs table for manual inspection. Analytics
 * failures never affect the main application.
 */
class ProcessAnalyticsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    /**
     * @param  list<array{name: string, event_id?: string, params?: array, ...}>  $events
     * @param  string|null  $sessionId   resolved analytics session id
     * @param  string|null  $anonymousId persistent client anonymous id
     * @param  string|null  $ip          request IP (captured before dispatch)
     * @param  string|null  $userAgent   request user-agent (captured before dispatch)
     * @param  int|null     $userId      authenticated user id (if any)
     */
    public function __construct(
        public array $events,
        public ?string $sessionId,
        public ?string $anonymousId,
        public ?string $ip,
        public ?string $userAgent,
        public ?int $userId,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        if (! config('services.analytics.backend_enabled', true)) {
            return;
        }

        try {
            $this->processBatch();
        } catch (\Throwable $e) {
            Log::warning('ProcessAnalyticsBatch failed: ' . $e->getMessage(), [
                'batch_size' => count($this->events),
                'session_id' => $this->sessionId,
            ]);
            throw $e; // let the queue retry
        }
    }

    private function processBatch(): void
    {
        if (empty($this->events)) {
            return;
        }

        // 1. Collect all event_ids from the batch for a single dedup check.
        $eventIds = [];
        foreach ($this->events as $e) {
            if (! empty($e['event_id'])) {
                $eventIds[] = $e['event_id'];
            }
        }

        // Single query: find which event_ids already exist.
        $existingIds = [];
        if ($eventIds) {
            $existingIds = AnalyticsEvent::whereIn('event_id', $eventIds)
                ->pluck('event_id')
                ->flip()
                ->all();
        }

        // 2. Build rows for batch insert, skipping duplicates.
        $rows = [];
        $conversionEvents = [];
        $now = now();

        foreach ($this->events as $e) {
            $eventId = $e['event_id'] ?? null;

            // Skip duplicate events.
            if ($eventId && isset($existingIds[$eventId])) {
                continue;
            }

            $name = $e['name'];
            $params = $e['params'] ?? [];

            // Extract reserved keys from params.
            $productType = $e['product_type'] ?? ($params['product_type'] ?? null);
            $productId = $e['product_id'] ?? ($params['product_id'] ?? null);
            $value = isset($e['value']) ? (float) $e['value'] : null;
            $currency = $e['currency'] ?? ($value !== null ? 'INR' : null);
            $channel = $e['channel'] ?? null;

            // Remove reserved keys from params before storing as meta.
            foreach (['product_type', 'product_id', 'value', 'currency', 'channel', 'event_id', 'anonymous_id', 'name'] as $k) {
                unset($params[$k]);
            }

            $row = [
                'event_type'     => $name,
                'event_id'       => $eventId,
                'product_type'   => $productType ? substr((string) $productType, 0, 30) : null,
                'product_id'     => $productId,
                'user_id'        => $this->userId,
                'session_id'     => $this->sessionId,
                'anonymous_id'   => $this->anonymousId,
                'correlation_id' => null,
                'channel'        => $channel ? substr((string) $channel, 0, 30) : null,
                'value'          => $value,
                'currency'       => $currency ? substr((string) $currency, 0, 3) : null,
                'source'         => 'client',
                'meta'           => ! empty($params) ? json_encode($params) : null,
                'ip_address'     => $this->ip,
                'user_agent'     => $this->userAgent,
                'created_at'     => $now,
            ];

            $rows[] = $row;

            // Mark duplicates so subsequent events in the same batch don't double-insert.
            if ($eventId) {
                $existingIds[$eventId] = true;
            }

            // Check if this is a conversion event that needs server-side forwarding.
            if (in_array($name, \App\Services\AnalyticsService::CONVERSION_EVENTS, true)) {
                $conversionEvents[] = [
                    'name'     => $name,
                    'event_id' => $eventId,
                    'value'    => $value,
                    'currency' => $currency,
                    'params'   => $params,
                    'product_type' => $productType,
                    'product_id'   => $productId,
                ];
            }
        }

        // 3. Single batch insert.
        if ($rows) {
            // Chunk into groups of 50 to avoid overly large queries.
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('analytics_events')->insert($chunk);
            }
        }

        // 4. Bump session event count (single update, not per-event).
        if ($this->sessionId && count($rows) > 0) {
            try {
                \App\Models\AnalyticsSession::where('session_id', $this->sessionId)
                    ->update([
                        'events_count'     => DB::raw('events_count + ' . count($rows)),
                        'last_activity_at' => $now,
                    ]);
            } catch (\Throwable $e) {
                // Non-critical — the session row may not exist yet.
            }
        }

        // 5. Dispatch server-side forwards for conversion events.
        foreach ($conversionEvents as $ce) {
            try {
                ForwardAnalyticsEvent::dispatch(
                    eventType: $ce['name'],
                    eventId: $ce['event_id'] ?: ('batch:' . uniqid()),
                    params: array_merge($ce['params'], array_filter([
                        'value'        => $ce['value'],
                        'currency'     => $ce['currency'],
                        'product_type' => $ce['product_type'],
                        'product_id'   => $ce['product_id'],
                    ], fn ($v) => $v !== null)),
                    userData: [],
                    clientId: $this->anonymousId ?? $this->sessionId,
                    ip: $this->ip,
                    userAgent: $this->userAgent,
                );
            } catch (\Throwable $e) {
                Log::warning('Conversion forward from batch failed: ' . $e->getMessage(), [
                    'event_type' => $ce['name'],
                ]);
            }
        }
    }
}
