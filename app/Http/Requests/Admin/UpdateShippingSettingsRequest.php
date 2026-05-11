<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourierAssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_courier_id' => ['nullable', 'integer', 'exists:couriers,id'],
            'courier_assignment_default' => ['required', Rule::enum(CourierAssignmentMode::class)],
            'auto_book_on_payment_confirmed' => ['sometimes', 'boolean'],
            'auto_book_cod_orders' => ['sometimes', 'boolean'],
            'tracking_sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'sender_snapshot' => ['required', 'array'],
            'sender_snapshot.business_name' => ['nullable', 'string', 'max:255'],
            'sender_snapshot.contact_name' => ['nullable', 'string', 'max:255'],
            'sender_snapshot.phone' => ['nullable', 'string', 'max:32'],
            'sender_snapshot.email' => ['nullable', 'email', 'max:255'],
            'sender_snapshot.line1' => ['nullable', 'string', 'max:255'],
            'sender_snapshot.city' => ['nullable', 'string', 'max:120'],
            'postex_pickup_address_code' => ['nullable', 'string', 'max:64'],
            'postex_store_address_code' => ['nullable', 'string', 'max:64'],
            'default_weight_kg' => ['required', 'numeric', 'min:0.001', 'max:500'],
            'default_length_cm' => ['required', 'numeric', 'min:1', 'max:300'],
            'default_width_cm' => ['required', 'numeric', 'min:1', 'max:300'],
            'default_height_cm' => ['required', 'numeric', 'min:1', 'max:300'],
            'courier_accounts' => ['nullable', 'array'],
            'courier_accounts.*.id' => ['required', 'integer', 'exists:courier_accounts,id'],
            'courier_accounts.*.name' => ['required', 'string', 'max:255'],
            'courier_accounts.*.cod_allowed' => ['sometimes', 'boolean'],
            'courier_accounts.*.is_active' => ['sometimes', 'boolean'],
            'courier_accounts.*.is_default' => ['sometimes', 'boolean'],
            'courier_accounts.*.service_code' => ['nullable', 'string', 'max:64'],
            'courier_accounts.*.city_restrictions_text' => ['nullable', 'string', 'max:2000'],
            'courier_accounts.*.api_token' => ['nullable', 'string', 'max:2000'],
            'courier_accounts.*.client_code' => ['nullable', 'string', 'max:64'],
            'courier_accounts.*.profile_id' => ['nullable', 'string', 'max:64'],
            'courier_accounts.*.api_vendor' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_book_on_payment_confirmed' => $this->boolean('auto_book_on_payment_confirmed'),
            'auto_book_cod_orders' => $this->boolean('auto_book_cod_orders'),
        ]);
    }
}
