<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fast2SMS (https://www.fast2sms.com) — India-focused. Supports two routes:
 *  - "dlt": transactional DLT route (requires approved sender + message_id)
 *  - "otp": Fast2SMS built-in OTP route (sends "<code> is your verification code")
 *
 * HTTP-only; no SDK/Composer dependency.
 */
class Fast2SmsDriver implements SmsDriver
{
    private const ENDPOINT = 'https://www.fast2sms.com/dev/bulkV2';

    /** @param array $config config('sms.providers.fast2sms') */
    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'fast2sms';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    public function send(string $to, string $message, array $context = []): array
    {
        // Fast2SMS expects local 10-digit Indian numbers (no + / country code).
        $number = $this->localNumber($to);
        if ($number === null) {
            return $this->fail('Fast2SMS supports Indian 10-digit numbers only.');
        }

        $route = $this->config['route'] ?? 'dlt';

        try {
            if ($route === 'otp' && filled($context['otp'] ?? null)) {
                // Fast2SMS built-in OTP route: "<code> is your OTP".
                $payload = [
                    'route' => 'otp',
                    'variables_values' => (string) $context['otp'],
                    'numbers' => $number,
                ];
            } else {
                // DLT transactional route. Field mapping (per Fast2SMS bulkV2):
                //   sender_id        -> approved DLT header (e.g. "JKCHNR")
                //   message          -> approved DLT template/message ID (e.g. "165676")
                //   variables_values -> pipe-separated values that fill the template
                //                       ({#var#}); for a 1-variable OTP template this
                //                       is just the code.
                $payload = array_filter([
                    'route' => 'dlt',
                    'sender_id' => $this->config['sender_id'] ?? null,
                    'message' => $this->config['message_id'] ?? null,
                    'variables_values' => isset($context['otp']) ? (string) $context['otp'] : null,
                    'numbers' => $number,
                ], fn ($v) => $v !== null);

                // If no DLT template id is configured, fall back to the quick
                // transactional "q" route with a raw message body.
                if (empty($payload['message'])) {
                    $payload = [
                        'route' => 'q',
                        'message' => $message,
                        'numbers' => $number,
                    ];
                }
            }

            // Fast2SMS accepts the API key as an `authorization` header with a
            // JSON body — this is the exact format confirmed working in the panel.
            $res = Http::withHeaders([
                'authorization' => (string) ($this->config['api_key'] ?? ''),
            ])->acceptJson()->asJson()->timeout(15)->post(self::ENDPOINT, $payload);

            $body = $res->json() ?? [];

            if ($res->successful() && ($body['return'] ?? false) === true) {
                $id = is_array($body['request_id'] ?? null) ? null : ($body['request_id'] ?? null);

                return ['success' => true, 'provider' => $this->name(), 'id' => $id, 'error' => null, 'raw' => $body];
            }

            $err = $body['message'] ?? ('HTTP ' . $res->status());
            $err = is_array($err) ? implode('; ', $err) : $err;

            return $this->fail((string) $err, $body);
        } catch (\Throwable $e) {
            Log::warning('Fast2SMS send failed: ' . $e->getMessage());

            return $this->fail($e->getMessage());
        }
    }

    private function localNumber(string $to): ?string
    {
        $digits = preg_replace('/\D+/', '', $to);
        // Strip a leading 91 country code if present.
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    private function fail(string $error, mixed $raw = null): array
    {
        return ['success' => false, 'provider' => $this->name(), 'id' => null, 'error' => $error, 'raw' => $raw];
    }
}
