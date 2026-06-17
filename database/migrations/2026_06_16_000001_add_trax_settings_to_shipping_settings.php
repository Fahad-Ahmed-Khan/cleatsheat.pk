<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->unsignedInteger('trax_pickup_address_id')->nullable()->after('postex_store_address_code');
            $table->unsignedSmallInteger('trax_shipping_mode_id')->default(1)->after('trax_pickup_address_id');
            $table->unsignedSmallInteger('trax_charges_mode_id')->default(4)->after('trax_shipping_mode_id');
            $table->unsignedSmallInteger('trax_item_product_type_id')->default(24)->after('trax_charges_mode_id');
            $table->unsignedSmallInteger('trax_delivery_type_id')->default(1)->after('trax_item_product_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn([
                'trax_pickup_address_id',
                'trax_shipping_mode_id',
                'trax_charges_mode_id',
                'trax_item_product_type_id',
                'trax_delivery_type_id',
            ]);
        });
    }
};

