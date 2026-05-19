<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingSettingsRequest;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\ShippingSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShippingSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $settings = ShippingSetting::current()->load('defaultCourier');

        $couriers = Courier::query()
            ->with(['accounts' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $courierAccountRows = [];
        foreach ($couriers as $courier) {
            foreach ($courier->accounts as $account) {
                $creds = $account->credentials ?? [];
                $courierAccountRows[] = [
                    'id' => $account->id,
                    'courier_name' => $courier->name,
                    'courier_adapter' => $courier->adapter,
                    'name' => $account->name,
                    'cod_allowed' => $account->cod_allowed,
                    'is_active' => $account->is_active,
                    'is_default' => $account->is_default,
                    'service_code' => $account->service_code ?? '',
                    'city_restrictions_text' => $account->city_restrictions
                        ? implode(', ', $account->city_restrictions)
                        : '',
                    // Never echo secrets back to the browser. The form preserves the
                    // stored value when the field is left blank.
                    'api_token' => '',
                    'client_code' => '',
                    'profile_id' => '',
                    'api_vendor' => '',
                    'has_api_token' => trim((string) ($creds['api_token'] ?? '')) !== '',
                    'has_client_code' => trim((string) ($creds['client_code'] ?? '')) !== '',
                    'has_profile_id' => trim((string) ($creds['profile_id'] ?? '')) !== '',
                    'has_api_vendor' => trim((string) ($creds['api_vendor'] ?? '')) !== '',
                ];
            }
        }

        return Inertia::render('Admin/Shipping/Settings', [
            'settings' => [
                'default_courier_id' => $settings->default_courier_id,
                'courier_assignment_default' => $settings->courier_assignment_default->value,
                'auto_book_on_payment_confirmed' => $settings->auto_book_on_payment_confirmed,
                'auto_book_cod_orders' => $settings->auto_book_cod_orders,
                'tracking_sync_interval_minutes' => $settings->tracking_sync_interval_minutes,
                'sender_snapshot' => $settings->sender_snapshot ?? [],
                'postex_pickup_address_code' => $settings->postex_pickup_address_code,
                'postex_store_address_code' => $settings->postex_store_address_code,
                'default_weight_kg' => (float) $settings->default_weight_kg,
                'default_length_cm' => (float) $settings->default_length_cm,
                'default_width_cm' => (float) $settings->default_width_cm,
                'default_height_cm' => (float) $settings->default_height_cm,
            ],
            'couriers_for_select' => $couriers->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ])->values()->all(),
            'courier_accounts_form' => $courierAccountRows,
        ]);
    }

    public function update(UpdateShippingSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $settings = ShippingSetting::current();
        $settings->fill([
            'default_courier_id' => $data['default_courier_id'] ?? null,
            'courier_assignment_default' => $data['courier_assignment_default'],
            'auto_book_on_payment_confirmed' => $data['auto_book_on_payment_confirmed'] ?? false,
            'auto_book_cod_orders' => $data['auto_book_cod_orders'] ?? false,
            'tracking_sync_interval_minutes' => $data['tracking_sync_interval_minutes'],
            'sender_snapshot' => $data['sender_snapshot'],
            'postex_pickup_address_code' => $data['postex_pickup_address_code'] ?: null,
            'postex_store_address_code' => $data['postex_store_address_code'] ?: null,
            'default_weight_kg' => $data['default_weight_kg'],
            'default_length_cm' => $data['default_length_cm'],
            'default_width_cm' => $data['default_width_cm'],
            'default_height_cm' => $data['default_height_cm'],
        ]);
        $settings->save();

        foreach ($data['courier_accounts'] ?? [] as $row) {
            CourierAccount::query()->whereKey($row['id'])->update([
                'name' => $row['name'],
                'cod_allowed' => $row['cod_allowed'] ?? false,
                'is_active' => $row['is_active'] ?? false,
                'is_default' => $row['is_default'] ?? false,
                'service_code' => $row['service_code'] ?: null,
                'city_restrictions' => ! empty($row['city_restrictions_text'])
                    ? array_map('trim', explode(',', $row['city_restrictions_text']))
                    : null,
            ]);

            $account = CourierAccount::query()->with('courier')->find($row['id']);
            if ($account === null) {
                continue;
            }

            $creds = $account->credentials ?? [];
            $credentialsChanged = false;

            if (! empty($row['api_token'])) {
                $creds['api_token'] = $row['api_token'];
                $credentialsChanged = true;
            }

            if ($account->courier?->adapter === 'runcourier') {
                if (! empty($row['client_code'])) {
                    $creds['client_code'] = $row['client_code'];
                    $credentialsChanged = true;
                }
                if (! empty($row['profile_id'])) {
                    $creds['profile_id'] = $row['profile_id'];
                    $credentialsChanged = true;
                }
                if (array_key_exists('api_vendor', $row)) {
                    $trimmedVendor = trim((string) $row['api_vendor']);
                    if ($trimmedVendor !== '') {
                        $creds['api_vendor'] = $trimmedVendor;
                    } else {
                        unset($creds['api_vendor']);
                    }
                    $credentialsChanged = true;
                }
            }

            if ($credentialsChanged) {
                $account->credentials = $creds;
                $account->save();
            }
        }

        return redirect()->route('admin.shipping-settings.edit')->with('status', 'Shipping settings saved.');
    }
}
