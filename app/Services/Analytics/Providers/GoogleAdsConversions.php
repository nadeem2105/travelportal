<?php

namespace App\Services\Analytics\Providers;

/**
 * Google Ads conversion readiness.
 *
 * Google Ads conversions are primarily reported CLIENT-SIDE via gtag
 * (send_to = AW-<id>/<label>) — that path is wired in the frontend analytics
 * service using the conversion labels below. TRUE server-side upload (Enhanced
 * Conversions / offline gclid import) requires the Google Ads API with OAuth,
 * which is intentionally out of scope here; this class provides the readiness
 * layer: the event → conversion-label mapping plus the persisted GCLID lookup so
 * that integration can be dropped in later without touching call sites.
 */
class GoogleAdsConversions
{
    public function isConfigured(): bool
    {
        return filled(config('services.analytics.google_ads.conversion_id'));
    }

    /** The gtag send_to token for an event, or null when not configured. */
    public function sendTo(string $eventType): ?string
    {
        $id = config('services.analytics.google_ads.conversion_id');
        if (! $id) {
            return null;
        }

        $label = match ($eventType) {
            'booking_confirmed', 'purchase', 'payment_success' => config('services.analytics.google_ads.booking_label'),
            'generate_lead', 'lead_created' => config('services.analytics.google_ads.lead_label'),
            'phone_click' => config('services.analytics.google_ads.call_label'),
            'whatsapp_click' => config('services.analytics.google_ads.whatsapp_label'),
            default => null,
        };

        return $label ? "{$id}/{$label}" : $id;
    }

    /**
     * Conversion descriptor for the browser (gtag) — consumed by the frontend
     * analytics service when reporting a Google Ads conversion.
     *
     * @return array<string,mixed>|null
     */
    public function conversionPayload(string $eventType, ?float $value = null, ?string $transactionId = null): ?array
    {
        $sendTo = $this->sendTo($eventType);
        if (! $sendTo) {
            return null;
        }

        return array_filter([
            'send_to' => $sendTo,
            'value' => $value,
            'currency' => $value !== null ? 'INR' : null,
            'transaction_id' => $transactionId,
        ], fn ($v) => $v !== null);
    }
}
