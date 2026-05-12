<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->decimal('highest_customer_offer_seen', 12, 2)->nullable()->after('current_offer');
            $table->decimal('lowest_shop_offer_given', 12, 2)->nullable()->after('highest_customer_offer_seen');
            $table->decimal('customer_integrity_floor', 12, 2)->nullable()->after('lowest_shop_offer_given');
            $table->unsignedInteger('concession_count')->default(0)->after('customer_integrity_floor');
            $table->unsignedInteger('negotiation_turn_count')->default(0)->after('concession_count');
            $table->unsignedTinyInteger('resistance_score')->default(0)->after('negotiation_turn_count');
            $table->boolean('stubborn_customer_mode')->default(false)->after('resistance_score');
            $table->timestamp('last_shop_concession_at')->nullable()->after('stubborn_customer_mode');
        });
    }

    public function down(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'highest_customer_offer_seen',
                'lowest_shop_offer_given',
                'customer_integrity_floor',
                'concession_count',
                'negotiation_turn_count',
                'resistance_score',
                'stubborn_customer_mode',
                'last_shop_concession_at',
            ]);
        });
    }
};
