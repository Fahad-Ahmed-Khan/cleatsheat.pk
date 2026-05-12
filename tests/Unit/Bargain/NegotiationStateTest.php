<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\NegotiationStage;
use App\Domain\Bargain\NegotiationState;
use App\Enums\BargainSessionState;
use App\Models\BargainSession;
use PHPUnit\Framework\TestCase;

class NegotiationStateTest extends TestCase
{
    public function test_repeated_offer_maps_to_frustrated(): void
    {
        $session = new BargainSession([
            'list_price' => '12000.00',
            'state' => BargainSessionState::Countered,
        ]);
        $session->id = 1;

        $messages = [
            ['role' => 'customer', 'body' => '5000', 'meta' => ['parsed_offer' => '5000.00']],
            ['role' => 'assistant', 'body' => 'No', 'meta' => []],
            ['role' => 'customer', 'body' => '5000 again', 'meta' => ['parsed_offer' => '5000.00']],
        ];

        $state = NegotiationState::fromConversation($session, $messages, '5000.00', '11900.00');

        $this->assertSame(NegotiationStage::Frustrated, $state->negotiationStage);
    }

    public function test_accepted_session_maps_to_accepted_stage(): void
    {
        $session = new BargainSession([
            'list_price' => '10000.00',
            'state' => BargainSessionState::Accepted,
        ]);
        $session->id = 2;

        $state = NegotiationState::fromConversation($session, [], '950.00', '950.00');

        $this->assertSame(NegotiationStage::Accepted, $state->negotiationStage);
    }
}
