@php($suffix = !empty($compact) ? '-'.$report->id : '')
<section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="follow-up-heading{{ $suffix }}">
    <h2 id="follow-up-heading{{ $suffix }}" class="text-lg font-semibold">{{ $report->forwarded() ? 'Report follow-up' : 'Report review' }}</h2>
    <p class="mt-3 text-sm text-muted">Email delivery and report resolution are separate. Sending an email never resolves this report.</p>
    @if($report->canReject())
        <form id="reject-report-form{{ $suffix }}" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_status" value="{{ $report->status }}"><input type="hidden" name="status" value="rejected">
            <div><label class="form-label" for="rejection_reason{{ $suffix }}">Rejection reason (required)</label><textarea class="form-input" id="rejection_reason{{ $suffix }}" name="rejection_reason" rows="3" maxlength="2000" required>{{ is_string(old('rejection_reason')) ? old('rejection_reason') : '' }}</textarea></div>
            <p class="text-xs text-muted">Rejecting records your reason and cancels any referral that has not started SMTP submission. Evidence and history stay available.</p>
            <button class="secondary-button" type="submit">Reject Report</button>
        </form>
    @elseif($report->forwarded() && $report->status === 'closed')
        <form id="reopen-report-form{{ $suffix }}" method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_status" value="closed"><input type="hidden" name="status" value="under_review">
            <div><label class="form-label" for="reopen_reason{{ $suffix }}">New information / reason to reopen (required)</label><textarea class="form-input" id="reopen_reason{{ $suffix }}" name="reopen_reason" rows="3" maxlength="2000" required></textarea></div>
            <p class="text-xs text-muted">Reopening returns this case to Forwarded / Under Review. The original closure and delivery history stay intact. No email is sent.</p>
            <button class="primary-button" type="submit">Reopen Case</button>
        </form>
    @elseif(($report->forwarded() && $report->availableTransitions()) || in_array($report->status, ['pending', 'verified', 'under_review', 'in_progress'], true))
        @php($followUpBlocked = !$report->forwarded())
        @php($transitions = $followUpBlocked ? array_intersect(\App\Models\PollutionReport::TRANSITIONS[$report->status], ['under_review', 'in_progress', 'resolved', 'closed']) : $report->availableTransitions())
        @if($followUpBlocked)
            <p id="follow-up-prerequisite{{ $suffix }}" role="status" class="mt-3 text-sm text-muted">Follow-up controls require recorded SMTP acceptance under the current forwarding policy. No acceptance is recorded for this report. Sending or an uncertain delivery does not establish forwarding. Review Email Information below; rejection remains unavailable after SMTP submission starts.</p>
        @endif
        <form id="report-follow-up-form{{ $suffix }}" data-report-follow-up method="POST" enctype="multipart/form-data" action="{{ route('admin.reports.status', $report) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <fieldset class="space-y-4" @disabled($followUpBlocked) @if($followUpBlocked) aria-describedby="follow-up-prerequisite{{ $suffix }}" @endif>
            <input type="hidden" name="expected_status" value="{{ $report->status }}">
            <div class="flex flex-wrap gap-3"><button type="button" class="secondary-button" data-status-choice="municipality_update">Record Municipality Update</button><button type="button" class="primary-button" data-status-choice="resolved">Mark as Resolved</button><button type="button" class="secondary-button" data-status-choice="closed">Close Case</button></div>
            <div><label class="form-label" for="follow_up_status{{ $suffix }}">Outcome or follow-up status</label><select class="form-input" id="follow_up_status{{ $suffix }}" name="status" required><option value="">Choose an action</option><option value="municipality_update">Record Municipality Update</option>@foreach($transitions as $value)<option value="{{ $value }}" @selected(old('status') === $value)>{{ \App\Models\PollutionReport::STATUSES[$value] }}</option>@endforeach</select></div>
            <div data-evidence-fields><label class="form-label" for="supporting_evidence{{ $suffix }}">Supporting Evidence <span data-evidence-required>(Required for Resolved)</span></label><input class="form-input" id="supporting_evidence{{ $suffix }}" data-evidence-input name="supporting_evidence[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" aria-describedby="evidence-help{{ $suffix }}"><p id="evidence-help{{ $suffix }}" class="mt-2 text-xs text-muted">Upload pictures or documents: up to 5 files, 5 MB each. Follow-up attachments are optional. Files remain private. Add remarks or evidence for a municipality update.</p><p data-evidence-error class="mt-2 text-sm text-red-800" role="alert" hidden></p><ul data-selected-evidence class="mt-3 space-y-3" aria-label="Selected supporting evidence"></ul></div>
            <div><label class="form-label" for="review_note{{ $suffix }}">Remarks (Optional)</label><textarea class="form-input" id="review_note{{ $suffix }}" name="review_note" rows="3" maxlength="2000">{{ is_string(old('review_note')) ? old('review_note') : '' }}</textarea></div>
            <div data-resolution-fields><label class="flex gap-3 text-sm"><input type="checkbox" name="resolution_confirmed" value="1"><span>I reviewed the supporting evidence and confirm that it supports resolving this environmental concern. Uploading a file alone does not establish resolution.</span></label></div>
            <div data-closure-fields><label class="form-label" for="closure_reason{{ $suffix }}">Closure reason (required for Closed)</label><textarea class="form-input" id="closure_reason{{ $suffix }}" name="closure_reason" rows="3" maxlength="2000">{{ is_string(old('closure_reason')) ? old('closure_reason') : '' }}</textarea><label class="form-label mt-3" for="follow_up_attempts{{ $suffix }}">Relevant follow-up attempts (if any)</label><textarea class="form-input" id="follow_up_attempts{{ $suffix }}" name="follow_up_attempts" rows="3" maxlength="2000">{{ is_string(old('follow_up_attempts')) ? old('follow_up_attempts') : '' }}</textarea><p class="mt-2 text-xs text-muted">Closed records administrative closure without verified resolution. A lack of municipality response does not automatically close a case.</p></div>
            <button type="submit" class="primary-button" data-status-submit>Save report update</button>
            </fieldset>
        </form>
    @elseif(in_array($report->status, ['rejected','resolved','closed'], true))
        <p class="mt-3 text-sm text-muted">This report's decision is preserved. Its evidence, delivery logs and complete audit history remain available.</p>
    @else
        <p class="mt-3 text-sm text-muted">Rejection is unavailable while SMTP submission is in progress or its outcome is uncertain. Review delivery history before further action.</p>
    @endif
</section>
