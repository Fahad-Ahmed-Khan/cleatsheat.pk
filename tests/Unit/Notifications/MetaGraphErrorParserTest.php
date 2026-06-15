<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\WhatsApp\MetaGraphErrorParser;
use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class MetaGraphErrorParserTest extends TestCase
{
    public function test_summarizes_template_name_exists_subcode(): void
    {
        $response = new Response(
            new \GuzzleHttp\Psr7\Response(400, [], json_encode([
                'error' => [
                    'message' => 'Invalid parameter',
                    'type' => 'OAuthException',
                    'code' => 100,
                    'error_subcode' => 2388023,
                ],
            ]) ?: '{}'),
        );

        $message = MetaGraphErrorParser::summarize(
            new RequestException($response),
        );

        $this->assertStringContainsString('order_placed_v2', $message);
        $this->assertStringContainsString('2388023', $message);
    }

    public function test_default_meta_name_uses_v2_suffix(): void
    {
        $this->assertSame('order_placed_v2', WhatsAppTemplateSyncService::defaultMetaNameForKey('order_placed'));
    }
}
