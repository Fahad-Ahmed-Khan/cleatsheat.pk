<?php

namespace Tests\Unit\Domain;

use App\Domain\Catalog\SearchQueryNormalizer;
use Tests\TestCase;

class SearchQueryNormalizerTest extends TestCase
{
    public function test_strips_fulltext_operators(): void
    {
        $this->assertSame('nike mercurial', SearchQueryNormalizer::normalize('nike +mercurial'));
    }

    public function test_boolean_query_prefixes_tokens(): void
    {
        $this->assertSame('+nike* +mercurial*', SearchQueryNormalizer::booleanQuery('nike mercurial'));
    }
}
