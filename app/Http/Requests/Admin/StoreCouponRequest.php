<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $this->input('code', '')));

        $merge = ['code' => $code];
        foreach (['min_cart_total', 'max_redemptions', 'starts_at', 'ends_at'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:coupons,code'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'min_cart_total' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->input('type') === CouponType::Percent->value && (float) $this->input('value') > 100) {
                $v->errors()->add('value', 'Percent discount cannot exceed 100%.');
            }
        });
    }
}
