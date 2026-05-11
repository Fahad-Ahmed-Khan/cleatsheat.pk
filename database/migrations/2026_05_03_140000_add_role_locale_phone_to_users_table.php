<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('customer')->after('password');
            $table->string('locale', 12)->default('en')->after('role');
            $table->string('phone', 32)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'locale', 'phone']);
        });
    }
};
