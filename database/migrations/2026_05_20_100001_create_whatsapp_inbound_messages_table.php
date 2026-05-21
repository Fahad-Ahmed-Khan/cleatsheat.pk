<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wa_message_id')->unique()->nullable();
            $table->string('from_number', 32)->index();
            $table->string('to_number', 32)->nullable();
            $table->string('type', 24)->default('text'); // text | button | interactive | status | image | unknown
            $table->text('body')->nullable();
            $table->string('button_payload')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('handled_as', 32)->nullable(); // confirmation_yes | confirmation_no | opt_out | unrelated | error
            $table->text('handler_notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound_messages');
    }
};
