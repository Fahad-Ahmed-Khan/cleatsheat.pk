<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'search_text')) {
                $table->text('search_text')->nullable();
            }
            $table->index(['is_active', 'slug']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText('search_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->dropFullText(['search_text']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'slug']);
            $table->dropColumn('search_text');
        });
    }
};
