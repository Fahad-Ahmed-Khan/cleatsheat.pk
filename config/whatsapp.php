<?php

return [
    /** Display name used in WhatsApp template footers and Meta sync padding. */
    'brand_name' => env('WHATSAPP_BRAND_NAME', 'CleatSheat.pk'),

    /**
     * Preferred: official WhatsApp Cloud API.
     *
     * - token: Permanent access token
     * - phone_number_id: WhatsApp Business phone number id
     * - api_version: Graph API version
     */
    'cloud' => [
        'enabled' => env('WHATSAPP_CLOUD_ENABLED', false),
        'token' => env('WHATSAPP_CLOUD_TOKEN'),
        'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
        /** Optional — resolved automatically from phone_number_id when empty */
        'waba_id' => env('WHATSAPP_CLOUD_WABA_ID'),
        'api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),
        /**
         * Webhook signature secret (App Secret from the Meta App Dashboard) and
         * verify token used by Meta's webhook subscription handshake.
         *
         * The verify token can also be set per-tenant via Admin → WhatsApp settings,
         * which takes precedence over this env value at runtime.
         */
        'app_secret' => env('WHATSAPP_CLOUD_APP_SECRET'),
        'webhook_verify_token' => env('WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN'),
        /**
         * Meta-approved template names (each body should define 4 variables:
         * {{1}} customer name, {{2}} order id/number, {{3}} total, {{4}} short status).
         * Leave name empty to send a plain text message instead (dev / fallback).
         */
        'templates' => [
            'order_placed' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_PLACED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
            'payment_received' => [
                'name' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIVED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
            'order_packed' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_PACKED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
            'order_shipped' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_SHIPPED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
            'order_delivered' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_DELIVERED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
            'order_canceled' => [
                'name' => env('WHATSAPP_TEMPLATE_ORDER_CANCELED', ''),
                'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
            ],
        ],
    ],

    /**
     * Fallback: custom bridge (your own service) that accepts {to, body, ...}.
     */
    'bridge' => [
        'enabled' => env('WHATSAPP_BRIDGE_ENABLED', true),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'api_url' => env('WHATSAPP_API_URL'),
        'from_number' => env('WHATSAPP_FROM_NUMBER'),
    ],

    /**
     * Retry policy for queued sends.
     */
    'retry' => [
        'tries' => (int) env('WHATSAPP_SEND_TRIES', 5),
        'backoff_seconds' => (int) env('WHATSAPP_SEND_BACKOFF', 60),
        'timeout_seconds' => (int) env('WHATSAPP_SEND_TIMEOUT', 30),
    ],

    /**
     * Meta Graph HTTP transport. On CLI, "stream" is used automatically when unset
     * (avoids libcurl segfaults on some shared hosts). Set WHATSAPP_HTTP_HANDLER=curl
     * to force curl, or stream explicitly for web + CLI.
     */
    'http' => [
        'handler' => env('WHATSAPP_HTTP_HANDLER'),
        'force_ipv4' => env('WHATSAPP_HTTP_FORCE_IPV4', true),
        'curl_http1' => env('WHATSAPP_HTTP_CURL_HTTP1', true),
        'retries' => (int) env('WHATSAPP_HTTP_RETRIES', 2),
    ],

    'queue' => env('WHATSAPP_QUEUE'),
];
