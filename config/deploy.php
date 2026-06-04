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

    /*
    |--------------------------------------------------------------------------
    | Deploy email notifications
    |--------------------------------------------------------------------------
    |
    | Uses Laravel mail (MAIL_* in .env). Hostinger deploy.sh calls
    | `php artisan deploy:notify` on success/failure. GitHub Actions can
    | email the same inbox via workflow secrets (see deploy-hostinger.yml).
    |
    | DEPLOY_NOTIFY_EMAIL may be a single address or comma-separated list.
    |
    */

    'notify_enabled' => (bool) env('DEPLOY_NOTIFY_ENABLED', false),

    'notify_emails' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DEPLOY_NOTIFY_EMAIL', '')),
    ))),

];
