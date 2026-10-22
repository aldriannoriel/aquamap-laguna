<section class="rounded-xl border border-line bg-white p-5 sm:p-6" aria-labelledby="resolution-evidence-heading">
    <h2 id="resolution-evidence-heading" class="text-lg font-semibold">Resolution Evidence</h2>
    <p class="mt-2 text-xs text-muted">Private supporting files from resolution and municipality follow-up actions. Original incident evidence and historical references remain in their own sections.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @forelse($report->reviews->flatMap->evidence as $evidence)
            <article class="min-w-0 rounded-lg border border-line p-4" data-stored-evidence>
                @if($evidence->isImage())<a href="{{ route('admin.reports.evidence', [$report, $evidence]) }}" target="_blank" rel="noopener noreferrer"><img src="{{ route('admin.reports.evidence', [$report, $evidence]) }}" alt="Supporting evidence: {{ $evidence->original_name }}" loading="lazy" class="mb-3 h-32 w-full rounded-md object-contain" width="240" height="128"></a>@endif
                <h3 class="break-words text-sm font-semibold">{{ $evidence->original_name }}</h3>
                <p class="mt-2 text-xs text-muted">Uploaded {{ $evidence->created_at->format('M j, Y H:i:s') }} {{ config('app.timezone') }} by {{ $evidence->review->administrator->name }}.</p>
                <a class="mt-2 block text-xs text-water underline" href="#report-review-{{ $evidence->report_review_id }}">Audit event #{{ $evidence->report_review_id }}: {{ $evidence->review->previous_status === $evidence->review->new_status ? 'Municipality follow-up' : (\App\Models\PollutionReport::STATUSES[$evidence->review->new_status] ?? 'Status update') }}</a>
                <div class="mt-3 flex flex-wrap gap-3 text-sm">@if($evidence->isImage())<a class="text-water underline" href="{{ route('admin.reports.evidence', [$report, $evidence]) }}" target="_blank" rel="noopener noreferrer">Open photo</a>@endif<a class="text-water underline" href="{{ route('admin.reports.evidence', [$report, $evidence, 'download'=>1]) }}">Download {{ $evidence->isImage() ? 'photo' : 'document' }}</a></div>
            </article>
        @empty<p class="text-sm text-muted">No supporting files have been uploaded. Historical resolution references remain in the audit history.</p>@endforelse
    </div>
</section>
