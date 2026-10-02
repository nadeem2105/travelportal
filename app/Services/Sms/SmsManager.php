<?php

namespace App\Services\Sms;

use App\Models\Otp;
use App\Services\Sms\Drivers\Fast2SmsDriver;
use App\Services\Sms\Drivers\Msg91Driver;
use App\Services\Sms\Drivers\TwilioDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Central SMS entry point. Resolves the active provider from config('sms')
 * (which the admin panel overlays via IntegrationSettings), sends plain texts,
 * and owns the phone-OTP lifecycle (generate → store in `otps` → verify).
 *
 * Purely additive: this does not touch the legacy App\Services\SmsService
 * (log/webhook), which other code may still use. New mobile-app phone auth
 * goes through here.
 */
class SmsManager
{
    /** @var array<string,class-string<SmsDriver>> */
    private const DRIVERS = [
        'fast2sms' => Fast2SmsDriver::class,
        'msg91' => Msg91Driver::class,
        'twilio' => TwilioDriver::class,
    ];

    public function enabled(): bool
    {
        return (bool) config('sms.enabled', false);
    }

    public function activeProvider(): string
    {
        return (string) config('sms.default', 'fast2sms');
    }

    /** Build the driver for the active (or given) provider. */
    public function driver(?string $provider = null): SmsDriver
    {
        $provider = $provider ?: $this->activeProvider();
        $class = self::DRIVERS[$provider] ?? Fast2SmsDriver::class;
        $config = (array) config("sms.providers.{$provider}", []);

        return new $class($config);
    }

    public function isConfigured(?string $provider = null): bool
    {
        return $this->driver($provider)->isConfigured();
    }

    /**
     * Send a raw text message. Returns the driver result array. A no-op
     * (success=false, error="disabled") when SMS is turned off globally.
     */
    public function send(string $to, string $message, array $context = []): array
    {
        if (! $this->enabled()) {
            return ['success' => false, 'provider' => $this->activeProvider(), 'id' => null, 'error' => 'SMS is disabled.', 'raw' => null];
        }

        $driver = $this->driver();
        if (! $driver->isConfigured()) {
            return ['success' => false, 'provider' => $driver->name(), 'id' => null, 'error' => 'SMS provider is not configured.', 'raw' => null];
        }

        return $driver->send($to, $message, $context);
    }

    // --- OTP lifecycle ----------------------------------------------------

    /**
     * Generate + store + dispatch a phone OTP.
     *
     * @return array{success:bool, error:?string, retry_after:?int, expires_at:?string}
     */
    public function sendOtp(string $phone, string $purpose = 'phone_login'): array
    {
        $identifier = $this->normalize($phone);
        if ($identifier === null) {
            return ['success' => false, 'error' => 'Please enter a valid phone number.', 'retry_after' => null, 'expires_at' => null];
        }

        // Resend cooldown: block if a fresh, unconsumed OTP was just issued.
        $cooldown = (int) config('sms.otp.resend_cooldown_seconds', 60);
        $recent = Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($recent && $recent->created_at && $recent->created_at->diffInSeconds(now()) < $cooldown) {
            $wait = $cooldown - $recent->created_at->diffInSeconds(now());

            return ['success' => false, 'error' => "Please wait {$wait}s before requesting another code.", 'retry_after' => $wait, 'expires_at' => null];
        }

        $length = (int) config('sms.otp.length', 6);
        $ttl = (int) config('sms.otp.ttl_minutes', 10);
        $code = $this->randomCode($length);
        $expiresAt = now()->addMinutes($ttl);

        // Invalidate prior live OTPs for this identifier+purpose.
        Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $otp = Otp::create([
            'identifier' => $identifier,
            'code' => $code,
            'purpose' => $purpose,
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ]);

        $message = str_replace(
            ['{code}', '{ttl}'],
            [$code, (string) $ttl],
            (string) config('sms.otp.message', 'Your verification code is {code}.')
        );

        $result = $this->send($identifier, $message, ['otp' => $code]);

        if (! $result['success']) {
            // Roll back the stored OTP so a failed send does not consume the cooldown.
            $otp->delete();
            Log::warning('OTP dispatch failed', ['provider' => $result['provider'] ?? null, 'error' => $result['error'] ?? null]);

            return ['success' => false, 'error' => $result['error'] ?? 'Could not send the verification code.', 'retry_after' => null, 'expires_at' => null];
        }

        return ['success' => true, 'error' => null, 'retry_after' => $cooldown, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /**
     * Verify a submitted OTP. Consumes it on success.
     *
     * @return array{success:bool, error:?string}
     */
    public function verifyOtp(string $phone, string $code, string $purpose = 'phone_login'): array
    {
        $identifier = $this->normalize($phone);
        if ($identifier === null) {
            return ['success' => false, 'error' => 'Please enter a valid phone number.'];
        }

        $otp = Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['success' => false, 'error' => 'No active code. Please request a new one.'];
        }

        if ($otp->isExpired()) {
            return ['success' => false, 'error' => 'This code has expired. Please request a new one.'];
        }

        $max = (int) config('sms.otp.max_attempts', 5);
        if ($otp->attempts >= $max) {
            $otp->forceFill(['consumed_at' => now()])->save();

            return ['success' => false, 'error' => 'Too many attempts. Please request a new code.'];
        }

        if (! hash_equals((string) $otp->code, trim($code))) {
            $otp->increment('attempts');

            return ['success' => false, 'error' => 'Incorrect code. Please try again.'];
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return ['success' => true, 'error' => null];
    }

    /** Normalise to a canonical identifier (+<country><number>), or null. */
    public function normalize(string $phone): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);
        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }
        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            $digits = '91' . $digits;
        }

        return '+' . $digits;
    }

    private function randomCode(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
