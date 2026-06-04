<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->unsignedInteger('hero_image_width')->nullable()->after('hero_image_url');
            $table->unsignedInteger('hero_image_height')->nullable()->after('hero_image_width');
            $table->json('hero_image_variants')->nullable()->after('hero_image_height');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->dropColumn(['hero_image_width', 'hero_image_height', 'hero_image_variants']);
        });
    }
};
