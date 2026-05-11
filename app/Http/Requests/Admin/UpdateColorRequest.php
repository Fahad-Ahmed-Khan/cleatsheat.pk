<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorRequest extends FormRequest
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
        $color = $this->route('color');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('colors', 'slug')->ignore($color->id),
            ],
            'hex' => ['nullable', 'string', 'max:7'],
        ];
    }
}
