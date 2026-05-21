<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('alt_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'is_active']);
        });

        Schema::create('pickup_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('couriers_riders')->nullOnDelete();
            $table->date('dispatch_date');
            $table->unsignedInteger('parcel_count')->default(0);
            $table->decimal('cod_total', 12, 2)->nullable();
            $table->json('shipment_ids')->nullable();
            $table->json('tracking_numbers')->nullable();
            $table->string('sent_via', 16)->default('auto'); // auto | manual
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->string('status', 16)->default('sent'); // sent | failed | skipped
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'dispatch_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_dispatches');
        Schema::dropIfExists('couriers_riders');
    }
};
