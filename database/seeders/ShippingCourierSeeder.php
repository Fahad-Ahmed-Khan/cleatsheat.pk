<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\ShippingSetting;
use Illuminate\Database\Seeder;

class ShippingCourierSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['code' => 'leopards', 'name' => 'Leopards Courier', 'adapter' => 'leopards', 'sort_order' => 10],
            ['code' => 'mp', 'name' => 'M&P Logistics', 'adapter' => 'mp', 'sort_order' => 20],
            ['code' => 'postex', 'name' => 'PostEx', 'adapter' => 'postex', 'sort_order' => 30],
            ['code' => 'trax', 'name' => 'Trax (Sonic)', 'adapter' => 'trax', 'sort_order' => 32],
            ['code' => 'runcourier', 'name' => 'Run Courier', 'adapter' => 'runcourier', 'sort_order' => 35],
            ['code' => 'tcs', 'name' => 'TCS', 'adapter' => 'tcs', 'sort_order' => 40],
            ['code' => 'generic', 'name' => 'Manual / Offline', 'adapter' => 'generic', 'sort_order' => 99],
        ];

        $firstCourierId = null;

        foreach ($definitions as $def) {
            $courier = Courier::query()->updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'adapter' => $def['adapter'],
                    'sort_order' => $def['sort_order'],
                    'config' => [],
                    'is_active' => true,
                ],
            );

            if ($firstCourierId === null && $def['code'] !== 'generic') {
                $firstCourierId = $courier->id;
            }

            CourierAccount::query()->firstOrCreate(
                [
                    'courier_id' => $courier->id,
                    'name' => $def['code'] === 'generic' ? 'Offline fulfilment' : 'Primary account',
                ],
                [
                    'credentials' => array_filter([
                        'api_token' => '',
                        'api_environment' => $def['code'] === 'trax' ? 'testing' : null,
                    ], fn ($v) => $v !== null),
                    'service_code' => null,
                    'cod_allowed' => true,
                    'city_restrictions' => null,
                    'is_active' => true,
                    'is_default' => true,
                ],
            );
        }

        $settings = ShippingSetting::current();
        if ($settings->default_courier_id === null && $firstCourierId !== null) {
            $settings->default_courier_id = $firstCourierId;
            $settings->save();
        }
    }
}
