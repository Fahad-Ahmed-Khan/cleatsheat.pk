<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();

            $table->string('site_name', 120)->nullable();
            $table->string('logo_url', 1024)->nullable();
            $table->string('logo_dark_url', 1024)->nullable();
            $table->string('favicon_url', 1024)->nullable();

            $table->string('primary_color', 7)->default('#dfff00');
            $table->string('secondary_color', 7)->default('#576500');
            $table->string('primary_foreground_color', 7)->default('#191e00');

            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_badge', 64)->nullable();
            $table->string('hero_image_url', 1024)->nullable();
            $table->string('hero_cta_label', 64)->nullable();
            $table->string('hero_cta_url', 1024)->nullable();

            $table->string('promo_banner_image_url', 1024)->nullable();
            $table->string('promo_banner_link_url', 1024)->nullable();
            $table->string('promo_banner_title', 120)->nullable();

            $table->string('default_meta_title')->nullable();
            $table->text('default_meta_description')->nullable();
            $table->string('default_og_image_url', 1024)->nullable();
            $table->string('twitter_site', 64)->nullable();

            $table->boolean('ga4_enabled')->default(false);
            $table->string('ga4_measurement_id', 64)->nullable();
            $table->boolean('gtm_enabled')->default(false);
            $table->string('gtm_container_id', 32)->nullable();
            $table->boolean('meta_pixel_enabled')->default(false);
            $table->string('meta_pixel_id', 32)->nullable();
            $table->boolean('tiktok_pixel_enabled')->default(false);
            $table->string('tiktok_pixel_id', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
