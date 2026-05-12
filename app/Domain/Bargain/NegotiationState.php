<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainSession;

/**
 * Immutable negotiation view for copy generation only (no floor / margin data).
 */
final readonly class NegotiationState
{
    public function __construct(
        public NegotiationStage $negotiationStage,
        public string $customerSeriousness,
        public string $toneStyle,
        public string $customerProgress,
        public int $repetitionLevel,
        public float $closeProbability,
        public ConversationSignals $signals,
    ) {}

    /**
     * @param  list<array{id?:int, role:string, body:string, meta?:array<string, mixed>}>  $messages
     */
    public static function fromConversation(
        BargainSession $session,
        array $messages,
        ?string $customerOffer,
        ?string $shopOffer,
    ): self {
        $signals = (new ConversationAnalyzer)->analyze($messages);

        if ($session->state === BargainSessionState::Accepted) {
            return self::compose(
                NegotiationStage::Accepted,
                $signals,
                $customerOffer,
                $shopOffer,
                (string) $session->list_price,
            );
        }

        $list = (string) $session->list_price;

        if ($customerOffer === null) {
            $stage = $signals->customerOfferAmountsChronological === []
                ? NegotiationStage::Exploring
                : NegotiationStage::Negotiating;

            return self::compose($stage, $signals, null, $shopOffer, $list);
        }

        $stage = self::resolveStage($signals, $customerOffer, $shopOffer, $list);

        return self::compose($stage, $signals, $customerOffer, $shopOffer, $list);
    }

    private static function resolveStage(
        ConversationSignals $signals,
        string $customerOffer,
        ?string $shopOffer,
        string $listPrice,
    ): NegotiationStage {
        if ($signals->sameOfferStreakAtEnd >= 2 && count($signals->customerOfferAmountsChronological) >= 2) {
            return NegotiationStage::Frustrated;
        }

        $gapToListPct = self::percentBelowList($customerOffer, $listPrice);
        $isOpening = $signals->totalCustomerMessages <= 1
            && count($signals->customerOfferAmountsChronological) <= 1;

        if ($isOpening && bccomp($gapToListPct, '32', 2) === 1) {
            return NegotiationStage::Lowball;
        }
        if ($isOpening) {
            return NegotiationStage::Opening;
        }

        if (bccomp($gapToListPct, '28', 2) === 1) {
            return NegotiationStage::Lowball;
        }

        if ($shopOffer !== null && bccomp($shopOffer, '0', 2) === 1) {
            $absGap = self::absGapPkr($customerOffer, $shopOffer);
            $near = self::nearCloseThresholdPkr($listPrice);
            if (bccomp($absGap, $near, 2) !== 1) {
                if ($signals->customerUsedFinalOrLast || $signals->isUltraShortLastCustomer) {
                    return NegotiationStage::FinalPush;
                }

                return NegotiationStage::NearClose;
            }
        }

        if ($signals->customerMessagesWithParsedAmount === 0) {
            return NegotiationStage::Exploring;
        }

        return NegotiationStage::Negotiating;
    }

    private static function compose(
        NegotiationStage $stage,
        ConversationSignals $signals,
        ?string $customerOffer,
        ?string $shopOffer,
        string $listPrice,
    ): self {
        $seriousness = 'medium';
        if ($signals->customerIsProgressing || count($signals->customerOfferAmountsChronological) >= 3) {
            $seriousness = 'high';
        } elseif ($stage === NegotiationStage::Exploring || $stage === NegotiationStage::Opening) {
            $seriousness = 'low';
        }

        $progress = 'none';
        if ($signals->customerIsProgressing) {
            $progress = count($signals->customerOfferAmountsChronological) >= 3 ? 'strong' : 'good';
        } elseif (count($signals->customerOfferAmountsChronological) >= 2) {
            $progress = 'slow';
        }

        $tone = 'warm';
        if ($stage === NegotiationStage::Frustrated) {
            $tone = 'blunt';
        } elseif ($signals->isUltraShortLastCustomer) {
            $tone = 'ultra_short';
        } elseif (count($signals->casualMarkersFound) >= 1) {
            $tone = 'casual';
        }

        $rep = min(3, max(0, $signals->sameOfferStreakAtEnd > 0 ? $signals->sameOfferStreakAtEnd - 1 : 0));

        $p = 0.38;
        if ($stage === NegotiationStage::NearClose) {
            $p += 0.34;
        }
        if ($stage === NegotiationStage::FinalPush) {
            $p += 0.18;
        }
        if ($signals->customerIsProgressing) {
            $p += 0.12;
        }
        if ($stage === NegotiationStage::Frustrated) {
            $p -= 0.28;
        }
        if ($stage === NegotiationStage::Lowball) {
            $p -= 0.08;
        }
        if ($stage === NegotiationStage::Exploring) {
            $p -= 0.05;
        }
        if ($customerOffer !== null && $shopOffer !== null && bccomp($shopOffer, '0', 2) === 1) {
            $absGap = self::absGapPkr($customerOffer, $shopOffer);
            $near = self::nearCloseThresholdPkr($listPrice);
            if (bccomp($absGap, $near, 2) !== 1) {
                $p += 0.1;
            }
        }
        $p = max(0.0, min(1.0, $p));

        return new self(
            negotiationStage: $stage,
            customerSeriousness: $seriousness,
            toneStyle: $tone,
            customerProgress: $progress,
            repetitionLevel: $rep,
            closeProbability: $p,
            signals: $signals,
        );
    }

    private static function percentBelowList(string $customerOffer, string $listPrice): string
    {
        if (bccomp($listPrice, '0', 2) !== 1) {
            return '0.00';
        }
        $gap = bcsub($listPrice, $customerOffer, 2);
        if (bccomp($gap, '0', 2) !== 1) {
            return '0.00';
        }

        return bcmul(bcdiv($gap, $listPrice, 6), '100', 2);
    }

    private static function absGapPkr(string $a, string $b): string
    {
        if (bccomp($a, $b, 2) >= 0) {
            return bcsub($a, $b, 2);
        }

        return bcsub($b, $a, 2);
    }

    private static function nearCloseThresholdPkr(string $listPrice): string
    {
        $pct = bcmul($listPrice, '0.04', 2);
        $min = '300.00';

        return bccomp($pct, $min, 2) === 1 ? $pct : $min;
    }
}
