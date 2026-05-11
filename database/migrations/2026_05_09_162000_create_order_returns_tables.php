<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 255);
            $table->boolean('restock')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained('order_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['order_return_id', 'order_item_id'], 'return_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};

