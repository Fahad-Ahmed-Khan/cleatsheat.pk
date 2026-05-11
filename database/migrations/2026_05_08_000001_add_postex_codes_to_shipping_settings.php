<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->string('postex_pickup_address_code', 64)->nullable()->after('sender_snapshot');
            $table->string('postex_store_address_code', 64)->nullable()->after('postex_pickup_address_code');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn(['postex_pickup_address_code', 'postex_store_address_code']);
        });
    }
};

