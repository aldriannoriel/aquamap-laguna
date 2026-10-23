@extends('layouts.admin')
@section('title', 'Report overview')
@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-water">Administrator workspace</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Community report overview</h1></div>
        <a href="{{ route('admin.reports.index', ['queue'=>'action']) }}" class="secondary-button inline-flex items-center">Manage reports</a>
    </div>
    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">Review observations, follow up on verified reports, and keep a clear record of each decision. All reports and evidence remain private.</p>
    <a class="mt-6 inline-block text-water underline" href="{{ route('admin.reports.index') }}">Total reports: <strong data-count="total">{{ $counts['total'] }}</strong></a>
    <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (\App\Models\PollutionReport::CATEGORIES as $status => $label)
            <a href="{{ route('admin.reports.index', ['category' => $status]) }}" class="rounded-xl border border-line bg-white p-5 hover:border-water">
                <span class="block text-sm font-medium text-muted">{{ $label }}</span><span data-count="{{ $status }}" class="mt-3 block text-4xl font-semibold text-estuary">{{ $counts[$status] }}</span>
            </a>
        @endforeach
    </div>
    <section class="mt-7 rounded-xl border border-line bg-white p-6">
        <h2 class="text-lg font-semibold">Keep a record of each decision</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">Send Pending reports to the incident municipality or reject with a reason. Only SMTP acceptance moves a report to Forwarded; Under Review and In Progress remain within Forwarded. Mark as Resolved with remarks and documented confirmation, or Close Case with a separate administrative reason. Closed cases can reopen when new information is documented. Email never resolves a report; every decision stays in the audit history.</p>
        <p class="mt-3 max-w-2xl text-xs leading-5 text-muted">Review statuses do not establish measured water quality or habitat suitability. Keep contact details and evidence within this workspace.</p>
    </section>
@endsection
