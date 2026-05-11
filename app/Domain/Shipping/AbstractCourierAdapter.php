<?php

namespace App\Domain\Shipping;

use App\Domain\Shipping\Contracts\CourierAdapterInterface;

abstract class AbstractCourierAdapter implements CourierAdapterInterface
{
    protected function sandboxMode(): bool
    {
        return (bool) config('shipping.sandbox', true);
    }

    protected function endpointBase(string $configKey): string
    {
        return rtrim((string) config('shipping.endpoints.'.$configKey), '/');
    }
}
