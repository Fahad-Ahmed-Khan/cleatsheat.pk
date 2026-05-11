<?php

return [

    'sandbox' => env('SHIPPING_SANDBOX', true),

    /**
     * Base URLs per adapter — replace with live endpoints from each courier’s integration docs.
     */
    'endpoints' => [
        'leopards' => env('LEOPARDS_API_BASE', 'https://api.leopardscourier.com.pk/api/v1'),
        'mp' => env('MP_API_BASE', 'https://api.mulphilogistics.com'),
        'postex' => env('POSTEX_API_BASE', 'https://api.postex.pk'),
        'runcourier' => env('RUN_COURIER_API_BASE', 'https://portal.runcourier.com'),
        'tcs' => env('TCS_API_BASE', 'https://api.tcscourier.com'),
    ],

    'webhook' => [
        'global_secret' => env('SHIPPING_WEBHOOK_SECRET'),
    ],

];
