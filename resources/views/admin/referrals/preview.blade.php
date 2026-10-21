@extends('layouts.admin')
@section('title', 'Preview referral')
@section('content')
<a href="{{ route('admin.reports.show', $report) }}" class="text-water underline">Back to report</a>
<h1 class="mt-4 text-3xl font-semibold">Review the referral email</h1>
<p class="mt-3 text-sm">{{ $agency->name }} / {{ $agency->email }} / {{ ucfirst($agency->verification_status) }}</p>
@if(!$ready)<p class="mt-4 rounded-lg border border-amber-300 bg-white p-4 text-sm">SMTP sender setup is incomplete. Configure it using the local email setup guide before sending. No credentials are displayed here.</p>@endif
@if($report->status !== 'verified')<p class="mt-4 text-sm font-semibold">Verify the report using its review decision before approving a referral.</p>@endif
<div class="mt-5 rounded-xl border border-line bg-white p-5 sm:p-7"><pre class="whitespace-pre-wrap break-words font-sans text-sm leading-7">@include('mail.referral-text', ['reference'=>'Assigned on approval'])</pre></div>
@if($includePhoto)<p class="mt-4 text-sm">Attachment: approved-supporting-photo.jpg. The approved image will be re-encoded to remove embedded metadata; original private evidence remains unchanged.</p>@endif
<form class="mt-5 max-w-3xl space-y-4" method="POST" action="{{ route('admin.referrals.store', $report) }}">
@csrf
<input type="hidden" name="agency_id" value="{{ $agency->id }}"><input type="hidden" name="include_photo" value="{{ (int)$includePhoto }}"><input type="hidden" name="review_hash" value="{{ $reviewHash }}">
<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I reviewed the recipient, description, precise coordinates and attachment permission. The email contains no unnecessary personal information.</span></label>
<button class="primary-button" @disabled(!$ready || $report->status !== 'verified')>Approve &amp; Send</button>
</form>
@endsection
