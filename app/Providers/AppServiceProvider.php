<?php

namespace App\Providers;

use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\MergeGuestWishlistOnLogin;
use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->normalizeSnappyBinariesOnWindows();

        Vite::prefetch(concurrency: 3);

        Order::observe(OrderObserver::class);

        Event::listen(Login::class, MergeGuestCartOnLogin::class);
        Event::listen(Login::class, MergeGuestWishlistOnLogin::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * knp-snappy builds an unquoted command when is_executable(escapeshellarg($binary)) is false,
     * which breaks paths that contain spaces on Windows ("C:/Program" is not recognized).
     * Resolving to an 8.3 short path avoids spaces so the spawned command works.
     */
    private function normalizeSnappyBinariesOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        foreach (['snappy.pdf.binary', 'snappy.image.binary'] as $key) {
            $path = Config::get($key);
            if (! is_string($path) || $path === '' || ! str_contains($path, ' ')) {
                continue;
            }

            $normalized = str_replace('/', '\\', $path);
            if (! is_file($normalized)) {
                continue;
            }

            $short = $this->windowsShortPathExe($normalized);
            if ($short !== null) {
                Config::set($key, $short);
            }
        }
    }

    private function windowsShortPathExe(string $absolutePath): ?string
    {
        $safe = str_replace('"', '', $absolutePath);
        $out = shell_exec('cmd /c for %I in ("'.$safe.'") do @echo %~sI');
        if ($out === null || $out === '') {
            return null;
        }

        $line = trim((string) preg_replace('/\R/', '', $out));
        if ($line === '' || str_contains($line, ' ')) {
            return null;
        }

        return is_file($line) ? $line : null;
    }
}
