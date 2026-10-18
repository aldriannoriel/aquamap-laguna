<section data-profile-section="overview" aria-label="Overview">
<h2 class="lake-detail-title">{{ $lake['name'] }}</h2>
<p class="lake-detail-meta">{{ $lake['municipality'] }} / {{ $lake['site_type'] === 'resort' ? 'Spring-associated resort' : $categories[$lake['category']] }}</p>
@if ($profile['image'])
    <figure class="mt-4">
        <img src="{{ $profile['image']['path'] }}" alt="{{ $profile['image']['alt'] }}" loading="lazy" @class(['aspect-[16/7] w-full rounded-lg', 'object-contain bg-mist' => ($profile['image']['fit'] ?? 'cover') === 'contain', 'object-cover' => ($profile['image']['fit'] ?? 'cover') !== 'contain'])>
        <figcaption class="mt-2 text-xs leading-5 text-muted">
            <span data-photo-credit class="block">Photo by {{ $profile['image']['author'] }} · <a href="{{ $profile['image']['source_url'] }}" rel="noopener noreferrer" target="_blank" class="underline">Source</a> · <a href="{{ $profile['image']['license_url'] }}" rel="noopener noreferrer" target="_blank" class="underline">License</a>.</span>
            <span class="block">{{ $profile['image']['license'] }}. {{ ($profile['image']['fit'] ?? 'cover') === 'contain' ? 'Full image fitted without cropping.' : 'Display may be cropped to fit.' }}</span>
        </figcaption>
    </figure>
@else
    <div data-photo-placeholder class="mt-4 flex min-h-24 items-center justify-center rounded-lg border border-dashed border-line bg-mist p-5 text-center text-xs text-muted">No authorized photograph available.</div>
@endif
<p class="lake-detail-description">{{ $lake['description'] ?? 'No description is available for this water body.' }}</p>
@if ($lake['aliases'])<p class="mt-2 text-xs text-muted">Also known as {{ implode(', ', $lake['aliases']) }}.</p>@endif
@if ($lake['official_url'])<a href="{{ $lake['official_url'] }}" class="lake-detail-source" rel="noopener noreferrer" target="_blank">Official website</a>@endif
</section>
<section data-profile-section="classification" class="mt-5" aria-label="Water classification">
<h3 class="font-semibold text-estuary">Water Classification</h3>
<p class="mt-3 text-sm"><span class="font-semibold">Water classification:</span> {{ $lake['water_type'] }}. This is not a water-safety classification.</p>
@if ($lake['site_type'] === 'resort')<p class="mt-2 text-sm"><span class="font-semibold">Spring type:</span> {{ $lake['spring_type'] === 'geothermal_hot_spring' ? 'Natural hot spring water (operator claim)' : ($lake['spring_type'] === 'natural_spring' ? 'Natural spring water (operator claim)' : 'Not Assessed') }}.</p>@endif
<p class="mt-3 text-xs leading-5 text-muted">A water type or an operator's spring-water claim does not establish drinking-water safety, bathing safety or habitat suitability. No current safety assessment is available.</p>
@if ($lake['site_type'] === 'resort')<p class="mt-2 text-xs text-muted">Spring-water supply is an operator claim. The mapped point is the resort, not a verified spring source. Temperature, composition and safety are not established.</p>@endif
</section>
<section data-profile-section="fish" class="mt-5" aria-label="Fish and habitat">
<div id="fish-records-{{ $lake['id'] }}">
    @if (in_array($lake['category'], ['waterfall', 'spring'], true) && empty($fishByLake[$lake['id']]))
        <div><h3 class="font-semibold">Fish habitat: Not Assessed</h3><p class="mt-2 text-xs text-muted">No location-specific fish evidence or habitat assessment is available. Freshwater or a spring-water claim does not establish fish presence or suitability.</p></div>
    @else
        @include('map.fish-cards', ['fish' => $fishByLake[$lake['id']] ?? []])
    @endif
</div>
</section>
<section data-profile-section="pollution" class="mt-5 space-y-5" aria-label="Pollution information">
    <section aria-label="Community pollution reports" class="rounded-lg border border-line p-4">
        <h3 class="font-semibold text-estuary">Community Pollution Reports</h3>
        <p data-verified-count class="mt-2 text-sm">{{ $lake['verified_report_count'] }} currently verified community {{ $lake['verified_report_count'] === 1 ? 'report' : 'reports' }}.</p>
        <p class="mt-2 text-xs leading-5 text-muted">Aggregate count only; resolved, pending and rejected reports are excluded. Individual report summaries and evidence remain private. Reports are observations, not scientific measurements. Zero does not establish clean or safe water.</p>
    </section>
    <section aria-label="Scientific water quality assessment" class="rounded-lg border border-line bg-mist p-4">
        <h3 class="font-semibold text-estuary">Scientific Water Quality Assessment</h3>
        <p data-scientific-assessment class="mt-2 text-sm font-medium">Scientific water-quality assessment: Not Assessed.</p>
        <p class="mt-2 text-xs leading-5 text-muted">No verified water-quality assessment available</p>
        <p class="mt-2 text-xs leading-5 text-muted">No published measurement values, units or sampling dates have been imported for this profile. Historical research references do not establish current water conditions or safety.</p>
    </section>
</section>
<section data-profile-section="research" class="mt-5 space-y-3" aria-label="Research and geographic references">
    <h3 class="font-semibold text-estuary">Research &amp; sources</h3>
    @forelse ($profile['references'] as $reference)
        <article data-research-card class="rounded-lg border border-line bg-white p-4 text-xs leading-5">
            <h4><a href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-water underline underline-offset-4">{{ $reference['title'] }}</a></h4>
            <p class="mt-2 text-muted">{{ $reference['publisher'] }}. {{ $reference['published_on'] ?? 'Publication date not provided' }}.</p>
        </article>
    @empty
        <p class="text-xs text-muted">No catalogued research reference is available for this water body.</p>
    @endforelse
    <article class="rounded-lg border border-line bg-mist p-4 text-xs leading-5">
    <h4 class="font-semibold">Geographic reference and limitations</h4>
@if ($lake['geography'])
    <p data-geographic-note class="mt-3 text-xs leading-5 text-muted">{{ $lake['geography']['note'] }} Source checked {{ $lake['geography']['checked_on'] }}; WGS84 (EPSG:4326).</p>
@else
    <p data-geographic-note class="mt-3 text-xs leading-5 text-muted">Location awaiting verification. This profile has no published map feature.</p>
@endif
    @if ($lake['geography'])<a href="{{ $lake['geography']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="lake-detail-source">Geographic source</a>@endif
    </article>
    @if ($profile['image'])
        <article data-image-credit class="rounded-lg border border-line bg-mist p-4 text-xs leading-5">
            <h4 class="font-semibold">Image attribution</h4>
            <p class="mt-2">{{ $profile['image']['author'] }}. {{ $profile['image']['license'] }}. Display may be cropped to fit.</p>
            <p class="mt-2"><a href="{{ $profile['image']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Original image</a> / <a href="{{ $profile['image']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Permission</a></p>
        </article>
    @endif
    @if ($lake['source_url'])<a href="{{ $lake['source_url'] }}" class="lake-detail-source" target="_blank" rel="noopener noreferrer">Read research source</a>@else<p class="mt-3 text-xs text-muted">No research source is available for this water body.</p>@endif
</section>
