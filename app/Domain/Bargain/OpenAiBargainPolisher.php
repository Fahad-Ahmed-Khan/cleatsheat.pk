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
     * @param  'roman_urdu'|'english'|'mixed'  $languageHint
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
            'You write short chat replies as a Pakistani online shoe seller (WhatsApp / website chat vibe).',
            'You will receive conversation CONTEXT and a DRAFT shopkeeper message.',
            'Rewrite the draft in the same meaning. Do NOT copy it sentence-for-sentence — change openings, clause order, and rhythm.',
            'Never use the same opening grammatical pattern as the assistant’s immediately previous message in CONTEXT (if any).',
            'If the customer message is ultra-short, you may reply in one or two very short lines.',
            'Mirror energy: calm customer → calm; hype/casual → casual; blunt → slightly blunt but never rude.',
            'Language:',
            '- Language hint "roman_urdu": mostly Roman Urdu (Latin script), natural Karachi/Lahore ecommerce tone.',
            '- "english": clear Pakistani English.',
            '- "mixed": blend naturally; keep customer’s English phrases if they mixed; sprinkle Roman Urdu, don’t force 100% Urdu.',
            'Tone: human stall/shopkeeper — not corporate support, not polished “sales AI”.',
            'Banned phrases (never use, even paraphrased closely): "great investment", "great news", "secured for you", "happy shopping".',
            'Avoid: "I understand your budget", "Mujhe samajh aata hai", "sirf PKR", "is price par le sakte hain", "zaroor pasand aayenge", "unbeatable", "The best I can do is", "Considering the listed price" (prefer fresh angles).',
            'If FACTS include customer last stated amount, acknowledge it naturally before the shop line.',
            'If the draft contains a shop counter below list, that PKR must stay in your reply — never replace it with list-only refusal.',
            'Sometimes use ultra-short closers near a deal (examples only, do not always use): "Chalein done karte hain", "Aap close hain", "Itna kar deta hun", "Isi pe final kar dein".',
            'Do not over-thank, over-apologize, or repeat salaams if CONTEXT already greeted.',
            'Do not repeat identical PKR lines from CONTEXT unless the customer needs clarity.',
            'If you mention the product, keep it natural ("is pair", "ye color") and optional.',
            'Keep ALL PKR amounts EXACTLY as in the draft (same digits and two decimals). Do not invent or round new prices.',
            'Never mention minimum prices, margins, floors, discount caps, or internal limits.',
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
     * @param  'roman_urdu'|'english'|'mixed'  $languageHint
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

        if (isset($facts['negotiation_stage'])) {
            $lines[] = '- Negotiation stage: '.(string) $facts['negotiation_stage'];
        }
        if (isset($facts['tone_style'])) {
            $lines[] = '- Tone target: '.(string) $facts['tone_style'];
        }
        if (isset($facts['customer_seriousness'])) {
            $lines[] = '- Customer seriousness: '.(string) $facts['customer_seriousness'];
        }
        if (isset($facts['repetition_level'])) {
            $lines[] = '- Repetition level (0=low): '.(string) $facts['repetition_level'];
        }
        if (isset($facts['close_probability'])) {
            $lines[] = '- Close probability (heuristic): '.(string) $facts['close_probability'];
        }
        if (isset($facts['customer_intent'])) {
            $lines[] = '- Detected customer intent: '.(string) $facts['customer_intent'];
        }
        if (isset($facts['intent_confidence'])) {
            $lines[] = '- Intent confidence: '.(string) $facts['intent_confidence'];
        }
        $avoid = $facts['assistant_avoid_snippets'] ?? '';
        if (is_string($avoid) && trim($avoid) !== '') {
            $lines[] = '- Avoid opening/closing similar to recent assistant lines: '.$avoid;
        }

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
