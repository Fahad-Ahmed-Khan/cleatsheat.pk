<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled_customer_notifications')->default(true);
            $table->boolean('enabled_admin_notifications')->default(true);
            $table->json('admin_recipients')->nullable();
            $table->timestamps();
        });

        DB::table('whatsapp_settings')->insert([
            'enabled_customer_notifications' => true,
            'enabled_admin_notifications' => true,
            'admin_recipients' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
