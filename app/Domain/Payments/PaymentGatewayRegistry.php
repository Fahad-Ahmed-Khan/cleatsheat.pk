<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGatewayInterface;
use App\Domain\Payments\Gateways\CodGateway;
use App\Domain\Payments\Gateways\EasypaisaGateway;
use App\Domain\Payments\Gateways\JazzCashGateway;
use App\Domain\Payments\Gateways\SafepayGateway;
use InvalidArgumentException;

class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways;

    public function __construct(
        CodGateway $cod,
        EasypaisaGateway $easypaisa,
        JazzCashGateway $jazzCash,
        SafepayGateway $safepay,
    ) {
        foreach ([$cod, $easypaisa, $jazzCash, $safepay] as $g) {
            $this->gateways[$g->code()] = $g;
        }
    }

    public function get(string $code): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$code])) {
            throw new InvalidArgumentException('Unknown payment gateway: '.$code);
        }

        return $this->gateways[$code];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->gateways);
    }
}
