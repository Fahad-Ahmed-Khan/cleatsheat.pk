<?php

namespace Tests\Feature\Seo;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VariantSize;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoCatalogSeeder::class);
    }

    public function test_product_page_schema_includes_aggregate_rating_when_reviews_exist(): void
    {
        $this->get(route('store.product', 'urban-runner-pro'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Product')
                ->where('seo.schema_json', fn ($json) => $this->productSchemaFromPayload($json)['aggregateRating']['reviewCount'] >= 1));
    }

    public function test_product_page_schema_marks_out_of_stock_when_no_inventory(): void
    {
        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        VariantSize::query()
            ->whereIn('product_variant_id', $product->variants()->pluck('id'))
            ->update(['stock_qty' => 0]);

        $this->get(route('store.product', 'urban-runner-pro'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Product')
                ->where('seo.schema_json', fn ($json) => ($this->productSchemaFromPayload($json)['offers']['availability'] ?? null) === 'https://schema.org/OutOfStock'));
    }

    public function test_product_page_schema_uses_aggregate_offer_for_multiple_in_stock_variants(): void
    {
        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();

        foreach ($product->variants()->where('is_active', true)->get() as $variant) {
            VariantSize::query()
                ->where('product_variant_id', $variant->id)
                ->update(['stock_qty' => 5]);
        }

        $this->get(route('store.product', 'urban-runner-pro'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Product')
                ->where('seo.schema_json', fn ($json) => ($this->productSchemaFromPayload($json)['offers']['@type'] ?? null) === 'AggregateOffer'));
    }

    public function test_product_page_includes_og_price_and_image_dimensions(): void
    {
        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        ProductImage::query()
            ->where('product_id', $product->id)
            ->update(['width' => 800, 'height' => 1000]);

        $this->get(route('store.product', 'urban-runner-pro'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Product')
                ->where('seo.product_price', '12999')
                ->where('seo.product_price_currency', 'PKR')
                ->where('seo.og_image_width', 800)
                ->where('seo.og_image_height', 1000)
                ->has('breadcrumbs', 4));
    }

    /**
     * @return array<string, mixed>
     */
    private function productSchemaFromPayload(mixed $payload): array
    {
        $schemas = is_array($payload) ? $payload : json_decode((string) $payload, true);
        $list = is_array($schemas) && isset($schemas[0]) ? $schemas : [$schemas];
        $productSchema = collect($list)->firstWhere('@type', 'Product');
        $this->assertIsArray($productSchema);

        return $productSchema;
    }
}
