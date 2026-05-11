<?php

use App\Http\Controllers\Web\Store\CartController;
use App\Http\Controllers\Web\Store\CategoryController;
use App\Http\Controllers\Web\Store\CheckoutController;
use App\Http\Controllers\Web\Store\HomeController;
use App\Http\Controllers\Web\Store\JournalController;
use App\Http\Controllers\Web\Store\OrderController;
use App\Http\Controllers\Web\Store\OrderTrackingController;
use App\Http\Controllers\Web\Store\ProductController;
use App\Http\Controllers\Web\Store\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('store.home');
Route::get('/shop', ShopController::class)->name('store.shop');
Route::get('/cart', [CartController::class, 'index'])->name('store.cart');
Route::post('/cart', [CartController::class, 'store'])->name('store.cart.add');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('store.cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('store.cart.items.destroy');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('store.checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('store.checkout.store');
Route::get('/checkout/thank-you', [CheckoutController::class, 'thankYou'])->name('store.checkout.thankyou');

Route::get('/track-order', [OrderTrackingController::class, 'show'])->name('store.order-tracking');
Route::post('/track-order', [OrderTrackingController::class, 'lookup'])->name('store.order-tracking.lookup');

Route::get('/c/{slug}', CategoryController::class)->name('store.category');
Route::get('/p/{slug}', ProductController::class)->name('store.product');

Route::get('/journal', [JournalController::class, 'index'])->name('store.journal.index');
Route::get('/journal/{slug}', [JournalController::class, 'show'])->name('store.journal.show');

Route::middleware('auth')->group(function (): void {
    Route::get('/orders/{order_number}', [OrderController::class, 'show'])->name('store.orders.show');
});
