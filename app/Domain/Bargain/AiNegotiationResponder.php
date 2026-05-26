<?php

namespace App\Domain\Bargain;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiNegotiationResponder
{
    /**
     * @param  array<int, array{role:string, body:string}>  $contextMessages  Ordered oldest -> newest
     */
    public function respond(
        array $contextMessages,
        NegotiationDecision $decision,
        string $customerMessage,
        string $languageHint,
    ): string {
        if (! filter_var(config('bargain.ai.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        $apiKey = trim((string) config('bargain.ai.api_key', ''));
        if ($apiKey === '') {
            Log::warning('bargain.ai_negotiate_skipped', ['reason' => 'missing_api_key']);

            return '';
        }

        $baseUrl = rtrim((string) config('bargain.ai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('bargain.ai.model', 'gpt-4o-mini');
        $verifySsl = filter_var(config('bargain.ai.http_verify', true), FILTER_VALIDATE_BOOLEAN);
        $temperature = (float) config('bargain.ai.temperature', 0.75);
        $maxTokens = (int) config('bargain.ai.max_tokens', 220);

        $system = $this->systemPrompt();
        $user = $this->buildUserPrompt($contextMessages, $decision, $customerMessage, $languageHint);

        $text = $this->callOnce($baseUrl, $apiKey, $model, $verifySsl, $temperature, $maxTokens, $system, $user);
        if ($text === '') {
            return '';
        }

        if ($this->isValidOutput($text, $decision)) {
            return $text;
        }

        // One retry with a strict correction.
        $repair = implode("\n", [
            'You violated the pricing rule by mentioning a price that is NOT allowed.',
            'Rewrite your reply with the same intent, but do NOT mention ANY numbers except the allowed prices listed.',
            'Keep it short, natural Roman Urdu (or per language hint). Plain text only.',
            '',
            'Your previous reply:',
            $text,
        ]);
        $text2 = $this->callOnce($baseUrl, $apiKey, $model, $verifySsl, max(0.2, $temperature - 0.15), $maxTokens, $system, $user."\n\n---\n\n".$repair);

        return $this->isValidOutput($text2, $decision) ? $text2 : '';
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You are a real Pakistani ecommerce shoe seller chatting on WhatsApp-style website chat.',
            'You negotiate naturally in short Roman Urdu (or Pakistani English if user is English).',
            'You MUST follow strict rules:',
            '- Never invent prices. Never output any numeric price other than the allowed prices provided.',
            '- Never go above list price.',
            '- Never go below the allowed shop offer / locked price.',
            '- Never renegotiate after the deal is locked/accepted.',
            '- Never mention internal floors/margins/policy.',
            '- When allowed_action is counter or finalize and target_shop_offer is set, you MUST clearly state that shop price (PKR with two decimals). Do NOT reply with only the list price.',
            '- Acknowledge the customer’s latest amount when customer_offer is set (e.g. "PKR X thora low hai" / "PKR X note kiya").',
            '- If the customer increased their amount vs earlier messages, acknowledge the move briefly before your line.',
            '- Avoid robotic repetition; rotate openers — do NOT keep saying "Samajh gaya", "Mujhe samajh aata hai", or "sirf PKR … hai".',
            '- Never ask "is price par le sakte hain?" when you have not offered a lower shop price yet.',
            '- Do not quit too quickly: if customer says "no/nahi" but didn’t cancel, ask what price would work and try to close with a small friendly push (within allowed action/price constraints).',
            '- Don’t repeatedly ask for budget if the customer already gave a number.',
            'Style: friendly stall-owner, not corporate sales bot; keep under ~55 words; plain text only.',
        ]);
    }

    /**
     * @param  array<int, array{role:string, body:string}>  $contextMessages
     */
    private function buildUserPrompt(array $contextMessages, NegotiationDecision $d, string $customerMessage, string $languageHint): string
    {
        $allowedPrices = array_values(array_unique(array_filter([
            $d->listPricePkr,
            $d->currentShopOfferPkr,
            $d->targetShopOfferPkr,
            $d->acceptedPricePkr,
        ], fn ($v) => is_string($v) && $v !== '')));

        $ctx = [];
        foreach ($contextMessages as $m) {
            $role = (string) ($m['role'] ?? '');
            $body = trim((string) ($m['body'] ?? ''));
            if ($role === '' || $body === '') {
                continue;
            }
            $ctx[] = strtoupper($role).': '.$body;
        }

        $actionHint = match ($d->allowedAction) {
            'counter', 'finalize' => 'Reply must include target_shop_offer as your shop price. Mention customer_offer if set.',
            'needs_amount' => 'Ask for a PKR number once; do not lecture about list price only.',
            'welcome' => 'Greet briefly, mention list price, ask their budget — no hard sell.',
            default => 'Stay on-topic; do not repeat the same line as your last assistant message.',
        };

        return implode("\n", [
            'LANGUAGE_HINT: '.$languageHint,
            '',
            'ACTION_HINT: '.$actionHint,
            '',
            'STATE:',
            'derived_state='.$d->derivedState,
            'allowed_action='.$d->allowedAction,
            'list_price='.$d->listPricePkr,
            'current_shop_offer='.(string) ($d->currentShopOfferPkr ?? ''),
            'target_shop_offer='.(string) ($d->targetShopOfferPkr ?? ''),
            'customer_offer='.(string) ($d->customerOfferPkr ?? ''),
            'accepted_price='.(string) ($d->acceptedPricePkr ?? ''),
            'integrity_floor='.(string) ($d->integrityFloorPkr ?? ''),
            '',
            'ALLOWED_PRICES (you may only mention these, exactly):',
            implode(', ', $allowedPrices) ?: '(none)',
            '',
            'CONVERSATION (oldest -> newest):',
            $ctx !== [] ? implode("\n", $ctx) : '(no context)',
            '',
            'LATEST_CUSTOMER_MESSAGE:',
            $customerMessage,
        ]);
    }

    private function callOnce(
        string $baseUrl,
        string $apiKey,
        string $model,
        bool $verifySsl,
        float $temperature,
        int $maxTokens,
        string $system,
        string $user,
    ): string {
        try {
            $req = Http::timeout(45)
                ->connectTimeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson();

            if (! $verifySsl) {
                $req = $req->withOptions(['verify' => false]);
            }

            $res = $req->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

            if (! $res->successful()) {
                $decoded = $res->json();
                Log::warning('bargain.ai_negotiate_http_error', [
                    'status' => $res->status(),
                    'openai_message' => data_get($decoded, 'error.message'),
                    'openai_code' => data_get($decoded, 'error.code'),
                ]);

                return '';
            }

            /** @var array<string, mixed> $json */
            $json = $res->json();
            $content = data_get($json, 'choices.0.message.content');
            if (! is_string($content)) {
                return '';
            }

            $out = trim($content);
            if ($out === '') {
                return '';
            }

            // Keep it plain text.
            $out = preg_replace('/```[\\s\\S]*?```/u', '', $out) ?? $out;

            return trim($out);
        } catch (\Throwable $e) {
            Log::warning('bargain.ai_negotiate_exception', ['message' => $e->getMessage()]);

            return '';
        }
    }

    private function isValidOutput(string $text, NegotiationDecision $d): bool
    {
        $allowed = array_values(array_unique(array_filter([
            $d->listPricePkr,
            $d->currentShopOfferPkr,
            $d->targetShopOfferPkr,
            $d->acceptedPricePkr,
            $d->customerOfferPkr,
        ], fn ($v) => is_string($v) && $v !== '')));

        $found = $this->extractMentionedPkrAmounts($text);
        foreach ($found as $pkr) {
            if (! in_array($pkr, $allowed, true)) {
                return false;
            }
        }

        $target = $d->targetShopOfferPkr;
        if (in_array($d->allowedAction, ['counter', 'finalize'], true)
            && is_string($target)
            && $target !== '') {
            if (! in_array($target, $found, true)) {
                return false;
            }

            $list = $d->listPricePkr;
            if (bccomp($target, $list, 2) === -1 && $found !== []) {
                $nonList = array_values(array_filter($found, fn (string $p) => $p !== $list));
                if ($nonList === []) {
                    return false;
                }
            }
        }

        if ($this->containsBannedRoboticPhrases($text)) {
            return false;
        }

        return true;
    }

    private function containsBannedRoboticPhrases(string $text): bool
    {
        $t = mb_strtolower($text);
        $banned = [
            'mujhe samajh aata hai',
            'i understand your budget',
            'considering the listed price',
            'great investment',
            'happy shopping',
            'zaroor pasand aayenge',
            'unbeatable',
        ];
        foreach ($banned as $phrase) {
            if (str_contains($t, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> normalized decimals with 2dp (e.g. "10500.00")
     */
    private function extractMentionedPkrAmounts(string $text): array
    {
        $out = [];

        // Any explicit PKR/Rs mentions.
        if (preg_match_all('/\b(?:pkr|rs\.?)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\b/iu', $text, $m) >= 1) {
            foreach ($m[1] as $raw) {
                $n = str_replace(',', '', (string) $raw);
                if (is_numeric($n)) {
                    $out[] = number_format((float) $n, 2, '.', '');
                }
            }
        }

        // Also capture bare 4–7 digit numbers (common PKR) to prevent sneaky numbers.
        if (preg_match_all('/\b(\d{4,7})(?:\.\d{1,2})?\b/u', $text, $m2) >= 1) {
            foreach ($m2[0] as $raw) {
                $n = str_replace(',', '', (string) $raw);
                if (is_numeric($n)) {
                    $out[] = number_format((float) $n, 2, '.', '');
                }
            }
        }

        $out = array_values(array_unique($out));

        return $out;
    }
}
