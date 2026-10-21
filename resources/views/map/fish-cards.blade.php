<section data-possible-fish class="mt-0" aria-label="Possible fish">
    <h3 class="text-base font-semibold text-estuary">Possible Fish</h3>
    <p class="profile-note">Fish that may live here based on general habitat conditions. Their presence has not been confirmed.</p>
    @php
        $assessment = $profile['possible_fish'] ?? null;
    @endphp
    @if($assessment && $assessment['candidates'])
        <p class="profile-note">Preliminary habitat estimate. {{ $assessment['habitat_explanation'] }}</p>
        <div class="possible-fish-grid" data-possible-fish-gallery>
            @foreach($assessment['candidates'] as $candidate)
                <figure class="min-w-0" data-possible-fish-card data-species-id="{{ $candidate['id'] }}">
                    @if($candidate['image'])<img class="possible-fish-thumb" src="{{ $candidate['image']['path'] }}" alt="{{ $candidate['image']['alt'] }}" width="160" height="160" loading="lazy" data-fish-image>
                    @else<div class="possible-fish-thumb possible-fish-placeholder" data-fish-placeholder role="img" aria-label="Reference photo unavailable for {{ $candidate['common_name'] }}"><svg width="40" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 12c5-8 13-7 16 0-3 7-11 8-16 0Zm16 0 3-4v8l-3-4Z"/><circle cx="7" cy="11" r=".7"/></svg><span>Photo unavailable</span></div>@endif
                    <figcaption class="possible-fish-name">{{ $candidate['common_name'] }}</figcaption>
                </figure>
            @endforeach
        </div>
        @if(!$assessment['complete'])<p class="profile-note">This assessment is partially populated. Further reviewed candidate links are pending.</p>@endif
        <details class="possible-fish-reasons" data-fish-reasons>
            <summary>Why these fish?</summary>
            <p class="profile-note">{{ $assessment['limitations'] }}</p>
            <div class="mt-3 space-y-4">
                @foreach($assessment['candidates'] as $candidate)
                    <article data-fish-explanation class="min-w-0 border-t border-line pt-3">
                        <h4 class="font-semibold text-estuary">{{ $candidate['common_name'] }} &mdash; <i>{{ $candidate['scientific_name'] }}</i></h4>
                        <p class="profile-note">{{ $candidate['explanation'] }}</p>
                        <p class="profile-note"><strong>Habitat needs:</strong> {{ $candidate['habitat_requirements'] }}</p>
                        <p class="profile-note"><strong>Limits:</strong> {{ $candidate['limitations'] }}</p>
                        @foreach($candidate['references'] as $reference)<a class="lake-detail-source" href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer">{{ $reference['title'] }}<span class="sr-only"> (general biology reference, opens in a new tab)</span></a>@endforeach
                        @if($candidate['image'])<p class="profile-note">Photo: {{ $candidate['image']['author'] }} / {{ $candidate['image']['license'] }}. <a class="text-water underline" href="{{ $candidate['image']['source_url'] }}" target="_blank" rel="noopener noreferrer">Source</a> / <a class="text-water underline" href="{{ $candidate['image']['license_url'] }}" target="_blank" rel="noopener noreferrer">License</a>. Resized and fitted without cropping; a reference photograph from another location.</p>@endif
                    </article>
                @endforeach
            </div>
        </details>
    @else<p class="profile-note">The preliminary habitat review has not been populated for this profile yet.</p>@endif
