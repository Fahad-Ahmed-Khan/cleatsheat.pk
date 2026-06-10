<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\WhatsApp\MetaGraphHttp;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MetaGraphHttpTest extends TestCase
{
    public function test_cli_defaults_to_stream_handler_when_unconfigured(): void
    {
        Config::set('whatsapp.http.handler', null);

        $options = MetaGraphHttp::client()->getOptions();

        if (PHP_SAPI === 'cli') {
            $this->assertArrayHasKey('handler', $options);
        } else {
            $this->assertArrayNotHasKey('handler', $options);
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

        $options = MetaGraphHttp::client()->getOptions();

        $this->assertArrayHasKey('handler', $options);
    }
}
