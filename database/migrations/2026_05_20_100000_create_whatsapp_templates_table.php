<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('audience', 16)->default('customer'); // customer | admin | rider
            $table->string('category', 32)->default('transactional'); // transactional | marketing | utility
            $table->text('body');
            $table->string('cloud_template_name')->nullable();
            $table->string('cloud_template_language', 16)->default('en_US');
            $table->boolean('has_buttons')->default(false);
            $table->json('button_payloads')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
