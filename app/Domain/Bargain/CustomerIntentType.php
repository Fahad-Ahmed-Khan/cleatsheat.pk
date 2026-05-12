<?php

namespace App\Domain\Bargain;

enum CustomerIntentType: string
{
    case Offer = 'offer';
    case Accept = 'accept';
    case Reject = 'reject';
    case AskDiscount = 'ask_discount';
    case AskBestPrice = 'ask_best_price';
    case Hesitation = 'hesitation';
    case Greeting = 'greeting';
    case Confused = 'confused';
    case Exit = 'exit';
    case Unknown = 'unknown';
}
