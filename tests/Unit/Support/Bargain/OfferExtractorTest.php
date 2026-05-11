<?php

namespace Tests\Unit\Support\Bargain;

use App\Support\Bargain\OfferExtractor;
use PHPUnit\Framework\TestCase;

class OfferExtractorTest extends TestCase
{
    public function test_extracts_k_suffix_thousands(): void
    {
        $this->assertSame('11400.00', OfferExtractor::extractPkrAmount('Can you do 11.4k?'));
        $this->assertSame('11000.00', OfferExtractor::extractPkrAmount('around 11k'));
        $this->assertSame('11600.00', OfferExtractor::extractPkrAmount('Ok lets do it, PKR 11.6K'));
    }

    public function test_pk_currency_tokens_take_precedence_over_plain_digits(): void
    {
        $this->assertSame('500.00', OfferExtractor::extractPkrAmount('PKR 500 and maybe 9999 later'));
    }

    public function test_extracts_large_plain_number_when_no_currency_prefix(): void
    {
        $this->assertSame('11433.00', OfferExtractor::extractPkrAmount('make offer near 11433 please'));
    }
}
