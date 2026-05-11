<?php

namespace App\Enums;

enum FitGuidance: string
{
    case TrueToSize = 'true_to_size';
    case RunsSmall = 'runs_small';
    case RunsLarge = 'runs_large';
}
