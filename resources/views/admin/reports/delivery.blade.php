@if($delivery->pollution_report_id !== null)
<section class="mt-5 rounded-xl border border-line bg-white p-5" aria-label="Email Information" data-delivery-reference="{{ $delivery->reference }}">
    <h2 class="text-lg font-semibold">Email Information</h2>
    <p id="delivery-current-status" data-delivery-poll="{{ route('admin.referrals.status', $report) }}" data-delivery-state="{{ $delivery->status }}" data-delivery-interrupted="{{ $delivery->interrupted() ? '1' : '0' }}" data-delivery-approved="{{ $delivery->deliveryApproved() ? '1' : '0' }}" class="mt-3 text-sm" role="status">{{ $delivery->label() }}</p>
    @if($delivery->interrupted())
        <p role="alert" class="mt-3 text-sm">The delivery has not completed within five minutes. SMTP acceptance is not recorded. Check the exact reference in the receiver inbox and provider records. No automatic resend is permitted.</p>
        @if($allowActions ?? false)
            <form method="POST" action="{{ route('admin.referrals.reconcile', [$report, $delivery]) }}" class="mt-4 space-y-3">
                @csrf
                <label class="flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>Record this interrupted attempt without claiming acceptance or sending another email.</span></label>
                <button class="secondary-button" type="submit">Reconcile interrupted delivery</button>
            </form>
        @endif
    @endif
    <p class="mt-2 text-sm">Recipient: {{ $delivery->recipient_snapshot['municipality'] ?? $delivery->recipient_snapshot['name'] }} / {{ $delivery->recipient_snapshot['email'] }}</p>
    @if($delivery->status === 'uncertain')
        <p class="mt-3 break-words text-sm">Search the recipient inbox and provider records for this exact reference: <strong>{{ $delivery->reference }}</strong>. Preserve a matching received message with its date and full headers, or a provider acceptance record identifying this message and recipient. Approval to send and reconciliation of an interrupted attempt do not confirm acceptance. No matching inbox message is not proof of failed delivery.</p>
    @endif
    @if($delivery->error_code === 'smtp_request_interrupted')<p role="alert" class="mt-3 text-sm">The send request was interrupted without a saved SMTP acknowledgement. The outcome is uncertain: check the exact reference in the receiver inbox and provider records. Retry and rejection remain blocked to prevent duplicate or contradictory actions.</p>@endif
    @if($delivery->error_code === 'preflight_interrupted')<p role="alert" class="mt-3 text-sm">Preparation was interrupted before SMTP submission started. Review the preserved message and configuration before an explicitly confirmed safe retry.</p>@endif
    @if($delivery->status === 'accepted')<p role="status" class="mt-3 text-sm font-semibold text-moss">Referral email sent successfully. SMTP accepted the message; inbox receipt {{ $delivery->inbox_confirmed_at ? 'was separately confirmed by an administrator' : 'is not yet confirmed' }}.</p>@endif
    @if($delivery->status === 'cancelled')<p class="mt-3 text-sm">Referral cancelled after report rejection. Further sending and retries are disabled; previous delivery events remain in history.</p>@endif
    @if($delivery->status === 'queued')<p class="mt-3 text-sm">This legacy approval is paused. Review its preserved snapshot and explicitly confirm Send Email before delivery. Its stored job stays intact.</p>@endif
    @if($delivery->error_code && !in_array($delivery->error_code, ['smtp_request_interrupted', 'preflight_interrupted'], true) && $delivery->status !== 'cancelled')<p role="alert" class="mt-3 text-sm">{{ match($delivery->error_code) { 'smtp_auth_rejected' => 'Gmail rejected sender authentication. Update the sender App Password locally, clear configuration, then retry.', 'smtp_connection_failed' => 'SMTP connection or TLS failed before submission. Check host, port and TLS settings locally, retry after correcting the settings.', 'worker_interrupted' => $delivery->sending_at ? 'Worker stopped after submission began. Check the inbox before any resend.' : 'Worker stopped before SMTP submission. Use Retry safely after reviewing the saved message.', 'smtp_rejected' => 'SMTP rejected this message. Check receiver and provider settings, then retry.', 'preflight_failed' => 'Delivery preparation failed. Check SMTP configuration and the approved private attachment before retrying.', default => 'The delivery outcome is uncertain. Check the receiver inbox and provider records before any further action.' } }}</p>@endif
    <p class="mt-3 text-xs">Last attempt: {{ $delivery->sending_at?->toIso8601String() ?? $delivery->failed_at?->toIso8601String() ?? 'Not attempted' }}. SMTP accepted: {{ $delivery->sent_at?->toIso8601String() ?: 'Not recorded' }}.</p>
    <details class="mt-4 text-sm"><summary>Historical email attempts and technical details</summary><p class="mt-3 break-words text-xs">Original recipient: {{ $delivery->recipient_snapshot['name'] }} / {{ $delivery->recipient_snapshot['email'] }}</p><p class="mt-3 break-all text-xs">Reference: {{ $delivery->reference }}. Attempts: {{ $delivery->attempts }}. Approved {{ $delivery->approved_at?->toIso8601String() }}. Originally scheduled {{ $delivery->queued_at?->toIso8601String() }}.</p><pre class="mt-3 whitespace-pre-wrap break-words font-sans text-xs">@include('mail.referral-text', ['details'=>$delivery->payload,'reference'=>$delivery->reference])</pre><ol class="mt-3 space-y-2">@foreach($delivery->events as $event)<li>{{ $event->created_at->toIso8601String() }} / {{ $event->event }}</li>@endforeach</ol></details>
    @if(($allowActions ?? false) && $delivery->retryable())
        <form method="POST" action="{{ route('admin.referrals.retry', [$report, $delivery]) }}" data-confirm-send="Retry this confirmed unsent message to {{ $delivery->recipient_snapshot['email'] }}?" class="mt-4 space-y-3">@csrf<label class="flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I corrected the failure and approve retrying this preserved message.</span></label><button class="secondary-button">Retry safely</button></form>
    @endif
    @if(($allowActions ?? false) && $delivery->status === 'accepted' && !$delivery->inbox_confirmed_at)
        <details class="mt-4 text-sm"><summary>Record separately verified inbox receipt (optional)</summary>
        <form method="POST" action="{{ route('admin.referrals.confirm', [$report, $delivery]) }}" class="mt-4 space-y-3">@csrf<label class="form-label" for="confirmation_reference">Reference found in receiver inbox</label><input class="form-input" id="confirmation_reference" name="confirmation_reference" required autocomplete="off"><label class="flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I personally found this exact reference in the receiver inbox.</span></label><button class="secondary-button">Confirm receiver inbox receipt</button></form>
        </details>
    @endif
</section>
@endif
