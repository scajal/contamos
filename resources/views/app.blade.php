<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- With no chosen scheme the device decides, which only the client
             knows. Applying it before the stylesheet loads keeps the first
             paint from flashing the wrong one. --}}
        @if (($appearance ?? 'system') === 'system')
            <script>
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            </script>
        @endif

        @if (app()->isProduction() && ($measurementId = config('services.google.analytics.measurement_id')))
            {{-- Google tag (gtag.js). GA4's enhanced measurement reports
                 Inertia's history changes as page views on its own. The
                 library is fetched only once the page has loaded, so its
                 ~170 KiB stops competing with the fonts and the bundle on
                 a slow connection; the calls below queue in the meantime. --}}
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', @json($measurementId));
                window.addEventListener('load', function () {
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}';
                    document.head.appendChild(script);
                });
            </script>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{-- The dictionary for this locale is its own chunk, fetched by
             the app before it mounts. Announcing it here lets the browser
             download it alongside the bundle instead of after it. --}}
        @unless (Vite::isRunningHot())
            <link rel="modulepreload" href="{{ Vite::asset('lang/'.app()->getLocale().'.json') }}">
            {{-- Headings are set in Switzer, and the hero headline is the
                 page's largest paint. Left to the stylesheet, the font is
                 only discovered once the CSS has been parsed. --}}
            <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset('resources/fonts/switzer/Switzer-Variable.woff2') }}" crossorigin>
        @endunless
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
