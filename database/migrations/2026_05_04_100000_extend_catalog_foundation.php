<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('size_chart_id')->nullable()->after('category_id')->constrained('size_charts')->nullOnDelete();
            $table->json('features')->nullable()->after('fit_notes');
        });

        Schema::table('size_chart_rows', function (Blueprint $table) {
            $table->string('uk_size', 16)->nullable()->after('sort_order');
            $table->string('eu_size', 16)->nullable()->after('uk_size');
            $table->string('pk_size', 16)->nullable()->after('eu_size');
            $table->decimal('foot_cm', 5, 2)->nullable()->after('pk_size');
        });

        Schema::table('variant_sizes', function (Blueprint $table) {
            $table->string('uk_size', 16)->nullable()->after('size_label');
            $table->string('eu_size', 16)->nullable()->after('uk_size');
            $table->string('pk_size', 16)->nullable()->after('eu_size');
        });

        foreach (DB::table('size_chart_rows')->cursor() as $row) {
            DB::table('size_chart_rows')->where('id', $row->id)->update([
                'uk_size' => $row->label,
                'eu_size' => null,
                'pk_size' => null,
            ]);
        }

        foreach (DB::table('variant_sizes')->cursor() as $row) {
            DB::table('variant_sizes')->where('id', $row->id)->update([
                'uk_size' => $row->size_label,
                'eu_size' => null,
                'pk_size' => null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('variant_sizes', function (Blueprint $table) {
            $table->dropColumn(['uk_size', 'eu_size', 'pk_size']);
        });

        Schema::table('size_chart_rows', function (Blueprint $table) {
            $table->dropColumn(['uk_size', 'eu_size', 'pk_size', 'foot_cm']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['size_chart_id']);
            $table->dropColumn(['size_chart_id', 'features']);
        });
    }
};
