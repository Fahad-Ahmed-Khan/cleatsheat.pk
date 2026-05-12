<?php

namespace App\Domain\Bargain;

use App\Models\BargainMessage;

/**
 * Single DB load of recent messages: full window for analysis + trimmed slice for the LLM.
 */
final readonly class ConversationWindow
{
    /**
     * @param  list<array{id:int, role:string, body:string, meta:array<string, mixed>}>  $messagesChronological  oldest first
     */
    public function __construct(
        public array $messagesChronological,
    ) {}

    public static function load(int $sessionId): self
    {
        $limit = (int) config('bargain.ai.analyzer.max_messages', 50);
        $rows = BargainMessage::query()
            ->where('bargain_session_id', $sessionId)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get(['id', 'role', 'body', 'meta'])
            ->reverse()
            ->values();

        $chron = [];
        foreach ($rows as $r) {
            $meta = $r->meta;
            $chron[] = [
                'id' => (int) $r->id,
                'role' => (string) $r->role,
                'body' => (string) $r->body,
                'meta' => is_array($meta) ? $meta : [],
            ];
        }

        return new self($chron);
    }

    /**
     * @return array{
     *     context: list<array{role:string, body:string}>,
     *     language_hint: 'roman_urdu'|'english'|'mixed',
     *     last_customer_offer: ?string,
     *     assistant_phrases: AssistantRecentPhrases
     * }
     */
    public function buildAiContextPackage(): array
    {
        $maxMessages = (int) config('bargain.ai.context.max_messages', 6);
        $maxCharsPer = (int) config('bargain.ai.context.max_chars_per_message', 300);
        $maxTotal = (int) config('bargain.ai.context.max_total_chars', 1800);

        $tail = array_slice($this->messagesChronological, -max(1, $maxMessages));

        $context = [];
        $total = 0;
        $lastCustomerBody = null;
        $lastCustomerOffer = null;

        foreach ($tail as $m) {
            $body = preg_replace('/\s+/u', ' ', trim($m['body'])) ?? '';
            if ($body === '') {
                continue;
            }
            if (mb_strlen($body) > $maxCharsPer) {
                $body = mb_substr($body, 0, $maxCharsPer);
            }

            $addLen = mb_strlen($body);
            if ($total + $addLen > $maxTotal) {
                break;
            }

            $context[] = ['role' => $m['role'], 'body' => $body];
            $total += $addLen;

            if ($m['role'] === 'customer') {
                $lastCustomerBody = $body;
                $parsed = $m['meta']['parsed_offer'] ?? null;
                $lastCustomerOffer = is_string($parsed) && $parsed !== '' ? $parsed : $lastCustomerOffer;
            }
        }

        $phraseInput = [];
        foreach ($this->messagesChronological as $m) {
            $phraseInput[] = [
                'role' => $m['role'],
                'body' => $m['body'],
            ];
        }

        $hint = BargainLanguageHint::fromCustomerText($lastCustomerBody ?? '');

        return [
            'context' => $context,
            'language_hint' => $hint,
            'last_customer_offer' => $lastCustomerOffer,
            'assistant_phrases' => AssistantRecentPhrases::fromMessages($phraseInput),
        ];
    }
}
