<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'video_url' => $this->video_url,
            'video_poster' => $this->video_poster,
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
                    'path' => $img->path,
                    'alt' => $img->alt,
                    'sort_order' => $img->sort_order,
                ])
                ->values()
                ->all()),
            'variants' => $this->whenLoaded(
                'variants',
                fn () => collect(ProductVariantResource::collection($this->variants)->resolve($request))
                    ->values()
                    ->all(),
            ),
        ];
    }
}
