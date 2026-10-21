<section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="incident-municipality-heading">
    <h2 id="incident-municipality-heading" class="text-lg font-semibold">Incident municipality</h2>
    <p class="mt-3 text-sm">Water-body recorded municipality: <strong>{{ $report->waterBody->municipality }}</strong></p>
    <p class="mt-3 text-sm">Incident municipality: <strong id="incident-municipality">{{ $routingSelection['municipality'] ?? 'Not selected / not verified' }}</strong>@if($routingSelection) (admin selected)@endif</p>
    <p class="mt-3 text-xs text-muted">{{ $incidentRouting['status'] === 'missing_incident_point' ? 'No saved incident coordinates are available.' : 'Saved incident coordinates are the routing reference, but no verified municipality boundary layer is installed.' }} Select the municipality manually after reviewing the location. Water-body locality, nearest town centres and routing assignments do not establish incident jurisdiction.</p>
    @if($routingSelection)<p class="mt-3 whitespace-pre-wrap break-words text-sm">Selection / override reason: {{ $routingSelection['reason'] }}</p>@endif
    @if(!$canSelectMunicipality)<p class="mt-3 text-xs text-muted">Existing decisions and referral snapshots are preserved. Historical referrals without an incident selection remain unverified.</p>@endif
</section>
