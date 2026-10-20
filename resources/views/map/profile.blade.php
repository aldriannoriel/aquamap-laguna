<section data-profile-section="overview" aria-label="Environmental Overview">
<h2 class="lake-detail-title">{{ $lake['name'] }}</h2>
<p class="lake-detail-meta">{{ $lake['municipality'] }} / {{ $categories[$lake['category']] }}</p>
@if (in_array($lake['category'], ['river', 'lake'], true))
    <div class="mt-3 border-l-4 border-water pl-3">@include('map.geographic-summary')</div>
@endif
<h3 class="mt-4 font-semibold text-estuary">Environmental Overview</h3>
@php
    $documentedFishCount = count(array_filter($fishByLake[$lake['id']] ?? [], fn (array $species): bool => $species['evidence_type'] && $species['occurrence_source_url'] && $species['sampling_period'] && $species['publication_year']));
@endphp
<dl data-environment-summary class="mt-3 divide-y divide-line rounded-lg border border-line bg-mist px-4 text-sm">
    <div class="py-3"><dt class="font-semibold text-estuary">Water body classification</dt><dd class="mt-1">{{ ucfirst($lake['water_type']) }}. Water type does not establish water safety.</dd></div>
    <div class="py-3"><dt class="font-semibold text-estuary">Aquatic species &amp; habitat</dt><dd class="mt-1">{{ $documentedFishCount > 0 ? $documentedFishCount.' documented historical species '.($documentedFishCount === 1 ? 'record' : 'records').'. See Fish & Habitat for study dates and citations.' : 'No documented location-specific fish record is available. This does not establish that fish are absent.' }} Potential habitat suitability remains unassessed.</dd></div>
    <div class="py-3"><dt class="font-semibold text-estuary">Visible pollution reports</dt><dd class="mt-1">{{ $lake['verified_report_count'] }} verified, unresolved community {{ $lake['verified_report_count'] === 1 ? 'observation' : 'observations' }}. {{ $lake['verified_report_count'] === 0 ? 'No verified reports does not mean clean or safe water.' : 'These counts do not establish pollution severity or water safety.' }}</dd></div>
</dl>
<p class="mt-3 text-xs leading-5 text-muted">Community-reported observations, not scientific measurements. Unresolved means verified and not marked resolved by an administrator; pending reports are excluded.</p>
<a href="{{ route('reports.create') }}" class="lake-detail-source">Report a visible environmental concern</a>
<p class="lake-detail-description">{{ $lake['description'] ?? 'No description is available for this water body.' }}</p>
<p class="mt-5 text-xs font-semibold text-muted">Reference photograph · does not show current environmental conditions</p>
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
</section>
<section data-profile-section="classification" class="mt-5" aria-label="Water classification">
<h3 class="font-semibold text-estuary">Water Classification</h3>
<p class="mt-3 text-sm"><span class="font-semibold">Water classification:</span> {{ $lake['water_type'] }}. This is not a water-safety classification.</p>
<p class="mt-3 text-xs leading-5 text-muted">A water type does not establish drinking-water safety, bathing safety or habitat suitability. No current safety assessment is available.</p>
</section>
<section data-profile-section="fish" class="mt-5" aria-label="Fish and habitat">
<div id="fish-records-{{ $lake['id'] }}">
    @if ($lake['category'] === 'waterfall' && empty($fishByLake[$lake['id']]))
        <div><h3 class="font-semibold">Fish habitat: Not Assessed</h3><p class="mt-2 text-xs text-muted">No location-specific fish evidence or habitat assessment is available. Freshwater does not establish fish presence or suitability.</p></div>
    @else
        @include('map.fish-cards', ['fish' => $fishByLake[$lake['id']] ?? []])
    @endif
