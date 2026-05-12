<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cart_items', 'pricing_key')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->string('pricing_key', 64)->default('regular')->after('size_label');
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {
            // In MySQL, foreign keys require an index on the referencing column.
            // The existing unique key may currently satisfy that requirement.
            // Add a dedicated index first so we can safely replace the unique constraint.
            $table->index('cart_id', 'cart_items_cart_id_idx');

            $table->dropUnique('cart_variant_size_unique');
            $table->unique(['cart_id', 'product_variant_id', 'size_label', 'pricing_key'], 'cart_variant_size_pricing_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_variant_size_pricing_unique');
            $table->unique(['cart_id', 'product_variant_id', 'size_label'], 'cart_variant_size_unique');
            $table->dropColumn('pricing_key');
            $table->dropIndex('cart_items_cart_id_idx');
        });
    }
};
