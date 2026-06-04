<?php

use App\Http\Controllers\Web\Store\Account\AddressController as AccountAddressController;
use App\Http\Controllers\Web\Store\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Web\Store\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Web\Store\Account\PasswordController as AccountPasswordController;
use App\Http\Controllers\Web\Store\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Web\Store\Account\WishlistController as AccountWishlistController;
use App\Http\Controllers\Web\Store\CartController;
use App\Http\Controllers\Web\Store\CategoryController;
use App\Http\Controllers\Web\Store\CheckoutController;
use App\Http\Controllers\Web\Store\HomeController;
use App\Http\Controllers\Web\Store\JournalController;
use App\Http\Controllers\Web\Store\OrderTrackingController;
use App\Http\Controllers\Web\Store\PageController;
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

Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('store.pages.privacy');
Route::get('/terms-and-conditions', [PageController::class, 'termsAndConditions'])->name('store.pages.terms');
Route::get('/return-policy', [PageController::class, 'returnPolicy'])->name('store.pages.returns');
Route::get('/payment-policy', [PageController::class, 'paymentPolicy'])->name('store.pages.payment');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('store.pages.disclaimer');
Route::get('/shipping-policy', [PageController::class, 'shippingPolicy'])->name('store.pages.shipping');
Route::get('/about', [PageController::class, 'about'])->name('store.pages.about');
Route::get('/faq', [PageController::class, 'faq'])->name('store.pages.faq');
Route::get('/contact', [PageController::class, 'contact'])->name('store.pages.contact');

Route::get('/c/{slug}', CategoryController::class)->name('store.category');
Route::get('/p/{slug}', ProductController::class)->name('store.product');

Route::get('/journal', [JournalController::class, 'index'])->name('store.journal.index');
Route::get('/journal/{slug}', [JournalController::class, 'show'])->name('store.journal.show');

Route::middleware(['auth', 'customer-account'])->prefix('account')->name('store.account.')->group(function (): void {
    Route::get('/', AccountDashboardController::class)->name('dashboard');
    Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order_number}', [AccountOrderController::class, 'show'])->name('orders.show');
    Route::get('/profile', [AccountProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile', [AccountProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [AccountProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/password', [AccountPasswordController::class, 'edit'])->name('password');
    Route::put('/password', [AccountPasswordController::class, 'update'])->name('password.update');
    Route::get('/wishlist', [AccountWishlistController::class, 'index'])->name('wishlist');
    Route::post('/wishlist/merge', [AccountWishlistController::class, 'merge'])->name('wishlist.merge');
    Route::post('/wishlist/{product}', [AccountWishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [AccountWishlistController::class, 'destroy'])->name('wishlist.destroy');
    Route::get('/addresses', [AccountAddressController::class, 'index'])->name('addresses');
    Route::post('/addresses', [AccountAddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{address}', [AccountAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AccountAddressController::class, 'destroy'])->name('addresses.destroy');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/orders/{order_number}', function (string $order_number) {
        return redirect()->route('store.account.orders.show', ['order_number' => $order_number], 301);
    })->name('store.orders.show');
});
