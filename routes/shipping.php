<?php

use App\Http\Controllers\Web\Shipping\ShippingWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shipping/{courier}', [ShippingWebhookController::class, 'handle'])
    ->name('webhooks.shipping');
