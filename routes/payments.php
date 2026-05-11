<?php

use App\Http\Controllers\Web\Store\PaymentCallbackController;
use App\Http\Controllers\Web\Store\PaymentRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/redirect/{gateway}/{token}', [PaymentRedirectController::class, 'form'])
    ->name('payments.redirect.form');

Route::match(['get', 'post'], '/payments/callback/{gateway}', [PaymentCallbackController::class, 'callback'])
    ->name('payments.callback');
