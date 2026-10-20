<div data-geography-summary class="space-y-2 text-sm leading-6">
@if ($lake['category'] === 'river')
    @if ($profile['mapped_length_km'] !== null)
        <p data-mapped-length><span class="font-semibold text-estuary">Mapped course length:</span> approximately {{ number_format($profile['mapped_length_km'], 3) }} km.</p>
        <p data-course-coverage class="text-xs text-muted">{{ ($profile['course']['coverage'] ?? null) === 'available_named_course' ? 'Available source-mapped course; '.count($profile['course']['source_objects']).' verified named segments.' : 'Earlier partial mapped reach; fuller course could not be validated.' }}</p>
    @elseif (in_array($lake['geography']['geometry']['type'] ?? null, ['LineString', 'MultiLineString'], true))
        <p class="font-semibold text-estuary">Mapped course length not yet verified.</p>
    @else
        <p data-course-missing class="font-semibold text-estuary">River course not yet mapped.</p>
        <p class="text-xs text-muted">{{ $lake['geography'] ? 'Only a source-checked point marker is available.' : 'No source-checked point is available; this river remains selectable without a map marker.' }}</p>
    @endif
    <p data-total-unverified class="text-xs font-semibold text-estuary">Whole-river length and complete headwater-to-mouth coverage are not independently verified.</p>
    @if ($profile['reported_length'])
        <p data-official-length><span class="font-semibold">Provincial reported figure:</span> {{ number_format($profile['reported_length']['value'], 2) }} km <span class="text-muted">(approximate; extent unspecified)</span></p>
    @else
        <p data-official-length class="font-semibold text-estuary">Length not yet verified.</p>
    @endif
    <details data-course-sources class="text-xs text-muted">
        <summary class="cursor-pointer font-semibold text-water">Course coverage and length sources</summary>
        @if ($profile['course'])<p class="mt-2">{{ $profile['course']['note'] }}</p>@endif
        <p class="mt-2">Mapped length follows the supplied centreline on the WGS84 ellipsoid. No straight connectors or disconnected gaps are counted. Coordinate precision and calculation rounding do not establish survey accuracy. A source-mapped length and a provincial approximate figure can refer to different extents.</p>
        @if ($profile['reported_length'])
            <a href="{{ $profile['reported_length']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-water underline">{{ $profile['reported_length']['reference'] }}</a>
            <p class="mt-2">{{ $profile['reported_length']['note'] }}</p>
        @endif
        @if ($profile['length_note'])<p class="mt-2">{{ $profile['length_note'] }}</p>@endif
        @if (!empty($profile['course']['source_objects']))
            <ul class="mt-2 space-y-1" aria-label="Original river geometry sources">
                @foreach ($profile['course']['source_objects'] as $source)
                    <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">{{ $source['name'] }}: OSM {{ $source['id'] }}, revision {{ $source['version'] }}</a> ({{ substr($source['timestamp'], 0, 10) }})</li>
                @endforeach
            </ul>
        @endif
    </details>
@elseif ($lake['category'] === 'lake')
    @if ($profile['surface_area'])
        <p data-surface-area><span class="font-semibold text-estuary">Reported surface area:</span> {{ number_format($profile['surface_area']['value'], 2) }} hectares.</p>
        <a href="{{ $profile['surface_area']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="block text-xs text-water underline">{{ $profile['surface_area']['reference'] }}</a>
        <p class="text-xs text-muted">{{ $profile['surface_area']['note'] }}</p>
    @else
        <p data-surface-area class="font-semibold text-estuary">Surface area not yet verified.</p>
    @endif
    <p class="text-xs text-muted">{{ in_array($lake['geography']['geometry']['type'] ?? null, ['Polygon', 'MultiPolygon'], true) ? 'Source-checked lake water-surface polygon; not a surveyed or legal boundary.' : 'Lake boundary not yet mapped. Only the existing source-checked point is shown when available.' }}</p>
@endif
@if ($lake['geography'] && $lake['category'] !== 'river')
    <p class="text-xs text-muted">{{ $lake['geography']['note'] }}</p>
@endif
@if ($lake['geography'])
    <p class="text-xs"><a href="{{ $lake['geography']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Geometry source</a>@if (isset($lake['geography']['license_url'])) · <a href="{{ $lake['geography']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">{{ $lake['geography']['license'] }}</a>@endif</p>
@endif
</div>
