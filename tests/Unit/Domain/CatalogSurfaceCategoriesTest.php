<?php

namespace Tests\Unit\Domain;

use App\Domain\Catalog\CatalogQueryService;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSurfaceCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_surface_categories_returns_ordered_children_of_configured_parent(): void
    {
        config(['store.surface_parent_slug' => 'football-shoes']);

        $parent = Category::factory()->create([
            'slug' => 'football-shoes',
            'name' => 'Football Shoes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sg = Category::factory()->create([
            'parent_id' => $parent->id,
            'slug' => 'football-sg',
            'name' => 'Soft Ground (SG)',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $fg = Category::factory()->create([
            'parent_id' => $parent->id,
            'slug' => 'football-fg',
            'name' => 'Firm Ground (FG)',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Category::factory()->create([
            'parent_id' => $parent->id,
            'slug' => 'football-ic',
            'name' => 'Indoor (IC)',
            'sort_order' => 3,
            'is_active' => false,
        ]);

        $service = app(CatalogQueryService::class);
        $surfaces = $service->surfaceCategories();

        $this->assertCount(2, $surfaces);
        $this->assertSame($fg->id, $surfaces->first()->id);
        $this->assertSame($sg->id, $surfaces->last()->id);
        $this->assertFalse($surfaces->contains('slug', 'football-ic'));
    }

    public function test_surface_categories_empty_when_parent_missing(): void
    {
        config(['store.surface_parent_slug' => 'missing-parent']);

        $this->assertTrue(app(CatalogQueryService::class)->surfaceCategories()->isEmpty());
    }
}
