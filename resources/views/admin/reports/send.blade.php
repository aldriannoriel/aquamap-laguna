<h2 id="review-heading" class="text-lg font-semibold">Send to Municipality</h2>
<p class="mt-3 text-sm text-muted">Review the report, evidence and location. Sending approves the report for referral and preserves your decision. It does not resolve the environmental concern.</p>
@if(!$ready)<p role="alert" class="mt-3 text-sm">SMTP configuration is incomplete. Follow the local email setup guide before sending.</p>@endif
@if($sendOptions)
    <p class="mt-3 text-xs text-muted">{{ $suggestedRecipient ? 'Recipient selected from verified municipality data. Review it before sending.' : 'Municipality is unknown or ambiguous. Select and verify the recipient manually.' }}</p>
    <form id="municipality-send-form" method="POST" action="{{ route('admin.referrals.store', $report) }}" class="mt-4 space-y-4">
        @csrf
        <div><label class="form-label" for="agency_id">Municipality recipient</label><select id="agency_id" name="agency_id" class="form-input" required><option value="">Choose a recipient manually</option>@foreach($recipients as $recipient)@if(isset($sendOptions[(string)$recipient->id]))<option value="{{ $recipient->id }}" @selected($suggestedRecipient === $recipient->id)>{{ $recipient->municipality }} / {{ $recipient->email }}</option>@endif @endforeach</select></div>
        @if($referral)<input type="hidden" name="include_photo" value="{{ (int)$referral->include_photo }}"><p class="text-xs text-muted">Sending uses the original approved recipient, content and attachment snapshot shown below.</p>
        @else<input type="hidden" name="include_photo" value="0">@if($report->photo_path)<label class="flex gap-3 text-sm"><input type="checkbox" id="include_photo" name="include_photo" value="1"><span>Include the supporting photo only if sharing is permitted and appropriate.</span></label>@endif @endif
        <input type="hidden" name="review_hash" id="send-review-hash">
        <button type="button" id="send-to-municipality" class="primary-button w-full" @disabled(!$ready)>Send to Municipality</button>
        <dialog id="municipality-confirmation" class="m-auto max-h-[90vh] w-[min(95vw,48rem)] overflow-y-auto rounded-xl border border-line bg-white p-5 text-ink backdrop:bg-estuary/40" aria-labelledby="municipality-confirm-heading">
            <h3 id="municipality-confirm-heading" class="text-xl font-semibold">Confirm municipality referral</h3><p id="send-recipient-preview" class="mt-3 break-words text-sm font-semibold"></p><pre id="send-message-preview" class="mt-4 whitespace-pre-wrap break-words font-sans text-sm leading-6"></pre><p id="send-photo-preview" class="mt-3 text-sm"></p>
            <label class="mt-4 flex gap-3 text-sm"><input type="checkbox" name="approved" value="1" required><span>I reviewed the receiver, content, precise location and attachment permission. Send this referral.</span></label>
            <div class="mt-5 flex flex-wrap gap-3"><button type="submit" id="confirm-municipality-send" class="primary-button">Confirm and send</button><button type="button" id="cancel-municipality-send" class="secondary-button">Cancel</button></div>
        </dialog>
    </form>
    <script id="municipality-send-options" type="application/json">@json($sendOptions)</script>
@elseif(!$referral)<p class="mt-3 text-sm">No eligible municipality recipient is available, or this report is closed. Review the <a class="text-water underline" href="{{ route('admin.agencies.index') }}">Agency Directory</a>.</p>@endif
@if($referral)
    <p id="delivery-current-status" data-delivery-poll="{{ route('admin.referrals.status', $report) }}" data-delivery-state="{{ $referral->status }}" data-delivery-approved="{{ $referral->deliveryApproved() ? '1' : '0' }}" class="mt-4 text-sm" role="status">{{ $referral->label() }}</p>
    @include('admin.reports.delivery', ['delivery'=>$referral,'allowActions'=>true])
@endif
@if(!$referral && in_array($report->status, ['pending','verified'], true))
    <form id="dismiss-report-form" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-6 space-y-3 border-t border-line pt-5">@csrf @method('PATCH')<input type="hidden" name="expected_status" value="{{ $report->status }}"><input type="hidden" name="status" value="{{ $report->status === 'pending' ? 'rejected' : 'resolved' }}"><label class="form-label" for="review_note">Reason for closing without email (optional)</label><textarea class="form-input" name="review_note" id="review_note" rows="3" maxlength="2000">{{ is_string(old('review_note')) ? old('review_note') : '' }}</textarea><button class="secondary-button">{{ $report->status === 'pending' ? 'Reject without sending' : 'Close without sending' }}</button></form>
@endif
