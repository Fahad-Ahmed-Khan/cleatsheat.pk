<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_includes_sitemap_directive(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.$base.'/sitemap.xml', false);
    }

    public function test_sitemap_xml_lists_home_and_catalog_routes(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($base.'/', false)
            ->assertSee($base.'/journal', false);
    }
}
