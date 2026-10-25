<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8b6340">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Kidu">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <script>
        // Tema del dispositivo antes de pintar (lib/theme.js usa las mismas claves).
        (function () {
            var theme = 'auto';
            try { theme = localStorage.getItem('kidu-theme') || 'auto'; } catch (e) {}
            if (['light', 'dark', 'nursery'].indexOf(theme) === -1) {
                theme = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
            var meta = { light: '#8b6340', dark: '#1c1814', nursery: '#0b0907' }[theme];
            document.querySelector('meta[name="theme-color"]').setAttribute('content', meta);
        })();
    </script>
    <title inertia>{{ config('app.name', 'Kidu Baby Tracker') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
