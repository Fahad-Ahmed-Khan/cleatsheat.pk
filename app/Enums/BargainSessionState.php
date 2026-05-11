<?php

namespace App\Enums;

enum BargainSessionState: string
{
    case Open = 'open';
    case Countered = 'countered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Consumed = 'consumed';
}
