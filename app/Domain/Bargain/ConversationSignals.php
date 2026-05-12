<?php

namespace App\Domain\Bargain;

/**
 * Pure read model from conversation text + stored meta (no policy secrets).
 */
final readonly class ConversationSignals
{
    /**
     * @param  list<string>  $customerOfferAmountsChronological  decimal strings with 2dp when parsed
     * @param  list<string>  $casualMarkersFound
     */
    public function __construct(
        public int $totalCustomerMessages,
        public int $totalAssistantMessages,
        public int $customerMessagesWithParsedAmount,
        public array $customerOfferAmountsChronological,
        public int $sameOfferStreakAtEnd,
        public bool $customerIsProgressing,
        public string $movementDirection,
        public float $momentumScore,
        public array $casualMarkersFound,
        public bool $assistantOpenerLikelyRepeated,
        public bool $customerUsedFinalOrLast,
        public bool $isUltraShortLastCustomer,
        public ?string $lastCustomerBodyRaw,
    ) {}
}
