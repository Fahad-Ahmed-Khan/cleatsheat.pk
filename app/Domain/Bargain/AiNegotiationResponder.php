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
            '- Avoid robotic repetition; do NOT keep saying the same opener (e.g. avoid repeating "Samajh gaya").',
            '- Do not quit too quickly: if customer says "no/nahi" but didn’t cancel, ask what price would work and try to close with a small friendly push (within allowed action/price constraints).',
            '- Don’t repeatedly ask for budget if the customer already gave a number.',
            'Style: friendly, natural, sometimes light humor; keep under ~60 words; plain text only.',
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

        return implode("\n", [
            'LANGUAGE_HINT: '.$languageHint,
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
        // If session is locked, disallow negotiation language changes via numbers.
        if ($d->derivedState === 'locked' && preg_match('/\b(counter|offer|kam|reduce|less)\b/i', $text) === 1) {
            // Still allow “guide to checkout”, but don’t hard-block—only block if it mentions numbers not allowed.
        }

        $allowed = array_values(array_unique(array_filter([
            $d->listPricePkr,
            $d->currentShopOfferPkr,
            $d->targetShopOfferPkr,
            $d->acceptedPricePkr,
        ], fn ($v) => is_string($v) && $v !== '')));

        $found = $this->extractMentionedPkrAmounts($text);
        foreach ($found as $pkr) {
            if (! in_array($pkr, $allowed, true)) {
                return false;
            }
        }

        return true;
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
