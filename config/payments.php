<?php

return [

    'redirect_token_ttl_minutes' => 60,

    'gateways' => [

        'cod' => [
            'enabled' => true,
        ],

        'easypaisa' => [
            'enabled' => env('EASYPAISA_ENABLED', true),
            'submit_url' => env('EASYPAISA_SUBMIT_URL', 'https://easypaisa.com.pk/easypay/Index.jsf'),
            'merchant_id' => env('EASYPAISA_MERCHANT_ID', ''),
            'store_id' => env('EASYPAISA_STORE_ID', ''),
            'hash_key' => env('EASYPAISA_HASH_KEY', ''),
            'amount_in_paisa' => filter_var(env('EASYPAISA_AMOUNT_IN_PAISA', false), FILTER_VALIDATE_BOOL),
        ],

        'jazzcash' => [
            'enabled' => env('JAZZCASH_ENABLED', true),
            'submit_url' => env('JAZZCASH_SUBMIT_URL', 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'),
            'merchant_id' => env('JAZZCASH_MERCHANT_ID', ''),
            'password' => env('JAZZCASH_PASSWORD', ''),
            'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT', ''),
            'amount_in_paisa' => filter_var(env('JAZZCASH_AMOUNT_IN_PAISE', true), FILTER_VALIDATE_BOOL),
        ],
    ],

];
