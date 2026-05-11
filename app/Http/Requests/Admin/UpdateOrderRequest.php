<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourierAssignmentMode;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'preferred_courier_id' => ['nullable', 'integer', 'exists:couriers,id'],
            'courier_assignment' => ['nullable', Rule::enum(CourierAssignmentMode::class)],
        ];
    }
}
