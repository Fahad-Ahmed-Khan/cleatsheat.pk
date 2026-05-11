<?php

namespace App\Enums;

enum Gender: string
{
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';
    case Kids = 'kids';
}
