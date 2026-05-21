<?php

use App\Http\Controllers\Web\Admin\AbandonedCartAdminController;
use App\Http\Controllers\Web\Admin\BargainSessionAdminController;
use App\Http\Controllers\Web\Admin\BrandAdminController;
use App\Http\Controllers\Web\Admin\CategoryAdminController;
use App\Http\Controllers\Web\Admin\ColorAdminController;
use App\Http\Controllers\Web\Admin\ContentPostAdminController;
use App\Http\Controllers\Web\Admin\CouponAdminController;
use App\Http\Controllers\Web\Admin\CourierAdminController;
use App\Http\Controllers\Web\Admin\CourierRiderAdminController;
use App\Http\Controllers\Web\Admin\CourierSettlementAdminController;
use App\Http\Controllers\Web\Admin\CustomerAdminController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\LogisticsTimelineAdminController;
use App\Http\Controllers\Web\Admin\LowStockAdminController;
use App\Http\Controllers\Web\Admin\MarketingSettingsAdminController;
use App\Http\Controllers\Web\Admin\NotificationLogAdminController;
use App\Http\Controllers\Web\Admin\OrderAdjustmentsAdminController;
use App\Http\Controllers\Web\Admin\OrderAdminController;
use App\Http\Controllers\Web\Admin\OrderBulkAdminController;
use App\Http\Controllers\Web\Admin\OrderReturnAdminController;
use App\Http\Controllers\Web\Admin\OrderReturnsAdminController;
use App\Http\Controllers\Web\Admin\PaymentSettingsAdminController;
use App\Http\Controllers\Web\Admin\PickupAdminController;
use App\Http\Controllers\Web\Admin\ProductAdminController;
use App\Http\Controllers\Web\Admin\ShippingSettingsAdminController;
use App\Http\Controllers\Web\Admin\SizeChartAdminController;
use App\Http\Controllers\Web\Admin\StorefrontAssistantSettingsAdminController;
use App\Http\Controllers\Web\Admin\StorefrontSettingsAdminController;
use App\Http\Controllers\Web\Admin\WhatsAppCampaignAdminController;
use App\Http\Controllers\Web\Admin\WhatsAppInboxAdminController;
use App\Http\Controllers\Web\Admin\WhatsAppManualMessageController;
use App\Http\Controllers\Web\Admin\WhatsAppSettingsAdminController;
use App\Http\Controllers\Web\Admin\WhatsAppTemplateAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('brands', BrandAdminController::class)->except(['show']);
    Route::resource('categories', CategoryAdminController::class)->except(['show']);
    Route::resource('colors', ColorAdminController::class)->except(['show']);
    Route::get('/products/export', [ProductAdminController::class, 'export'])->name('products.export');
    Route::post('/products/import', [ProductAdminController::class, 'import'])->name('products.import');
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
    Route::post('/abandoned-carts/{cart}/whatsapp', [AbandonedCartAdminController::class, 'sendReminder'])
        ->name('abandoned-carts.whatsapp.send');
    Route::post('/abandoned-carts/whatsapp/bulk', [AbandonedCartAdminController::class, 'bulkSendReminder'])
        ->name('abandoned-carts.whatsapp.bulk');

    Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [OrderAdminController::class, 'export'])->name('orders.export');
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
    Route::patch('/orders/bulk/update-payment-status', [OrderBulkAdminController::class, 'updatePaymentStatus'])
        ->name('orders.bulk.update-payment-status');
    Route::post('/orders/bulk/print/labels', [OrderBulkAdminController::class, 'printLabels'])
        ->name('orders.bulk.print-labels');
    Route::post('/orders/bulk/print/packing-slips', [OrderBulkAdminController::class, 'printPackingSlips'])
        ->name('orders.bulk.print-packing-slips');

    Route::post('/orders/{order}/admin-discount', [OrderAdjustmentsAdminController::class, 'setAdminDiscount'])
        ->name('orders.admin-discount.set');

    Route::post('/orders/{order}/returns', [OrderReturnsAdminController::class, 'store'])
        ->name('orders.returns.store');

    Route::get('/returns', [OrderReturnAdminController::class, 'index'])->name('returns.index');
    Route::get('/returns/{orderReturn}', [OrderReturnAdminController::class, 'show'])->name('returns.show');

    Route::get('/inventory/low-stock', [LowStockAdminController::class, 'index'])->name('inventory.low-stock');

    Route::get('/customers', [CustomerAdminController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerAdminController::class, 'show'])->name('customers.show');

    Route::get('/finance/courier-settlements', [CourierSettlementAdminController::class, 'index'])
        ->name('finance.courier-settlements');

    Route::get('/logistics/timeline', [LogisticsTimelineAdminController::class, 'index'])
        ->name('logistics.timeline');

    Route::get('/couriers', [CourierAdminController::class, 'index'])->name('couriers.index');

    Route::resource('riders', CourierRiderAdminController::class)
        ->parameters(['riders' => 'rider'])
        ->except(['show']);
    Route::post('/riders/{rider}/send-test', [CourierRiderAdminController::class, 'sendTest'])
        ->name('riders.send-test');

    Route::get('/pickups', [PickupAdminController::class, 'index'])->name('pickups.index');
    Route::post('/pickups/send', [PickupAdminController::class, 'send'])->name('pickups.send');

    Route::get('/coupons', [CouponAdminController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [CouponAdminController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [CouponAdminController::class, 'store'])->name('coupons.store');

    Route::get('/payment-settings', [PaymentSettingsAdminController::class, 'edit'])->name('payment-settings.edit');
    Route::patch('/payment-settings', [PaymentSettingsAdminController::class, 'update'])->name('payment-settings.update');

    Route::get('/shipping-settings', [ShippingSettingsAdminController::class, 'edit'])->name('shipping-settings.edit');
    Route::patch('/shipping-settings', [ShippingSettingsAdminController::class, 'update'])->name('shipping-settings.update');

    Route::get('/whatsapp-settings', [WhatsAppSettingsAdminController::class, 'edit'])->name('whatsapp-settings.edit');
    Route::patch('/whatsapp-settings', [WhatsAppSettingsAdminController::class, 'update'])->name('whatsapp-settings.update');

    Route::resource('whatsapp-templates', WhatsAppTemplateAdminController::class)
        ->parameters(['whatsapp-templates' => 'whatsapp_template'])
        ->except(['show']);
    Route::post('/whatsapp-templates/{whatsapp_template}/send-test', [WhatsAppTemplateAdminController::class, 'sendTest'])
        ->name('whatsapp-templates.send-test');

    Route::resource('whatsapp-campaigns', WhatsAppCampaignAdminController::class)
        ->parameters(['whatsapp-campaigns' => 'whatsapp_campaign']);
    Route::post('/whatsapp-campaigns/{whatsapp_campaign}/send', [WhatsAppCampaignAdminController::class, 'sendNow'])
        ->name('whatsapp-campaigns.send');
    Route::post('/whatsapp-campaigns/{whatsapp_campaign}/cancel', [WhatsAppCampaignAdminController::class, 'cancel'])
        ->name('whatsapp-campaigns.cancel');
    Route::post('/whatsapp-campaigns/preview-count', [WhatsAppCampaignAdminController::class, 'previewCount'])
        ->name('whatsapp-campaigns.preview-count');

    Route::get('/whatsapp-inbox', [WhatsAppInboxAdminController::class, 'index'])->name('whatsapp-inbox.index');

    Route::post('/orders/{order}/whatsapp', [WhatsAppManualMessageController::class, 'sendOrder'])
        ->name('orders.whatsapp.send');
    Route::post('/customers/{customer}/whatsapp', [WhatsAppManualMessageController::class, 'sendCustomer'])
        ->name('customers.whatsapp.send');
    Route::post('/riders/{rider}/whatsapp', [WhatsAppManualMessageController::class, 'sendRider'])
        ->name('riders.whatsapp.send');

    Route::get('/marketing-settings', [MarketingSettingsAdminController::class, 'edit'])->name('marketing-settings.edit');
    Route::patch('/marketing-settings', [MarketingSettingsAdminController::class, 'update'])->name('marketing-settings.update');

    Route::get('/storefront-settings', [StorefrontSettingsAdminController::class, 'edit'])->name('storefront-settings.edit');
    Route::patch('/storefront-settings', [StorefrontSettingsAdminController::class, 'update'])->name('storefront-settings.update');

    Route::get('/storefront-assistant', [StorefrontAssistantSettingsAdminController::class, 'edit'])
        ->name('storefront-assistant.edit');
    Route::patch('/storefront-assistant', [StorefrontAssistantSettingsAdminController::class, 'update'])
        ->name('storefront-assistant.update');

    Route::resource('content-posts', ContentPostAdminController::class)->except(['show']);

    Route::get('/notifications', [NotificationLogAdminController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notificationLog}/retry', [NotificationLogAdminController::class, 'retry'])
        ->name('notifications.retry');
});
