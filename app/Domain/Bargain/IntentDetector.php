<?php

namespace App\Domain\Bargain;

final class IntentDetector
{
    /**
     * @return array{type: string, confidence: float, matched: list<string>}
     */
    public function detect(string $message, ?string $parsedOfferPkr = null): array
    {
        $t = mb_strtolower(trim($message));
        if ($t === '') {
            return ['type' => 'casual_chat', 'confidence' => 0.4, 'matched' => ['empty']];
        }

        $matched = [];

        // Explicit offer intent should beat generic question marks.
        if ($parsedOfferPkr !== null && $parsedOfferPkr !== '') {
            $matched[] = 'offer_amount';

            return ['type' => 'offer', 'confidence' => 0.92, 'matched' => $matched];
        }

        // Accept / finalize intent (strong).
        $acceptPatterns = [
            '/\b(done|ok|okay|oky|theek|thik|theek hai|thik hai|confirm|accept|lock)\b/u',
            '/\b(lock\s*kar\s*do|lock\s*kardo|confirm\s*kar\s*do|pakka|final)\b/u',
            '/\b(ho\s*gaya|ho\s*gia|done\s*hai)\b/u',
        ];
        foreach ($acceptPatterns as $re) {
            if (preg_match($re, $t) === 1) {
                $matched[] = 'accept';

                return ['type' => 'accept', 'confidence' => 0.92, 'matched' => $matched];
            }
        }

        // Hard exit: customer is clearly walking away / cancelling.
        if (preg_match('/\b(cancel|chor\s*do|chhor\s*do|leave|rehne\s*do|close\s*it|end\s*it)\b/u', $t) === 1) {
            $matched[] = 'exit';

            return ['type' => 'exit', 'confidence' => 0.9, 'matched' => $matched];
        }

        // Soft reject: "no / nahi" means pushback, not necessarily leaving.
        if (preg_match('/\b(nahi|nahin|no)\b/u', $t) === 1) {
            $matched[] = 'reject_soft';

            return ['type' => 'reject_soft', 'confidence' => 0.74, 'matched' => $matched];
        }

        // Discount / best price asks.
        if (preg_match('/\b(discount|off|kam\s*kar|km\s*kar|best\s*price|best|last\s*price|final\s*price)\b/u', $t) === 1) {
            $matched[] = 'discount';
            $best = preg_match('/\b(best\s*price|last\s*price|final\s*price)\b/u', $t) === 1;

            return ['type' => $best ? 'ask_best_price' : 'ask_discount', 'confidence' => 0.72, 'matched' => $matched];
        }

        // Greetings.
        if (preg_match('/\b(ass?alam|salam|aoa|hi|hello|hey)\b/u', $t) === 1) {
            $matched[] = 'greeting';

            return ['type' => 'greeting', 'confidence' => 0.7, 'matched' => $matched];
        }

        // Questions (delivery, size, original, etc.)
        $questionPatterns = [
            '/\b(delivery|shipping|cod|cash\s*on\s*delivery|return|exchange|size|available|stock|original|authentic)\b/u',
            '/\b(kya|kaise|kab|kitne\s*din|delivery\s*free|free\s*delivery)\b/u',
            '/\?$/u',
        ];
        foreach ($questionPatterns as $re) {
            if (preg_match($re, $t) === 1) {
                $matched[] = 'question';

                return ['type' => 'question', 'confidence' => 0.72, 'matched' => $matched];
            }
        }

        // Confusion.
        if (preg_match('/\b(samajh\s*nahi|confuse|what\??|kahein|matlab)\b/u', $t) === 1) {
            $matched[] = 'confusion';

            return ['type' => 'confusion', 'confidence' => 0.66, 'matched' => $matched];
        }

        if (preg_match('/\b(pkr|rs\.?)\b/u', $t) === 1 && preg_match('/\d/u', $t) === 1) {
            $matched[] = 'offer_currency';

            return ['type' => 'offer', 'confidence' => 0.65, 'matched' => $matched];
        }

        // Casual chat / off-topic.
        if (preg_match('/\b(haha|hehe|lol|funny|yar|yaar|bhai|boss)\b/u', $t) === 1) {
            $matched[] = 'casual';

            return ['type' => 'casual_chat', 'confidence' => 0.6, 'matched' => $matched];
        }

        return ['type' => 'casual_chat', 'confidence' => 0.45, 'matched' => ['default']];
    }
}
