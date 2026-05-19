<?php

namespace App\Domain\Bargain;

final readonly class NegotiationDecision
{
    /**
     * @param  'counter'|'finalize'|'accept_prompt'|'answer_question'|'reject'|'casual_reply'|'needs_amount'|'welcome'  $allowedAction
     */
    public function __construct(
        public string $allowedAction,
        public ?string $targetShopOfferPkr,
        public ?string $customerOfferPkr,
        public string $derivedState,
        public string $listPricePkr,
        public ?string $currentShopOfferPkr,
        public ?string $acceptedPricePkr,
        public ?string $integrityFloorPkr,
    ) {}
}
