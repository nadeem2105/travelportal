<?php

namespace App\Services\Sms;

/**
 * Contract every concrete SMS provider driver implements. Kept deliberately
 * small: the manager owns normalisation, enable/disable and result shaping, the
 * driver only knows how to hand one text message to one gateway over HTTP.
 */
interface SmsDriver
{
    /**
     * Send a plain-text SMS to an E.164-ish number.
     *
     * @param  string  $to       Normalised recipient (e.g. +919876543210)
     * @param  string  $message  Message body (OTP text is pre-rendered)
     * @param  array   $context  Optional hints (e.g. ['otp' => '123456'] for OTP routes)
     * @return array{success:bool, provider:string, id:?string, error:?string, raw:mixed}
     */
    public function send(string $to, string $message, array $context = []): array;

    /** Provider slug, e.g. "fast2sms". */
    public function name(): string;

    /** True when the driver has the credentials it needs to send. */
    public function isConfigured(): bool;
}
