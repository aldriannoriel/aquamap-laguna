<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Laguna's lakes, rivers and waterfalls through sourced geography and ecological research.">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Water explorer | AquaMap Laguna</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="map-dashboard min-h-screen bg-mist font-sans text-ink antialiased">
    <a href="#main" class="skip-link">Skip to water explorer</a>
    @include('partials.header')
    <main id="main" class="explorer-main">
        <h1 class="sr-only">Laguna water explorer</h1>
        <div class="explorer-layout">
            <details id="water-sidebar" class="explorer-sidebar" open>
                <summary class="sidebar-toggle">Find a water body <span>Search &amp; browse {{ count($lakes) }} places</span></summary>
                <div class="sidebar-body">
                    <div class="sidebar-controls">
                        <h2 id="lake-list-heading" class="text-lg font-semibold text-estuary">Find a water body</h2>
                        <label for="water-search" class="sr-only">Search by name, alternate name or municipality</label>
                        <div class="water-search-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><input id="water-search" type="search" class="form-input" placeholder="Name, alias or municipality" autocomplete="off" aria-controls="water-list"></div>
                        <div class="category-list" role="group" aria-label="Water body categories">
                            @foreach (['all' => 'All Water Bodies', ...$categories] as $category => $label)
                                <button type="button" data-category="{{ $category }}" aria-pressed="{{ $category === 'all' ? 'true' : 'false' }}" aria-controls="water-list lake-map" class="category-filter"><span class="category-dot category-{{ $category }}" aria-hidden="true"></span><span>{{ $label }}</span><span data-category-count>{{ count(array_filter($lakes, fn ($item) => $category === 'all' || $item['category'] === $category)) }}</span></button>
                            @endforeach
                        </div>
                        <p id="filter-status" class="text-xs text-muted" role="status">{{ count($lakes) }} profiles / {{ count(array_filter($lakes, fn ($item) => $item['geography'])) }} source-checked map locations.</p>
                    </div>
                    <ul id="water-list" class="water-list" aria-labelledby="lake-list-heading">
                        @forelse ($lakes as $lake)
                            <li data-water-row="{{ $lake['id'] }}"><button type="button" data-lake-id="{{ $lake['id'] }}" aria-pressed="false" aria-controls="water-detail-dialog" class="lake-card">
                                <span class="water-row-name">{{ $lake['name'] }}</span><span class="water-row-place">{{ $lake['municipality'] }}</span>
                                <span class="water-row-meta"><span>{{ $categories[$lake['category']] }}</span><span class="selection-label">View details</span></span>
                                @unless ($lake['geography'])<span class="water-row-unmapped">Location awaiting verification; not mapped.</span>@endunless
                            </button></li>
                        @empty
                            <li class="p-4 text-sm text-muted">No water body records are available. Please check again later.</li>
                        @endforelse
                    </ul>
                    <p id="filter-empty" class="p-4 text-sm text-muted" hidden>No places match. Clear your search or choose another category.</p>
                </div>
            </details>
            <section class="explorer-map" aria-labelledby="map-heading">
                <div class="map-toolbar"><h2 id="map-heading">Laguna, Philippines</h2><button id="reset-map" type="button" class="secondary-button" disabled>Reset map &amp; filters</button></div>
                <div class="map-stage">
                    <div id="lake-map" role="region" aria-label="Interactive water body map. Focus or click the map to enable wheel zoom. Use arrow keys to pan and plus or minus to zoom." aria-describedby="map-status wheel-hint"></div>
                    <aside id="water-detail-dialog" class="water-profile" role="dialog" aria-modal="false" aria-labelledby="selected-lake-heading" hidden>
                        <div class="profile-header"><div class="min-w-0"><p class="profile-eyebrow">Water body profile</p><h2 id="selected-lake-heading">Water body profile</h2></div><button id="close-water-detail" type="button" class="profile-close" aria-label="Close profile and clear selection"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
                        <div id="lake-information" class="profile-content"><div id="selected-lake"></div></div>
                        <div id="profile-tabs" class="profile-tabs" role="tablist" aria-label="Water body profile sections">
                            @foreach (['overview' => 'Profile', 'classification' => 'Water Type', 'fish' => 'Fish & Habitat', 'research' => 'Research & Sources'] as $tab => $label)
                                <button id="profile-tab-{{ $tab }}" type="button" role="tab" data-profile-tab="{{ $tab }}" aria-controls="profile-panel-{{ $tab }}" aria-selected="{{ $tab === 'overview' ? 'true' : 'false' }}" tabindex="{{ $tab === 'overview' ? '0' : '-1' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">@switch($tab)@case('overview')<path d="M12 3C9 7 6 11 6 15a6 6 0 0 0 12 0c0-4-3-8-6-12Z"/>@break @case('classification')<path d="M3 8c3-4 6 4 9 0s6 4 9 0M3 15c3-4 6 4 9 0s6 4 9 0"/>@break @case('fish')<path d="M3 12c5-8 13-7 16 0-3 7-11 8-16 0Zm16 0 3-4v8l-3-4Z"/><circle cx="7" cy="11" r=".7"/>@break @default <path d="M3 4h6l3 2 3-2h6v15h-6l-3 2-3-2H3V4Zm9 2v15"/>@endswitch</svg><span>{{ $label }}</span></button>
                            @endforeach
                        </div>
                    </aside>
                </div>
                <p id="wheel-hint" class="map-wheel-hint">Click or focus the map to zoom with a mouse wheel or trackpad.</p>
                <p id="tile-status" role="status" class="px-3 py-2 text-xs text-estuary" hidden>Basemap tiles are unavailable. Markers, mapped shapes, coordinates and profiles remain available. Check your connection or try again later.</p>
                <p id="map-status" role="status" class="map-status">Loading the map. Select a sidebar item to open its profile.</p>
            </section>
        </div>
        <div class="map-context">
            <p>Select a marker, shape or sidebar item for environmental context, historical species records and research sources.</p>
            <details id="research-notes"><summary>Map context &amp; data limits</summary><p>Source-checked WGS84 geography is a reference, not a survey, legal boundary or incident location. River courses may be incomplete. Original lake-point datum remains unconfirmed. Water type and historical fish records do not establish current safety or suitability. Community reports and evidence are private.</p></details>
        </div>
        <noscript><p class="mt-3 rounded-lg border border-line bg-white p-4 text-sm">Enable JavaScript for the interactive map, filters, individual profiles and research sources.</p></noscript>
    </main>
    @foreach ($lakes as $lake)
        <template id="profile-{{ $lake['id'] }}"><div class="min-w-0 text-sm leading-6">@include('map.profile', ['lake' => $lake, 'profile' => $profiles[$lake['id']]])</div></template>
    @endforeach
    <a href="{{ route('reports.create') }}" class="report-fab"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V3m0 1h14l-2 5 2 5H4"/></svg><span>Report a Concern</span></a>
    <script id="lake-data" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
</body>
</html>
