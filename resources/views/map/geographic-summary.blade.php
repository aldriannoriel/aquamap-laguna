<div data-geography-summary class="space-y-2 text-sm leading-6">
@if ($lake['category'] === 'river')
    @if ($profile['reported_length'])
        <p data-official-length><span class="font-semibold text-estuary">Reported river length:</span> {{ number_format($profile['reported_length']['value'], 2) }} km <span class="text-muted">(approximate)</span></p>
        <a href="{{ $profile['reported_length']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="block text-xs text-water underline">{{ $profile['reported_length']['reference'] }}</a>
        <p class="text-xs text-muted">{{ $profile['reported_length']['note'] }}</p>
    @else
        <p data-official-length class="font-semibold text-estuary">Length not yet verified.</p>
        @if ($profile['length_note'])<p class="text-xs text-muted">{{ $profile['length_note'] }}</p>@endif
    @endif
    @if ($profile['mapped_length_km'] !== null)
        <p data-mapped-length><span class="font-semibold">Mapped segment length:</span> {{ number_format($profile['mapped_length_km'], 3) }} km.</p>
        <p class="text-xs text-muted">Partial river course only. Calculated along the mapped segments using great-circle distances on a mean-radius Earth sphere; not the official total river length. Disconnected segments are not joined.</p>
    @else
        <p data-course-missing class="font-semibold text-estuary">River course not yet mapped.</p>
        <p class="text-xs text-muted">{{ $lake['geography'] ? 'Only a source-checked point marker is available.' : 'No source-checked point is available; this river remains selectable without a map marker.' }}</p>
    @endif
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
@if ($lake['geography'])
    <p class="text-xs text-muted">{{ $lake['geography']['note'] }}</p>
    <p class="text-xs"><a href="{{ $lake['geography']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">Geometry source</a>@if (isset($lake['geography']['license_url'])) · <a href="{{ $lake['geography']['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-water underline">{{ $lake['geography']['license'] }}</a>@endif</p>
@endif
</div>
