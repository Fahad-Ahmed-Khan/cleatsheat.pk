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

    #[Test]
    public function test_normalizes_trailing_variable_for_pickup_notice(): void
    {
        $result = MetaTemplateBodyConverter::convert(
            "Salaam, please pick {parcels} parcel(s) from our warehouse today. Total COD: PKR {cod_total}. Tracking #s:\n{tracking_list}"
        );

        $this->assertStringEndsWith('- CleatSheat.pk.', $result['body']);
        $this->assertStringContainsString('{{3}} - CleatSheat.pk.', $result['body']);
    }

    #[Test]
    public function test_normalizes_high_variable_ratio_for_admin_new_order(): void
    {
        $result = MetaTemplateBodyConverter::convert(
            "New order {order} · PKR {total}\nCustomer: {name} ({phone})\nCity: {city}\nPayment: {payment}\nStatus: {status}"
        );

        $this->assertStringContainsString('automated notification from CleatSheat.pk', $result['body']);
        $this->assertStringContainsString('Status: {{7}} - CleatSheat.pk.', $result['body']);
    }
}
