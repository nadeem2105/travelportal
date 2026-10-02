<?php

namespace App\Services\Analytics\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Conversions API (server-side). Sends the same conversions the browser
 * Pixel sends, sharing the SAME event_id so Meta deduplicates the browser and
 * server events into one. PII used for matching (email, phone) is SHA-256 hashed
 * here — raw values are never transmitted.
 *
 * https://developers.facebook.com/docs/marketing-api/conversions-api
 */
class MetaConversionsApi
{
    /** Internal event slug → Meta standard event name. */
    private const MAP = [
        'booking_confirmed' => 'Purchase',
        'purchase' => 'Purchase',
        'generate_lead' => 'Lead',
        'lead_created' => 'Lead',
        'begin_checkout' => 'InitiateCheckout',
        'view_package' => 'ViewContent',
        'view_hotel' => 'ViewContent',
        'view_item' => 'ViewContent',
        'travel_search' => 'Search',
        'search_hotel' => 'Search',
        'search_flight' => 'Search',
        'search_cab' => 'Search',
    ];

    public function isConfigured(): bool
    {
        $c = config('services.analytics.meta');

        return filled($c['pixel_id'] ?? null) && filled($c['capi_token'] ?? null);
    }

    /**
     * @param  array<string,mixed>  $params     custom_data (value, currency, …)
     * @param  array<string,mixed>  $userData   raw match keys: email, phone, ip, user_agent, fbc, fbp
     */
    public function send(string $eventType, string $eventId, array $params = [], array $userData = [], ?string $eventSourceUrl = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $eventName = self::MAP[$eventType] ?? 'CustomEvent';
        $version = config('services.analytics.meta.api_version', 'v21.0');
        $pixelId = config('services.analytics.meta.pixel_id');

        $customData = array_filter([
            'currency' => $params['currency'] ?? null,
            'value' => isset($params['value']) ? (float) $params['value'] : null,
            'content_type' => $params['product_type'] ?? null,
            'content_ids' => isset($params['product_id']) ? [(string) $params['product_id']] : null,
            'order_id' => $params['transaction_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $payload = [
            'data' => [array_filter([
                'event_name' => $eventName,
                'event_time' => time(),
                'event_id' => $eventId,               // dedup key with the browser Pixel
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl,
                'user_data' => $this->hashUserData($userData),
                'custom_data' => $customData ?: null,
            ], fn ($v) => $v !== null)],
        ];

        if ($code = config('services.analytics.meta.test_event_code')) {
            $payload['test_event_code'] = $code;
        }

        try {
            $resp = Http::timeout(4)->post(
                "https://graph.facebook.com/{$version}/{$pixelId}/events?access_token=" . urlencode((string) config('services.analytics.meta.capi_token')),
                $payload
            );

            if (! $resp->successful()) {
                Log::warning('Meta CAPI send non-2xx', ['status' => $resp->status(), 'event' => $eventName, 'body' => $resp->body()]);
            }

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI send failed: ' . $e->getMessage(), ['event' => $eventName]);

            return false;
        }
    }

    /**
     * Hash the PII match keys per Meta's normalization rules (trim + lowercase,
     * digits-only for phone) then SHA-256. Non-PII signals (ip, ua, fbc, fbp)
     * are passed as-is (Meta requires them raw).
     *
     * @param  array<string,mixed>  $u
     * @return array<string,mixed>
     */
    private function hashUserData(array $u): array
    {
        $out = [];
        if (! empty($u['email'])) {
            $out['em'] = [hash('sha256', strtolower(trim((string) $u['email'])))];
        }
        if (! empty($u['phone'])) {
            $digits = preg_replace('/\D+/', '', (string) $u['phone']);
            if ($digits !== '') {
                $out['ph'] = [hash('sha256', $digits)];
            }
        }
        if (! empty($u['ip'])) {
            $out['client_ip_address'] = $u['ip'];
        }
        if (! empty($u['user_agent'])) {
            $out['client_user_agent'] = $u['user_agent'];
        }
        if (! empty($u['fbc'])) {
            $out['fbc'] = $u['fbc'];
        }
        if (! empty($u['fbp'])) {
            $out['fbp'] = $u['fbp'];
        }

        return $out;
    }
}
