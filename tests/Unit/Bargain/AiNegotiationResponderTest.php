<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\AiNegotiationResponder;
use App\Domain\Bargain\NegotiationDecision;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AiNegotiationResponderTest extends TestCase
{
    public function test_rejects_counter_reply_that_only_mentions_list_price(): void
    {
        $responder = new AiNegotiationResponder;
        $method = new ReflectionMethod(AiNegotiationResponder::class, 'isValidOutput');
        $method->setAccessible(true);

        $decision = new NegotiationDecision(
            allowedAction: 'counter',
            targetShopOfferPkr: '11800.00',
            customerOfferPkr: '6000.00',
            derivedState: 'negotiating',
            listPricePkr: '12999.00',
            currentShopOfferPkr: '11800.00',
            acceptedPricePkr: null,
            integrityFloorPkr: null,
        );

        $bad = 'Samajh gaya, lekin price sirf PKR 12999.00 hai. Kya aap is price par le sakte hain?';
        $good = 'PKR 6000.00 thora low hai. Best main PKR 11800.00 tak kar sakta hun.';

        $this->assertFalse($method->invoke($responder, $bad, $decision));
        $this->assertTrue($method->invoke($responder, $good, $decision));
    }
}
