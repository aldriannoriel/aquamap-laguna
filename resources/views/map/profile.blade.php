<section data-profile-section="overview" aria-label="Profile">
    <h2 class="lake-detail-title">{{ $lake['name'] }}</h2>
    <p class="lake-detail-meta">{{ $lake['municipality'] }} / Laguna, Philippines</p>
    <p class="profile-category">{{ $categories[$lake['category']] }}</p>
    @if ($lake['description'])<p class="lake-detail-description">{{ \Illuminate\Support\Str::limit($lake['description'], 240) }}</p>@else<p class="lake-detail-fallback">No description is available for this water body.</p>@endif
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
        <p data-water-type-basis class="profile-note">Basis: {{ ($profile['water_type_evidence']['basis'] ?? 'verified_information') === 'general_geographic_assessment' ? 'General geographic assessment; salinity has not been measured here.' : 'Verified information in the cited source; not a current salinity measurement.' }}@if($profile['water_type_evidence']['scope'] ?? null) Scope: {{ $profile['water_type_evidence']['scope'] }}.@endif</p>
        <p class="profile-note">{{ $profile['water_type_evidence']['note'] }}</p>
        @if($profile['water_type_evidence']['limitations'] ?? null)<p class="profile-note">{{ $profile['water_type_evidence']['limitations'] }}</p>@endif
        <a href="{{ $profile['water_type_evidence']['source_url'] }}" target="_blank" rel="noopener noreferrer" class="lake-detail-source">{{ $profile['water_type_evidence']['reference'] }}</a>
    @else
        <p data-water-type class="mt-3 font-semibold">Water type not yet verified</p>
        <p class="profile-note">No sufficient location-specific salinity or hydrological evidence has been reviewed. River salinity can vary by reach, season and conditions near the mouth.</p>
    @endif
    <p class="profile-note">Freshwater, Brackish Water and Saltwater describe hydrological conditions. They are separate from official DENR water-quality classes. Water type alone does not establish clean or safe water.</p>
</section>
<section data-profile-section="fish" aria-label="Fish and habitat" class="mt-5">@include('map.fish-cards', ['fish' => $fishByLake[$lake['id']] ?? []])</section>
@include('map.research-sources')
