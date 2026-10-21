<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="same-origin">
        <title>@yield('title', 'Administrator') | AquaMap Laguna</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-mist font-sans text-ink antialiased">
        <a href="#main" class="skip-link">Skip to administrator content</a>
        @include('partials.header')
        @can('access-admin')
            <div class="border-b border-line bg-white">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-8">
                    <nav aria-label="Administrator navigation" class="flex flex-wrap items-center gap-5 text-sm font-semibold text-estuary">
                        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Overview</a>
                        <a href="{{ route('admin.reports.index', ['queue'=>'action']) }}" @if(request()->routeIs('admin.reports.*')) aria-current="page" @endif>Manage reports</a>
                        <a href="{{ route('admin.agencies.index') }}">Agency directory</a>
                        <a href="{{ route('admin.referrals.index') }}">Referrals &amp; email test</a>
                    </nav>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button id="admin-logout" class="secondary-button" type="submit">Sign out</button></form>
                </div>
            </div>
        @endcan
        <main id="main" class="mx-auto max-w-6xl px-4 py-7 sm:px-8 sm:py-10">
            @if ($errors->any())
                <section id="form-errors" role="alert" tabindex="-1" class="mb-6 rounded-xl border border-red-300 bg-white p-5 text-sm">
                    <h2 class="font-semibold text-red-800">Please check your details</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </section>
            @endif
            @yield('content')
        </main>
    </body>
</html>
