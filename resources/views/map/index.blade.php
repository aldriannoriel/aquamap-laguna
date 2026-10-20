<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Community environmental awareness for Laguna's waters: aquatic species, water body classification and verified visible pollution observations.">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <title>Environmental water map | AquaMap Laguna</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-mist font-sans text-ink antialiased">
        <a href="#main" class="skip-link">Skip to water explorer</a>
        @include('partials.header')
        <main id="main" class="mx-auto max-w-[1600px] px-4 py-6 sm:px-8 sm:py-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Understand Laguna's aquatic environments</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">Learn about Laguna's lakes, rivers and waterfalls through documented species records, water body classification and community-reported visible pollution.</p>
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
                        <p id="filter-status" class="text-xs leading-5 text-muted" role="status">{{ count(array_filter($lakes, fn ($item) => $item['geography'])) }} source-checked locations available. Research profiles are also listed below.</p>
                        <a href="#lake-map" class="inline-block text-sm font-semibold text-water underline underline-offset-4 lg:hidden">Go to map</a>
                    </div>
                    <ul id="water-list" class="space-y-3 px-4 pb-5 max-h-72 overflow-y-auto lg:max-h-[570px]" aria-label="Water body selection">
                        @forelse ($lakes as $lake)
                            <li data-water-row="{{ $lake['id'] }}">
                                <button type="button" data-lake-id="{{ $lake['id'] }}" aria-pressed="false" aria-controls="map-selection" class="lake-card w-full rounded-xl border border-line p-4 text-left hover:border-water hover:bg-mist">
                                    <span class="block text-lg font-semibold">{{ $lake['name'] }}</span><span class="mt-1 block text-sm text-muted">{{ $lake['municipality'] }}</span>
                                    <span class="mt-3 flex flex-wrap justify-between gap-2 text-xs"><span class="font-semibold text-water">{{ $categories[$lake['category']] }}</span><span class="selection-label text-muted">View details</span></span>
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
                        <p class="mt-2 text-xs leading-5 text-muted">River lines may cover selected reaches. Lake, river and waterfall features do not define legal boundaries or exact incident locations.</p>
                    </details>
                </aside>
                <section class="min-w-0" aria-labelledby="map-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                        <h2 id="map-heading" class="text-sm font-semibold">Source-checked locations</h2>
                        <button id="reset-map" type="button" class="secondary-button" disabled>Reset map &amp; filters</button>
                    </div>
                    <div id="map-selection" class="border-b border-line bg-mist px-5 py-4" hidden>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 id="map-selection-heading" class="font-semibold text-estuary"></h3>
                            <div class="flex gap-2"><button id="open-water-profile" type="button" class="secondary-button" aria-haspopup="dialog" aria-controls="water-detail-dialog">Full profile</button><button id="clear-water-selection" type="button" class="secondary-button">Clear selection</button></div>
                        </div>
                        <div id="map-selection-summary" class="mt-3 max-h-48 overflow-y-auto" tabindex="0" role="region" aria-label="Selected water body geography"></div>
                    </div>
                    <div id="lake-map" class="h-[380px] w-full bg-mist sm:h-[500px] lg:h-[540px]" role="region" aria-label="Interactive water body map. Use arrow keys to pan and plus or minus to zoom." aria-describedby="map-status"></div>
                    <p id="tile-status" role="status" class="border-t border-line bg-mist px-5 py-3 text-xs leading-5 text-estuary" hidden>Some map tiles could not load. Check your connection. Water body details remain available.</p>
                    <p id="map-status" role="status" class="border-t border-line px-5 py-3 text-xs leading-5 text-muted">Loading the map. Profiles are also available in the references below.</p>
                    <p class="border-t border-line p-5 text-sm leading-6 text-muted">Select a marker, shape or sidebar item for environmental context, historical species records and verified community observations.</p>
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
            <footer class="flex flex-wrap justify-between gap-3 pt-6 text-xs leading-5 text-muted"><p>AquaMap Laguna. Community awareness for our aquatic environments.</p><p>No reports does not mean clean water. Geography is a reference, not a safety assessment.</p></footer>
        </main>
        <dialog id="water-detail-dialog" class="water-detail-dialog text-ink" aria-labelledby="selected-lake-heading">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-line bg-white px-4 py-3">
                <h2 id="selected-lake-heading" class="min-w-0 text-base font-semibold text-estuary">Water body profile</h2>
                <button id="close-water-detail" type="button" class="secondary-button" autofocus>Close profile</button>
            </div>
            <div class="shrink-0 border-b border-line px-3 py-2">
                <div id="profile-tabs" class="profile-tabs" role="tablist" aria-label="Water body profile sections">
                    @foreach (['overview' => 'Environmental Overview', 'classification' => 'Water Classification', 'fish' => 'Fish & Habitat', 'pollution' => 'Visible Pollution Reports', 'research' => 'Research & Sources'] as $tab => $label)
                        <button id="profile-tab-{{ $tab }}" type="button" role="tab" data-profile-tab="{{ $tab }}" aria-controls="profile-panel-{{ $tab }}" aria-selected="{{ $tab === 'overview' ? 'true' : 'false' }}" tabindex="{{ $tab === 'overview' ? '0' : '-1' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div id="lake-information" class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain p-4">
                <div id="selected-lake" hidden></div>
            </div>
        </dialog>
        <script id="lake-data" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
    </body>
</html>
