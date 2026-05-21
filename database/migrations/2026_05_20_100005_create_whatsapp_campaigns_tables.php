<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('whatsapp_campaigns', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();

            $table->json('segment')->nullable();

            $table->string('status', 24)->default('draft');

            $table->timestamp('scheduled_for')->nullable();

            $table->unsignedInteger('sent_count')->default(0);

            $table->unsignedInteger('failed_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {

            $table->id();

            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('phone', 32);

            $table->string('name')->nullable();

            $table->string('status', 24)->default('pending');

            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'status']);

        });

        if (Schema::hasColumn('notification_logs', 'campaign_id')) {

            Schema::table('notification_logs', function (Blueprint $table) {

                $table->foreign('campaign_id')->references('id')->on('whatsapp_campaigns')->nullOnDelete();

            });

        }

    }

    public function down(): void
    {

        Schema::table('notification_logs', function (Blueprint $table) {

            if (Schema::hasColumn('notification_logs', 'campaign_id')) {

                $table->dropForeign(['campaign_id']);

            }

        });

        Schema::dropIfExists('whatsapp_campaign_recipients');

        Schema::dropIfExists('whatsapp_campaigns');

    }

};
