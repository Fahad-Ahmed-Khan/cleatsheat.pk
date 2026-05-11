<?php

namespace App\Http\Requests\Admin;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'size_chart_id' => ['nullable', 'integer', 'exists:size_charts,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'string', 'max:512'],
            'video_url' => ['nullable', 'string', 'max:1024'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:51200'],
            'video_poster' => ['nullable', 'string', 'max:1024'],
            'fit_guidance' => ['required', Rule::enum(FitGuidance::class)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'shoe_type' => ['required', Rule::enum(ShoeType::class)],
            'fit_notes' => ['nullable', 'string'],
            'size_info' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'is_active' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*.path' => ['nullable', 'string', 'max:1024', 'required_without:images.*.file'],
            'images.*.file' => ['nullable', 'file', 'image', 'max:4096', 'required_without:images.*.path'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.sku' => [
                'required', 'string', 'max:64', 'distinct',
                Rule::unique('product_variants', 'sku')->where(
                    fn ($query) => $query->where('product_id', '<>', $product->id)
                ),
            ],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.sizes' => ['required', 'array', 'min:1'],
            'variants.*.sizes.*.size_label' => ['required', 'string', 'max:32'],
            'variants.*.sizes.*.uk_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.eu_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.pk_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.stock_qty' => ['required', 'integer', 'min:0'],
            'variants.*.sizes.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        $this->merge([
            'size_chart_id' => $this->filled('size_chart_id') ? (int) $this->input('size_chart_id') : null,
        ]);

        $variants = $this->input('variants', []);
        foreach ($variants as $i => $v) {
            if (($v['compare_at_price'] ?? '') === '' || $v['compare_at_price'] === null) {
                $variants[$i]['compare_at_price'] = null;
            }
        }
        $this->merge(['variants' => $variants]);

        if ($this->has('features')) {
            $features = array_values(array_filter(
                $this->input('features', []),
                static fn ($f) => is_string($f) && $f !== ''
            ));
            $this->merge(['features' => $features ?: null]);
        }

        if ($this->has('images')) {
            $images = array_values(array_filter(
                $this->input('images', []),
                static fn ($img) => is_array($img) && (
                    ! empty($img['path'] ?? null) || (($img['file'] ?? null) instanceof UploadedFile)
                )
            ));
            $this->merge(['images' => $images]);
        }
    }
}
