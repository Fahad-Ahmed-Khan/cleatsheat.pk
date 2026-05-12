<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateStorefrontAssistantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $text = (string) $this->input('allowed_routes_text', '');
        $split = preg_split('/\r\n|\r|\n/', $text);
        $split = is_array($split) ? $split : [];
        $lines = array_values(array_filter(
            array_map(static fn (mixed $line): string => trim((string) $line), $split),
            static fn (string $line): bool => $line !== '',
        ));

        $this->merge([
            'allowed_routes' => $lines,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sizeChartRule = ['nullable', 'integer', 'min:1'];
        if (Schema::hasTable('size_charts')) {
            $sizeChartRule[] = Rule::exists('size_charts', 'id');
        }

        return [
            'enabled' => ['sometimes', 'boolean'],
            'preview_enabled' => ['sometimes', 'boolean'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:30'],
            'snooze_days' => ['required', 'integer', 'min:0', 'max:365'],

            'allowed_routes' => ['array', 'max:50'],
            'allowed_routes.*' => ['string', 'min:1', 'max:120'],

            'ui' => ['array'],
            'ui.title' => ['nullable', 'string', 'max:80'],
            'ui.subtitle' => ['nullable', 'string', 'max:140'],
            'ui.welcome' => ['nullable', 'string', 'max:200'],
            'ui.size_chart_id' => $sizeChartRule,
            'ui.open_button_label' => ['nullable', 'string', 'max:30'],
            'ui.start_button_label' => ['nullable', 'string', 'max:30'],
            'ui.next_button_label' => ['nullable', 'string', 'max:30'],
            'ui.back_button_label' => ['nullable', 'string', 'max:30'],
            'ui.submit_button_label' => ['nullable', 'string', 'max:30'],
            'ui.close_button_label' => ['nullable', 'string', 'max:30'],

            'steps' => ['array', 'max:12'],
            'steps.*.key' => ['required', 'string', 'max:40'],
            'steps.*.label' => ['required', 'string', 'max:80'],
            'steps.*.required' => ['sometimes', 'boolean'],
            'steps.*.multiple' => ['sometimes', 'boolean'],
            'steps.*.type' => ['required', 'in:select,radio,number,text'],
            'steps.*.placeholder' => ['nullable', 'string', 'max:60'],
            'steps.*.min' => ['nullable', 'numeric'],
            'steps.*.max' => ['nullable', 'numeric'],
            'steps.*.step' => ['nullable', 'numeric', 'min:0.1', 'max:2'],
            'steps.*.options' => ['nullable', 'array', 'max:50'],
            'steps.*.options.*.label' => ['required_with:steps.*.options', 'string', 'max:60'],
            'steps.*.options.*.value' => ['required_with:steps.*.options', 'string', 'max:60'],

            'mapping' => ['array', 'max:50'],
        ];
    }
}
