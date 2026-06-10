<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author_name', 120);
            $table->string('city', 80)->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('email', 255)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reviews');
    }
};
