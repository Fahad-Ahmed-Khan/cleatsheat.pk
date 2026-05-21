<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_settings', 'enabled_cod_confirmation')) {
                $table->boolean('enabled_cod_confirmation')->default(true)->after('enabled_admin_notifications');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'enabled_shipment_status_customer_alerts')) {
                $table->boolean('enabled_shipment_status_customer_alerts')->default(true)->after('enabled_cod_confirmation');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'enabled_pickup_notices')) {
                $table->boolean('enabled_pickup_notices')->default(true)->after('enabled_shipment_status_customer_alerts');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'pickup_notice_time')) {
                $table->string('pickup_notice_time', 5)->default('11:00')->after('enabled_pickup_notices');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'cloud_webhook_verify_token')) {
                $table->string('cloud_webhook_verify_token', 128)->nullable()->after('pickup_notice_time');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'marketing_opt_out_keyword')) {
                $table->string('marketing_opt_out_keyword', 32)->default('STOP')->after('cloud_webhook_verify_token');
            }
            if (! Schema::hasColumn('whatsapp_settings', 'promotional_throttle_per_minute')) {
                $table->unsignedSmallInteger('promotional_throttle_per_minute')->default(20)->after('marketing_opt_out_keyword');
            }
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_logs', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('notification_logs', 'wa_message_id')) {
                $table->string('wa_message_id')->nullable()->after('template_key');
                $table->index('wa_message_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'whatsapp_opted_out')) {
                $table->boolean('whatsapp_opted_out')->default(false)->after('phone');
            }
            if (! Schema::hasColumn('users', 'whatsapp_opted_out_at')) {
                $table->timestamp('whatsapp_opted_out_at')->nullable()->after('whatsapp_opted_out');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'whatsapp_opted_out')) {
                $table->dropColumn(['whatsapp_opted_out', 'whatsapp_opted_out_at']);
            }
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            if (Schema::hasColumn('notification_logs', 'wa_message_id')) {
                $table->dropIndex(['wa_message_id']);
                $table->dropColumn('wa_message_id');
            }
            if (Schema::hasColumn('notification_logs', 'campaign_id')) {
                $table->dropColumn('campaign_id');
            }
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            foreach ([
                'enabled_cod_confirmation',
                'enabled_shipment_status_customer_alerts',
                'enabled_pickup_notices',
                'pickup_notice_time',
                'cloud_webhook_verify_token',
                'marketing_opt_out_keyword',
                'promotional_throttle_per_minute',
            ] as $col) {
                if (Schema::hasColumn('whatsapp_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
