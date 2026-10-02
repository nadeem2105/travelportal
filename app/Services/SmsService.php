<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(protected ?SettingsService $settings = null)
    {
    }

    /**
     * Send an SMS/WhatsApp text message to the specified recipient.
     */
    public function send(string $phone, string $message, array $options = []): bool
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (empty($normalizedPhone)) {
            Log::warning('SMS dispatch skipped: invalid or empty phone number', ['raw_phone' => $phone]);
            return false;
        }

        $driver = $this->settings?->get('sms_driver', config('services.sms.driver', 'log')) ?? 'log';

        try {
            return match ($driver) {
                'webhook' => $this->sendViaWebhook($normalizedPhone, $message, $options),
                default => $this->sendViaLog($normalizedPhone, $message, $options),
            };
        } catch (\Throwable $e) {
            Log::error('SMS dispatch exception: ' . $e->getMessage(), [
                'phone' => $normalizedPhone,
                'driver' => $driver,
            ]);

            return false;
        }
    }

    /**
     * Development and audit driver: writes message payload to application log.
     */
    protected function sendViaLog(string $phone, string $message, array $options): bool
    {
        Log::info("SMS Notification [{$phone}]: {$message}", [
            'recipient' => $phone,
            'message' => $message,
            'options' => $options,
            'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
        ]);

        return true;
    }

    /**
     * Webhook driver: sends an HTTP POST request to third-party SMS/WhatsApp gateway.
     */
    protected function sendViaWebhook(string $phone, string $message, array $options): bool
    {
        $gatewayUrl = $this->settings?->get('sms_gateway_url', config('services.sms.url'));

        if (! $gatewayUrl) {
            Log::warning('SMS webhook gateway URL not configured; falling back to log.');
            return $this->sendViaLog($phone, $message, $options);
        }

        $response = Http::timeout(5)->post($gatewayUrl, [
            'to' => $phone,
            'message' => $message,
            'options' => $options,
        ]);

        return $response->successful();
    }

    /**
     * Standardize phone number formatting.
     */
    public function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // If local 10-digit Indian number without country code, prepend +91
        if (preg_match('/^[6-9]\d{9}$/', $cleaned)) {
            $cleaned = '+91' . $cleaned;
        }

        return $cleaned;
    }
}
