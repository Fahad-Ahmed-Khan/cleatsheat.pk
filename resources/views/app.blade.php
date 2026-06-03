<!DOCTYPE html>
@php
    use App\Support\Seo\LcpPreloadUrl;
    $lcpImage = isset($page) && is_array($page) ? LcpPreloadUrl::fromInertiaPage($page) : null;
    $appOrigin = rtrim((string) config('app.url'), '/');
@endphp
<html lang="{{ config('app.html_lang', 'en-PK') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @if ($appOrigin !== '')
            <link rel="preconnect" href="{{ $appOrigin }}" crossorigin>
        @endif
        @if ($lcpImage)
            <link rel="preload" as="image" href="{{ $lcpImage }}" fetchpriority="high">
        @endif

        <!-- Fonts (loaded async so they don't block first render; text uses fallback then swaps) -->
        @php
            $fontCss = 'https://fonts.bunny.net/css?family=figtree:400,500,600|inter:400,500,700|sora:700,800&display=swap';
        @endphp
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="{{ $fontCss }}" rel="stylesheet" media="print" onload="this.media='all'">
        <noscript><link href="{{ $fontCss }}" rel="stylesheet"></noscript>

        <!-- Scripts -->
        @php
            $isAdminPage = str_starts_with($page['component'] ?? '', 'Admin/');
            $entry = $isAdminPage ? 'resources/js/admin.js' : 'resources/js/app.js';
        @endphp
        @if ($isAdminPage)
            @routes
        @else
            @routes(['only' => [
                'store.*',
                'login',
                'register',
                'logout',
                'password.*',
                'verification.*',
                'profile.edit',
                'dashboard',
            ]])
        @endif
        @if (! $isAdminPage)
        <script>
            (function () {
                try {
                    if (localStorage.getItem('store.theme') !== 'light') {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>
        @endif
        @vite([$entry, "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
