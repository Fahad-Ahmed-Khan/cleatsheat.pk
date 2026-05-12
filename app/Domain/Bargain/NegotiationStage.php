<?php

namespace App\Domain\Bargain;

enum NegotiationStage: string
{
    case Opening = 'opening';
    case Lowball = 'lowball';
    case Exploring = 'exploring';
    case Negotiating = 'negotiating';
    case NearClose = 'near_close';
    case FinalPush = 'final_push';
    case Accepted = 'accepted';
    case Frustrated = 'frustrated';
}
