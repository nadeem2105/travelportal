<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Website analytics & conversion tracking. PUBLIC IDs (measurement id, GTM
    | container, Meta pixel, Google Ads id) are safe to render in the browser and
    | are admin-editable via the Settings table (overlaid onto these keys). The
    | SECRETS (GA4 Measurement Protocol api_secret, Meta Conversions API access
    | token) are server-only and come from integration_settings (encrypted),
    | overlaid by App\Services\Settings\IntegrationSettings::applyAnalytics().
    | .env is a fallback for all of them.
    */
    'analytics' => [
        'enabled' => env('ANALYTICS_ENABLED', true),

        // First-party backend capture (always on; independent of third parties).
        'backend_enabled' => env('ANALYTICS_BACKEND_ENABLED', true),

        // Google Analytics 4 + Measurement Protocol (server-side).
        'ga4' => [
            'measurement_id' => env('GA_MEASUREMENT_ID'),      // public (G-XXXXXXX)
            'api_secret' => env('GA4_API_SECRET'),             // secret (server-side MP)
        ],

        // Google Tag Manager (public container id, GTM-XXXXXXX).
        'gtm' => [
            'container_id' => env('GTM_CONTAINER_ID'),
        ],

        // Meta Pixel (public) + Conversions API (secret access token).
        'meta' => [
            'pixel_id' => env('META_PIXEL_ID'),
            'capi_token' => env('META_CAPI_ACCESS_TOKEN'),
            'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
            'api_version' => env('META_CAPI_API_VERSION', 'v21.0'),
        ],

        // Google Ads conversion tracking (public conversion id + per-action labels).
        'google_ads' => [
            'conversion_id' => env('GOOGLE_ADS_ID'),                     // AW-XXXXXXXXX
            'booking_label' => env('GOOGLE_ADS_CONVERSION_BOOKING'),
            'lead_label' => env('GOOGLE_ADS_CONVERSION_LEAD'),
            'call_label' => env('GOOGLE_ADS_CONVERSION_CALL'),
            'whatsapp_label' => env('GOOGLE_ADS_CONVERSION_WHATSAPP'),
        ],

        // Search Console is read-only/verification — the meta verification tag.
        'search_console_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    /*
    | Google Ads API (campaign management). OAuth + developer token. Live calls are
    | gated by 'enabled'; never store secrets outside .env.
    */
    'google_ads' => [
        'enabled' => env('GOOGLE_ADS_ENABLED', false),
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'redirect_uri' => env('GOOGLE_ADS_REDIRECT_URI'),
        'api_version' => env('GOOGLE_ADS_API_VERSION', 'v17'),
    ],

    /*
    | Meta Marketing API (campaign management). OAuth app credentials.
    */
    'meta_ads' => [
        'enabled' => env('META_ADS_ENABLED', false),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'api_version' => env('META_ADS_API_VERSION', 'v21.0'),
        'redirect_uri' => env('META_ADS_REDIRECT_URI'),
        'webhook_verify_token' => env('META_ADS_WEBHOOK_VERIFY_TOKEN'),
    ],

    /*
    | AI providers for the marketing copilot / creative / analysis. Provider-agnostic:
    | the app talks to an AiProviderInterface; these just hold credentials + defaults.
    */
    'ai' => [
        'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
        'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'monthly_budget' => env('AI_MONTHLY_BUDGET'),          // optional cap (currency units)
        'enable_image' => env('AI_ENABLE_IMAGE', false),
        'enable_video' => env('AI_ENABLE_VIDEO', false),
        'enable_auto_optimize' => env('AI_ENABLE_AUTO_OPTIMIZE', false),
        // AI image generation (Ad Creative Studio). Provider reuses its own key
        // from providers.* below. Gated by enable_image.
        'image_provider' => env('AI_IMAGE_PROVIDER', 'openai'),
        'image_model' => env('AI_IMAGE_MODEL', 'gpt-image-1'),
        'providers' => [
            'openai' => ['api_key' => env('OPENAI_API_KEY'), 'model' => env('OPENAI_MODEL')],
            'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL')],
            'gemini' => ['api_key' => env('GEMINI_API_KEY'), 'model' => env('GEMINI_MODEL')],
            'groq' => ['api_key' => env('GROQ_API_KEY'), 'model' => env('GROQ_MODEL')],
            'openrouter' => ['api_key' => env('OPENROUTER_API_KEY'), 'model' => env('OPENROUTER_MODEL')],
        ],
    ],

    /*
    | Meta Lead Ads — leadgen webhook + Graph API lead fetch. Field map translates
    | the lead form's field names to CRM fields.
    */
    'meta_leads' => [
        'enabled' => env('META_LEADS_ENABLED', false),
        'api_version' => env('META_LEADS_API_VERSION', 'v21.0'),
        'verify_token' => env('META_LEADS_VERIFY_TOKEN'),
        'app_secret' => env('META_LEADS_APP_SECRET'),
        'page_access_token' => env('META_LEADS_PAGE_ACCESS_TOKEN'),
    ],

    /*
    | Google Ads Lead Form Extensions — webhook. The key must match the "Webhook
    | key" configured on the Google lead form.
    */
    'google_leads' => [
        'enabled' => env('GOOGLE_LEADS_ENABLED', false),
        'key' => env('GOOGLE_LEADS_KEY'),
    ],

    /*
    | WhatsApp Cloud API (Meta). All values come from the Meta App / WhatsApp
    | Business account. Keep the access token and app secret out of source
    | control — set them in .env only.
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'default_template_lang' => env('WHATSAPP_DEFAULT_TEMPLATE_LANG', 'en_US'),

        // Approved template names for transactional events. Leave blank to skip
        // WhatsApp for that event (email/SMS still fire). Body variable order is
        // documented in docs/WHATSAPP_SETUP.md.
        'templates' => [
            'booking_confirmed' => env('WHATSAPP_TEMPLATE_BOOKING_CONFIRMED'),
            'booking_invoice' => env('WHATSAPP_TEMPLATE_BOOKING_INVOICE'),
            'booking_cancelled' => env('WHATSAPP_TEMPLATE_BOOKING_CANCELLED'),
            'hotel_voucher' => env('WHATSAPP_TEMPLATE_HOTEL_VOUCHER'),
            'cab_voucher' => env('WHATSAPP_TEMPLATE_CAB_VOUCHER'),
            'quotation_sent' => env('WHATSAPP_TEMPLATE_QUOTATION_SENT'),
            // Trip Operations — proactive (template) messages, valid outside the 24h window.
            'trip_driver_assigned' => env('WHATSAPP_TEMPLATE_TRIP_DRIVER_ASSIGNED'),
            'trip_driver_sheet' => env('WHATSAPP_TEMPLATE_TRIP_DRIVER_SHEET'),
            'trip_customer_itinerary' => env('WHATSAPP_TEMPLATE_TRIP_CUSTOMER_ITINERARY'),
            'trip_driver_reminder' => env('WHATSAPP_TEMPLATE_TRIP_DRIVER_REMINDER'),
            'trip_tomorrow_plan' => env('WHATSAPP_TEMPLATE_TRIP_TOMORROW_PLAN'),
        ],

        // Attach the relevant PDF to the transactional template. ONLY enable when
        // the matching template was created in Meta WITH a document header —
        // otherwise Meta rejects the header parameter.
        'attach_documents' => [
            'quotation_sent' => env('WHATSAPP_ATTACH_QUOTATION_PDF', false),
            'booking_confirmed' => env('WHATSAPP_ATTACH_BOOKING_INVOICE', false),
            'booking_itinerary' => env('WHATSAPP_ATTACH_BOOKING_ITINERARY', true),
            'booking_invoice' => env('WHATSAPP_ATTACH_BOOKING_INVOICE_PDF', env('WHATSAPP_ATTACH_BOOKING_INVOICE', true)),
            'hotel_voucher' => env('WHATSAPP_ATTACH_HOTEL_VOUCHER', true),
            'cab_voucher' => env('WHATSAPP_ATTACH_CAB_VOUCHER', true),
            'trip_customer_itinerary' => env('WHATSAPP_ATTACH_TRIP_ITINERARY', true),
            'trip_driver_sheet' => env('WHATSAPP_ATTACH_TRIP_DRIVER_SHEET', true),
        ],

        /*
        | AI Travel Assistant — an LLM layer over inbound WhatsApp that answers
        | customer self-service questions (bookings, payments, documents) by
        | calling ownership-scoped backend tools, and helps with new inquiries.
        |
        | It NEVER replaces the deterministic keyword auto-replies: those run
        | first and always win. The assistant is only invoked when NO keyword
        | rule matched AND the bot is not paused (human handoff). Disabled by
        | default — enable only after an AI provider key is set. Falls back to a
        | safe no-op (NullAiProvider) when the provider is unconfigured, so it
        | never fabricates an answer.
        */
        'ai_assistant' => [
            'enabled' => env('WHATSAPP_AI_ASSISTANT_ENABLED', false),
            // Provider/model default to the shared services.ai config when blank.
            'provider' => env('WHATSAPP_AI_PROVIDER'),
            'model' => env('WHATSAPP_AI_MODEL'),
            // Max tool-call round-trips per inbound message (guards runaway loops / cost).
            'max_tool_iterations' => (int) env('WHATSAPP_AI_MAX_TOOL_ITERATIONS', 5),
            // How many prior messages of history to feed the model for context.
            'history_limit' => (int) env('WHATSAPP_AI_HISTORY_LIMIT', 12),
            // Require identity verification before exposing any private booking data.
            'require_verification' => env('WHATSAPP_AI_REQUIRE_VERIFICATION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social sign-in (mobile app)
    |--------------------------------------------------------------------------
    | Token-based social login: the app obtains an ID token from Google / Apple
    | natively and posts it to our API, which verifies it server-side. Only the
    | (public) client identifiers live here — there are no secrets to expose.
    */
    'google' => [
        // Accepted OAuth client IDs (comma-separated: iOS, Android, Web).
        'client_ids' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', '')))),

        // Web "Sign in with Google" (Google Identity Services) client ID. Public
        // by design — rendered in the browser. When set, the website shows a
        // "Continue with Google" button; the returned ID token is verified
        // server-side. No client secret is required for this flow.
        //
        // Both values are normally managed from the admin panel (Settings →
        // Social Login) and overlaid onto this config at boot; .env is a fallback.
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
        'web_enabled' => env('GOOGLE_WEB_ENABLED', false),
    ],

    'apple' => [
        // Accepted Apple Service/App bundle IDs (aud claim), comma-separated.
        'client_ids' => array_filter(array_map('trim', explode(',', (string) env('APPLE_CLIENT_IDS', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook sign-in (website — "Continue with Facebook")
    |--------------------------------------------------------------------------
    | Uses the Facebook JavaScript SDK: the browser obtains a short-lived user
    | access token and posts it to our API, which verifies it server-side via
    | the Graph API (debug_token, using an app access token = app_id|app_secret)
    | before trusting the returned profile.
    |
    | Unlike Google's GIS flow, this REQUIRES an app secret — it is a real
    | secret and must never be exposed in the browser. Both values are normally
    | managed from the admin panel (Settings → Social Login) and overlaid onto
    | this config at boot; .env is a fallback.
    */
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'enabled' => env('FACEBOOK_ENABLED', false),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
    ],

];
