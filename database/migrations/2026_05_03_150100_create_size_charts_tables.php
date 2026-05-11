<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gender', 32)->nullable();
            $table->string('shoe_type', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('size_chart_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label', 32);
            $table->json('measurements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_chart_rows');
        Schema::dropIfExists('size_charts');
    }
};
