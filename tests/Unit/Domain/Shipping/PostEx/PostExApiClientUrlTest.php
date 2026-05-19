<?php

namespace Tests\Unit\Domain\Shipping\PostEx;

use App\Domain\Shipping\PostEx\PostExApiClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostExApiClientUrlTest extends TestCase
{
    #[DataProvider('baseProvider')]
    public function test_normalize_configured_base_strips_integration_path_suffixes(string $configured, string $expected): void
    {
        $this->assertSame($expected, PostExApiClient::normalizeConfiguredBase($configured));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function baseProvider(): array
    {
        return [
            'host only' => ['https://api.postex.pk', 'https://api.postex.pk'],
            'trailing slash' => ['https://api.postex.pk/', 'https://api.postex.pk'],
            'full api prefix from docs' => ['https://api.postex.pk/services/integration/api', 'https://api.postex.pk'],
            'order segment' => ['https://api.postex.pk/services/integration/api/order', 'https://api.postex.pk'],
            'integration only' => ['https://api.postex.pk/services/integration', 'https://api.postex.pk'],
        ];
    }
}
