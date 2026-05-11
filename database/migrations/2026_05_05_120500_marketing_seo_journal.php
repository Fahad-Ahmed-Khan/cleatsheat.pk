<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('home_meta_title')->nullable();
            $table->text('home_meta_description')->nullable();
            $table->string('default_og_image_url', 1024)->nullable();
            $table->string('twitter_site', 64)->nullable();

            $table->boolean('ga4_enabled')->default(false);
            $table->string('ga4_measurement_id', 32)->nullable();

            $table->boolean('meta_pixel_enabled')->default(false);
            $table->string('meta_pixel_id', 32)->nullable();

            $table->boolean('tiktok_pixel_enabled')->default(false);
            $table->string('tiktok_pixel_id', 64)->nullable();

            $table->string('robots_mode', 32)->default('allow_all');
            $table->text('robots_custom')->nullable();

            $table->timestamps();
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('pillar_keyword', 128)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('og_image_url', 1024)->nullable()->after('meta_description');
            $table->text('intro_html')->nullable()->after('og_image_url');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('size_info')->nullable()->after('fit_notes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_info');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['og_image_url', 'intro_html']);
        });

        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('marketing_settings');
    }
};
