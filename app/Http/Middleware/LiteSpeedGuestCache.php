<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits LiteSpeed (Hostinger) full-page cache directives for guest, non-Inertia
 * storefront HTML so repeat/anonymous traffic is served from the edge cache,
 * collapsing TTFB and "Document request latency".
 *
 * The app only signals cacheability here; actual storage is controlled by
 * LiteSpeed Cache being enabled at the server (CacheLookup on, see public/.htaccess).
 * Anything that can be personalised (logged-in users, Inertia XHR JSON, flashed
 * session messages, non-200, non-HTML) is explicitly marked no-cache.
 */
class LiteSpeedGuestCache
{
    /** Public, anonymous-safe GET pages that are identical for every guest. */
    private const CACHEABLE_ROUTES = [
        'store.home',
        'store.shop',
        'store.category',
        'store.product',
        'store.journal.index',
        'store.journal.show',
        'store.pages.privacy',
        'store.pages.terms',
        'store.pages.returns',
        'store.pages.payment',
        'store.pages.disclaimer',
        'store.pages.shipping',
        'store.pages.about',
        'store.pages.faq',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ttl = (int) config('store.guest_cache_ttl', 300);

        if ($ttl > 0 && $this->isCacheable($request, $response)) {
            $response->headers->set('X-LiteSpeed-Cache-Control', "public,max-age={$ttl}");
            $response->headers->set('X-LiteSpeed-Tag', 'guest-storefront');
        } else {
            // Be explicit so authed/dynamic responses are never edge-cached.
            $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache, esi=off');
        }

        return $response;
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        // Inertia XHR navigations return JSON keyed to the same URL — never cache those.
        if ($request->headers->has('X-Inertia')) {
            return false;
        }

        if ($request->user() !== null) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $routeName = $request->route()?->getName();
        if (! in_array($routeName, self::CACHEABLE_ROUTES, true)) {
            return false;
        }

        // A page rendered with a one-time flash message must not be shared.
        if ($request->hasSession()) {
            foreach (['success', 'error', 'status', 'flash_payment_error'] as $key) {
                if ($request->session()->has($key)) {
                    return false;
                }
            }
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
