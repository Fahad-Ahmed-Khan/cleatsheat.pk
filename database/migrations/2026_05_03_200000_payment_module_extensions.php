<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('fallback_online_failed_to_cod')->default(true);
            $table->timestamps();
        });

        DB::table('payment_site_settings')->insert([
            'fallback_online_failed_to_cod' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('payment_method_configs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_code', 32)->unique();
            $table->boolean('enabled')->default(true);
            $table->string('customer_label');
            $table->decimal('fee_fixed', 12, 2)->default(0);
            $table->decimal('fee_percent', 8, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['gateway_code' => 'cod', 'customer_label' => 'Cash on delivery', 'sort_order' => 10],
            ['gateway_code' => 'easypaisa', 'customer_label' => 'Easypaisa', 'sort_order' => 20],
            ['gateway_code' => 'jazzcash', 'customer_label' => 'JazzCash', 'sort_order' => 30],
        ] as $row) {
            DB::table('payment_method_configs')->insert([
                ...$row,
                'enabled' => true,
                'fee_fixed' => 0,
                'fee_percent' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_code', 32);
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 32);
            $table->decimal('amount', 12, 2);
            $table->string('external_reference')->nullable()->index();
            $table->json('request_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 32);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_method_configs');
        Schema::dropIfExists('payment_site_settings');
    }
};
