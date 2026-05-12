<?php

namespace App\Domain\Bargain;

/**
 * Lightweight "memory" from recent assistant turns to steer variation (no DB migration).
 */
final readonly class AssistantRecentPhrases
{
    /**
     * @param  list<string>  $avoidSnippets  normalized short snippets (openers / first lines)
     */
    public function __construct(
        public array $avoidSnippets,
    ) {}

    /**
     * @param  list<array{role:string, body:string}>  $messagesChronological
     */
    public static function fromMessages(array $messagesChronological, int $takeLastAssistant = 4): self
    {
        $assistantBodies = [];
        foreach ($messagesChronological as $m) {
            if (($m['role'] ?? '') !== 'assistant') {
                continue;
            }
            $b = trim((string) ($m['body'] ?? ''));
            if ($b !== '') {
                $assistantBodies[] = $b;
            }
        }

        $lastFew = array_slice($assistantBodies, -$takeLastAssistant);
        $snippets = [];
        foreach ($lastFew as $body) {
            $firstLine = explode("\n", $body, 2)[0];
            $norm = mb_strtolower(preg_replace('/\s+/u', ' ', trim($firstLine)) ?? '');
            if (mb_strlen($norm) > 96) {
                $norm = mb_substr($norm, 0, 96);
            }
            if ($norm !== '') {
                $snippets[] = $norm;
            }
        }

        return new self(array_values(array_unique($snippets)));
    }
}
