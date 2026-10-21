<section data-profile-section="research" aria-label="Research and geographic references" class="mt-5 space-y-3">
    <h3 class="font-semibold text-estuary">Research &amp; Sources</h3>
    @if ($lake['aliases'])<p data-alternative-names class="profile-note">Alternative names: {{ implode(', ', $lake['aliases']) }}.</p>@endif
    @foreach ($profile['research']['groups'] as $group => $category)
        <details data-research-group="{{ $group }}" class="source-group" @if ($group === 'geography') open @endif>
            <summary>{{ $category['label'] }} ({{ count($category['sources']) }})</summary>
            @if ($group === 'geography')
                @if (!$lake['source_url'])<p class="profile-note">No research source is available for this water body.</p>@endif
                <p data-geographic-note class="profile-note">{{ $lake['geography']['note'] ?? 'Location awaiting verification. This profile has no published map feature.' }}</p>
                @if (!$lake['geography'] && $profile['course'])<p class="profile-note">{{ $profile['course']['note'] }}</p>@endif
                @if ($profile['original_geography_note'])<details class="mt-3 text-xs text-muted"><summary>Earlier catalogue reach provenance</summary><p data-original-geographic-note class="mt-2">{{ $profile['original_geography_note'] }}</p></details>@endif
            @elseif ($group === 'measurements')
                @if ($lake['category'] === 'river')<p class="profile-note">Mapped length sums consecutive source vertices on the WGS84 ellipsoid. Disconnected parts are not joined. Calculation rounding and coordinate digits do not establish survey accuracy. Provincial figures may describe different extents.</p>@endif
                @if ($profile['length_note'])<p class="profile-note">{{ $profile['length_note'] }}</p>@endif
            @elseif ($group === 'hydrology')
                <p class="profile-note">Earlier catalogue classification: {{ ucfirst($profile['legacy_classification']) }}. This retained source context is not independently verified as a current salinity assessment or an official DENR water-quality classification.</p>
            @elseif ($group === 'photography' && $profile['image'])
                <div data-image-credit class="profile-note"><h4 class="font-semibold">Image attribution</h4><p>{{ $profile['image']['author'] }}. {{ $profile['image']['license'] }}. {{ ($profile['image']['fit'] ?? 'cover') === 'contain' ? 'Full image fitted without cropping.' : 'Display may be cropped to fit.' }}</p></div>
            @endif
            @forelse ($category['sources'] as $key)
                @php($citation = $profile['research']['citations'][$key])
                @if ($citation['group'] === $group)
                    <article data-research-card data-research-source="{{ $citation['id'] }}" id="{{ $citation['id'] }}" @if (isset($citation['flags']['fish_name'])) data-fish-name-evidence @endif class="mt-3 min-w-0 text-xs">
                        <details class="rounded-md border border-line p-2">
                            <summary class="font-semibold text-estuary">{{ $citation['title'] ?? 'Source title not provided' }}</summary>
                            <p class="mt-2 text-muted">Author / organization: {{ $citation['publisher'] ?? 'Not provided' }}.</p>
                            <p class="mt-1 text-muted">Publication year: {{ $citation['year'] ?? 'Not provided' }}.</p>
                            <p class="mt-2 break-all"><a data-source-document @if (isset($citation['flags']['fish_name'])) data-fish-name-reference @endif @if (isset($citation['flags']['course'])) data-course-source @endif href="{{ $citation['url'] }}" target="_blank" rel="noopener noreferrer" class="text-estuary underline underline-offset-4">{{ $citation['url'] }}<span class="sr-only"> (opens source in a new tab)</span></a></p>
                            @foreach ($citation['contexts'] as $context)<p class="mt-1 text-muted">Source date/context: {{ $context }}.</p>@endforeach
                            <p class="mt-2 font-semibold">Claims supported</p>
                            <ul class="mt-1 list-disc space-y-2 pl-4">
                                @foreach ($citation['claims'] as $purpose => $claims)
                                    @foreach ($claims as $claim)<li><span class="font-medium">{{ $profile['research']['groups'][$purpose]['label'] }}:</span> {{ $claim }}</li>@endforeach
                                @endforeach
                            </ul>
                            @foreach ($citation['locations'] as $location)
                                @if ($location !== $citation['url'])<p class="mt-2 break-all"><a href="{{ $location }}" target="_blank" rel="noopener noreferrer" class="text-estuary underline">Additional page/table reference: {{ parse_url($location, PHP_URL_FRAGMENT) }}</a></p>@endif
                            @endforeach
                        </details>
                    </article>
                @else
                    <p class="profile-note"><a data-citation-jump="{{ $citation['id'] }}" href="#{{ $citation['id'] }}" class="text-estuary underline">{{ $citation['title'] ?? 'Source title not provided' }}</a> — full citation in {{ $profile['research']['groups'][$citation['group']]['label'] }}. {{ implode(' ', $citation['claims'][$group]) }}</p>
                @endif
            @empty
                <p class="profile-note">No {{ strtolower($category['label']) }} source is catalogued for this profile. Missing evidence remains unverified.</p>
            @endforelse
        </details>
    @endforeach
</section>
