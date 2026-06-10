<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('header_text', 60)->nullable()->after('body');
            $table->string('footer_text', 60)->nullable()->after('header_text');
            $table->json('url_buttons')->nullable()->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn(['header_text', 'footer_text', 'url_buttons']);
        });
    }
};
