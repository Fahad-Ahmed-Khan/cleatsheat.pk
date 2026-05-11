<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('storefront_assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('delay_seconds')->default(4);
            $table->unsignedSmallInteger('snooze_days')->default(7);

            $table->json('allowed_routes_json')->nullable();
            $table->json('ui_json')->nullable();
            $table->json('steps_json')->nullable();
            $table->json('mapping_json')->nullable();

            $table->boolean('preview_enabled')->default(false);
            $table->timestamps();
        });

        DB::table('storefront_assistant_settings')->insert([
            'enabled' => true,
            'delay_seconds' => 4,
            'snooze_days' => 7,
            'allowed_routes_json' => json_encode([
                // Custom allowlist default: empty means "show nowhere" until configured.
            ], JSON_THROW_ON_ERROR),
            'ui_json' => json_encode([
                'title' => 'Find your perfect pair',
                'subtitle' => 'Answer 2 quick questions — we’ll filter the catalogue for you.',
                'welcome' => 'Hey! I can help you find the right shoes in under a minute.',
                'open_button_label' => 'Need help?',
                'start_button_label' => 'Start',
                'next_button_label' => 'Next',
                'back_button_label' => 'Back',
                'submit_button_label' => 'Show matches',
                'close_button_label' => 'Not now',
            ], JSON_THROW_ON_ERROR),
            'steps_json' => json_encode([
                [
                    'key' => 'size_uk',
                    'label' => 'UK size',
                    'required' => true,
                    'type' => 'select',
                    'multiple' => true,
                    'options' => [
                        ['label' => '6', 'value' => '6'],
                        ['label' => '7', 'value' => '7'],
                        ['label' => '8', 'value' => '8'],
                        ['label' => '9', 'value' => '9'],
                        ['label' => '10', 'value' => '10'],
                        ['label' => '11', 'value' => '11'],
                        ['label' => '12', 'value' => '12'],
                    ],
                ],
                [
                    'key' => 'type',
                    'label' => 'Type',
                    'required' => false,
                    'type' => 'select',
                    'multiple' => true,
                    'options' => [
                        ['label' => 'Athletic', 'value' => 'athletic'],
                        ['label' => 'Sneaker', 'value' => 'sneaker'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'mapping_json' => json_encode([
                'size_uk' => 'size_uk',
                'type' => 'type',
            ], JSON_THROW_ON_ERROR),
            'preview_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefront_assistant_settings');
    }
};

