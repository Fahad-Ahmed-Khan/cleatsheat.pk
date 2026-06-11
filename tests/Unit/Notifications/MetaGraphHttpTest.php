<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\WhatsApp\MetaGraphHttp;
use GuzzleHttp\Handler\StreamHandler;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\TestCase;

class MetaGraphHttpTest extends TestCase
{
    public function test_cli_defaults_to_stream_handler_when_unconfigured(): void
    {
        Config::set('whatsapp.http.handler', null);

        $request = MetaGraphHttp::client();
        $options = $request->getOptions();

        $this->assertArrayNotHasKey('handler', $options);

        if (PHP_SAPI === 'cli') {
            $this->assertInstanceOf(StreamHandler::class, $this->requestHandler($request));
        } else {
            $this->assertNull($this->requestHandler($request));
            $this->assertArrayHasKey('curl', $options);
        }
    }

    public function test_can_force_curl_handler_via_config(): void
    {
        Config::set('whatsapp.http.handler', 'curl');

        $options = MetaGraphHttp::client()->getOptions();

        $this->assertArrayNotHasKey('handler', $options);
        $this->assertArrayHasKey('curl', $options);
    }

    public function test_can_force_stream_handler_via_config(): void
    {
        Config::set('whatsapp.http.handler', 'stream');

        $request = MetaGraphHttp::client();

        $this->assertArrayNotHasKey('handler', $request->getOptions());
        $this->assertInstanceOf(StreamHandler::class, $this->requestHandler($request));
    }

    public function test_stream_handler_respects_http_fake(): void
    {
        Config::set('whatsapp.http.handler', 'stream');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['ok' => true], 200),
        ]);

        $response = MetaGraphHttp::client()
            ->post('https://graph.facebook.com/v21.0/123/messages', ['type' => 'template']);

        $this->assertTrue($response->successful());
        Http::assertSentCount(1);
    }

    private function requestHandler(PendingRequest $request): mixed
    {
        $property = new ReflectionProperty($request, 'handler');

        return $property->getValue($request);
    }
}