</div>
</section>
<section data-profile-section="pollution" class="mt-5 space-y-5" aria-label="Visible Pollution Reports">
    <section aria-label="Community pollution reports" class="rounded-lg border border-line p-4">
        <h3 class="font-semibold text-estuary">Visible Pollution Reports</h3>
        <p data-verified-count class="mt-2 text-sm">{{ $lake['verified_report_count'] }} currently verified community {{ $lake['verified_report_count'] === 1 ? 'report' : 'reports' }}.</p>
        <p data-unresolved-count class="mt-2 text-sm">Unresolved verified observations: {{ $lake['verified_report_count'] }}.</p>
        <p class="mt-2 text-xs leading-5 text-muted">Unresolved means currently verified and not marked resolved by an administrator. Both figures refer to the same reports; do not add them together. Resolved, pending and rejected reports are excluded.</p>
        @if ($lake['verified_report_count'] === 0)<p data-no-verified-reports class="mt-3 text-sm font-medium">No currently verified observations are available. Environmental conditions remain unknown.</p>@endif
        <table data-report-categories class="mt-4 w-full text-left text-xs leading-5">
            <caption class="pb-2 text-left text-muted">Reported categories among {{ $lake['verified_report_count'] }} currently verified, unresolved community observations. Each report has one submitted category.</caption>
            <thead><tr class="border-b border-line"><th scope="col" class="py-2 pr-3 font-semibold">Observation category</th><th scope="col" class="py-2 text-right font-semibold">Reports</th></tr></thead>
            <tbody>
                @foreach ($profile['report_categories'] as $category => $count)
                    @if ($category !== 'uncategorized' || $count > 0)
                        <tr data-report-category="{{ $category }}" class="border-b border-line"><th scope="row" class="py-2 pr-3 font-normal">{{ $reportCategories[$category] }}</th><td class="py-2 text-right tabular-nums">{{ $count }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @if ($profile['report_categories']['uncategorized'] > 0)<p class="mt-2 text-xs text-muted">Other legacy category groups older unrecognized categories for this summary only. Original report data is preserved.</p>@endif
        <a href="{{ route('reports.create') }}" class="lake-detail-source">Report a visible environmental concern</a>
    </section>
    <section aria-label="Understanding community observations" class="rounded-lg border border-line bg-mist p-4">
        <h3 class="font-semibold text-estuary">What these observations mean</h3>
        <p class="mt-2 text-sm">Community-reported observations, not scientific measurements.</p>
        <p class="mt-2 text-xs leading-5 text-muted">Administrator verification is a review of a reported observation, not a scientific assessment. Counts do not establish pollution levels, trends, percentages, water safety or habitat suitability. Zero does not establish clean or safe water.</p>
        <p class="mt-2 text-xs leading-5 text-muted">A report marked resolved records an administrator's decision; it does not establish environmental recovery. Individual descriptions, contacts, incident points, photos and review history remain private.</p>
    </section>
</section>
<section data-profile-section="research" class="mt-5 space-y-3" aria-label="Research and geographic references">
    <h3 class="font-semibold text-estuary">Research &amp; sources</h3>
    @if ($lake['aliases'])<p data-alternative-names class="text-xs text-muted">Alternative names: {{ implode(', ', $lake['aliases']) }}.</p>@endif
    @if ($lake['official_url'])<a href="{{ $lake['official_url'] }}" class="lake-detail-source" rel="noopener noreferrer" target="_blank">Official reference</a>@endif
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
            <p class="mt-2">{{ $profile['image']['author'] }}. {{ $profile['image']['license'] }}. {{ ($profile['image']['fit'] ?? 'cover') === 'contain' ? 'Full image fitted without cropping.' : 'Display may be cropped to fit.' }}</p>
            <p class="mt-2"><a href="{{ $profile['image']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Original image</a> / <a href="{{ $profile['image']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Permission</a></p>
        </article>
    @endif
    @if ($lake['source_url'])<a href="{{ $lake['source_url'] }}" class="lake-detail-source" target="_blank" rel="noopener noreferrer">Read research source</a>@else<p class="mt-3 text-xs text-muted">No research source is available for this water body.</p>@endif
</section>
