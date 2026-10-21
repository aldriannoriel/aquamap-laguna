@extends('layouts.admin')
@section('title', 'Referrals and email test')
@section('content')
<h1 class="text-3xl font-semibold">Referrals &amp; email test</h1>
<p class="mt-3 max-w-3xl text-sm leading-6 text-muted">Reports are sent only after administrator review. Transport acceptance does not prove inbox delivery, agency review or action. Sending never resolves a report.</p>
@if(session('referral_saved'))<p role="status" class="mt-5 rounded-lg border border-line bg-white p-4 text-sm">{{ session('referral_saved') }}</p>@endif
<section class="mt-6 rounded-xl border border-line bg-white p-5">
<h2 class="text-xl font-semibold">Check delivery to the testing inbox</h2>
<p class="mt-3 text-sm">{{ $ready ? 'SMTP settings are present. Delivery has not been verified by this check.' : 'SMTP setup is incomplete. Follow docs/EMAIL_REFERRALS.md locally; configure sender credentials in .env and start the referral queue worker.' }}</p>
<form method="POST" action="{{ route('admin.referrals.test') }}" class="mt-4 space-y-4">
@csrf<input type="hidden" name="request_token" value="{{ $testToken }}">
<label class="flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>Send one live test email to <strong>{{ \App\Models\Referral::TEST_RECIPIENT }}</strong>. This includes no community report or private photo.</span></label>
<button class="primary-button" @disabled(!$ready)>Send live test email</button>
</form></section>
<div class="mt-6 space-y-4">
@forelse($referrals as $referral)
<article class="rounded-xl border border-line bg-white p-5">
<h2 class="break-all text-lg font-semibold">{{ $referral->reference }}</h2>
<p class="mt-2 text-sm">{{ $referral->recipient_snapshot['name'] }} / {{ $referral->recipient_snapshot['email'] }}</p>
<p class="mt-2 text-sm"><strong>{{ $referral->status === 'accepted' ? 'Accepted by transport' : ucfirst($referral->status) }}</strong> / {{ $referral->pollution_report_id ? 'Report AQ-'.$referral->pollution_report_id : 'Live delivery test' }}</p>
<dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
@foreach(['approved_at'=>'Approved','queued_at'=>'Queued','sending_at'=>'Sending started','sent_at'=>'Transport accepted','failed_at'=>'Failure / uncertain outcome','inbox_confirmed_at'=>'Administrator-confirmed inbox receipt'] as $field=>$label)
<div><dt class="font-semibold">{{ $label }}</dt><dd>{{ $referral->$field?->toIso8601String() ?: 'Not recorded' }}</dd></div>
@endforeach
</dl>
@if($referral->provider_message_id)<p class="mt-3 break-all text-xs">Transport message ID: {{ $referral->provider_message_id }}</p>@endif
@if($referral->error_code)<p class="mt-3 text-sm">Delivery code: {{ $referral->error_code }}. Check sender configuration or provider records; credentials and raw transport errors are never shown.</p>@endif
@if(in_array($referral->status, ['sending','uncertain']))<p class="mt-3 text-sm font-semibold">Delivery may already have occurred. Do not resend without checking provider records and the inbox.</p>@endif
<details class="mt-3 text-sm"><summary>Referral history</summary><ol class="mt-2 space-y-2">@foreach($referral->events as $event)<li>{{ $event->created_at->toIso8601String() }} / {{ $event->event }}</li>@endforeach</ol></details>
@if($referral->status === 'failed' && !$referral->sending_at)
<form method="POST" action="{{ route('admin.referrals.retry', $referral) }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf<label class="text-sm"><input type="checkbox" name="approved" value="1" required> Configuration fixed; retry this pre-send failure.</label><button class="secondary-button">Retry safely</button></form>
@endif
@if(!$referral->pollution_report_id && $referral->status === 'accepted' && !$referral->inbox_confirmed_at)
<form method="POST" action="{{ route('admin.referrals.confirm', $referral) }}" class="mt-4 space-y-3">@csrf<label class="form-label" for="receipt-{{ $referral->id }}">Reference found in the received test email</label><input class="form-input" id="receipt-{{ $referral->id }}" name="confirmation_reference" required autocomplete="off"><label class="flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I personally found this exact reference in aquamaplaguna@gmail.com.</span></label><button class="secondary-button">Record inbox receipt</button></form>
@endif
</article>
@empty<p class="rounded-xl border border-line bg-white p-6 text-sm">No referrals have been approved. Review a verified report to choose a recipient, or use the explicit live test above.</p>@endforelse
</div><div class="mt-5">{{ $referrals->links() }}</div>
@endsection
