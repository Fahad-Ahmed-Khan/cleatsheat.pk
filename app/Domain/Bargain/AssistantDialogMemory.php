<?php

namespace App\Domain\Bargain;

/**
 * Lightweight scan of recent assistant turns to avoid repetitive budget prompts.
 */
final readonly class AssistantDialogMemory
{
    public function __construct(
        public int $budgetPromptCount,
        public int $welcomeAskCount,
    ) {}

    /**
     * @param  list<array{id?:int, role:string, body:string, meta?:array<string, mixed>}>  $messagesChronological
     */
    public static function fromMessages(array $messagesChronological, int $lookbackAssistant = 12): self
    {
        $budget = 0;
        $welcome = 0;
        $assistantSeen = 0;
        foreach (array_reverse($messagesChronological) as $m) {
            if (($m['role'] ?? '') !== 'assistant') {
                continue;
            }
            if ($assistantSeen >= $lookbackAssistant) {
                break;
            }
            $assistantSeen++;
            $meta = is_array($m['meta'] ?? null) ? $m['meta'] : [];
            $kind = (string) ($meta['kind'] ?? '');
            if ($kind === 'needs_amount') {
                $budget++;
            }
            if ($kind === 'welcome') {
                $welcome++;
            }
            $body = mb_strtolower((string) ($m['body'] ?? ''));
            if ($kind === '' && (str_contains($body, 'budget') || str_contains($body, 'pkr me') || str_contains($body, 'amount'))) {
                $budget++;
            }
        }

        return new self($budget, $welcome);
    }
}
