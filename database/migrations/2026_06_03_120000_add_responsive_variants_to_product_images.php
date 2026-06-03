<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('alt');
            $table->unsignedInteger('height')->nullable()->after('width');
            // List of generated responsive WebP variants: [{"w":320,"path":"products/x-320.webp"}, ...]
            $table->json('variants')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'variants']);
        });
    }
};
