<?php

namespace App\Domain\Bargain;

final class SeededRng
{
    private int $stateLo;

    private int $stateHi;

    public function __construct(string $seedMaterial)
    {
        $hash = hash('sha256', $seedMaterial, true);
        $lo = substr($hash, 0, 8);
        $hi = substr($hash, 8, 8);

        // unpack as two 32-bit parts to avoid platform int size differences
        $a = unpack('N2', $lo);
        $b = unpack('N2', $hi);

        $this->stateLo = (int) (($a[1] << 1) ^ $a[2] ^ 0x9E3779B9);
        $this->stateHi = (int) (($b[1] << 1) ^ $b[2] ^ 0xBB67AE85);

        if ($this->stateLo === 0 && $this->stateHi === 0) {
            $this->stateLo = 0x12345678;
            $this->stateHi = 0x9ABCDEF0;
        }
    }

    /**
     * @return float in [0, 1)
     */
    public function float01(): float
    {
        $u = $this->nextUint32();

        return $u / 4294967296.0;
    }

    private function nextUint32(): int
    {
        // xorshift-ish 64-bit variant approximated with two 32-bit ints for portability.
        $x = $this->stateLo;
        $y = $this->stateHi;

        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17) & 0xFFFFFFFF;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        $t = ($x + $y) & 0xFFFFFFFF;

        $this->stateLo = $y;
        $this->stateHi = $t;

        return $t;
    }
}
