<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 16);
            $table->decimal('value', 12, 2);
            $table->decimal('min_cart_total', 12, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('guest_token')->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->string('currency', 8)->default('PKR');
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('size_label', 32);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id', 'size_label'], 'cart_variant_size_unique');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 32);
            $table->string('line1');
            $table->string('city');
            $table->string('area')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->uuid('guest_token')->nullable();
            $table->string('status', 32);
            $table->string('payment_status', 32);
            $table->string('payment_gateway', 32)->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('cod_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->json('shipping_address_snapshot');
            $table->json('billing_address_snapshot')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku');
            $table->string('size_label', 32);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('status', 32);
            $table->decimal('amount', 12, 2);
            $table->string('external_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->string('recipient', 128);
            $table->string('template_key', 64);
            $table->json('payload')->nullable();
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('coupons');
    }
};
