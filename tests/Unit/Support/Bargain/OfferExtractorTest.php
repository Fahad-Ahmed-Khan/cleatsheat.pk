<?php

namespace Tests\Unit\Support\Bargain;

use App\Support\Bargain\OfferExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferExtractorTest extends TestCase
{
    /**
     * @return array<string, string|null>
     */
    public static function amountCases(): array
    {
        return [
            'plain' => ['10900 please', '10900.00'],
            'comma' => ['PKR 10,900', '10900.00'],
            'comma_rs' => ['Rs 10,900', '10900.00'],
            'spaced' => ['10 900 max', '10900.00'],
            'k_lower' => ['10k final', '10000.00'],
            'k_decimal' => ['11.5k', '11500.00'],
            'k_upper' => ['12K only', '12000.00'],
            'pkr_comma' => ['PKR 10,900 done', '10900.00'],
        ];
    }

    #[DataProvider('amountCases')]
    public function test_extracts_expected_amount(string $text, ?string $expected): void
    {
        $this->assertSame($expected, OfferExtractor::extractPkrAmount($text));
    }
}
