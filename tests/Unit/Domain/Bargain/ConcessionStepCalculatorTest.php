<?php

namespace Tests\Unit\Domain\Bargain;

use App\Domain\Bargain\ConcessionStepCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ConcessionStepCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_offer_is_deterministic_for_same_seed(): void
    {
        Config::set('bargain.counter.concession.enabled', true);
        Config::set('bargain.counter.concession.randomness', 0.18);
        Config::set('bargain.counter.concession.round_to', 50);
        Config::set('bargain.counter.concession.min_step_pkr', 25);
        Config::set('bargain.counter.concession.near_floor_gap_threshold_pkr', 400);

        $a = ConcessionStepCalculator::nextOffer('12000.00', '11300.00', null, 'bargain:1:10');
        $b = ConcessionStepCalculator::nextOffer('12000.00', '11300.00', null, 'bargain:1:10');

        $this->assertSame($a, $b);
    }

    public function test_next_offer_is_within_bounds_and_monotonic(): void
    {
        Config::set('bargain.counter.concession.enabled', true);
        Config::set('bargain.counter.concession.randomness', 0.0);
        Config::set('bargain.counter.concession.round_to', 50);
        Config::set('bargain.counter.concession.min_step_pkr', 50);
        Config::set('bargain.counter.concession.near_floor_gap_threshold_pkr', 400);

        $list = '12000.00';
        $min = '11300.00';

        $first = ConcessionStepCalculator::nextOffer($list, $min, null, 'bargain:1:1');
        $second = ConcessionStepCalculator::nextOffer($list, $min, $first, 'bargain:1:2');

        $this->assertTrue((float) $first >= (float) $min);
        $this->assertTrue((float) $first <= (float) $list);
        $this->assertTrue((float) $second >= (float) $min);
        $this->assertTrue((float) $second < (float) $first, 'Second offer should be lower than first.');
    }

    public function test_rounding_uses_25_near_floor_and_50_otherwise(): void
    {
        Config::set('bargain.counter.concession.enabled', true);
        Config::set('bargain.counter.concession.randomness', 0.0);
        Config::set('bargain.counter.concession.round_to', 50);
        Config::set('bargain.counter.concession.min_step_pkr', 25);
        Config::set('bargain.counter.concession.near_floor_gap_threshold_pkr', 400);

        // Far from floor: denom=50
        $far = ConcessionStepCalculator::nextOffer('12000.00', '11300.00', null, 'bargain:1:1');
        $this->assertSame(0, ((int) round((float) $far)) % 50);

        // Near floor: gap <= 400 => denom=25
        $nearPrev = '11650.00'; // gap=350
        $near = ConcessionStepCalculator::nextOffer('12000.00', '11300.00', $nearPrev, 'bargain:1:3');
        $this->assertSame(0, ((int) round((float) $near)) % 25);
    }
}

