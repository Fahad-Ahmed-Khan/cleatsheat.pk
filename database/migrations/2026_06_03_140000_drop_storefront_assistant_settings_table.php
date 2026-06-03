<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('storefront_assistant_settings');
    }

    public function down(): void
    {
        // Restored by 2026_05_11_000001_create_storefront_assistant_settings_table if needed.
    }
};
