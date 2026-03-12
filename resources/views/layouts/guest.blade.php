<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="tg-page tg-auth-page antialiased">
        <div class="tg-atmosphere"></div>

        <main class="tg-auth-shell">
            <div class="tg-auth-frame">
                <a class="tg-auth-logo" href="/">
                    <x-application-logo class="tg-auth-logo__mark fill-current" />
                </a>

                <section class="tg-auth-card">
                    {{ $slot }}
                </section>
            </div>
        </main>
    </body>
</html>
