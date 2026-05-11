<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bargain_rules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('scope', 32);
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('max_discount_percent', 5, 2);
            $table->decimal('min_margin_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('bargain_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('guest_token')->nullable();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('state', 32);
            $table->decimal('list_price', 12, 2);
            $table->decimal('current_offer', 12, 2)->nullable();
            $table->decimal('accepted_price', 12, 2)->nullable();
            $table->string('checkout_token', 64)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('bargain_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bargain_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bargain_messages');
        Schema::dropIfExists('bargain_sessions');
        Schema::dropIfExists('bargain_rules');
    }
};
