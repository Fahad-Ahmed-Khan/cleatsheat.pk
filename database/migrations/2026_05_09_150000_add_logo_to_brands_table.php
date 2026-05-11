<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->string('logo_path', 1024)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};

