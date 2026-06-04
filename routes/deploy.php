<?php

use App\Http\Controllers\Web\Webhooks\GitHubDeployWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/github/deploy', [GitHubDeployWebhookController::class, 'handle'])
    ->name('webhooks.github.deploy');
