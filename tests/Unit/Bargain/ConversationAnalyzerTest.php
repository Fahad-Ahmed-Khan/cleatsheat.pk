<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\ConversationAnalyzer;
use PHPUnit\Framework\TestCase;

class ConversationAnalyzerTest extends TestCase
{
    public function test_progression_7000_to_10000_to_11000(): void
    {
        $analyzer = new ConversationAnalyzer;
        $messages = [
            ['role' => 'assistant', 'body' => 'Hi', 'meta' => []],
            ['role' => 'customer', 'body' => '7000', 'meta' => ['parsed_offer' => '7000.00']],
            ['role' => 'assistant', 'body' => 'Counter', 'meta' => []],
            ['role' => 'customer', 'body' => '10000', 'meta' => ['parsed_offer' => '10000.00']],
            ['role' => 'assistant', 'body' => 'Ok', 'meta' => []],
            ['role' => 'customer', 'body' => '11000 final', 'meta' => ['parsed_offer' => '11000.00']],
        ];

        $signals = $analyzer->analyze($messages);

        $this->assertTrue($signals->customerIsProgressing);
        $this->assertSame('up', $signals->movementDirection);
        $this->assertTrue($signals->customerUsedFinalOrLast);
        $this->assertContains('11000.00', $signals->customerOfferAmountsChronological);
    }
}
