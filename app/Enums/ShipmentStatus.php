<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
