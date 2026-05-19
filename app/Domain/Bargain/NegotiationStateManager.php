<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainSession;

final class NegotiationStateManager
{
    /**
     * @return 'negotiating'|'soft_agreement'|'awaiting_acceptance'|'accepted'|'locked'|'declined'|'expired'
     */
    public function deriveState(BargainSession $session, string $intentType): string
    {
        if ($session->state === BargainSessionState::Expired) {
            return 'expired';
        }
        if ($session->state === BargainSessionState::Declined) {
            return 'declined';
        }
        if ($session->state === BargainSessionState::Consumed) {
            return 'locked';
        }
        if ($session->state === BargainSessionState::Accepted) {
            return 'locked';
        }

        // Open / Countered
        if ($intentType === 'accept' && $session->current_offer !== null) {
            return 'awaiting_acceptance';
        }

        return 'negotiating';
    }
}
