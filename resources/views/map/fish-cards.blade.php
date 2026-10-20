<section data-fish-section class="mt-0" aria-label="Fish and habitat information">
    <h3 class="text-base font-semibold text-estuary">Fish &amp; habitat</h3>
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
                        <p data-filipino-name class="mt-1 text-xs text-muted">Filipino common name: Not yet verified.</p>
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
