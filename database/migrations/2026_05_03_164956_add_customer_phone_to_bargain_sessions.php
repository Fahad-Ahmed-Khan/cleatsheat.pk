<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->string('customer_phone', 32)->nullable()->after('guest_token');
            $table->string('customer_key', 128)->nullable()->after('customer_phone');

            $table->index(['customer_key', 'product_variant_id', 'state'], 'bargain_sessions_customer_variant_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->dropIndex('bargain_sessions_customer_variant_state_idx');
            $table->dropColumn(['customer_phone', 'customer_key']);
        });
    }
};
