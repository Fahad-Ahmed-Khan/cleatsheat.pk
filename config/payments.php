<?php

return [

    'redirect_token_ttl_minutes' => 60,

    'gateways' => [

        'cod' => [
            'enabled' => true,
        ],

        'easypaisa' => [
            'enabled' => env('EASYPAISA_ENABLED', false),
            'submit_url' => env('EASYPAISA_SUBMIT_URL', 'https://easypaisa.com.pk/easypay/Index.jsf'),
            'merchant_id' => env('EASYPAISA_MERCHANT_ID', ''),
            'store_id' => env('EASYPAISA_STORE_ID', ''),
            'hash_key' => env('EASYPAISA_HASH_KEY', ''),
            'amount_in_paisa' => filter_var(env('EASYPAISA_AMOUNT_IN_PAISA', false), FILTER_VALIDATE_BOOL),
        ],

        'jazzcash' => [
            'enabled' => env('JAZZCASH_ENABLED', false),
            'submit_url' => env('JAZZCASH_SUBMIT_URL', 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'),
            'merchant_id' => env('JAZZCASH_MERCHANT_ID', ''),
            'password' => env('JAZZCASH_PASSWORD', ''),
            'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT', ''),
            'amount_in_paisa' => filter_var(env('JAZZCASH_AMOUNT_IN_PAISE', true), FILTER_VALIDATE_BOOL),
        ],

        'safepay' => [
            'enabled' => env('SAFEPAY_ENABLED', true),
            'environment' => env('SAFEPAY_ENVIRONMENT', 'sandbox'),
            'api_key' => env('SAFEPAY_API_KEY', ''),
            'merchant_api_key' => env('SAFEPAY_MERCHANT_API_KEY', ''),
            'webhook_secret' => env('SAFEPAY_WEBHOOK_SECRET', ''),
            'intent' => env('SAFEPAY_INTENT', 'CYBERSOURCE'),
            'currency' => env('SAFEPAY_CURRENCY', 'PKR'),
            'include_fees' => filter_var(env('SAFEPAY_INCLUDE_FEES', false), FILTER_VALIDATE_BOOL),
        ],
    ],

];
