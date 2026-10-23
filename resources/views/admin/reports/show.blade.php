@extends('layouts.admin')
@section('title', 'Report details')
@section('content')
    <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-water underline underline-offset-4">Back to reports</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3"><h1 class="text-3xl font-semibold tracking-tight">Report #{{ $report->id }}</h1><div id="report-current-status" class="flex flex-wrap items-center gap-3"><span class="text-xs text-muted">Report status:</span><span class="rounded-lg bg-white px-3 py-2 text-sm font-semibold">{{ \App\Models\PollutionReport::CATEGORIES[$report->category] }}</span>@if(in_array($report->status, ['under_review','in_progress'], true))@include('admin.reports.status', ['status' => $report->status])@endif @if($referral)<span class="text-xs text-muted">Email delivery:</span><span class="rounded-lg bg-white px-3 py-2 text-sm font-semibold">{{ $referral->label() }}</span>@endif</div></div>
    @if(session('review_saved'))<p id="review-success" role="status" tabindex="-1" class="mt-5 rounded-xl border border-moss/30 bg-white p-4 text-sm text-moss">Your decision was recorded in the audit history. No email was sent.</p>@endif
    @if(session('referral_saved'))<p role="status" class="mt-4 rounded-lg border border-line bg-white p-4 text-sm">{{ session('referral_saved') }}</p>@endif
    @if(session('routing_saved'))<p role="status" class="mt-4 rounded-lg border border-line bg-white p-4 text-sm">{{ session('routing_saved') }}</p>@endif
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="mb-4 text-lg font-semibold">Report Information</h2>
                <h2 class="text-xl font-semibold">{{ $report->waterBody->name }}</h2>
                <p class="mt-2 text-sm text-muted">{{ $report->waterBody->municipality }} &middot; {{ \App\Models\PollutionReport::TYPES[$report->pollution_type] ?? 'Other observation' }}</p>
                <p class="mt-2 text-xs leading-5 text-muted">Submitted {{ $report->created_at->format('M j, Y H:i:s') }} {{ config('app.timezone') }}</p>
                <h3 class="mt-5 text-sm font-semibold">What the reporter saw</h3><p id="report-description" class="mt-2 text-sm leading-7 break-words whitespace-pre-wrap">{{ $report->description }}</p>
                <h3 class="mt-5 text-sm font-semibold">Reported problem location</h3>
                @if($report->latitude !== null && $report->longitude !== null)
                    <div id="private-report-map" class="mt-3 h-72 rounded-lg border border-line" role="region" aria-label="Private reported problem location" data-latitude="{{ $report->latitude }}" data-longitude="{{ $report->longitude }}"></div>
                    <p id="private-tiles" role="status" class="mt-2 text-sm text-muted" hidden>Basemap tiles are unavailable. The saved coordinates and incident marker remain available below and on this map.</p>
                @endif
                <p class="mt-3 text-sm">Landmark / barangay supplied by reporter: {{ $report->landmark ?: 'Not provided' }}.</p>
                <p class="mt-2 text-sm">Observation date: {{ $report->observed_on?->format('Y-m-d') ?: 'Not provided' }}.</p>
                @if($report->location_accuracy_m !== null)<p class="mt-2 text-sm">Device-reported GPS accuracy: {{ $report->location_accuracy_m }} metres; not surveyed accuracy.</p>@endif
                <p class="mt-2 text-sm leading-6">@if($report->latitude !== null && $report->longitude !== null)Latitude {{ $report->latitude }}, longitude {{ $report->longitude }}@else No incident point was provided. @endif</p>
                <p class="mt-2 text-xs leading-5 text-muted">Incident points are reporter-selected and unverified. Lake reference datum and precision remain unconfirmed. A lake reference point is never substituted for an incident location.</p>
                <h3 class="mt-5 text-sm font-semibold">Private photo evidence</h3>
                @if($report->photo_path)
                    <a id="private-photo" href="{{ route('admin.reports.photo', $report) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm font-semibold text-water underline underline-offset-4">View private photo (opens in a new tab)</a>
                @else
                    <p class="mt-2 text-sm text-muted">No photo was provided.</p>
                @endif
            </section>
            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Private reporter details</h2>
                <dl class="mt-4 space-y-3 text-sm break-words"><div><dt class="font-semibold">Name</dt><dd class="mt-1">{{ $report->reporter_name ?: 'Not provided (anonymous submission).' }}</dd></div><div><dt class="font-semibold">Email</dt><dd class="mt-1">{{ $report->reporter_email ?: 'Not provided.' }}</dd></div></dl>
                <p class="mt-4 text-xs leading-5 text-muted">Use contact information only for necessary follow-up. Do not copy it into review notes or public channels.</p>
            </section>
            @include('admin.reports.evidence')
            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Audit history</h2>
                <ol class="mt-4 space-y-5" id="audit-history">
                    @forelse($report->reviews as $review)
                        <li id="report-review-{{ $review->id }}" class="border-l-2 border-water pl-4 text-sm">@if($selection = $review->incidentRouting())<p class="font-semibold">Incident municipality selected: {{ $selection['municipality'] }}</p><p class="mt-2 whitespace-pre-wrap break-words">Selection / override reason: {{ $selection['reason'] }}</p>@else<p class="font-semibold">{{ \App\Models\PollutionReport::STATUSES[$review->previous_status] ?? ucfirst($review->previous_status) }} to {{ \App\Models\PollutionReport::STATUSES[$review->new_status] ?? ucfirst($review->new_status) }}</p><p class="mt-2 leading-6 break-words whitespace-pre-wrap">{{ $review->review_note ?: 'No review note provided.' }}</p>@endif<p class="mt-1 text-xs leading-5 text-muted">{{ $review->administrator->name }} &middot; {{ $review->created_at->format('M j, Y H:i:s') }} {{ config('app.timezone') }}</p></li>
                    @empty
                        <li class="text-sm text-muted">No status changes have been recorded.</li>
                    @endforelse
                </ol>
            </section>
        </div>
        <div class="min-w-0 space-y-6">
            @include('admin.reports.incident-municipality')
            @if($canSendReferral)<section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="review-heading">@include('admin.reports.send')</section>@endif
            @include('admin.reports.actions')
            @if($referral)@include('admin.reports.delivery', ['delivery'=>$referral,'allowActions'=>true])
            @else<section class="rounded-xl border border-line bg-white p-5 sm:p-6"><h2 class="text-lg font-semibold">Email Information</h2><p class="mt-3 text-sm">Not Sent</p><p class="mt-2 text-xs text-muted">No email attempt has been made.</p></section>@endif
        </div>
    </div>
@endsection
