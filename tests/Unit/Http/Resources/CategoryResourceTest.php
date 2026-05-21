<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_collection_includes_children_as_plain_array(): void
    {
        $parent = Category::factory()->create([
            'parent_id' => null,
            'slug' => 'parent-cat',
            'is_active' => true,
        ]);
        Category::factory()->create([
            'parent_id' => $parent->id,
            'slug' => 'child-cat',
            'is_active' => true,
        ]);

        $parent->load(['children' => fn ($q) => $q->active()]);

        $resolved = CategoryResource::collection(collect([$parent]))->resolve();
        $children = $resolved[0]['children'] ?? null;

        $this->assertIsArray($children);
        $this->assertCount(1, $children);
        $this->assertSame('child-cat', $children[0]['slug']);
    }
}
