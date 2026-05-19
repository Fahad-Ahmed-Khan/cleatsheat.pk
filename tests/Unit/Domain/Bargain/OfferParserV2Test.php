<?php

namespace Tests\Unit\Domain\Bargain;

use App\Domain\Bargain\OfferParserV2;
use Tests\TestCase;

class OfferParserV2Test extends TestCase
{
    public function test_parses_k_notation_single(): void
    {
        $p = new OfferParserV2;
        $r = $p->parse('10.5k final');
        $this->assertSame('10500.00', $r['singleAmountPkr'] ?? null);
    }

    public function test_parses_range_k_notation(): void
    {
        $p = new OfferParserV2;
        $r = $p->parse('10k sy 11k');
        $this->assertSame('10000.00', $r['rangeMinPkr'] ?? null);
        $this->assertSame('11000.00', $r['rangeMaxPkr'] ?? null);
    }

    public function test_parses_hazar(): void
    {
        $p = new OfferParserV2;
        $r = $p->parse('gyarah hazar');
        $this->assertSame('11000.00', $r['singleAmountPkr'] ?? null);
    }
}
