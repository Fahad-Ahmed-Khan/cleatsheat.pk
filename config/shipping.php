<?php

return [

    'sandbox' => env('SHIPPING_SANDBOX', false),

    /**
     * Base URLs per adapter — replace with live endpoints from each courier’s integration docs.
     */
    'endpoints' => [
        'leopards' => env('LEOPARDS_API_BASE', 'https://api.leopardscourier.com.pk/api/v1'),
        'mp' => env('MP_API_BASE', 'https://api.mulphilogistics.com'),
        // Host only (e.g. https://api.postex.pk). If you paste .../services/integration/api from docs, it is normalized automatically.
        'postex' => env('POSTEX_API_BASE', 'https://api.postex.pk'),
        'trax' => [
            'testing' => env('TRAX_API_BASE_TESTING', 'https://app.sonic.pk'),
            'live' => env('TRAX_API_BASE_LIVE', 'https://sonic.pk'),
        ],
        'runcourier' => env('RUN_COURIER_API_BASE', 'https://portal.runcourier.com'),
        'tcs' => env('TCS_API_BASE', 'https://api.tcscourier.com'),
    ],

    'webhook' => [
        'global_secret' => env('SHIPPING_WEBHOOK_SECRET'),
    ],

];
