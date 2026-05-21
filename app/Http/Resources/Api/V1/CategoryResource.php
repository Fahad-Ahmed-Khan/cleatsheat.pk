<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
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
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_image_url' => $this->og_image_url,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => CategoryResource::collection($this->children)->resolve(),
            ),
        ];
    }
}
