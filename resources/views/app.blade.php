<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <!-- App instalable (PWA) -->
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#0F2A47">
        <meta name="base-path" content="{{ request()->getBaseUrl() }}">
        <meta name="application-name" content="RIC Condominios">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="RIC Condominios">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="format-detection" content="telephone=no">
        <link rel="apple-touch-icon" href="{{ asset('app/apple-touch-icon.png') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('app/favicon-64.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700,800&display=swap" rel="stylesheet" />

        <!-- App instalable: service worker y aviso de "instalar" del navegador (se captura
             antes de que cargue Vue porque el navegador puede lanzarlo muy pronto). -->
        <script>
            window.__ricBase = @json(rtrim(asset(''), '/') . '/');
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.__eventoInstalar = e;
                window.dispatchEvent(new Event('ric:instalable'));
            });
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register(@json(asset('sw.js'))).catch(function () {});
                });
            }
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>