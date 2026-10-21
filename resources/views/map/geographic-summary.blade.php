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
@elseif ($lake['category'] === 'lake')
    @if ($profile['surface_area'])
        <p data-surface-area><span class="font-semibold text-estuary">Reported surface area:</span> {{ number_format($profile['surface_area']['value'], 2) }} hectares.</p>
    @else
        <p data-surface-area class="font-semibold text-estuary">Surface area not yet verified.</p>
    @endif
    <p class="text-xs text-muted">{{ in_array($lake['geography']['geometry']['type'] ?? null, ['Polygon', 'MultiPolygon'], true) ? 'Source-checked lake water-surface polygon; not a surveyed or legal boundary.' : 'Lake boundary not yet mapped. Only the existing source-checked point is shown when available.' }}</p>
@elseif ($lake['category'] === 'waterfall')
    <p><span class="font-semibold">Waterfall height:</span> Not yet verified.</p>
    <p class="text-xs text-muted">{{ $lake['geography'] ? 'Source-checked location reference; physical measurements are not yet verified.' : 'Geographic location: Not yet verified.' }}</p>
@endif
</div>
