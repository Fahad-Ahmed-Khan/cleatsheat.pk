<?php

namespace App\Http\Controllers\Web\Store\Account\Concerns;

trait BuildsAccountSeo
{
    /**
     * @return array{title: string, description: string, canonical: string, robots: string}
     */
    protected function accountSeo(string $title, string $path): array
    {
        $canonical = rtrim(config('app.url'), '/').$path;

        return [
            'title' => $title.' — '.config('app.name'),
            'description' => $title.' for your '.config('app.name').' account.',
            'canonical' => $canonical,
            'robots' => 'noindex, nofollow',
        ];
    }
}
