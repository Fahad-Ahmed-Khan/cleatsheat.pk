<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->json('meta_parameter_order')->nullable()->after('cloud_template_language');
            $table->string('meta_sync_status', 32)->nullable()->after('meta_parameter_order');
            $table->text('meta_sync_error')->nullable()->after('meta_sync_status');
            $table->timestamp('meta_last_synced_at')->nullable()->after('meta_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn([
                'meta_parameter_order',
                'meta_sync_status',
                'meta_sync_error',
                'meta_last_synced_at',
            ]);
        });
    }
};
