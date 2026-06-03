<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Support\Storage\PublicAssetUrl;
use App\Support\Store\ProductCardMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight product payload for listing/rail cards and the quick-add sheet.
 *
 * Omits heavy PDP-only fields (description, SEO meta, fit/size copy, features,
 * video) to keep the Inertia payload small on listing pages.
 *
 * @mixin Product
 */
class ProductCardResource extends JsonResource
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
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn ($img) => [
                    'path' => PublicAssetUrl::resolve($img->path),
                    'srcset' => ProductResource::buildSrcset($img->variants),
                    'width' => $img->width,
                    'height' => $img->height,
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
