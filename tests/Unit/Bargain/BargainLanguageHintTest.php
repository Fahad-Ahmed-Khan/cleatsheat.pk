<?php

namespace Tests\Unit\Bargain;

use App\Domain\Bargain\BargainLanguageHint;
use PHPUnit\Framework\TestCase;

class BargainLanguageHintTest extends TestCase
{
    public function test_plain_english_price_stays_english(): void
    {
        $this->assertSame('english', BargainLanguageHint::fromCustomerText('Can you do PKR 850?'));
    }

    public function test_roman_urdu_markers(): void
    {
        $this->assertSame('roman_urdu', BargainLanguageHint::fromCustomerText('bhai yar scene ye hai, PKR 10k final kar dein'));
    }

    public function test_mixed_when_both_signals(): void
    {
        $t = 'Please bhi consider kar dein, yar thora sa discount ho jaye';
        $hint = BargainLanguageHint::fromCustomerText($t);
        $this->assertContains($hint, ['mixed', 'roman_urdu']);
    }
}
