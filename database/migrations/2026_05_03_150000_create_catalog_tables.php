<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('hex', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->string('canonical_url', 512)->nullable();
            $table->string('fit_guidance', 32);
            $table->string('gender', 32);
            $table->string('shoe_type', 32);
            $table->text('fit_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 1024);
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->restrictOnDelete();
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('variant_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('size_label', 32);
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->unique(['product_variant_id', 'size_label'], 'variant_size_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_sizes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
