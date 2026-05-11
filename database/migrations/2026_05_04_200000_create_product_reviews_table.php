<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('author_display', 120);
            $table->unsignedTinyInteger('rating');
            /** small | true_to_size | runs_large — fit perception */
            $table->string('fit_feedback', 24)->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
