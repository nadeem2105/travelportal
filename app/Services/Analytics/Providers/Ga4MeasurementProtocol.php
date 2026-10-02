<?php

namespace App\Services\Analytics\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side GA4 via the Measurement Protocol. Mirrors browser gtag events so
 * conversions are still counted when the client pixel is blocked. Attribution to
 * the same user requires the GA4 client_id (from the _ga cookie); when absent we
 * fall back to a stable anonymous/session id so the hit is still recorded.
 *
 * https://developers.google.com/analytics/devguides/collection/protocol/ga4
 */
class Ga4MeasurementProtocol
{
    /** Internal event slug → GA4 event name. */
    private const MAP = [
        'booking_confirmed' => 'purchase',
        'purchase' => 'purchase',
        'generate_lead' => 'generate_lead',
        'lead_created' => 'generate_lead',
        'begin_checkout' => 'begin_checkout',
        'payment_success' => 'purchase',
        'refund' => 'refund',
        'booking_cancelled' => 'refund',
    ];

    public function isConfigured(): bool
    {
        $c = config('services.analytics.ga4');

        return filled($c['measurement_id'] ?? null) && filled($c['api_secret'] ?? null);
    }

    /**
     * @param  array<string,mixed>  $params
     */
    public function send(string $eventType, ?string $clientId, array $params = []): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $name = self::MAP[$eventType] ?? $this->sanitizeName($eventType);
        $clientId = $clientId ?: (string) \Illuminate\Support\Str::uuid();

        // GA4 requires numeric value + currency together for revenue events.
        $eventParams = array_filter($params, fn ($v) => $v !== null && $v !== '');
        if (isset($eventParams['value']) && empty($eventParams['currency'])) {
            $eventParams['currency'] = 'INR';
        }

        try {
            $resp = Http::timeout(4)->post('https://www.google-analytics.com/mp/collect?' . http_build_query([
                'measurement_id' => config('services.analytics.ga4.measurement_id'),
                'api_secret' => config('services.analytics.ga4.api_secret'),
            ]), [
                'client_id' => $clientId,
                'events' => [[
                    'name' => $name,
                    'params' => $eventParams,
                ]],
            ]);

            if (! $resp->successful()) {
                Log::warning('GA4 MP send non-2xx', ['status' => $resp->status(), 'event' => $name]);
            }

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('GA4 MP send failed: ' . $e->getMessage(), ['event' => $name]);

            return false;
        }
    }

    private function sanitizeName(string $name): string
    {
        return substr(preg_replace('/[^a-z0-9_]/', '_', strtolower($name)), 0, 40);
    }
}
