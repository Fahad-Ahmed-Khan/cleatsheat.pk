<?php

namespace Tests\Unit\Domain\Bargain;

use App\Domain\Bargain\IntentDetector;
use Tests\TestCase;

class IntentDetectorTest extends TestCase
{
    public function test_detects_accept_intent(): void
    {
        $d = new IntentDetector;
        $r = $d->detect('theek hai lock kar do');
        $this->assertSame('accept', $r['type']);
        $this->assertGreaterThanOrEqual(0.85, $r['confidence']);
    }

    public function test_detects_question_intent(): void
    {
        $d = new IntentDetector;
        $r = $d->detect('delivery free hai?');
        $this->assertSame('question', $r['type']);
    }

    public function test_detects_offer_when_parsed_amount_present(): void
    {
        $d = new IntentDetector;
        $r = $d->detect('pkr 10500', '10500.00');
        $this->assertSame('offer', $r['type']);
        $this->assertGreaterThanOrEqual(0.8, $r['confidence']);
    }

    public function test_detects_casual_chat(): void
    {
        $d = new IntentDetector;
        $r = $d->detect('hahaha ap funny ho');
        $this->assertSame('casual_chat', $r['type']);
    }
}
