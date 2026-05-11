<?php

namespace Database\Factories;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'size_chart_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('####')),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'fit_guidance' => FitGuidance::TrueToSize,
            'gender' => Gender::Unisex,
            'shoe_type' => ShoeType::Sneaker,
            'fit_notes' => null,
            'features' => null,
            'is_active' => true,
        ];
    }
}
