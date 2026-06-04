<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route groups for @routes('store') / @routes('admin')
    |--------------------------------------------------------------------------
    |
    | See vendor/tightenco/ziggy README — groups are referenced by name in Blade,
    | not passed as ['only' => ...] (that causes "Array to string conversion").
    |
    */

    'groups' => [
        'store' => [
            'store.*',
            'admin.dashboard',
            'admin.login',
            'admin.logout',
            'login',
            'register',
            'logout',
            'password.*',
            'verification.*',
            'profile.edit',
            'dashboard',
        ],
    ],

];
