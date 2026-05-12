<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BargainController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\GuestOrderLookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\SizeChartController;
use App\Http\Controllers\Webhooks\EasypaisaWebhookController;
use App\Http\Controllers\Webhooks\JazzCashWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment webhooks (URL prefix /api from Laravel's api routing)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/easypaisa', EasypaisaWebhookController::class);
Route::post('/webhooks/jazzcash', JazzCashWebhookController::class);

/*
|--------------------------------------------------------------------------
| Versioned JSON API (prefix /api applied by framework)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/categories/{slug}/products', [CatalogController::class, 'categoryProducts']);
    Route::get('/products/{slug}', [CatalogController::class, 'product']);
    Route::get('/size-charts/{sizeChart}', [SizeChartController::class, 'show'])
        ->name('api.size-charts.show');

    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);

    Route::get('/checkout/payment-methods', [CheckoutController::class, 'paymentMethods']);
    Route::post('/checkout', [CheckoutController::class, 'store']);

    Route::post('/orders/lookup', GuestOrderLookupController::class);

    Route::prefix('bargain')->group(function (): void {
        Route::post('/sessions', [BargainController::class, 'start']);
        Route::get('/sessions/{session}', [BargainController::class, 'status']);
        Route::post('/sessions/{session}/messages', [BargainController::class, 'message']);
        Route::post('/sessions/{session}/accept', [BargainController::class, 'accept']);
        Route::post('/sessions/{session}/decline', [BargainController::class, 'decline']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'user']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order_number}', [OrderController::class, 'show']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    });
});
