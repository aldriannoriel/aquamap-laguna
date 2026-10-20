<section data-profile-section="overview" aria-label="Profile">
    <h2 class="lake-detail-title">{{ $lake['name'] }}</h2>
    <p class="lake-detail-meta">{{ $lake['municipality'] }} / Laguna, Philippines</p>
    <p class="profile-category">{{ $categories[$lake['category']] }}@if ($lake['site_type'] === 'resort') / Property reference @endif</p>
    @if ($lake['description'])<p class="lake-detail-description">{{ \Illuminate\Support\Str::limit($lake['description'], 240) }}</p>@else<p class="lake-detail-fallback">No description is available for this water body.</p>@endif
    @if ($lake['site_type'] === 'resort')<p class="profile-note">The source identifies this resort property. A natural spring source and its physical characteristics are not independently verified.</p>@endif
    <div class="profile-measurements">@include('map.geographic-summary')</div>
    @if ($profile['image'])
        <figure class="profile-photo"><img data-water-image src="{{ $profile['image']['path'] }}" alt="{{ $profile['image']['alt'] }}" loading="lazy" width="960" height="640" class="{{ ($profile['image']['fit'] ?? 'cover') === 'contain' ? 'object-contain' : 'object-cover' }}"><figcaption>Photo by {{ $profile['image']['author'] }} / {{ $profile['image']['license'] }}. Credits and permission in Research &amp; Sources.</figcaption></figure>
    @else
        <p data-image-placeholder class="profile-note">No authorized photograph available.</p>
    @endif
    <a href="{{ route('reports.create') }}" class="profile-report-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V3m0 1h14l-2 5 2 5H4"/></svg>Report a visible environmental concern</a>
</section>
<section data-profile-section="classification" aria-label="Water Type" class="mt-5">
    <h3 class="font-semibold text-estuary">Water Type</h3>
    @if ($profile['water_type_evidence'])
        <p data-water-type class="mt-3 text-lg font-semibold text-estuary">{{ ['freshwater' => 'Freshwater', 'brackish' => 'Brackish Water', 'saltwater' => 'Saltwater'][$profile['water_type_evidence']['type']] }}</p>
        <p class="profile-note">{{ $profile['water_type_evidence']['note'] }}</p>
        <a href="{{ $profile['water_type_evidence']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="lake-detail-source">{{ $profile['water_type_evidence']['reference'] }}</a>
    @else
        <p data-water-type class="mt-3 font-semibold">Water type not yet verified</p>
        <p class="profile-note">No sufficient location-specific salinity or hydrological evidence has been reviewed. River salinity can vary by reach, season and conditions near the mouth.</p>
    @endif
    <p class="profile-note">Freshwater, Brackish Water and Saltwater describe hydrological conditions. They are separate from official DENR water-quality classes. Water type alone does not establish clean or safe water.</p>
