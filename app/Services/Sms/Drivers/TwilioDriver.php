<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Programmable Messaging (https://www.twilio.com). Uses HTTP Basic auth
 * (Account SID + Auth Token) against the Messages resource. Either a from-number
 * or a Messaging Service SID must be configured.
 *
 * HTTP-only; no SDK/Composer dependency.
 */
class TwilioDriver implements SmsDriver
{
    /** @param array $config config('sms.providers.twilio') */
    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'twilio';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['sid'] ?? null)
            && filled($this->config['auth_token'] ?? null)
            && (filled($this->config['from'] ?? null) || filled($this->config['messaging_service_sid'] ?? null));
    }

    public function send(string $to, string $message, array $context = []): array
    {
        $number = $this->e164($to);
        if ($number === null) {
            return $this->fail('Twilio requires an E.164 phone number.');
        }

        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . urlencode($this->config['sid']) . '/Messages.json';

        $form = ['To' => $number, 'Body' => $message];
        if (filled($this->config['messaging_service_sid'] ?? null)) {
            $form['MessagingServiceSid'] = $this->config['messaging_service_sid'];
        } else {
            $form['From'] = $this->config['from'];
        }

        try {
            $res = Http::asForm()
                ->withBasicAuth($this->config['sid'], $this->config['auth_token'])
                ->timeout(15)
                ->post($endpoint, $form);

            $body = $res->json() ?? [];

            if ($res->successful() && filled($body['sid'] ?? null)) {
                return ['success' => true, 'provider' => $this->name(), 'id' => $body['sid'], 'error' => null, 'raw' => $body];
            }

            return $this->fail((string) ($body['message'] ?? ('HTTP ' . $res->status())), $body);
        } catch (\Throwable $e) {
            Log::warning('Twilio send failed: ' . $e->getMessage());

            return $this->fail($e->getMessage());
        }
    }

    private function e164(string $to): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $to);
        if ($digits === '') {
            return null;
        }
        // Assume Indian numbers when a bare 10-digit number is supplied.
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return '+' . $digits;
    }

    private function fail(string $error, mixed $raw = null): array
    {
        return ['success' => false, 'provider' => $this->name(), 'id' => null, 'error' => $error, 'raw' => $raw];
    }
}
