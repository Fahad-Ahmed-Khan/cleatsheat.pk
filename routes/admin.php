<?php

use App\Http\Controllers\Web\Admin\AbandonedCartAdminController;
use App\Http\Controllers\Web\Admin\BargainSessionAdminController;
use App\Http\Controllers\Web\Admin\BrandAdminController;
use App\Http\Controllers\Web\Admin\CategoryAdminController;
use App\Http\Controllers\Web\Admin\ColorAdminController;
use App\Http\Controllers\Web\Admin\ContentPostAdminController;
use App\Http\Controllers\Web\Admin\CouponAdminController;
use App\Http\Controllers\Web\Admin\CourierAdminController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\MarketingSettingsAdminController;
use App\Http\Controllers\Web\Admin\OrderAdminController;
use App\Http\Controllers\Web\Admin\OrderBulkAdminController;
use App\Http\Controllers\Web\Admin\OrderAdjustmentsAdminController;
use App\Http\Controllers\Web\Admin\OrderReturnsAdminController;
use App\Http\Controllers\Web\Admin\PaymentSettingsAdminController;
use App\Http\Controllers\Web\Admin\ProductAdminController;
use App\Http\Controllers\Web\Admin\ShippingSettingsAdminController;
use App\Http\Controllers\Web\Admin\SizeChartAdminController;
use App\Http\Controllers\Web\Admin\StorefrontAssistantSettingsAdminController;
use App\Http\Controllers\Web\Admin\WhatsAppSettingsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('brands', BrandAdminController::class)->except(['show']);
    Route::resource('categories', CategoryAdminController::class)->except(['show']);
    Route::resource('colors', ColorAdminController::class)->except(['show']);
    Route::resource('products', ProductAdminController::class)->except(['show']);
    Route::get('/products/{product}', [ProductAdminController::class, 'show'])->name('products.show');
    Route::patch('/products/{product}/toggle-active', [ProductAdminController::class, 'toggleActive'])
        ->name('products.toggle-active');
    Route::get('/products/{product}/variants', [ProductAdminController::class, 'variants'])
        ->name('products.variants');

    Route::resource('size-charts', SizeChartAdminController::class)
        ->parameters(['size-charts' => 'size_chart'])
        ->except(['show']);

    Route::get('/bargaining', [BargainSessionAdminController::class, 'index'])->name('bargaining.index');
    Route::get('/bargaining/{bargain_session}', [BargainSessionAdminController::class, 'show'])->name('bargaining.show');

    Route::get('/abandoned-carts', [AbandonedCartAdminController::class, 'index'])->name('abandoned-carts.index');

    Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [OrderAdminController::class, 'update'])->name('orders.update');
    Route::post('/orders/{order}/shipment/book', [OrderAdminController::class, 'book'])
        ->name('orders.shipment.book');
    Route::post('/orders/{order}/shipment/sync-tracking', [OrderAdminController::class, 'syncTracking'])
        ->name('orders.shipment.sync-tracking');
    Route::get('/orders/{order}/shipments/{shipment}/postex/invoice', [OrderAdminController::class, 'postExInvoice'])
        ->name('orders.shipment.postex.invoice');
    Route::get('/orders/{order}/postex/load-sheet', [OrderAdminController::class, 'postExLoadSheet'])
        ->name('orders.postex.load-sheet');
    Route::post('/orders/{order}/shipments/{shipment}/postex/cancel', [OrderAdminController::class, 'postExCancel'])
        ->name('orders.shipment.postex.cancel');

    Route::post('/orders/bulk/book', [OrderBulkAdminController::class, 'book'])
        ->name('orders.bulk.book');
    Route::post('/orders/bulk/sync-tracking', [OrderBulkAdminController::class, 'syncTracking'])
        ->name('orders.bulk.sync-tracking');
    Route::patch('/orders/bulk/update-status', [OrderBulkAdminController::class, 'updateStatus'])
        ->name('orders.bulk.update-status');
    Route::post('/orders/bulk/print/labels', [OrderBulkAdminController::class, 'printLabels'])
        ->name('orders.bulk.print-labels');
    Route::post('/orders/bulk/print/packing-slips', [OrderBulkAdminController::class, 'printPackingSlips'])
        ->name('orders.bulk.print-packing-slips');

    Route::post('/orders/{order}/admin-discount', [OrderAdjustmentsAdminController::class, 'setAdminDiscount'])
        ->name('orders.admin-discount.set');

    Route::post('/orders/{order}/returns', [OrderReturnsAdminController::class, 'store'])
        ->name('orders.returns.store');

    Route::get('/couriers', [CourierAdminController::class, 'index'])->name('couriers.index');
    Route::get('/coupons', [CouponAdminController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [CouponAdminController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [CouponAdminController::class, 'store'])->name('coupons.store');

    Route::get('/payment-settings', [PaymentSettingsAdminController::class, 'edit'])->name('payment-settings.edit');
    Route::patch('/payment-settings', [PaymentSettingsAdminController::class, 'update'])->name('payment-settings.update');

    Route::get('/shipping-settings', [ShippingSettingsAdminController::class, 'edit'])->name('shipping-settings.edit');
    Route::patch('/shipping-settings', [ShippingSettingsAdminController::class, 'update'])->name('shipping-settings.update');

    Route::get('/whatsapp-settings', [WhatsAppSettingsAdminController::class, 'edit'])->name('whatsapp-settings.edit');
    Route::patch('/whatsapp-settings', [WhatsAppSettingsAdminController::class, 'update'])->name('whatsapp-settings.update');

    Route::get('/marketing-settings', [MarketingSettingsAdminController::class, 'edit'])->name('marketing-settings.edit');
    Route::patch('/marketing-settings', [MarketingSettingsAdminController::class, 'update'])->name('marketing-settings.update');

    Route::get('/storefront-assistant', [StorefrontAssistantSettingsAdminController::class, 'edit'])
        ->name('storefront-assistant.edit');
    Route::patch('/storefront-assistant', [StorefrontAssistantSettingsAdminController::class, 'update'])
        ->name('storefront-assistant.update');

    Route::resource('content-posts', ContentPostAdminController::class)->except(['show']);
});
