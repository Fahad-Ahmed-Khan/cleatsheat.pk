<?php

use App\Http\Controllers\Web\Store\PaymentCallbackController;
use App\Http\Controllers\Web\Store\PaymentRedirectController;
use App\Http\Controllers\Web\Webhooks\SafepayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/redirect/{gateway}/{token}', [PaymentRedirectController::class, 'form'])
    ->name('payments.redirect.form');

Route::match(['get', 'post'], '/payments/callback/{gateway}', [PaymentCallbackController::class, 'callback'])
    ->name('payments.callback');

Route::post('/webhooks/safepay', [SafepayWebhookController::class, 'handle'])
    ->name('webhooks.safepay');
