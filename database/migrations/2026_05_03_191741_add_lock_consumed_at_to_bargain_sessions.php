<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->timestamp('lock_consumed_at')->nullable()->after('checkout_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bargain_sessions', function (Blueprint $table) {
            $table->dropColumn('lock_consumed_at');
        });
    }
};
