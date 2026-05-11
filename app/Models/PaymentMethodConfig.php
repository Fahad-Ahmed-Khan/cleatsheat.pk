<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodConfig extends Model
{
    protected $fillable = [
        'gateway_code', 'enabled', 'customer_label', 'fee_fixed', 'fee_percent', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'fee_fixed' => 'decimal:2',
            'fee_percent' => 'decimal:4',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function enabledOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('gateway_code')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function enabledCodes(): array
    {
        return static::enabledOrdered()->pluck('gateway_code')->values()->all();
    }

    /**
     * Fee applied on subtotal − discount + shipping (before COD / gateway surcharge).
     */
    public function feeOnOrderBase(string $baseAmount): string
    {
        return bcadd(
            (string) $this->fee_fixed,
            bcmul($baseAmount, bcdiv((string) $this->fee_percent, '100', 6), 2),
            2
        );
    }
}
