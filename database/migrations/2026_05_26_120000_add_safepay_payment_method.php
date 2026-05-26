<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('payment_method_configs')->updateOrInsert(
            ['gateway_code' => 'safepay'],
            [
                'enabled' => true,
                'customer_label' => 'Pay online (Card / Wallet)',
                'fee_fixed' => 0,
                'fee_percent' => 0,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        // Safepay supersedes the bespoke wallet hand-offs. Disable the legacy
        // rows so they no longer show up on the storefront; admins can still
        // re-enable them from Payment Settings if a fallback is needed.
        DB::table('payment_method_configs')
            ->whereIn('gateway_code', ['easypaisa', 'jazzcash'])
            ->update([
                'enabled' => false,
                'sort_order' => DB::raw('sort_order + 100'),
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = now();

        DB::table('payment_method_configs')->where('gateway_code', 'safepay')->delete();

        DB::table('payment_method_configs')->where('gateway_code', 'easypaisa')->update([
            'enabled' => true,
            'sort_order' => 20,
            'updated_at' => $now,
        ]);

        DB::table('payment_method_configs')->where('gateway_code', 'jazzcash')->update([
            'enabled' => true,
            'sort_order' => 30,
            'updated_at' => $now,
        ]);
    }
};
