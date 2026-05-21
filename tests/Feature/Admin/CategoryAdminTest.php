<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_parent_and_subcategory(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Football Shoes',
                'slug' => 'football-shoes',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $parent = Category::query()->where('slug', 'football-shoes')->first();
        $this->assertNotNull($parent);
        $this->assertNull($parent->parent_id);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'parent_id' => $parent->id,
                'name' => 'Firm Ground',
                'slug' => 'football-fg',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $child = Category::query()->where('slug', 'football-fg')->first();
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_subcategory_cannot_use_nested_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'root-cat']);
        $mid = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'mid-cat']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'parent_id' => $mid->id,
                'name' => 'Too Deep',
                'slug' => 'too-deep',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('parent_id');
    }
}
