<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'awaiting_confirmation')) {
                $table->boolean('awaiting_confirmation')->default(false)->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'confirmation_sent_at')) {
                $table->timestamp('confirmation_sent_at')->nullable()->after('awaiting_confirmation');
            }
            if (! Schema::hasColumn('orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmation_sent_at');
            }
            if (! Schema::hasColumn('orders', 'confirmation_channel')) {
                $table->string('confirmation_channel', 32)->nullable()->after('confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'awaiting_confirmation',
                'confirmation_sent_at',
                'confirmed_at',
                'confirmation_channel',
            ] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
