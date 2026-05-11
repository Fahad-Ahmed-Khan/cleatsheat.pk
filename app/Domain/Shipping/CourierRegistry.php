<?php

namespace App\Domain\Shipping;

use App\Domain\Shipping\Contracts\CourierAdapterInterface;
use App\Domain\Shipping\Couriers\GenericCourierAdapter;
use App\Domain\Shipping\Couriers\LeopardsCourierAdapter;
use App\Domain\Shipping\Couriers\MpCourierAdapter;
use App\Domain\Shipping\Couriers\PostExCourierAdapter;
use App\Domain\Shipping\Couriers\RunCourierAdapter;
use App\Domain\Shipping\Couriers\TcsCourierAdapter;
use App\Models\Courier;
use InvalidArgumentException;

class CourierRegistry
{
    public function __construct(
        private readonly GenericCourierAdapter $generic,
        private readonly LeopardsCourierAdapter $leopards,
        private readonly MpCourierAdapter $mp,
        private readonly PostExCourierAdapter $postEx,
        private readonly RunCourierAdapter $runCourier,
        private readonly TcsCourierAdapter $tcs,
    ) {}

    public function forCourier(Courier $courier): CourierAdapterInterface
    {
        return match ($courier->adapter) {
            'leopards' => $this->leopards,
            'mp' => $this->mp,
            'postex' => $this->postEx,
            'runcourier' => $this->runCourier,
            'tcs' => $this->tcs,
            'generic', '' => $this->generic,
            default => throw new InvalidArgumentException('Unknown courier adapter: '.$courier->adapter),
        };
    }
}
