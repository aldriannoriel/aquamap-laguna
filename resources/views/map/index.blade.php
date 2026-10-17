<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Explore the sourced locations of Sampaloc, Pandin and Yambo lakes in Laguna.">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <title>Lake explorer | AquaMap Laguna</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-mist font-sans text-ink antialiased">
        <a href="#main" class="skip-link">Skip to lake explorer</a>
        <header class="border-b border-line bg-white">
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('map.index') }}" class="flex items-center gap-3 rounded-lg" aria-label="AquaMap Laguna home">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-estuary text-white" aria-hidden="true">
                        <svg viewBox="0 0 32 32" class="size-8 fill-none" stroke="currentColor" stroke-width="1.7"><path d="M16 3C12 9 7 14 7 19a9 9 0 0 0 18 0c0-5-5-10-9-16Z"/><path d="M8 18c4-4 7 4 11 0s5-1 5-1M10 23c4-3 6 3 11 0"/></svg>
                    </span>
                    <span><span class="block text-xl font-semibold tracking-tight">AquaMap <span class="font-normal text-muted">Laguna</span></span><span class="block text-xs text-muted">A little closer to our waters</span></span>
                </a>
                <nav aria-label="Main navigation" class="flex items-center gap-5 text-sm">
                    <a href="{{ route('map.index') }}" aria-current="page" class="border-b-2 border-estuary py-2 font-semibold text-estuary">Lake explorer</a>
                    <a href="#research-notes" class="py-2 text-muted hover:text-estuary">About the data</a>
                </nav>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-[1600px] px-4 py-6 sm:px-8 sm:py-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">San Pablo lake explorer</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">Find a lake, explore its location, and follow the research behind it.</p>
                </div>
                <p class="flex items-center gap-2 text-sm text-muted"><span class="size-2 rounded-full bg-water" aria-hidden="true"></span>Laguna, Philippines</p>
            </div>

            <div class="grid overflow-hidden rounded-2xl border border-line bg-white shadow-sm lg:grid-cols-[320px_minmax(0,1fr)] xl:grid-cols-[350px_minmax(0,1fr)]">
                <aside class="order-2 flex min-w-0 flex-col border-t border-line lg:order-1 lg:border-t-0 lg:border-r" aria-labelledby="lake-list-heading">
                    <div class="flex items-center justify-between gap-3 px-5 pt-6 pb-4">
                        <h2 id="lake-list-heading" class="text-lg font-semibold">Explore the lakes</h2>
                        <span class="rounded-full bg-mist px-2.5 py-1 text-xs font-medium text-muted">{{ count($lakes) }} available</span>
                    </div>
                    <p class="px-5 pb-4 text-sm text-muted">Choose a lake to see it on the map.</p>
                    <ul class="space-y-3 px-4 pb-6" aria-label="Lake selection">
                        @forelse ($lakes as $lake)
                            <li>
                                <button type="button" data-lake-id="{{ $lake['id'] }}" aria-pressed="false" aria-controls="lake-information" class="lake-card group w-full rounded-xl border border-line p-4 text-left transition-colors hover:border-water hover:bg-mist">
                                    <span class="flex items-start justify-between gap-3">
                                        <span class="min-w-0"><span class="block text-lg font-semibold">{{ $lake['name'] }}</span><span class="mt-1 block text-sm text-muted">{{ $lake['municipality'] }}</span></span>
                                        <svg class="mt-1 size-5 shrink-0 text-water" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s7-6 7-12A7 7 0 0 0 5 9c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                                    </span>
                                    <span class="mt-4 flex items-center justify-between gap-2"><span class="rounded-md bg-moss/10 px-2 py-1 text-xs font-medium text-moss">{{ ucfirst($lake['water_type']) }}</span><span class="selection-label text-xs text-muted">View lake</span></span>
                                </button>
                            </li>
                        @empty
                            <li class="rounded-xl bg-mist p-4 text-sm leading-6 text-muted">No lake records are available. Please check again later.</li>
                        @endforelse
                    </ul>
                    <details id="research-notes" class="mx-5 mt-auto mb-6 scroll-mt-5 rounded-xl border border-line bg-mist p-4" open>
                        <summary class="cursor-pointer text-sm font-semibold text-estuary">A map with context</summary>
                        <p class="mt-3 text-xs leading-5 text-muted">Markers show published lake locations. Their datum and precision are unconfirmed; WGS84 is assumed for this display. They do not define shorelines or legal boundaries.</p>
                        <p class="mt-2 text-xs leading-5 text-muted">Water quality and fish suitability have not been established. Each lake’s research link provides its geographic reference.</p>
                    </details>
                </aside>

                <section class="order-1 min-w-0 lg:order-2" aria-labelledby="map-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                        <h2 id="map-heading" class="flex items-center gap-2 text-sm font-semibold"><span class="size-2 rounded-full bg-water" aria-hidden="true"></span>Lake locations</h2>
                        <button id="reset-map" type="button" class="flex min-h-10 items-center gap-2 rounded-lg border border-line px-3 text-sm font-medium text-estuary hover:bg-mist disabled:cursor-not-allowed disabled:opacity-50" disabled>
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 9a8 8 0 1 1 1 9M4 4v5h5"/></svg>
                            Show all lakes
                        </button>
                    </div>
                    <div class="relative isolate bg-mist">
                        <div id="lake-map" class="h-[420px] w-full sm:h-[500px] lg:h-[540px]" role="region" aria-label="Interactive lake map. Use arrow keys to pan and plus or minus to zoom." aria-describedby="map-status"></div>
                        <div id="map-location-note" class="pointer-events-none absolute top-4 left-4 z-[500] rounded-lg border border-line bg-white/95 px-3 py-2 text-xs font-medium text-estuary shadow-sm">Published lake locations</div>
                    </div>
                    <p id="tile-status" role="status" class="border-t border-line bg-mist px-5 py-3 text-xs leading-5 text-estuary" hidden>Some map tiles could not load. Check your connection. Lake markers and details remain available.</p>
                    <p id="map-status" role="status" class="border-t border-line px-5 py-3 text-xs leading-5 text-muted">Loading the interactive map. Lake information is also available below.</p>
                    <div id="lake-information" class="scroll-mt-4 border-t border-line p-5 sm:p-6" aria-labelledby="overview-heading">
                        <div id="selection-placeholder">
                            <h2 id="overview-heading" class="text-xl font-semibold">Every lake has a story.</h2>
                            <p class="mt-2 text-sm leading-6 text-muted">Select a marker or a lake from the list to explore its location and research.</p>
                        </div>
                        <div id="selected-lake" hidden></div>
                    </div>
                </section>
            </div>
            <noscript><p class="mt-5 rounded-xl border border-line bg-white p-4 text-sm">Enable JavaScript to use the interactive map. The lake details and research links below remain available.</p></noscript>
            <section class="mt-6" aria-labelledby="lake-reference-heading">
                <details class="rounded-xl border border-line bg-white px-5 py-4">
                    <summary id="lake-reference-heading" class="cursor-pointer text-sm font-medium text-estuary">Lake details and research references</summary>
                    <div class="mt-5 grid gap-6 md:grid-cols-3">
                        @foreach ($lakes as $lake)
                            <article class="min-w-0 text-sm leading-6">
                                <h3 class="font-semibold">{{ $lake['name'] }}</h3>
                                <p class="text-muted">{{ $lake['municipality'] }} · {{ ucfirst($lake['water_type']) }}</p>
                                <p class="mt-2 text-muted">{{ $lake['description'] ?? 'No description is available for this lake.' }}</p>
                                @if ($lake['source_url'])
                                    <a href="{{ $lake['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block font-medium text-water underline underline-offset-4">Read research source<span class="sr-only"> for {{ $lake['name'] }} (opens in a new tab)</span></a>
                                @else
                                    <p class="mt-3 text-muted">No research source is available for this lake.</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </details>
            </section>
            <footer class="flex flex-wrap justify-between gap-3 pt-6 text-xs leading-5 text-muted"><p>AquaMap Laguna · Know the place. Respect the water.</p><p>Geographic reference only. No current water-quality assessment.</p></footer>
        </main>
        <script id="lake-data" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
    </body>
</html>
