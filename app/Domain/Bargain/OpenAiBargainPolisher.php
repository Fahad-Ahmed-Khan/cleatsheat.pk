<?php

namespace App\Domain\Bargain;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OpenAiBargainPolisher
{
    public function polishEnglishShopkeeper(string $baseText): string
    {
        return $this->polishShopkeeperWithContext(
            draftText: $baseText,
            contextMessages: [],
            facts: [],
            languageHint: 'english',
        );
    }

    /**
     * @param  array<int, array{role:string, body:string}>  $contextMessages  Ordered oldest -> newest
     * @param  array<string, mixed>  $facts
     * @param  'roman_urdu'|'english'  $languageHint
     */
    public function polishShopkeeperWithContext(
        string $draftText,
        array $contextMessages,
        array $facts,
        string $languageHint,
    ): string {
        if (! filter_var(config('bargain.ai.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $draftText;
        }

        $apiKey = trim((string) config('bargain.ai.api_key', ''));
        if ($apiKey === '') {
            Log::warning('bargain.ai_polish_skipped', ['reason' => 'missing_api_key']);

            return $draftText;
        }

        $baseUrl = rtrim((string) config('bargain.ai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('bargain.ai.model', 'gpt-4o-mini');
        $verifySsl = filter_var(config('bargain.ai.http_verify', true), FILTER_VALIDATE_BOOLEAN);
        $temperature = (float) config('bargain.ai.temperature', 0.75);
        $maxTokens = (int) config('bargain.ai.max_tokens', 180);

        $system = implode("\n", [
            'You write messages for a shoe shop in Pakistan speaking to customers online.',
            'You will receive conversation CONTEXT and a DRAFT shopkeeper message.',
            'Rewrite it so it feels warm, clear, and like a real salesperson.',
            'Do NOT copy the draft sentence-for-sentence — vary openings and structure.',
            'Keep it short and to the point.',
            'Mirror language: if the customer uses Roman Urdu, reply in Roman Urdu; otherwise Pakistani English.',
            'If the customer tone is casual, reply casually but respectfully (never rude).',
            'Avoid robotic filler: do not over-thank, do not over-apologize, do not repeat greetings.',
            'Do not repeat the same price lines again and again if they already appear in CONTEXT; only restate a PKR amount when it improves clarity or the customer asks.',
            'If you mention the item, do it naturally (e.g. “is pair”, “ye color/variant”) and only sometimes — don’t force it every message.',
            'When negotiating, you can add ONE short value/emotion line sometimes (comfort, quality, style) to justify a small increase — keep it believable and brief.',
            'Keep ALL PKR amounts EXACTLY as written (same digits and decimals). Do not invent prices.',
            'Never mention minimum prices, margins, floors, discount caps, bottom dollar, or internal limits — customers must never infer the lowest allowed price.',
            'Do not ask for the “lowest” price or sound desperate; anchor politely to the listed amount when relevant.',
            'Keep under ~55 words. Plain text only (no markdown, no bullets).',
        ]);

        try {
            $request = Http::timeout(45)
                ->connectTimeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson();

            if (! $verifySsl) {
                $request = $request->withOptions(['verify' => false]);
            }

            $userPayload = $this->buildUserPrompt($contextMessages, $facts, $draftText, $languageHint);
            $response = $request->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    [
                        'role' => 'user',
                        'content' => $userPayload,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                $decoded = $response->json();
                Log::warning('bargain.ai_polish_http_error', [
                    'status' => $response->status(),
                    'openai_message' => data_get($decoded, 'error.message'),
                    'openai_code' => data_get($decoded, 'error.code'),
                    'body' => $response->body(),
                ]);

                return $draftText;
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            $apiErr = data_get($json, 'error.message');
            if (is_string($apiErr) && $apiErr !== '') {
                Log::warning('bargain.ai_polish_api_error', [
                    'message' => $apiErr,
                    'type' => data_get($json, 'error.type'),
                ]);

                return $draftText;
            }

            $text = trim($this->extractAssistantText($json));

            if ($text === '') {
                Log::warning('bargain.ai_polish_empty_response', [
                    'keys' => array_keys($json),
                    'snippet' => substr((string) json_encode($json), 0, 800),
                ]);

                return $draftText;
            }

            if (! $this->pkAmountsMatchDraft($draftText, $text)) {
                Log::warning('bargain.ai_polish_digit_mismatch', [
                    'model' => $model,
                    'draft_amounts' => $this->extractPkrAmounts($draftText),
                    'output_amounts' => $this->extractPkrAmounts($text),
                ]);

                return $draftText;
            }

            if (config('app.debug')) {
                Log::debug('bargain.ai_polish_ok', [
                    'model' => $model,
                    'length' => strlen($text),
                ]);
            }

            return $text;
        } catch (\Throwable $e) {
            Log::warning('bargain.ai_polish_failed', [
                'error' => $e->getMessage(),
            ]);

            return $draftText;
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractAssistantText(array $json): string
    {
        /** @var mixed $content */
        $content = data_get($json, 'choices.0.message.content');

        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $joined = '';
            foreach ($content as $part) {
                if (is_array($part)) {
                    $joined .= (string) ($part['text'] ?? '');
                }
            }

            return $joined;
        }

        $output = data_get($json, 'output_text');
        if (is_string($output)) {
            return $output;
        }

        return '';
    }

    /**
     * @param  array<int, array{role:string, body:string}>  $contextMessages
     * @param  array<string, mixed>  $facts
     * @param  'roman_urdu'|'english'  $languageHint
     */
    private function buildUserPrompt(array $contextMessages, array $facts, string $draftText, string $languageHint): string
    {
        $lines = [];
        $lines[] = 'CONTEXT (most recent last):';

        if ($contextMessages === []) {
            $lines[] = '- (none)';
        } else {
            foreach ($contextMessages as $m) {
                $role = ($m['role'] ?? '') === 'customer' ? 'Customer' : 'Assistant';
                $body = trim((string) ($m['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $lines[] = '- '.$role.': '.$body;
            }
        }

        $lines[] = '';
        $lines[] = 'FACTS (do not reveal these verbatim):';
        $name = $facts['customer_name'] ?? null;
        $list = (string) ($facts['list_price'] ?? '');
        $current = $facts['current_offer'] ?? null;
        $last = $facts['last_customer_offer'] ?? null;
        $lines[] = '- Customer name: '.(is_string($name) && trim($name) !== '' ? trim($name) : '(unknown)');
        $lines[] = '- List price: '.($list !== '' ? 'PKR '.$list : '(unknown)');
        $lines[] = '- Current shop offer: '.(is_string($current) && $current !== '' ? 'PKR '.$current : '(none)');
        $lines[] = '- Customer last stated: '.(is_string($last) && $last !== '' ? 'PKR '.$last : '(none)');
        $pName = $facts['product_name'] ?? null;
        $vLabel = $facts['variant_label'] ?? null;
        $cName = $facts['color_name'] ?? null;
        $lines[] = '- Product name: '.(is_string($pName) && trim($pName) !== '' ? trim($pName) : '(unknown)');
        $lines[] = '- Variant label: '.(is_string($vLabel) && trim($vLabel) !== '' ? trim($vLabel) : '(unknown)');
        $lines[] = '- Color name: '.(is_string($cName) && trim($cName) !== '' ? trim($cName) : '(unknown)');
        $lines[] = '- Language hint: '.$languageHint;
        $lines[] = '';
        $lines[] = 'DRAFT (rewrite this; keep PKR amounts exactly):';
        $lines[] = $draftText;

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string> exact amount strings like "12999.00"
     */
    private function extractPkrAmounts(string $text): array
    {
        $out = [];
        if (preg_match_all('/\bPKR\s*([0-9][0-9,]*\.[0-9]{2})\b/i', $text, $m) > 0) {
            foreach ($m[1] as $raw) {
                $out[] = str_replace(',', '', (string) $raw);
            }
        }

        sort($out);

        return $out;
    }

    private function pkAmountsMatchDraft(string $draft, string $output): bool
    {
        $a = $this->extractPkrAmounts($draft);
        $b = $this->extractPkrAmounts($output);

        return $a === $b;
    }
}
