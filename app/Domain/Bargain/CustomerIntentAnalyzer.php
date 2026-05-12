<?php

namespace App\Domain\Bargain;

use App\Models\BargainSession;

final class CustomerIntentAnalyzer
{
    public function analyze(
        string $message,
        BargainSession $session,
        ?string $parsedOffer,
        ConversationSignals $priorSignals,
    ): CustomerIntentResult {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($message)) ?? '');
        $stripped = preg_replace('/\p{Extended_Pictographic}/u', '', $normalized) ?? '';
        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        if ($normalized === '') {
            return new CustomerIntentResult(CustomerIntentType::Unknown, 0.2, $parsedOffer, $normalized, ['empty']);
        }

        if ($stripped === '' && preg_match('/\p{Extended_Pictographic}/u', $message) === 1) {
            return new CustomerIntentResult(CustomerIntentType::Unknown, 0.25, $parsedOffer, $normalized, ['emoji_only']);
        }

        $currentOffer = $session->current_offer !== null ? (string) $session->current_offer : null;

        [$greetingHit, $gHits] = $this->scanPatterns($normalized, [
            '/^(assalam|salam|aoa|ao\s*salam|hello|hi|hey)\b/u' => 'greeting',
        ]);
        if ($greetingHit && mb_strlen($stripped) <= 28) {
            return new CustomerIntentResult(CustomerIntentType::Greeting, 0.82, $parsedOffer, $normalized, $gHits);
        }

        [$exitHit, $eHits] = $this->scanPatterns($normalized, [
            '/\b(cancel\s*order|order\s*cancel|nahi\s*lena|nahin\s*lena|pass\s*kar|leave\s*it|forget\s*it)\b/u' => 'exit_strong',
        ]);
        if ($exitHit) {
            return new CustomerIntentResult(CustomerIntentType::Exit, 0.88, $parsedOffer, $normalized, $eHits);
        }

        [$rejHit, $rHits] = $this->scanPatterns($normalized, [
            '/\b(reject|na\s*manzoor|nahi\s*chahiye|not\s*interested)\b/u' => 'reject',
        ]);
        if ($rejHit) {
            return new CustomerIntentResult(CustomerIntentType::Reject, 0.8, $parsedOffer, $normalized, $rHits);
        }

        if ($priorSignals->sameOfferStreakAtEnd >= 2 && preg_match('/\bnahi\b/u', $normalized) === 1) {
            return new CustomerIntentResult(CustomerIntentType::Reject, 0.76, $parsedOffer, $normalized, ['frustrated_nahi']);
        }

        [$discHit, $dHits] = $this->scanPatterns($normalized, [
            '/\b(kuch\s+discount|discount\s*mil|thora\s*kam|thori\s*kam|kam\s*kar|adjust\s*kar|kitna\s*kam|kitni\s*discount)\b/u' => 'ask_discount',
            '/\b(last\s*price|last\s*rate|best\s*kya|best\s*price|best\s*milega|kam\s*se\s*kam)\b/u' => 'ask_discount_en',
        ]);
        if ($discHit) {
            return new CustomerIntentResult(CustomerIntentType::AskDiscount, 0.86, $parsedOffer, $normalized, $dHits);
        }

        [$bestHit, $bHits] = $this->scanPatterns($normalized, [
            '/\b(best\s*kya\s*hoga|best\s*kya\s*hogi|lowest\s*kya|minimum\s*kya|final\s*rate)\b/u' => 'ask_best',
        ]);
        if ($bestHit) {
            return new CustomerIntentResult(CustomerIntentType::AskBestPrice, 0.84, $parsedOffer, $normalized, $bHits);
        }

        [$hesHit, $hesHits] = $this->scanPatterns($normalized, [
            '/\b(done|final|scene|okay|ok|theek|lock|confirm)\s*\?/u' => 'hesitation_q',
        ]);
        if ($hesHit) {
            return new CustomerIntentResult(CustomerIntentType::Hesitation, 0.72, $parsedOffer, $normalized, $hesHits);
        }

        [$strongAccept, $hitsAccept] = $this->scanPatterns($normalized, [
            '/\b(pack\s*kar|pack\s*kardo|order\s*karna|order\s*lag|book\s*kar|lock\s*kar|checkout)\b/u' => 'accept_action',
            '/\b(confirm|confirmed|accepted|accept)\b/u' => 'accept_en',
            '/\b(theek\s*hai|thik\s*hai|teak\s*hy|chalega|chale\s*ga|done|final\s*hai|final\s*hog|may\s*accept|main\s*accept)\b/u' => 'accept_ur',
            '/\b(apki\s*price\s*theek|aapki\s*price\s*theek|price\s*theek\s*hai)\b/u' => 'accept_price_ok',
        ]);

        if ($strongAccept && $currentOffer !== null) {
            if ($parsedOffer === null || $this->parsedAmountCompatibleWithAccept($parsedOffer, $currentOffer, $session)) {
                return new CustomerIntentResult(CustomerIntentType::Accept, 0.92, $parsedOffer, $normalized, $hitsAccept);
            }
        }

        if ($parsedOffer !== null && $this->looksOfferDominant($message, $normalized)) {
            if ($strongAccept && $parsedOffer !== null && ! $this->parsedAmountCompatibleWithAccept($parsedOffer, $currentOffer, $session)) {
                return new CustomerIntentResult(CustomerIntentType::Offer, 0.78, $parsedOffer, $normalized, array_merge(['numeric_dominant'], $hitsAccept));
            }

            return new CustomerIntentResult(CustomerIntentType::Offer, 0.8, $parsedOffer, $normalized, ['numeric_dominant']);
        }

        [$weakAccept, $hitsWeak] = $this->scanPatterns($normalized, [
            '/^(ok|okay|theek|thik|han|haan|yes|yep|done|chalo|go|sahi|sahi\s*hai)\.?$/u' => 'accept_short',
            '/\b(lock|book|confirm|accept)\b/u' => 'accept_soft',
        ]);
        if ($currentOffer !== null && $weakAccept) {
            return new CustomerIntentResult(CustomerIntentType::Accept, 0.78, $parsedOffer, $normalized, $hitsWeak);
        }

        [$confHit, $cHits] = $this->scanPatterns($normalized, [
            '/\b(samajh\s*nahi|confus|smjh\s*nahi|kya\s*matlab)\b/u' => 'confused',
        ]);
        if ($confHit) {
            return new CustomerIntentResult(CustomerIntentType::Confused, 0.75, $parsedOffer, $normalized, $cHits);
        }

        if (preg_match('/^(done|theek\s*hai|thik\s*hai|okay|ok|confirm|chalega)\.?$/u', $normalized) === 1) {
            return new CustomerIntentResult(CustomerIntentType::Accept, 0.78, $parsedOffer, $normalized, ['bare_accept_word']);
        }

        if (mb_strlen($stripped) <= 4) {
            return new CustomerIntentResult(CustomerIntentType::Unknown, 0.35, $parsedOffer, $normalized, ['very_short']);
        }

        return new CustomerIntentResult(CustomerIntentType::Unknown, 0.45, $parsedOffer, $normalized, ['fallback']);
    }

    private function parsedAmountCompatibleWithAccept(?string $parsed, ?string $currentOffer, BargainSession $session): bool
    {
        if ($parsed === null) {
            return true;
        }
        $list = (string) $session->list_price;
        if (bccomp($parsed, $list, 2) === 1) {
            return false;
        }
        if ($currentOffer !== null && bccomp($parsed, $currentOffer, 2) === -1) {
            return false;
        }

        return true;
    }

    private function looksOfferDominant(string $raw, string $normalized): bool
    {
        if (preg_match('/\b(pkr|rs\.?|rupee)\b/i', $raw) === 1) {
            return true;
        }
        if (preg_match('/\b\d{3,7}(?:\.\d{1,2})?\b/', $normalized) === 1) {
            return preg_match('/\b(done|theek|accept|final)\b/u', $normalized) !== 1;
        }

        return false;
    }

    /**
     * @param  array<string, string>  $patterns  regex => label
     * @return array{0: bool, 1: list<string>}
     */
    private function scanPatterns(string $normalized, array $patterns): array
    {
        $hits = [];
        foreach ($patterns as $rx => $label) {
            if (preg_match($rx, $normalized) === 1) {
                $hits[] = (string) $label;
            }
        }

        return [$hits !== [], $hits];
    }
}
