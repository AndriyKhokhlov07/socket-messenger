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
    <body class="tg-page tg-app-page antialiased">
        <div class="tg-atmosphere"></div>

        <div class="tg-app-shell">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="tg-app-header">
                    <div class="tg-app-header__inner">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="tg-app-main">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
