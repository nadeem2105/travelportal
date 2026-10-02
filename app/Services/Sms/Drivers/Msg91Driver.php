<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MSG91 (https://msg91.com). When a template_id is configured we use the OTP
 * flow endpoint (recommended for verification); otherwise we fall back to the
 * generic SMS flow with a raw message body.
 *
 * HTTP-only; no SDK/Composer dependency.
 */
class Msg91Driver implements SmsDriver
{
    private const OTP_ENDPOINT = 'https://control.msg91.com/api/v5/otp';
    private const FLOW_ENDPOINT = 'https://control.msg91.com/api/v5/flow';

    /** @param array $config config('sms.providers.msg91') */
    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'msg91';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['auth_key'] ?? null);
    }

    public function send(string $to, string $message, array $context = []): array
    {
        $number = $this->e164Digits($to);
        if ($number === null) {
            return $this->fail('MSG91 requires a valid phone number.');
        }

        $templateId = $this->config['template_id'] ?? null;

        try {
            if (filled($templateId) && filled($context['otp'] ?? null)) {
                // Native OTP endpoint — MSG91 renders the code into the template.
                $res = Http::withHeaders(['authkey' => $this->config['auth_key']])
                    ->timeout(15)
                    ->post(self::OTP_ENDPOINT, array_filter([
                        'template_id' => $templateId,
                        'mobile' => $number,
                        'otp' => (string) $context['otp'],
                        'sender' => $this->config['sender_id'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''));
            } elseif (filled($templateId)) {
                // Flow endpoint with a single "OTP"/"VAR1" style variable.
                $res = Http::withHeaders(['authkey' => $this->config['auth_key'], 'Content-Type' => 'application/json'])
                    ->timeout(15)
                    ->post(self::FLOW_ENDPOINT, [
                        'template_id' => $templateId,
                        'sender' => $this->config['sender_id'] ?? null,
                        'recipients' => [['mobiles' => $number, 'var1' => $message]],
                    ]);
            } else {
                return $this->fail('MSG91 requires a template_id to send messages.');
            }

            $body = $res->json() ?? [];

            if ($res->successful() && strtolower((string) ($body['type'] ?? '')) !== 'error') {
                return ['success' => true, 'provider' => $this->name(), 'id' => $body['request_id'] ?? ($body['message'] ?? null), 'error' => null, 'raw' => $body];
            }

            return $this->fail((string) ($body['message'] ?? ('HTTP ' . $res->status())), $body);
        } catch (\Throwable $e) {
            Log::warning('MSG91 send failed: ' . $e->getMessage());

            return $this->fail($e->getMessage());
        }
    }

    private function e164Digits(string $to): ?string
    {
        $digits = preg_replace('/\D+/', '', $to);
        if (strlen($digits) === 10) {
            $digits = ($this->config['default_country'] ?? '91') . $digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    private function fail(string $error, mixed $raw = null): array
    {
        return ['success' => false, 'provider' => $this->name(), 'id' => null, 'error' => $error, 'raw' => $raw];
    }
}
