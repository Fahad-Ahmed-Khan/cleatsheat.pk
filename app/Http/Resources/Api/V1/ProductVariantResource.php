<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'bargain_enabled' => (bool) $this->bargain_enabled,
            'color' => $this->whenLoaded('color', fn () => [
                'id' => $this->color->id,
                'name' => $this->color->name,
                'slug' => $this->color->slug,
                'hex' => $this->color->hex,
            ]),
            'sizes' => $this->whenLoaded('sizes', fn () => $this->sizes
                ->map(fn ($s) => [
                    'size_label' => $s->size_label,
                    'uk_size' => $s->uk_size,
                    'eu_size' => $s->eu_size,
                    'pk_size' => $s->pk_size,
                    'stock_qty' => $s->stock_qty,
                    'in_stock' => $s->stock_qty > 0,
                ])
                ->values()
                ->all()),
        ];
    }
}
