<?php

namespace App\Enums;

enum ShoeType: string
{
    case Sneaker = 'sneaker';
    case Boot = 'boot';
    case Sandal = 'sandal';
    case Formal = 'formal';
    case Athletic = 'athletic';
    case Other = 'other';
}
