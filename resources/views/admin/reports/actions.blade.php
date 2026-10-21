<section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="follow-up-heading">
    <h2 id="follow-up-heading" class="text-lg font-semibold">{{ $report->forwarded() ? 'Report follow-up' : 'Report review' }}</h2>
    <p class="mt-3 text-sm text-muted">Email delivery and report resolution are separate. Sending an email never resolves this report.</p>
    @if($report->canReject())
        <form id="reject-report-form" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_status" value="{{ $report->status }}"><input type="hidden" name="status" value="rejected">
            <div><label class="form-label" for="rejection_reason">Rejection reason (required)</label><textarea class="form-input" id="rejection_reason" name="rejection_reason" rows="4" maxlength="2000" required>{{ is_string(old('rejection_reason')) ? old('rejection_reason') : '' }}</textarea></div>
            <p class="text-xs text-muted">Rejecting records your reason and cancels any referral that has not started SMTP submission. Evidence and history stay available.</p>
            <button class="secondary-button" type="submit">Reject Report</button>
        </form>
    @elseif($report->forwarded() && ($transitions = $report->availableTransitions()))
        <p class="mt-3 text-sm text-muted">This report was forwarded. Record municipality follow-up here; these updates do not send another email.</p>
        <form id="report-follow-up-form" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_status" value="{{ $report->status }}">
            <div><label class="form-label" for="follow_up_status">Report status</label><select class="form-input" id="follow_up_status" name="status" required><option value="">Choose a status</option>@foreach($transitions as $value)<option value="{{ $value }}" @selected(old('status') === $value)>{{ \App\Models\PollutionReport::STATUSES[$value] }}</option>@endforeach</select></div>
            <div><label class="form-label" for="review_note">Remarks (required for Resolved)</label><textarea class="form-input" id="review_note" name="review_note" rows="4" maxlength="2000">{{ is_string(old('review_note')) ? old('review_note') : '' }}</textarea></div>
            <div id="resolution-basis-fields"><label class="form-label" for="resolution_basis">Documented resolution basis or supporting evidence reference (required for Resolved)</label><textarea class="form-input" id="resolution_basis" name="resolution_basis" rows="5" maxlength="4000" aria-describedby="resolution-basis-help">{{ is_string(old('resolution_basis')) ? old('resolution_basis') : '' }}</textarea><p id="resolution-basis-help" class="mt-2 text-xs text-muted">Document the inspection result, municipality response, case/document reference or follow-up evidence supporting resolution. A sent email or the original concern photo alone does not establish resolution.</p></div>
            <div id="closure-reason-fields"><label class="form-label" for="closure_reason">Closure reason (required for Closed)</label><textarea class="form-input" id="closure_reason" name="closure_reason" rows="4" maxlength="2000">{{ is_string(old('closure_reason')) ? old('closure_reason') : '' }}</textarea><p class="mt-2 text-xs text-muted">Use Closed for an administrative closure. This records no environmental resolution and does not reject a forwarded report.</p></div>
            <button type="submit" class="primary-button">Update report status</button>
        </form>
    @elseif(in_array($report->status, ['rejected','resolved','closed'], true))
        <p class="mt-3 text-sm text-muted">This report's decision is final. Its evidence, delivery logs and complete audit history remain available.</p>
    @else
        <p class="mt-3 text-sm text-muted">Rejection is unavailable while SMTP submission is in progress or its outcome is uncertain. Review delivery history before further action.</p>
    @endif
</section>