</section>
<section data-profile-section="fish" aria-label="Fish and habitat" class="mt-5">@include('map.fish-cards', ['fish' => $fishByLake[$lake['id']] ?? []])</section>
<section data-profile-section="research" aria-label="Research and geographic references" class="mt-5 space-y-3">
    <h3 class="font-semibold text-estuary">Research &amp; Sources</h3>
    @if ($lake['aliases'])<p data-alternative-names class="profile-note">Alternative names: {{ implode(', ', $lake['aliases']) }}.</p>@endif
    <details class="source-group" open><summary>Geographic location &amp; GIS geometry</summary>
        @if ($lake['geography'])
            <p data-geographic-note class="profile-note">{{ $lake['geography']['note'] }} Source checked {{ $lake['geography']['checked_on'] }}; WGS84 (EPSG:4326).</p>
            <p class="mt-2 text-xs"><a href="{{ $lake['geography']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Geometry source</a>@if (isset($lake['geography']['license_url'])) / <a href="{{ $lake['geography']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">{{ $lake['geography']['license'] }}</a>@endif</p>
        @else
            <p data-geographic-note class="profile-note">Location awaiting verification. This profile has no published map feature.</p>
            @if ($profile['course'])<p class="profile-note">{{ $profile['course']['note'] }}</p>@endif
        @endif
        @if (!empty($profile['course']['source_objects']))<ul data-course-sources class="source-way-list" aria-label="Original river geometry sources">@foreach ($profile['course']['source_objects'] as $source)<li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['name'] }}: OSM {{ $source['id'] }}, revision {{ $source['version'] }}</a> ({{ substr($source['timestamp'], 0, 10) }})</li>@endforeach</ul>@endif
        @if ($profile['original_geography_note'])<details class="mt-3 text-xs text-muted"><summary>Earlier catalogue reach provenance</summary><p data-original-geographic-note class="mt-2">{{ $profile['original_geography_note'] }}</p></details>@endif
    </details>
    <details class="source-group"><summary>Measurements &amp; hydrology</summary>
        @foreach (['reported_length' => 'Provincial approximate river length', 'surface_area' => 'Historical reported lake surface area', 'water_type_evidence' => 'Water type evidence'] as $key => $purpose)
            @if ($profile[$key])<article class="mt-3 text-xs"><h4 class="font-semibold">{{ $purpose }}</h4><a href="{{ $profile[$key]['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">{{ $profile[$key]['reference'] }}</a><p class="mt-1 text-muted">{{ $profile[$key]['note'] }}</p></article>@endif
        @endforeach
        @if ($lake['category'] === 'river')<p class="profile-note">Mapped length sums consecutive source vertices on the WGS84 ellipsoid. Disconnected parts are not joined. Calculation rounding and coordinate digits do not establish survey accuracy. Provincial figures may describe different extents.</p>@endif
        @if ($profile['length_note'])<p class="profile-note">{{ $profile['length_note'] }}</p>@endif
        <p class="profile-note">Earlier catalogue classification: {{ ucfirst($profile['legacy_classification']) }}. This retained source context is not independently verified as a current salinity assessment or an official DENR water-quality classification.</p>
    </details>
    <details class="source-group"><summary>Location research &amp; historical records</summary>
        @forelse ($profile['references'] as $reference)<article data-research-card class="mt-3 text-xs"><h4><a href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-water underline">{{ $reference['title'] }}</a></h4><p class="mt-1 text-muted">{{ $reference['publisher'] }}. {{ $reference['published_on'] ?? 'Publication date not provided' }}.</p></article>@empty<p class="profile-note">No additional catalogued research reference is available.</p>@endforelse
        @if ($lake['source_url'])<a href="{{ $lake['source_url'] }}" class="lake-detail-source" target="_blank" rel="noopener noreferrer">Original catalogue research source</a>@else<p class="profile-note">No research source is available for this water body.</p>@endif
        @if ($lake['official_url'])<a href="{{ $lake['official_url'] }}" class="lake-detail-source" target="_blank" rel="noopener noreferrer">Official location reference</a>@endif
    </details>
    <details class="source-group"><summary>Fish species &amp; habitat evidence</summary>
        @forelse ($fishByLake[$lake['id']] ?? [] as $species)<article class="mt-3 text-xs"><h4 class="font-semibold">{{ $species['common_name'] }} / <i>{{ $species['scientific_name'] }}</i></h4><p class="mt-1">Historical study: {{ $species['publication_year'] ?? 'Year not provided' }}; sampled {{ $species['sampling_period'] ?? 'Period not provided' }}. Supports only the documented occurrence context; present suitability is unassessed.</p>@if ($species['occurrence_source_url'])<a class="text-water underline" href="{{ $species['occurrence_source_url'] }}" target="_blank" rel="noopener noreferrer">Location-specific occurrence study</a>@endif @if ($species['source_url'])<a class="mt-2 block text-water underline" href="{{ $species['source_url'] }}" target="_blank" rel="noopener noreferrer">General species habitat reference</a>@endif</article>@empty<p class="profile-note">No location-specific fish study has been catalogued.</p>@endforelse
    </details>
    <details class="source-group"><summary>Photographs &amp; image licensing</summary>
        @if ($profile['image'])<article data-image-credit class="mt-3 text-xs"><h4 class="font-semibold">Image attribution</h4><p>{{ $profile['image']['author'] }}. {{ $profile['image']['license'] }}. {{ ($profile['image']['fit'] ?? 'cover') === 'contain' ? 'Full image fitted without cropping.' : 'Display may be cropped to fit.' }}</p><p class="mt-2"><a href="{{ $profile['image']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Original image</a> / <a href="{{ $profile['image']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Permission</a></p></article>@else<p class="profile-note">No verified photograph or reusable image permission is available.</p>@endif
    </details>
</section>
