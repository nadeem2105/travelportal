<?php

return [

    /*
    | Default country calling code used when normalizing local phone numbers.
    */
    'default_country_code' => env('CRM_DEFAULT_COUNTRY_CODE', '91'),

    /*
    | Lead assignment strategy: round_robin | source | none
    | - round_robin: distributes across active staff who hold `leads.view`
    | - source: uses source_rules below, falling back to round_robin
    */
    'assignment' => [
        'strategy' => env('CRM_ASSIGNMENT_STRATEGY', 'round_robin'),
        // map lead_source slug => admin role slug (team) to receive those leads
        'source_rules' => [
            // 'b2b' => 'b2b-team',
            // 'corporate' => 'corporate-team',
        ],
    ],

    /*
    | Configurable lead scoring. Each rule adds its points when the signal is
    | present. Admins can later override these in settings; the engine reads
    | from here by default so nothing is hardcoded in the service.
    */
    'scoring' => [
        'has_email' => 5,
        'has_budget' => 15,
        'package_interest' => 20,
        'travel_within_30_days' => 15,
        'whatsapp_conversation' => 10,
        'quotation_requested' => 20,
        'checkout_started' => 25,
        'payment_initiated' => 50,
        'booking_confirmed' => 100,
        // per-source bonus
        'source_bonus' => [
            'referral' => 10,
            'existing-customer' => 15,
        ],
    ],

    /*
    | Attribution: query keys captured from the landing request and persisted
    | to the lead. First-touch values are never overwritten once set.
    */
    'attribution' => [
        'session_key' => 'crm_attribution',
        'params' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'],
        'cookie_days' => 90,
    ],
];
