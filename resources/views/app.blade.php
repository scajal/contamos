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
                 Inertia's history changes as page views on its own. --}}
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', @json($measurementId));
            </script>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{-- The dictionary for this locale is its own chunk, fetched by
             the app before it mounts. Announcing it here lets the browser
             download it alongside the bundle instead of after it. --}}
        @unless (Vite::isRunningHot())
            <link rel="modulepreload" href="{{ Vite::asset('lang/'.app()->getLocale().'.json') }}">
        @endunless
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
