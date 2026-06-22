<?php

namespace Tests\Feature\Seo;

use Database\Seeders\DemoCatalogSeeder;
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
            ->assertSee('Sitemap: '.$base.'/sitemap.xml', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /account', false)
            ->assertSee('Disallow: /cart', false)
            ->assertSee('Disallow: /checkout', false);
    }

    public function test_sitemap_xml_lists_home_shop_journal_and_static_pages(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee($base.'/', false);
        $response->assertSee($base.'/shop', false);
        $response->assertSee($base.'/journal', false);
        $response->assertSee($base.'/privacy-policy', false);
        $response->assertSee($base.'/payment-policy', false);
        $response->assertSee($base.'/about', false);
        $response->assertSee($base.'/faq', false);
        $response->assertSee($base.'/contact', false);
    }

    public function test_static_pages_render_with_seo_title(): void
    {
        $this->get('/payment-policy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/StaticPage')
                ->where('slug', 'payment-policy')
                ->has('seo.title')
                ->has('seo.description')
                ->has('seo.canonical'));

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/StaticPage')
                ->where('slug', 'faq')
                ->has('seo.schema_json'));

        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/StaticPage')
                ->where('slug', 'contact')
                ->has('seo.schema_json'));
    }

    public function test_sitemap_uses_index_when_url_count_exceeds_threshold(): void
    {
        config([
            'seo.sitemap_index_threshold' => 5,
            'seo.sitemap_chunk_size' => 2,
        ]);

        $this->seed(DemoCatalogSeeder::class);

        $base = rtrim((string) config('app.url'), '/');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<sitemapindex', false)
            ->assertSee($base.'/sitemap/products-1.xml', false);

        $this->get('/sitemap/products-1.xml')
            ->assertOk()
            ->assertSee($base.'/p/', false);
    }
}
