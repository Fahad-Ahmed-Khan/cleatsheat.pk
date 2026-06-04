<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub deploy webhook
    |--------------------------------------------------------------------------
    |
    | When CI pushes the `production` branch, GitHub sends a signed POST to
    | /webhooks/github/deploy. The server pulls that branch and runs deploy.sh.
    |
    | GitHub → Settings → Webhooks → Add webhook:
    |   Payload URL: https://your-domain.com/webhooks/github/deploy
    |   Content type: application/json
    |   Secret: same as DEPLOY_WEBHOOK_SECRET
    |   Events: Just the push event
    |
    */

    'enabled' => (bool) env('DEPLOY_WEBHOOK_ENABLED', false),

    'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET', ''),

    'branch' => env('DEPLOY_BRANCH', 'production'),

];
