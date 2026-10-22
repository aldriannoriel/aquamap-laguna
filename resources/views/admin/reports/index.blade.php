@extends('layouts.admin')
@section('title', 'Manage reports')
@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Manage reports</h1>
    <p class="mt-3 text-sm leading-6 text-muted">Review concerns and evidence privately. Dates use {{ config('app.timezone') }}.</p>
    <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Report workflow">@foreach([''=>'All reports'] + \App\Models\PollutionReport::CATEGORIES as $value=>$label)<a class="rounded-lg border border-line px-4 py-3 {{ ($filters['category'] ?? '') === $value && $queue === 'all' ? 'bg-estuary text-white' : 'bg-white text-water' }}" href="{{ route('admin.reports.index', $value ? ['category'=>$value] : []) }}" @if(($filters['category'] ?? '') === $value && $queue === 'all') aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <form id="report-filters" method="GET" action="{{ route('admin.reports.index') }}" class="mt-6 grid gap-4 rounded-xl border border-line bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="queue" value="{{ $queue }}">
        @if(isset($filters['category']))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
        <div class="sm:col-span-2"><label for="q" class="form-label">Search observations</label><input id="q" name="q" maxlength="200" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="Description or lake name"></div>
        <div><label for="lake" class="form-label">Water body</label><select id="lake" name="lake" class="form-input"><option value="">All water bodies</option>@foreach($lakes as $lake)<option value="{{ $lake->id }}" @selected((string)($filters['lake'] ?? '') === (string)$lake->id)>{{ $lake->name }}</option>@endforeach</select></div>
        <div><label for="status" class="form-label">Detailed status (optional)</label><select id="status" name="status" class="form-input"><option value="">All detailed statuses</option>@foreach(\App\Models\PollutionReport::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="type" class="form-label">Observation type</label><select id="type" name="type" class="form-input"><option value="">All types</option>@foreach(\App\Models\PollutionReport::TYPES as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @foreach(['from' => 'Submitted from', 'to' => 'Submitted through'] as $field => $label)
            <div><label for="{{ $field }}" class="form-label">{{ $label }}</label><input type="date" id="{{ $field }}" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" class="form-input"></div>
        @endforeach
        <div class="flex items-end gap-3"><button id="apply-filters" type="submit" class="min-h-12 rounded-lg bg-estuary px-4 py-2 text-sm font-semibold text-white hover:bg-water">Apply filters</button><a href="{{ route('admin.reports.index') }}" class="py-3 text-sm text-water underline underline-offset-4">Reset</a></div>
    </form>
    <p class="mt-6 text-sm text-muted" role="status">{{ $reports->total() }} {{ $reports->total() === 1 ? 'report' : 'reports' }} found</p>
    <div class="mt-3 space-y-3" id="admin-report-list">
        @forelse($reports as $report)
            <article data-report-id="{{ $report->id }}" class="min-w-0 rounded-xl border border-line bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <a href="{{ route('admin.reports.show', $report) }}" class="font-semibold text-estuary underline underline-offset-4">Report #{{ $report->id }} &middot; {{ $report->waterBody->name }}</a>
                    <div class="flex flex-wrap gap-2 text-xs"><span class="rounded-lg bg-mist px-3 py-2 font-semibold">{{ \App\Models\PollutionReport::CATEGORIES[$report->category] }}</span>@if(in_array($report->status, ['under_review','in_progress'], true))<span class="py-2">{{ \App\Models\PollutionReport::STATUSES[$report->status] }}</span>@endif @if($report->referral)<span class="rounded-lg bg-mist px-3 py-2">Email: {{ $report->referral->label() }}</span>@endif</div>
                </div>
                <p class="mt-2 text-xs leading-5 text-muted">{{ \App\Models\PollutionReport::TYPES[$report->pollution_type] ?? 'Other observation' }} &middot; {{ $report->created_at->format('M j, Y H:i') }} {{ config('app.timezone') }}</p>
                <div class="mt-3 flex items-start gap-4">
                    @if($report->photo_path)<a class="shrink-0" href="{{ route('admin.reports.photo', $report) }}" target="_blank" rel="noopener noreferrer" aria-label="Open private evidence for report {{ $report->id }}"><img src="{{ route('admin.reports.photo', $report) }}" alt="Private submitted evidence" loading="lazy" class="h-20 w-20 rounded-lg border border-line object-cover"></a>@endif
                    <div class="min-w-0"><p class="text-sm leading-6 break-words">{{ \Illuminate\Support\Str::limit($report->description, 180) }}</p><p class="mt-2 break-words text-xs leading-5 text-muted">Incident location: {{ $report->landmark ?: 'No landmark supplied' }}.@if($report->latitude !== null && $report->longitude !== null) {{ $report->latitude }}, {{ $report->longitude }}@else No saved incident point.@endif</p></div>
                </div>
                <div class="mt-4 flex flex-wrap gap-3 text-sm"><a class="secondary-button" href="{{ route('admin.reports.show', $report) }}">Full evidence &amp; location</a>@if($report->category === 'pending' && in_array($report->status, ['pending','verified'], true) && (!$report->referral || ($report->referral->status === 'queued' && $report->referral->attempts === 0)))<a class="primary-button" href="{{ route('admin.reports.show', $report) }}#review-heading">Send Email</a>@endif</div>
                @if($report->canReject() || ($report->forwarded() && $report->availableTransitions()))<details class="mt-4"><summary class="cursor-pointer text-sm font-semibold text-water">{{ $report->canReject() ? 'Reject Report' : ($report->status === 'closed' ? 'Reopen Case' : 'Mark as Resolved / Close Case') }}</summary><div class="mt-3">@include('admin.reports.actions', ['compact'=>true])</div></details>@endif
            </article>
        @empty
            <p class="rounded-xl border border-line bg-white p-6 text-sm text-muted">{{ $queue === 'action' ? 'No reports are awaiting forwarding with these filters. Sent referrals appear in Forwarded / Processed Reports.' : 'No reports match these filters.' }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $reports->links() }}</div>
@endsection
