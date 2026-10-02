<?php

namespace App\Jobs;

use App\Services\Analytics\Providers\Ga4MeasurementProtocol;
use App\Services\Analytics\Providers\MetaConversionsApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Forwards one server-side conversion to GA4 (Measurement Protocol) and Meta
 * (Conversions API) off the request cycle. The shared $eventId is the dedup key
 * so a browser event + this server event count once on each platform. Both
 * providers are best-effort and self-guarding (no-op when unconfigured), so a
 * missing key never fails the booking flow. Retries are bounded.
 */
class ForwardAnalyticsEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string,mixed>  $params    custom/event params (value, currency, product_*)
     * @param  array<string,mixed>  $userData  raw match keys (email, phone) — hashed in the CAPI provider
     */
    public function __construct(
        public string $eventType,
        public string $eventId,
        public array $params = [],
        public array $userData = [],
        public ?string $clientId = null,
        public ?string $eventSourceUrl = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $fbc = null,
        public ?string $fbp = null,
    ) {}

    public function handle(Ga4MeasurementProtocol $ga4, MetaConversionsApi $meta): void
    {
        if (! config('services.analytics.enabled', true)) {
            return;
        }

        try {
            $ga4->send($this->eventType, $this->clientId, $this->params);
        } catch (\Throwable $e) {
            Log::warning('ForwardAnalyticsEvent GA4 failed: ' . $e->getMessage(), ['event' => $this->eventType]);
        }

        try {
            $meta->send(
                $this->eventType,
                $this->eventId,
                $this->params,
                array_merge($this->userData, array_filter([
                    'ip' => $this->ip,
                    'user_agent' => $this->userAgent,
                    'fbc' => $this->fbc,
                    'fbp' => $this->fbp,
                ])),
                $this->eventSourceUrl,
            );
        } catch (\Throwable $e) {
            Log::warning('ForwardAnalyticsEvent Meta CAPI failed: ' . $e->getMessage(), ['event' => $this->eventType]);
        }
    }
}
