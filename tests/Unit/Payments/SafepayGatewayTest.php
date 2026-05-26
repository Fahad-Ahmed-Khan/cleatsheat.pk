<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\Gateways\SafepayGateway;
use App\Domain\Payments\Safepay\SafepayClientFactory;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafepayGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, int}> */
    public static function amountProvider(): iterable
    {
        yield 'whole rupees' => ['1500.00', 150000];
        yield 'mixed decimals' => ['299.99', 29999];
        yield 'sub-rupee precision rounds half up' => ['10.005', 1001];
        yield 'zero' => ['0', 0];
        yield 'large amount' => ['125000.50', 12500050];
    }

    #[DataProvider('amountProvider')]
    public function test_converts_pkr_to_paisa(string $rupees, int $expected): void
    {
        $factory = Mockery::mock(SafepayClientFactory::class);
        $gateway = new SafepayGateway($factory);

        $this->assertSame($expected, $gateway->toMinorUnits($rupees));
    }

    public function test_code_is_safepay(): void
    {
        $factory = Mockery::mock(SafepayClientFactory::class);
        $gateway = new SafepayGateway($factory);

        $this->assertSame('safepay', $gateway->code());
    }
}
