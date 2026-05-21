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
        $this->assertNull($meta['quick_add']);
    }
}
