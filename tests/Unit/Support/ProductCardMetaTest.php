<?php

namespace Tests\Unit\Support;

use App\Models\Product;
use App\Support\Store\ProductCardMeta;
use Tests\TestCase;

class ProductCardMetaTest extends TestCase
{
    public function test_surface_label_from_category_slug(): void
    {
        $product = new Product(['name' => 'Test Boot']);
        $product->setRelation('category', (object) ['slug' => 'firm-ground-fg', 'name' => 'Firm Ground (FG)']);

        $this->assertSame('FG', ProductCardMeta::surfaceLabel($product));
    }

    public function test_for_product_returns_card_fields(): void
    {
        $product = new Product([
            'name' => 'Test',
            'features' => ['Second-hand, inspected', '100% authentic gear'],
        ]);
        $product->setRelation('category', (object) ['slug' => 'ag', 'name' => 'AG']);
        $product->setRelation('variants', collect());

        $meta = ProductCardMeta::forProduct($product);

        $this->assertSame('AG', $meta['card_surface_label']);
        $this->assertStringContainsString('authentic', strtolower($meta['card_authenticity_label']));
        $this->assertSame('used', $meta['card_condition_kind']);
        $this->assertSame('Pre-Loved', $meta['card_condition_badge']);
        $this->assertNull($meta['quick_add']);
    }

    public function test_parses_condition_rating_from_description(): void
    {
        $product = new Product([
            'name' => 'Nike Boot',
            'description' => '<p>Used. Condition: <strong>9.5/10</strong>.</p>',
            'features' => [],
        ]);
        $product->setRelation('category', (object) ['slug' => 'football-fg', 'name' => 'FG']);
        $product->setRelation('variants', collect());

        $meta = ProductCardMeta::forProduct($product);

        $this->assertSame('used', $meta['card_condition_kind']);
        $this->assertSame('9.5/10 Condition', $meta['card_condition_badge']);
    }

    public function test_brand_new_from_feature(): void
    {
        $product = new Product([
            'name' => 'New Boot',
            'features' => ['Brand new in box', '100% authentic'],
        ]);
        $product->setRelation('category', (object) ['slug' => 'football-fg', 'name' => 'FG']);
        $product->setRelation('variants', collect());

        $meta = ProductCardMeta::forProduct($product);

        $this->assertSame('new', $meta['card_condition_kind']);
        $this->assertSame('Brand New', $meta['card_condition_badge']);
    }

    public function test_defaults_to_pre_loved_without_signals(): void
    {
        $product = new Product([
            'name' => 'Boot',
            'features' => [],
        ]);
        $product->setRelation('category', (object) ['slug' => 'football-ag', 'name' => 'AG']);
        $product->setRelation('variants', collect());

        $meta = ProductCardMeta::forProduct($product);

        $this->assertSame('used', $meta['card_condition_kind']);
        $this->assertSame('Pre-Loved', $meta['card_condition_badge']);
    }
}
