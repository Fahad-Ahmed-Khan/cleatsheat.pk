<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Support\Storage\PublicAssetUrl;
use App\Support\Store\ProductCardMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $card = ProductCardMeta::forProduct($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'video_url' => PublicAssetUrl::resolve($this->video_url),
            'video_poster' => PublicAssetUrl::resolve($this->video_poster),
            'fit_guidance' => $this->fit_guidance?->value,
            'gender' => $this->gender?->value,
            'shoe_type' => $this->shoe_type?->value,
            'fit_notes' => $this->fit_notes,
            'size_info' => $this->size_info,
            'features' => $this->features ?? [],
            'size_chart_id' => $this->size_chart_id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn ($img) => [
                    'path' => PublicAssetUrl::resolve($img->path),
                    'alt' => $img->alt,
                    'sort_order' => $img->sort_order,
                ])
                ->values()
                ->all()),
            'variants' => $this->whenLoaded(
                'variants',
                fn () => collect(ProductVariantResource::collection(
                    $this->variants->where('is_active', true)->values()
                )->resolve($request))
                    ->values()
                    ->all(),
            ),
            'card_surface_label' => $card['card_surface_label'],
            'card_condition_label' => $card['card_condition_label'],
            'card_condition_kind' => $card['card_condition_kind'],
            'card_condition_badge' => $card['card_condition_badge'],
            'card_authenticity_label' => $card['card_authenticity_label'],
            'quick_add' => $card['quick_add'],
        ];
    }
}
