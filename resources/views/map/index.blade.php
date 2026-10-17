<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Explore Laguna's waters with sourced geography and research context.">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <title>Water explorer | AquaMap Laguna</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-mist font-sans text-ink antialiased">
        <a href="#main" class="skip-link">Skip to water explorer</a>
        @include('partials.header')
        <main id="main" class="mx-auto max-w-[1600px] px-4 py-6 sm:px-8 sm:py-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Explore Laguna's waters</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">From crater lakes to river reaches. Find a place and follow the evidence behind it.</p>
                </div>
                <p class="text-sm text-muted">Laguna, Philippines</p>
            </div>
            <div class="grid overflow-hidden rounded-2xl border border-line bg-white shadow-sm lg:grid-cols-[320px_minmax(0,1fr)] xl:grid-cols-[350px_minmax(0,1fr)]">
                <aside class="min-w-0 border-b border-line lg:border-r lg:border-b-0" aria-labelledby="lake-list-heading">
                    <div class="space-y-4 p-5">
                        <h2 id="lake-list-heading" class="text-lg font-semibold">Find a water body</h2>
                        <div><label for="water-search" class="form-label">Search places</label><input id="water-search" type="search" class="form-input" placeholder="Name, alternate name or municipality" autocomplete="off" aria-controls="water-list"></div>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Water body categories">
                            @foreach (['all' => 'All Water Bodies', ...$categories] as $category => $label)
                                <button type="button" data-category="{{ $category }}" aria-pressed="{{ $category === 'all' ? 'true' : 'false' }}" aria-controls="water-list lake-map" class="category-filter flex min-h-12 items-center justify-between gap-2 rounded-lg border border-line px-3 py-2 text-left text-xs font-semibold {{ $category === 'all' ? 'col-span-2' : '' }}"><span>{{ $label }}</span><span data-category-count>{{ count(array_filter($lakes, fn ($item) => $category === 'all' || $item['category'] === $category)) }}</span></button>
                            @endforeach
                        </div>
                        <p id="filter-status" class="text-xs leading-5 text-muted" role="status">{{ count($lakes) }} profiles available. Only source-checked geometry is mapped.</p>
                        <a href="#lake-map" class="inline-block text-sm font-semibold text-water underline underline-offset-4 lg:hidden">Go to map</a>
                    </div>
                    <ul id="water-list" class="space-y-3 px-4 pb-5 max-h-72 overflow-y-auto lg:max-h-[570px]" aria-label="Water body selection">
                        @forelse ($lakes as $lake)
                            <li data-water-row="{{ $lake['id'] }}">
                                <button type="button" data-lake-id="{{ $lake['id'] }}" aria-pressed="false" aria-controls="lake-information" class="lake-card w-full rounded-xl border border-line p-4 text-left hover:border-water hover:bg-mist">
                                    <span class="block text-lg font-semibold">{{ $lake['name'] }}</span><span class="mt-1 block text-sm text-muted">{{ $lake['municipality'] }}</span>
                                    <span class="mt-3 flex flex-wrap justify-between gap-2 text-xs"><span class="font-semibold text-water">{{ $lake['site_type'] === 'resort' ? 'Spring-associated resort' : $categories[$lake['category']] }}</span><span class="selection-label text-muted">View details</span></span>
                                    @unless ($lake['geography'])<span class="mt-2 block text-xs text-muted">Location awaiting verification; not mapped.</span>@endunless
                                </button>
                            </li>
                        @empty
                            <li class="rounded-xl bg-mist p-4 text-sm leading-6 text-muted">No water body records are available. Please check again later.</li>
                        @endforelse
                    </ul>
                    <p id="filter-empty" class="px-5 pb-5 text-sm leading-6 text-muted" hidden>No places match. Clear your search or choose another category.</p>
                    <details id="research-notes" class="mx-5 mb-5 scroll-mt-5 rounded-xl border border-line bg-mist p-4" open>
                        <summary class="cursor-pointer text-sm font-semibold text-estuary">A map with context</summary>
                        <p class="mt-3 text-xs leading-5 text-muted">Mapped features use source-checked WGS84 geography. Source verification is not survey certification: positional precision remains unconfirmed. Original published lake points retain their unconfirmed datum and are not used as verified geometry.</p>
                        <p class="mt-2 text-xs leading-5 text-muted">River lines may cover selected reaches. Resort points locate properties, not spring sources. These features do not define legal boundaries or exact incident locations.</p>
                    </details>
                </aside>
                <section class="min-w-0" aria-labelledby="map-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                        <h2 id="map-heading" class="text-sm font-semibold">Source-checked locations</h2>
                        <button id="reset-map" type="button" class="secondary-button" disabled>Reset map &amp; filters</button>
                    </div>
                    <div id="lake-map" class="h-[380px] w-full bg-mist sm:h-[500px] lg:h-[540px]" role="region" aria-label="Interactive water body map. Use arrow keys to pan and plus or minus to zoom." aria-describedby="map-status"></div>
                    <p id="tile-status" role="status" class="border-t border-line bg-mist px-5 py-3 text-xs leading-5 text-estuary" hidden>Some map tiles could not load. Check your connection. Water body details remain available.</p>
                    <p id="map-status" role="status" class="border-t border-line px-5 py-3 text-xs leading-5 text-muted">Loading the map. Profiles are also available in the references below.</p>
                    <div id="lake-information" class="scroll-mt-4 border-t border-line p-5 sm:p-6" aria-labelledby="overview-heading">
                        <div id="selection-placeholder"><h2 id="overview-heading" class="text-xl font-semibold">Follow the water. Check the evidence.</h2><p class="mt-2 text-sm leading-6 text-muted">Select a map feature or a place in the list to explore its geography, fish records and research.</p></div>
                        <div id="selected-lake" hidden></div>
                    </div>
                </section>
            </div>
            <p class="mt-5 text-sm text-muted">Observed a pollution concern? <a href="{{ route('reports.create') }}" class="font-semibold text-water underline underline-offset-4">Submit a private report</a>.</p>
            <noscript><p class="mt-5 rounded-xl border border-line bg-white p-4 text-sm">Enable JavaScript for map interactions and filtering. All available profiles and research references remain accessible below.</p></noscript>
            <details class="mt-6 rounded-xl border border-line bg-white px-5 py-4">
                <summary class="cursor-pointer text-sm font-medium text-estuary">Water body profiles and research references</summary>
                <div class="mt-5 grid gap-8 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($lakes as $lake)
                        <article id="profile-{{ $lake['id'] }}" class="min-w-0 text-sm leading-6">
                            @include('map.profile', ['lake' => $lake, 'profile' => $profiles[$lake['id']]])
                        </article>
                    @endforeach
                </div>
            </details>
            <footer class="flex flex-wrap justify-between gap-3 pt-6 text-xs leading-5 text-muted"><p>AquaMap Laguna. Know the place. Respect the water.</p><p>Geography is a reference, not a safety assessment.</p></footer>
        </main>
        <script id="lake-data" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
    </body>
</html>
