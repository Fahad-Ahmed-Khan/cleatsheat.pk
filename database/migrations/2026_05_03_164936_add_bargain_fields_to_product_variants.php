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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('bargain_enabled')->default(false)->after('compare_at_price');
            $table->decimal('bargain_min_price', 12, 2)->nullable()->after('bargain_enabled');
            $table->decimal('bargain_max_discount_percent', 5, 2)->nullable()->after('bargain_min_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'bargain_enabled',
                'bargain_min_price',
                'bargain_max_discount_percent',
            ]);
        });
    }
};
