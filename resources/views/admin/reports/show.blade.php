@extends('layouts.admin')
@section('title', 'Report details')
@section('content')
    <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-water underline underline-offset-4">Back to reports</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3"><h1 class="text-3xl font-semibold tracking-tight">Report #{{ $report->id }}</h1><div id="report-current-status">@include('admin.reports.status', ['status' => $report->status])</div></div>
    @if(session('review_saved'))<p id="review-success" role="status" tabindex="-1" class="mt-5 rounded-xl border border-moss/30 bg-white p-4 text-sm text-moss">Status updated. The review has been recorded in the audit history.</p>@endif
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="text-xl font-semibold">{{ $report->waterBody->name }}</h2>
                <p class="mt-2 text-sm text-muted">{{ $report->waterBody->municipality }} &middot; {{ \App\Models\PollutionReport::TYPES[$report->pollution_type] ?? 'Other observation' }}</p>
                <p class="mt-2 text-xs leading-5 text-muted">Submitted {{ $report->created_at->format('M j, Y H:i:s') }} {{ config('app.timezone') }}</p>
                <h3 class="mt-5 text-sm font-semibold">Observation</h3><p id="report-description" class="mt-2 text-sm leading-7 break-words whitespace-pre-wrap">{{ $report->description }}</p>
                <h3 class="mt-5 text-sm font-semibold">Reported incident location</h3>
                @if($report->latitude !== null && $report->longitude !== null)
                    <div id="private-report-map" class="mt-3 h-72 rounded-lg border border-line" role="region" aria-label="Private reported problem location" data-latitude="{{ $report->latitude }}" data-longitude="{{ $report->longitude }}"></div>
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
            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Audit history</h2>
                <ol class="mt-4 space-y-5" id="audit-history">
                    @forelse($report->reviews as $review)
                        <li class="border-l-2 border-water pl-4 text-sm"><p class="font-semibold">{{ ucfirst($review->previous_status) }} to {{ ucfirst($review->new_status) }}</p><p class="mt-1 text-xs leading-5 text-muted">{{ $review->administrator->name }} &middot; {{ $review->created_at->format('M j, Y H:i:s') }} {{ config('app.timezone') }}</p><p class="mt-2 leading-6 break-words whitespace-pre-wrap">{{ $review->review_note ?: 'No review note provided.' }}</p></li>
                    @empty
                        <li class="text-sm text-muted">No status changes have been recorded.</li>
                    @endforelse
                </ol>
            </section>
        </div>
        <section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="review-heading">
            <div class="mb-6 border-b border-line pb-6">
                <h2 class="text-lg font-semibold">Environmental referral</h2>
                @if($referral)<p class="mt-3 text-sm">Referral {{ $referral->reference }} / {{ $referral->status }}. Repeated approval does not send again.</p><a class="mt-3 inline-block text-water underline" href="{{ route('admin.referrals.index') }}">View referral history</a>
                @elseif($recipients->isNotEmpty())
                    <p class="mt-3 text-xs text-muted">Suggestions use explicit water-body assignments and locality labels. They do not establish barangay jurisdiction. Verify the report before sending.</p>
                    <form method="GET" action="{{ route('admin.referrals.preview', $report) }}" class="mt-4 space-y-4">
                        <div><label for="agency_id" class="form-label">Suggested recipient / change recipient</label><select id="agency_id" name="agency_id" class="form-input" required>@foreach($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} / {{ $recipient->verification_status }} / {{ $recipient->email }}</option>@endforeach</select></div>
                        @if($report->photo_path)<label class="flex gap-3 text-sm"><input type="checkbox" name="include_photo" value="1"><span>Include the supporting photo only if sharing is permitted and appropriate.</span></label>@endif
                        <button class="secondary-button">Preview email</button>
                    </form>
                @else<p class="mt-3 text-sm">No active recipient is assigned to this water body. <a class="text-water underline" href="{{ route('admin.agencies.create') }}">Add a provisional or verified recipient</a>.</p>@endif
            </div>
            <h2 id="review-heading" class="text-lg font-semibold">Review decision</h2>
            @if($available = \App\Models\PollutionReport::TRANSITIONS[$report->status] ?? [])
                <form id="review-form" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $report->status }}">
                    <div><label for="status" class="form-label">Next status</label><select id="status" name="status" class="form-input" required><option value="">Choose a decision</option>@foreach($available as $value)<option value="{{ $value }}" @selected(old('status') === $value)>{{ \App\Models\PollutionReport::STATUSES[$value] }}</option>@endforeach</select></div>
                    <div><label for="review_note" class="form-label">Review note (optional)</label><textarea id="review_note" name="review_note" rows="4" maxlength="2000" class="form-input" aria-describedby="review-note-help">{{ is_string(old('review_note')) ? old('review_note') : '' }}</textarea><p id="review-note-help" class="mt-2 text-xs leading-5 text-muted">Describe the reason for your decision. Avoid additional personal information. Maximum 2,000 characters.</p></div>
                    <button id="save-review" type="submit" class="min-h-12 w-full rounded-lg bg-estuary px-4 py-3 text-sm font-semibold text-white hover:bg-water">Record decision</button>
                </form>
            @else
                <p class="mt-3 text-sm leading-6 text-muted">No further status changes are available for this report.</p>
            @endif
            <p class="mt-5 text-xs leading-5 text-muted">Reports remain private after review. Status changes do not create water-quality measurements or habitat assessments.</p>
        </section>
    </div>
@endsection
