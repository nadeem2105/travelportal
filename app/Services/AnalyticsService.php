<?php

namespace App\Services;

use App\Jobs\ForwardAnalyticsEvent;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * First-party analytics facade.
 *
 * track() writes one append-only row to analytics_events and — for conversion
 * events — queues a server-side forward to GA4 (Measurement Protocol) and Meta
 * (Conversions API) so important conversions are counted even when the browser
 * pixel is blocked. Duplicate protection is by event_id: the same event_id is
 * inserted once, and it is ALSO used as the dedup key across the browser event
 * and the server event on Google/Meta.
 *
 * Never throws — analytics must never break a booking.
 */
class AnalyticsService
{
    /**
     * Events that represent a conversion and are mirrored server-side to the ad
     * platforms. Keep in sync with the event dictionary (config/analytics_events).
     */
    public const CONVERSION_EVENTS = [
        'booking_confirmed',
        'purchase',
        'generate_lead',
        'lead_created',
        'payment_success',
    ];

    /**
     * Record an analytics event. $payload may carry reserved keys:
     *   product_type, product_id, user_id, session_id, anonymous_id,
     *   event_id (dedup), value, currency, channel, source (server|client),
     *   forward (bool), user_data (array for CAPI matching — hashed in the job).
     * Everything else is stored in meta.
     */
    public static function track(string $eventType, array $payload = []): ?AnalyticsEvent
    {
        try {
            if (! config('services.analytics.backend_enabled', true)) {
                return null;
            }

            $productType = $payload['product_type'] ?? null;
            $productId = $payload['product_id'] ?? null;
            $userId = $payload['user_id'] ?? auth('web')->id();
            $eventId = $payload['event_id'] ?? null;
            $anonymousId = $payload['anonymous_id'] ?? null;
            $value = $payload['value'] ?? null;
            $currency = $payload['currency'] ?? ($value !== null ? 'INR' : null);
            $channel = $payload['channel'] ?? null;
            $source = $payload['source'] ?? 'server';
            $sessionId = $payload['session_id']
                ?? request()->cookie('lz_sid')
                ?? (session()->isStarted() ? session()->getId() : null);
            $forward = $payload['forward'] ?? in_array($eventType, self::CONVERSION_EVENTS, true);
            $userData = $payload['user_data'] ?? [];

            foreach (['product_type', 'product_id', 'user_id', 'event_id', 'anonymous_id',
                'value', 'currency', 'channel', 'source', 'session_id', 'forward', 'user_data'] as $k) {
                unset($payload[$k]);
            }

            $attributes = [
                'event_type' => $eventType,
                'product_type' => $productType,
                'product_id' => $productId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'anonymous_id' => $anonymousId,
                'correlation_id' => app()->has('correlation_id') ? app('correlation_id') : request()->header('X-Correlation-ID'),
                'channel' => $channel,
                'value' => $value,
                'currency' => $currency,
                'source' => $source,
                'meta' => ! empty($payload) ? $payload : null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ];

            // Idempotency: an event_id is inserted at most once. A repeat is a
            // duplicate (retry / double-submit) and must NOT re-forward revenue.
            if ($eventId) {
                $event = AnalyticsEvent::firstOrCreate(['event_id' => $eventId], $attributes + ['event_id' => $eventId]);
                if (! $event->wasRecentlyCreated) {
                    return $event;
                }
            } else {
                $event = AnalyticsEvent::create($attributes);
            }

            if ($forward && config('services.analytics.enabled', true)) {
                self::forward($eventType, $event, $value, $currency, $userData, $payload);
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('Analytics tracking failed: ' . $e->getMessage(), ['event_type' => $eventType]);

            return null;
        }
    }

    /**
     * Convenience: track a completed booking as a purchase conversion with the
     * real revenue figures pulled from the Booking. Uses booking_reference as the
     * event_id so browser `purchase` and server forward dedupe to one conversion.
     */
    public static function trackPurchase(Booking $booking, string $eventType = 'booking_confirmed'): ?AnalyticsEvent
    {
        $contact = (array) ($booking->contact ?? []);

        return self::track($eventType, [
            'event_id' => 'booking:' . $booking->booking_reference,
            'product_type' => $booking->product_type,
            'product_id' => $booking->product_id,
            'user_id' => $booking->user_id,
            'value' => (float) $booking->total_amount,
            'currency' => $booking->currency ?: 'INR',
            'transaction_id' => $booking->booking_reference,
            'tax' => (float) ($booking->tax_amount ?? 0),
            'discount' => (float) ($booking->discount_amount ?? 0),
            'booking_reference' => $booking->booking_reference,
            'forward' => true,
            // Hashed in the CAPI provider — raw values never leave the server.
            'user_data' => array_filter([
                'email' => $contact['email'] ?? $booking->user?->email,
                'phone' => $contact['phone'] ?? $booking->user?->phone,
            ]),
        ]);
    }

    /**
     * Queue the server-side forward to GA4 MP + Meta CAPI (+ Google Ads readiness).
     */
    protected static function forward(string $eventType, AnalyticsEvent $event, $value, $currency, array $userData, array $meta): void
    {
        try {
            ForwardAnalyticsEvent::dispatch(
                eventType: $eventType,
                eventId: $event->event_id ?: ('evt:' . $event->id),
                params: array_merge($meta, array_filter([
                    'value' => $value,
                    'currency' => $currency,
                    'product_type' => $event->product_type,
                    'product_id' => $event->product_id,
                ], fn ($v) => $v !== null)),
                userData: $userData,
                clientId: request()->cookie('_ga') ? self::ga4ClientIdFromCookie(request()->cookie('_ga')) : ($event->anonymous_id ?: $event->session_id),
                eventSourceUrl: request()->fullUrl(),
                ip: request()->ip(),
                userAgent: substr((string) request()->userAgent(), 0, 255),
                fbc: request()->cookie('_fbc'),
                fbp: request()->cookie('_fbp'),
            );
        } catch (\Throwable $e) {
            Log::warning('Analytics forward dispatch failed: ' . $e->getMessage(), ['event_type' => $eventType]);
        }
    }

    /** GA4 client id lives in the _ga cookie as GA1.1.<clientId>.<ts>. */
    public static function ga4ClientIdFromCookie(?string $ga): ?string
    {
        if (! $ga) {
            return null;
        }
        $parts = explode('.', $ga);

        return count($parts) >= 4 ? $parts[2] . '.' . $parts[3] : null;
    }

    /**
     * Aggregate funnel metrics for a given date range.
     */
    public function funnelSummary(Carbon $from, Carbon $to): array
    {
        $searches = AnalyticsEvent::whereBetween('created_at', [$from, $to])
            ->whereIn('event_type', ['search_flight', 'search_hotel', 'search_cab', 'travel_search'])
            ->count();

        $productViews = AnalyticsEvent::whereBetween('created_at', [$from, $to])
            ->whereIn('event_type', ['view_package', 'view_hotel', 'view_item'])
            ->count();

        $checkouts = AnalyticsEvent::whereBetween('created_at', [$from, $to])
            ->where('event_type', 'begin_checkout')
            ->count();

        $confirmed = Booking::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        $checkoutRate = $checkouts > 0 ? round(($confirmed / $checkouts) * 100, 1) : 0.0;
        $totalInteractions = $searches + $productViews;
        $overallRate = $totalInteractions > 0 ? round(($confirmed / $totalInteractions) * 100, 1) : 0.0;

        return [
            'searches' => $searches,
            'product_views' => $productViews,
            'checkouts' => $checkouts,
            'confirmed' => $confirmed,
            'checkout_conversion_rate' => $checkoutRate,
            'overall_conversion_rate' => $overallRate,
        ];
    }
}
