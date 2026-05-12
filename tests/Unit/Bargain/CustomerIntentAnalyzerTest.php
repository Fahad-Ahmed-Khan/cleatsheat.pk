<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\ConversationAnalyzer;
use App\Domain\Bargain\CustomerIntentAnalyzer;
use App\Domain\Bargain\CustomerIntentType;
use App\Enums\BargainSessionState;
use App\Models\BargainSession;
use PHPUnit\Framework\TestCase;

class CustomerIntentAnalyzerTest extends TestCase
{
    public function test_done_maps_to_accept_when_shop_line_exists(): void
    {
        $session = new BargainSession([
            'list_price' => '1000.00',
            'current_offer' => '950.00',
            'state' => BargainSessionState::Countered,
        ]);
        $session->id = 1;
        $prior = (new ConversationAnalyzer)->analyze([]);
        $r = (new CustomerIntentAnalyzer)->analyze('theek hai done', $session, null, $prior);

        $this->assertSame(CustomerIntentType::Accept, $r->type);
        $this->assertGreaterThanOrEqual(0.72, $r->confidence);
    }

    public function test_plain_done_is_accept_intent_even_without_shop_line(): void
    {
        $session = new BargainSession([
            'list_price' => '1000.00',
            'current_offer' => null,
            'state' => BargainSessionState::Open,
        ]);
        $session->id = 2;
        $prior = (new ConversationAnalyzer)->analyze([]);
        $r = (new CustomerIntentAnalyzer)->analyze('done', $session, null, $prior);

        $this->assertSame(CustomerIntentType::Accept, $r->type);
        $this->assertGreaterThanOrEqual(0.72, $r->confidence);
    }

    public function test_discount_question(): void
    {
        $session = new BargainSession([
            'list_price' => '1000.00',
            'current_offer' => '950.00',
            'state' => BargainSessionState::Countered,
        ]);
        $session->id = 3;
        $prior = (new ConversationAnalyzer)->analyze([]);
        $r = (new CustomerIntentAnalyzer)->analyze('kuch discount mil sakta hai?', $session, null, $prior);

        $this->assertSame(CustomerIntentType::AskDiscount, $r->type);
    }
}
