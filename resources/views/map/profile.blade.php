<h2 class="lake-detail-title">{{ $lake['name'] }}</h2>
<p class="lake-detail-meta">{{ $lake['municipality'] }} / {{ $lake['site_type'] === 'resort' ? 'Spring-associated resort' : $categories[$lake['category']] }}</p>
@if ($profile['image'])
    <figure class="mt-4">
        <img src="{{ $profile['image']['path'] }}" alt="{{ $profile['image']['alt'] }}" loading="lazy" class="aspect-[16/7] w-full rounded-lg object-cover">
        <figcaption class="mt-2 text-xs leading-5 text-muted">Image: {{ $profile['image']['author'] }}. {{ $profile['image']['license'] }}. Display may be cropped to fit. <a href="{{ $profile['image']['source_url'] }}" rel="noopener noreferrer" target="_blank" class="underline">Original</a> / <a href="{{ $profile['image']['license_url'] }}" rel="noopener noreferrer" target="_blank" class="underline">Permission</a>.</figcaption>
    </figure>
@else
    <div data-photo-placeholder class="mt-4 flex min-h-24 items-center justify-center rounded-lg border border-dashed border-line bg-mist p-5 text-center text-xs text-muted">No authorized photograph available.</div>
@endif
<p class="lake-detail-description">{{ $lake['description'] ?? 'No description is available for this water body.' }}</p>
<p class="mt-3 text-sm"><span class="font-semibold">Water classification:</span> {{ $lake['water_type'] }}. This is not a water-safety classification.</p>
@if ($lake['site_type'] === 'resort')<p class="mt-2 text-sm"><span class="font-semibold">Spring type:</span> {{ $lake['spring_type'] === 'geothermal_hot_spring' ? 'Natural hot spring water (operator claim)' : ($lake['spring_type'] === 'natural_spring' ? 'Natural spring water (operator claim)' : 'Not Assessed') }}.</p>@endif
@if ($lake['aliases'])<p class="mt-2 text-xs text-muted">Also known as {{ implode(', ', $lake['aliases']) }}.</p>@endif
@if ($lake['geography'])
    <p data-geographic-note class="mt-3 text-xs leading-5 text-muted">{{ $lake['geography']['note'] }} Source checked {{ $lake['geography']['checked_on'] }}; WGS84 (EPSG:4326).</p>
@else
    <p data-geographic-note class="mt-3 text-xs leading-5 text-muted">Location awaiting verification. This profile has no published map feature.</p>
@endif
@if ($lake['site_type'] === 'resort')<p class="mt-2 text-xs text-muted">Natural hot spring water is an operator claim. The mapped point is the resort, not a verified spring source. Temperature, composition and safety are not established.</p>@endif
@if ($lake['official_url'])<a href="{{ $lake['official_url'] }}" class="lake-detail-source" rel="noopener noreferrer" target="_blank">Official website</a>@endif
<div id="fish-records-{{ $lake['id'] }}">
    @if (in_array($lake['category'], ['waterfall', 'spring'], true) && empty($fishByLake[$lake['id']]))
        <div class="mt-5 border-t border-line pt-4"><h3 class="font-semibold">Fish habitat: Not Assessed</h3><p class="mt-2 text-xs text-muted">No location-specific fish evidence or habitat assessment is available. Freshwater or a spring-water claim does not establish fish presence or suitability.</p></div>
    @else
        @include('map.fish-cards', ['fish' => $fishByLake[$lake['id']] ?? []])
    @endif
</div>
<section class="mt-5 border-t border-line pt-4" aria-label="Pollution information">
    <h3 class="font-semibold">Pollution information</h3>
    <p data-verified-count class="mt-2 text-sm">{{ $lake['verified_report_count'] }} currently verified community {{ $lake['verified_report_count'] === 1 ? 'report' : 'reports' }}.</p>
    <p class="mt-1 text-xs text-muted">Aggregate count only; resolved, pending and rejected reports are excluded. Reports are observations, not scientific measurements. Zero does not establish clean or safe water.</p>
    <p data-scientific-assessment class="mt-3 text-sm font-medium">Scientific water-quality assessment: Not Assessed.</p>
    <p class="mt-1 text-xs text-muted">No verified water-quality assessment available</p>
</section>
<section class="mt-5 rounded-lg border border-line bg-mist p-4" aria-label="Research and geographic references">
    <h3 class="font-semibold">Research &amp; sources</h3>
    @forelse ($profile['references'] as $reference)
        <div class="mt-3 text-xs leading-5"><a href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-water underline underline-offset-4">{{ $reference['title'] }}</a><p class="text-muted">{{ $reference['publisher'] }}. {{ $reference['published_on'] ?? 'Publication date not provided' }}.</p></div>
    @empty
        <p class="mt-3 text-xs text-muted">No catalogued research reference is available for this water body.</p>
    @endforelse
    @if ($lake['source_url'])<a href="{{ $lake['source_url'] }}" class="lake-detail-source" target="_blank" rel="noopener noreferrer">Read research source</a>@else<p class="mt-3 text-xs text-muted">No research source is available for this water body.</p>@endif
</section>
