<h2 id="review-heading" class="text-lg font-semibold">{{ $report->forwarded() ? 'Municipality referral' : 'Send to Municipality' }}</h2>
<p class="mt-3 text-sm text-muted">Review the report, evidence and location. Sending approves the report for referral and preserves your decision. It does not resolve the environmental concern.</p>
@if(!$ready)<p role="alert" class="mt-3 text-sm">SMTP configuration is incomplete. Follow the local email setup guide before sending.</p>@endif
@if($canSendReferral)
    <p class="mt-3 text-xs text-muted">Choose the incident municipality and explain your selection. Review one final confirmation before sending. Opening the preview records your selection; cancelling sends no email.</p>
    <form id="municipality-send-form" method="POST" action="{{ route('admin.referrals.store', $report) }}" data-review-url="{{ route('admin.referrals.review', $report) }}" data-smtp-ready="{{ $ready ? '1' : '0' }}" class="mt-4 space-y-4">
        @csrf
        <input type="hidden" name="point_key" value="{{ $incidentRouting['point_key'] }}"><input type="hidden" name="expected_status" value="{{ $report->status }}">
        <div><label class="form-label" for="agency_id">Incident municipality / recipient email</label><select id="agency_id" name="agency_id" class="form-input" required><option value="">Select after reviewing the incident location</option>@foreach($recipients as $recipient)@if(!$referral || $referral->agency_id === $recipient->id)<option value="{{ $recipient->id }}" @selected($suggestedRecipient === $recipient->id)>{{ $recipient->municipality }} / {{ $recipient->email }}</option>@endif @endforeach</select></div>
        <div><label class="form-label" for="routing_reason">Selection / override reason (required)</label><textarea class="form-input" id="routing_reason" name="routing_reason" rows="3" maxlength="2000" required>{{ is_string(old('routing_reason')) ? old('routing_reason') : ($routingSelection['reason'] ?? '') }}</textarea></div>
        @if($referral)<input type="hidden" name="include_photo" value="{{ (int)$referral->include_photo }}"><p class="text-xs text-muted">Sending uses the original approved recipient, content and attachment snapshot shown below.</p>
        @else<input type="hidden" name="include_photo" value="0">@if($report->photo_path)<label class="flex gap-3 text-sm"><input type="checkbox" id="include_photo" name="include_photo" value="1"><span>Include the supporting photo only if sharing is permitted and appropriate.</span></label>@endif @endif
        <input type="hidden" name="review_hash" id="send-review-hash">
        <p id="send-preview-error" role="alert" class="text-sm text-red-800" hidden></p>
        <button type="button" id="send-to-municipality" class="primary-button w-full" @disabled(!$ready || !$selectedRecipient || !$sendOptions)>Send to Municipality</button>
        <dialog id="municipality-confirmation" class="m-auto max-h-[90vh] w-[min(95vw,48rem)] overflow-y-auto rounded-xl border border-line bg-white p-5 text-ink backdrop:bg-estuary/40" aria-labelledby="municipality-confirm-heading">
            <h3 id="municipality-confirm-heading" class="text-xl font-semibold">Confirm municipality referral</h3><p id="send-recipient-preview" class="mt-3 break-words text-sm font-semibold"></p><pre id="send-message-preview" class="mt-4 whitespace-pre-wrap break-words font-sans text-sm leading-6"></pre><p id="send-photo-preview" class="mt-3 text-sm"></p>
            <label class="mt-4 flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I reviewed the receiver, content, precise location and attachment permission. Send this referral.</span></label>
            <div class="mt-5 flex flex-wrap gap-3"><button type="submit" id="confirm-municipality-send" class="primary-button">Confirm and send</button><button type="button" id="cancel-municipality-send" class="secondary-button">Cancel</button></div>
        </dialog>
    </form>
    <noscript><p class="mt-3 text-sm">Enable JavaScript to review the private confirmation before sending. No email is sent from this page without confirmation.</p></noscript>
@elseif(!$referral)<p class="mt-3 text-sm">No eligible municipality recipient is available, or this report is closed. Review the <a class="text-water underline" href="{{ route('admin.agencies.index') }}">Agency Directory</a>.</p>@endif
@if($referral)
    <p id="delivery-current-status" data-delivery-poll="{{ route('admin.referrals.status', $report) }}" data-delivery-state="{{ $referral->status }}" data-delivery-approved="{{ $referral->deliveryApproved() ? '1' : '0' }}" class="mt-4 text-sm" role="status">{{ $referral->label() }}</p>
    @include('admin.reports.delivery', ['delivery'=>$referral,'allowActions'=>true])
@endif
