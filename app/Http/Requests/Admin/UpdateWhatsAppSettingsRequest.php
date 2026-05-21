<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $text = (string) $this->input('admin_recipients_text', '');
        $split = preg_split('/\r\n|\r|\n/', $text);
        $split = is_array($split) ? $split : [];
        $lines = array_values(array_filter(
            array_map(static fn (mixed $line): string => trim((string) $line), $split),
            static fn (string $line): bool => $line !== '',
        ));

        $this->merge([
            'admin_recipients' => $lines,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled_customer_notifications' => ['sometimes', 'boolean'],
            'enabled_admin_notifications' => ['sometimes', 'boolean'],
            'enabled_cod_confirmation' => ['sometimes', 'boolean'],
            'enabled_shipment_status_customer_alerts' => ['sometimes', 'boolean'],
            'enabled_pickup_notices' => ['sometimes', 'boolean'],
            'pickup_notice_time' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'cloud_webhook_verify_token' => ['nullable', 'string', 'min:8', 'max:128'],
            'marketing_opt_out_keyword' => ['nullable', 'string', 'max:32'],
            'promotional_throttle_per_minute' => ['nullable', 'integer', 'min:1', 'max:600'],
            'admin_recipients' => ['array', 'max:30'],
            'admin_recipients.*' => ['string', 'min:7', 'max:32'],
        ];
    }
}
