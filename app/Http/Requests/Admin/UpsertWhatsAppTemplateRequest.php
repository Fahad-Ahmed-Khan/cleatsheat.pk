<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertWhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('button_payloads');
        $normalized = [];
        if (is_array($raw)) {
            foreach ($raw as $b) {
                if (! is_array($b)) {
                    continue;
                }
                $id = trim((string) ($b['id'] ?? ''));
                $title = trim((string) ($b['title'] ?? ''));
                if ($id === '' && $title === '') {
                    continue;
                }
                $normalized[] = ['id' => $id, 'title' => mb_substr($title, 0, 20)];
            }
        }
        $this->merge([
            'button_payloads' => $normalized,
            'has_buttons' => $this->boolean('has_buttons') && $normalized !== [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $templateId = $this->route('whatsapp_template')?->id ?? $this->route('whatsappTemplate')?->id;

        return [
            'key' => [
                'required', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9_]+$/i',
                Rule::unique('whatsapp_templates', 'key')->ignore($templateId),
            ],
            'label' => ['required', 'string', 'max:120'],
            'audience' => ['required', Rule::in(['customer', 'admin', 'rider'])],
            'category' => ['required', Rule::in(['transactional', 'utility', 'marketing'])],
            'body' => ['required', 'string', 'min:5', 'max:4000'],
            'cloud_template_name' => ['nullable', 'string', 'max:120'],
            'cloud_template_language' => ['nullable', 'string', 'max:16'],
            'has_buttons' => ['boolean'],
            'button_payloads' => ['array', 'max:3'],
            'button_payloads.*.id' => ['required_with:button_payloads', 'string', 'max:120'],
            'button_payloads.*.title' => ['required_with:button_payloads', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
