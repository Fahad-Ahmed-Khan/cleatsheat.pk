<?php

namespace App\Http\Controllers\Web\Store\Account\Concerns;

use App\Support\Seo\SeoPresenter;

trait BuildsAccountSeo
{
    /**
     * @return array{title: string, description: string, canonical: string, robots: string}
     */
    protected function accountSeo(string $title, string $path): array
    {
        return app(SeoPresenter::class)->privatePageSeo(
            $title,
            $path,
            $title.' for your '.config('app.name').' account.',
        );
    }
}
