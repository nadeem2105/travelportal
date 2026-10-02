<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Sending
    |--------------------------------------------------------------------------
    |
    | Transactional SMS (phone-OTP for the mobile app, alerts, etc.). Like the
    | WhatsApp / AI blocks, these values are a pure .env fallback: the admin
    | panel stores the effective provider + credentials (encrypted) in the
    | integration_settings table and App\Services\Settings\IntegrationSettings
    | overlays them onto this config at boot. So editing the panel is enough;
    | .env is only used before anything is configured.
    |
    */

    // Master on/off. When false, SmsManager::send() is a no-op that returns false.
    'enabled' => (bool) env('SMS_ENABLED', false),

    // Active driver: fast2sms | msg91 | twilio
    'default' => env('SMS_PROVIDER', 'fast2sms'),

    // OTP behaviour (shared across drivers).
    'otp' => [
        'length' => (int) env('SMS_OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('SMS_OTP_TTL', 10),
        'max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('SMS_OTP_RESEND_COOLDOWN', 60),
        // {code} is replaced with the generated OTP. Keep it aligned with your
        // approved DLT template on the provider side.
        'message' => env('SMS_OTP_MESSAGE', 'Your Leemroz Travels verification code is {code}. It is valid for {ttl} minutes. Do not share it with anyone.'),
    ],

    'providers' => [

        'fast2sms' => [
            'api_key' => env('FAST2SMS_API_KEY'),
            // 'dlt' (transactional, requires DLT template) or 'otp' (Fast2SMS OTP route).
            'route' => env('FAST2SMS_ROUTE', 'dlt'),
            'sender_id' => env('FAST2SMS_SENDER_ID'),
            // Required for the DLT route.
            'message_id' => env('FAST2SMS_MESSAGE_ID'),
        ],

        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'sender_id' => env('MSG91_SENDER_ID'),
            // Approved flow / template id used for OTP.
            'template_id' => env('MSG91_TEMPLATE_ID'),
            // Default country code prefix when a number is supplied without one.
            'default_country' => env('MSG91_DEFAULT_COUNTRY', '91'),
        ],

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            // Either a from-number (E.164, e.g. +15551234567) or a Messaging Service SID.
            'from' => env('TWILIO_FROM'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        ],

    ],

];
