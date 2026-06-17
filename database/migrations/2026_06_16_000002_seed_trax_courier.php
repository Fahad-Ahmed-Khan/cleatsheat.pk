<?php

use App\Models\Courier;
use App\Models\CourierAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $courier = Courier::query()->updateOrCreate(
            ['code' => 'trax'],
            [
                'name' => 'Trax (Sonic)',
                'adapter' => 'trax',
                'sort_order' => 32,
                'config' => [],
                'is_active' => true,
            ],
        );

        $account = CourierAccount::query()->firstOrCreate(
            [
                'courier_id' => $courier->id,
                'name' => 'Primary account',
            ],
            [
                'credentials' => [
                    'api_token' => '',
                    'api_environment' => 'testing',
                ],
                'service_code' => null,
                'cod_allowed' => true,
                'city_restrictions' => null,
                'is_active' => true,
                'is_default' => true,
            ],
        );

        $creds = $account->credentials ?? [];
        if (! isset($creds['api_environment']) || ! in_array($creds['api_environment'], ['testing', 'live'], true)) {
            $creds['api_environment'] = 'testing';
            $account->credentials = $creds;
            $account->save();
        }
    }

    public function down(): void
    {
        $courier = Courier::query()->where('code', 'trax')->first();
        if ($courier === null) {
            return;
        }

        CourierAccount::query()->where('courier_id', $courier->id)->delete();
        $courier->delete();
    }
};
