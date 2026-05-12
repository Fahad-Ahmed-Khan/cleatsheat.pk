<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\ContextualOfferCorrector;
use PHPUnit\Framework\TestCase;

class ContextualOfferCorrectorTest extends TestCase
{
    public function test_typo_near_shop_line_suggests_shop_amount(): void
    {
        $c = new ContextualOfferCorrector;
        $r = $c->correct('1150.00', '12000.00', '11150.00', []);

        $this->assertSame('11150.00', $r->corrected);
        $this->assertGreaterThanOrEqual(0.82, $r->confidence);
    }

    public function test_no_correction_when_already_meets_shop(): void
    {
        $c = new ContextualOfferCorrector;
        $r = $c->correct('11150.00', '12000.00', '11150.00', []);

        $this->assertNull($r->corrected);
    }
}
