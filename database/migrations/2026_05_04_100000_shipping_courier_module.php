<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipments')->where('status', 'label_created')->update(['status' => 'booked']);
        DB::table('shipments')->where('status', 'exception')->update(['status' => 'failed']);

        Schema::table('couriers', function (Blueprint $table) {
            $table->string('adapter', 32)->default('generic')->after('code');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('preferred_courier_id')->nullable()->after('coupon_id')->constrained('couriers')->nullOnDelete();
            $table->string('courier_assignment', 16)->default('auto')->after('preferred_courier_id');
        });

        Schema::create('courier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('credentials');
            $table->string('service_code', 64)->nullable();
            $table->boolean('cod_allowed')->default(true);
            $table->json('city_restrictions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->string('courier_assignment_default', 16)->default('auto');
            $table->boolean('auto_book_on_payment_confirmed')->default(false);
            $table->boolean('auto_book_cod_orders')->default(false);
            $table->unsignedSmallInteger('tracking_sync_interval_minutes')->default(30);
            $table->json('sender_snapshot')->nullable();
            $table->decimal('default_weight_kg', 8, 3)->default(1.0);
            $table->decimal('default_length_cm', 8, 2)->default(30);
            $table->decimal('default_width_cm', 8, 2)->default(20);
            $table->decimal('default_height_cm', 8, 2)->default(15);
            $table->timestamps();
        });

        DB::table('shipping_settings')->insert([
            'default_courier_id' => null,
            'courier_assignment_default' => 'auto',
            'auto_book_on_payment_confirmed' => false,
            'auto_book_cod_orders' => false,
            'tracking_sync_interval_minutes' => 30,
            'sender_snapshot' => json_encode([
                'business_name' => 'Tryino',
                'contact_name' => 'Warehouse',
                'phone' => '',
                'email' => '',
                'line1' => '',
                'city' => 'Karachi',
            ], JSON_THROW_ON_ERROR),
            'default_weight_kg' => 1,
            'default_length_cm' => 30,
            'default_width_cm' => 20,
            'default_height_cm' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('courier_account_id')->nullable()->after('courier_id')->constrained()->nullOnDelete();
            $table->string('booking_reference')->nullable()->after('tracking_number');
            $table->decimal('cod_amount', 12, 2)->nullable()->after('meta');
            $table->decimal('shipping_charges', 12, 2)->nullable()->after('cod_amount');
            $table->decimal('weight_kg', 10, 3)->nullable()->after('shipping_charges');
            $table->decimal('length_cm', 10, 2)->nullable()->after('weight_kg');
            $table->decimal('width_cm', 10, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 10, 2)->nullable()->after('width_cm');
            $table->json('sender_snapshot')->nullable()->after('height_cm');
            $table->json('receiver_snapshot')->nullable()->after('sender_snapshot');
            $table->string('label_url')->nullable()->after('receiver_snapshot');
            $table->string('invoice_url')->nullable()->after('label_url');
            $table->json('last_booking_response')->nullable()->after('invoice_url');
            $table->json('last_tracking_response')->nullable()->after('last_booking_response');
            $table->timestamp('booked_at')->nullable()->after('shipped_at');
            $table->timestamp('delivered_at')->nullable()->after('booked_at');
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->nullable();
            $table->string('description')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('courier_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16);
            $table->string('endpoint', 512)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_api_logs');
        Schema::dropIfExists('shipment_events');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['courier_account_id']);
            $table->dropColumn([
                'courier_account_id', 'booking_reference', 'cod_amount', 'shipping_charges',
                'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'sender_snapshot', 'receiver_snapshot',
                'label_url', 'invoice_url', 'last_booking_response', 'last_tracking_response',
                'booked_at', 'delivered_at', 'failed_at',
            ]);
        });

        Schema::dropIfExists('shipping_settings');

        Schema::dropIfExists('courier_accounts');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['preferred_courier_id']);
            $table->dropColumn(['preferred_courier_id', 'courier_assignment']);
        });

        Schema::table('couriers', function (Blueprint $table) {
            $table->dropColumn(['adapter', 'sort_order']);
        });
    }
};
