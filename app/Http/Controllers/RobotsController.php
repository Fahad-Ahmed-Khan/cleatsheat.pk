<?php

namespace App\Http\Controllers;

use App\Models\MarketingSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim(config('app.url'), '/');
        $sitemap = $base.'/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /cart',
            'Disallow: /checkout',
            '',
            'Sitemap: '.$sitemap,
        ];

        $body = implode("\n", $lines)."\n";

        if (Schema::hasTable('marketing_settings')) {
            $m = MarketingSetting::query()->first();
            if ($m && $m->robots_mode === 'custom' && filled($m->robots_custom)) {
                $custom = trim((string) $m->robots_custom);
                if ($custom !== '') {
                    $body = $custom;
                    if (! str_contains($custom, 'Sitemap:')) {
                        $body .= "\n\nSitemap: ".$sitemap;
                    }
                    $body .= "\n";
                }
            }
        }

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
