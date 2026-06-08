<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_query_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 120);
            $table->unsignedInteger('results_count')->default(0);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['query', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_logs');
    }
};
