<?php

namespace App\Domain\Bargain;

/**
 * Session-scoped inputs for concession stepping (no policy secrets).
 */
final readonly class ConcessionContext
{
    public function __construct(
        public int $concessionCount = 0,
        public int $resistanceScore = 0,
        public int $sameOfferStreakAtEnd = 0,
        public bool $stubbornCustomerMode = false,
        public ?string $integrityFloorPkr = null,
    ) {}
}
