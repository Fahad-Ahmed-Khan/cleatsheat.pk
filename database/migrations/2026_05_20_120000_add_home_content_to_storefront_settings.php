<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->json('home_testimonials')->nullable()->after('tiktok_pixel_id');
            $table->json('home_social_posts')->nullable();
            $table->text('home_seo_html')->nullable();
            $table->string('instagram_url', 512)->nullable();
            $table->string('tiktok_url', 512)->nullable();
            $table->boolean('newsletter_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->dropColumn([
                'home_testimonials',
                'home_social_posts',
                'home_seo_html',
                'instagram_url',
                'tiktok_url',
                'newsletter_enabled',
            ]);
        });
    }
};
