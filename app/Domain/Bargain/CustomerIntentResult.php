<?php

namespace App\Domain\Bargain;

/**
 * @param  list<string>  $matchedPatterns
 */
final readonly class CustomerIntentResult
{
    /**
     * @param  list<string>  $matchedPatterns
     */
    public function __construct(
        public CustomerIntentType $type,
        public float $confidence,
        public ?string $extractedOffer,
        public string $normalizedText,
        public array $matchedPatterns,
    ) {}
}