</section>
<details class="mt-5" data-documented-fish>
<summary class="cursor-pointer font-semibold text-estuary">Documented Fish Records ({{ count($fish) }})</summary>
<section data-fish-section class="mt-3" aria-label="Documented fish records">
    <p class="mt-1 text-sm leading-6 text-muted">Occurrence records describe what a study found. They do not establish habitat suitability.</p>
    <div class="mt-4 grid gap-3">
        @forelse ($fish as $species)
            @php
                $documented = $species['evidence_type'] && $species['occurrence_source_url']
                    && $species['sampling_period'] && $species['publication_year'];
            @endphp
            <article data-fish-card class="min-w-0 overflow-hidden rounded-xl border border-line bg-white">
                <div class="flex flex-wrap items-start justify-between gap-3 bg-mist/70 p-3">
                    <div class="min-w-0">
                        <h4 class="text-base font-semibold text-ink">{{ $species['common_name'] }}</h4>
                        <p class="mt-1 text-sm text-muted"><i>{{ $species['scientific_name'] }}</i></p>
                        @if ($species['filipino_name_evidence'] ?? null)
                            <p data-filipino-name class="mt-1 text-xs text-muted">Filipino common name: <span lang="fil">{{ $species['filipino_name_evidence']['name'] }}</span>. <a data-fish-name-source href="{{ $species['filipino_name_evidence']['sources'][0]['url'] }}" target="_blank" rel="noopener noreferrer" class="text-estuary underline underline-offset-4">Name source<span class="sr-only"> for {{ $species['scientific_name'] }} (opens in a new tab)</span></a></p>
                        @else
                            <p data-filipino-name class="mt-1 text-xs text-muted">Filipino common name: Not yet verified.</p>
                        @endif
                    </div>
                    <span data-evidence-badge class="rounded-md bg-water/10 px-2 py-1 text-xs font-medium text-estuary">
                        {{ $documented ? ($species['evidence_type'] === 'aquaculture' ? 'Historical aquaculture' : 'Historical wild occurrence') : 'Evidence incomplete' }}
                    </span>
                </div>
                <div class="grid gap-3 p-3 text-sm leading-6 ">
                    <section class="min-w-0">
                        <h5 class="font-semibold text-estuary">Documented occurrence</h5>
                        @if ($documented)
                            <p class="mt-2 text-muted">Sampling period: <span data-sampling-period>{{ $species['sampling_period'] }}</span>.</p>
                            <p class="text-muted">Publication year: {{ $species['publication_year'] }}.</p>
                            <p class="mt-2 text-muted">{{ $species['evidence_type'] === 'aquaculture' ? 'Fish were sampled from aquaculture operations. This is not a wild-population record or confirmation of current presence.' : 'This is a historical wild-occurrence record, not confirmation of current presence.' }}</p>
                        @else
                            <p class="mt-2 text-muted">Water-body-specific occurrence evidence is incomplete. Evidence type, study dates, and a research source are needed before this entry can be treated as a documented occurrence.</p>
                        @endif
                        @if ($species['occurrence_source_url'])
                            <a href="{{ $species['occurrence_source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block font-medium text-water underline underline-offset-4">Occurrence study<span class="sr-only"> for {{ $species['common_name'] }} (opens in a new tab)</span></a>
                        @else
                            <p class="mt-3 text-muted">No occurrence research source is available.</p>
                        @endif
                    </section>
                    <section class="min-w-0">
                        <h5 class="font-semibold text-estuary">Potential habitat compatibility</h5>
                        <p class="mt-2"><span data-compatibility-status="{{ $species['compatibility_status'] }}" class="inline-block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900">Insufficient data</span></p>
                        <p class="mt-2 text-muted">Not assessed. Current water-body measurements and complete species requirements have not been validated. Historical occurrence does not establish present suitability.</p>
                    </section>
                    <section class="min-w-0 border-t border-line pt-4 ">
                        <h5 class="font-semibold text-estuary">General species habitat</h5>
                        <p class="mt-2 text-muted">{{ $species['habitat_requirements'] ?? 'Habitat requirements are not available for this species.' }}</p>
                        @if ($species['source_url'])
                            <a href="{{ $species['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block font-medium text-water underline underline-offset-4">Species habitat reference<span class="sr-only"> for {{ $species['common_name'] }} (opens in a new tab)</span></a>
                        @else
                            <p class="mt-3 text-muted">No species habitat reference is available.</p>
                        @endif
                    </section>
                </div>
            </article>
        @empty
            <p class="rounded-xl bg-mist p-3 text-sm leading-6 text-muted">No verified fish records are available for this water body. This does not establish that fish are absent. Potential habitat compatibility remains unassessed.</p>
        @endforelse
    </div>
</section>
</details>
