<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('####')),
            'meta_title' => null,
            'meta_description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
