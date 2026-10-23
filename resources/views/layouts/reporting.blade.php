<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <title>@yield('title', 'Report a Water Problem') | AquaMap Laguna</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="aquamap-ui aquamap-reporting min-h-screen bg-mist font-sans text-ink antialiased">
        <a href="#main" class="skip-link">Skip to main content</a>
        @include('partials.header')
        <main id="main" class="mx-auto max-w-6xl px-4 py-7 sm:px-8 sm:py-10">
            @yield('content')
        </main>
    </body>
</html>
