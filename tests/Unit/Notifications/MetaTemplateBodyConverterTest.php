<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\WhatsApp\MetaTemplateBodyConverter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaTemplateBodyConverterTest extends TestCase
{
    #[Test]
    public function test_converts_placeholders_in_order_of_appearance(): void
    {
        $result = MetaTemplateBodyConverter::convert(
            "Hi {name}, your order {order} has been placed.\nTotal: PKR {total}."
        );

        $this->assertSame(
            "Hi {{1}}, your order {{2}} has been placed.\nTotal: PKR {{3}}.",
            $result['body'],
        );
        $this->assertSame(['name', 'order', 'total'], $result['parameter_order']);
        $this->assertSame(['Test Customer', 'ORD-1001', '4500'], $result['examples']);
    }

    #[Test]
    public function test_rejects_unknown_placeholder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MetaTemplateBodyConverter::convert('Hello {unknown}');
    }
}
