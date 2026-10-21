@extends('layouts.admin')
@section('title', 'Manage reports')
@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Manage reports</h1>
    <p class="mt-3 text-sm leading-6 text-muted">Search descriptions or lake names. Dates use {{ config('app.timezone') }}. Reports stay private at every status.</p>
    <nav class="mt-5 flex flex-wrap gap-3 text-sm" aria-label="Report workflow">@foreach(['action'=>'Action required','processed'=>'Processed / referral history','all'=>'All reports'] as $value=>$label)<a class="rounded-lg border border-line px-4 py-3 {{ $queue === $value ? 'bg-estuary text-white' : 'bg-white text-water' }}" href="{{ route('admin.reports.index', ['queue'=>$value]) }}" @if($queue === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <form id="report-filters" method="GET" action="{{ route('admin.reports.index') }}" class="mt-6 grid gap-4 rounded-xl border border-line bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="queue" value="{{ $queue }}">
        <div class="sm:col-span-2"><label for="q" class="form-label">Search observations</label><input id="q" name="q" maxlength="200" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="Description or lake name"></div>
        <div><label for="lake" class="form-label">Lake</label><select id="lake" name="lake" class="form-input"><option value="">All lakes</option>@foreach($lakes as $lake)<option value="{{ $lake->id }}" @selected((string)($filters['lake'] ?? '') === (string)$lake->id)>{{ $lake->name }}</option>@endforeach</select></div>
        <div><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-input"><option value="">All statuses</option>@foreach(\App\Models\PollutionReport::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
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
                    <span class="rounded-lg bg-mist px-3 py-2 text-xs font-semibold">{{ $report->workflow_status }}</span>
                </div>
                <p class="mt-2 text-xs leading-5 text-muted">{{ \App\Models\PollutionReport::TYPES[$report->pollution_type] ?? 'Other observation' }} &middot; {{ $report->created_at->format('M j, Y H:i') }} {{ config('app.timezone') }}</p>
                <p class="mt-3 text-sm leading-6 break-words">{{ \Illuminate\Support\Str::limit($report->description, 180) }}</p>
            </article>
        @empty
            <p class="rounded-xl border border-line bg-white p-6 text-sm text-muted">{{ $queue === 'action' ? 'No reports need action with these filters. Sent referrals appear in Processed / referral history.' : 'No reports match these filters.' }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $reports->links() }}</div>
<details class="mt-7 rounded-xl border border-line bg-white p-5"><summary class="font-semibold">Preserved legacy delivery history ({{ $legacyDeliveries->total() }})</summary>@forelse($legacyDeliveries as $delivery)@include('admin.reports.delivery', ['allowActions'=>false])@empty<p class="mt-3 text-sm text-muted">No legacy delivery records.</p>@endforelse<div class="mt-4">{{ $legacyDeliveries->links() }}</div></details>
@endsection
