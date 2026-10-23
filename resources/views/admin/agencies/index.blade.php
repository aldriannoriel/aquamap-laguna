@extends('layouts.admin')
@section('title', 'Agency directory')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-3xl font-semibold">Agency directory</h1><p class="mt-2 text-sm text-muted">Keep one recipient per municipality or city. Check the problem location before choosing a recipient; catalogue matches and saved associations do not confirm jurisdiction.</p></div><a href="{{ route('admin.agencies.create') }}" class="primary-button">Add recipient</a></div>
@if(session('directory_saved'))<p role="status" class="mt-5 rounded-lg border border-line bg-white p-4">{{ session('directory_saved') }}</p>@endif
<form method="GET" class="mt-6 grid gap-3 sm:grid-cols-4">
<div><label class="form-label" for="q">Search municipality, name or email</label><input class="form-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
<div><label class="form-label" for="active">Status</label><select class="form-input" id="active" name="active"><option value="">All</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactive</option></select></div>
<div><label class="form-label" for="verification">Verification</label><select class="form-input" id="verification" name="verification"><option value="">All</option>@foreach(['provisional','verified'] as $status)<option value="{{ $status }}" @selected(($filters['verification'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
<button class="secondary-button self-end">Filter recipients</button>
</form>
<div class="mt-6 space-y-4">
@forelse($agencies as $agency)
<article class="rounded-xl border border-line bg-white p-5">
<div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold">{{ $agency->name }}</h2><p class="mt-1 text-sm">{{ $agency->municipality }}{{ $agency->barangay ? ' / '.$agency->barangay : '' }}</p><p class="mt-2 text-sm break-all">{{ $agency->email }}</p></div><p class="text-sm font-semibold">{{ $agency->active ? 'Active' : 'Inactive' }} / {{ ucfirst($agency->verification_status) }}</p></div>
@if($agency->verification_status === 'provisional')<p class="mt-3 text-sm text-muted">Provisional routing only. Official responsibilities and jurisdiction have not been verified.</p>@endif
<p class="mt-3 text-sm"><strong>Catalogue locality matches:</strong> {{ ($municipalityBodies[$agency->municipality] ?? collect())->pluck('name')->join(', ') ?: 'No exact locality matches; verify routing manually.' }}</p>
@if($agency->waterBodies->isNotEmpty())<p class="mt-2 text-sm"><strong>Catalogue associations:</strong> {{ $agency->waterBodies->pluck('name')->join(', ') }}</p>@endif
@if($agency->notes)<p class="mt-3 text-sm whitespace-pre-wrap">{{ $agency->notes }}</p>@endif
<a class="mt-4 inline-block text-sm font-semibold text-water underline" href="{{ route('admin.agencies.edit', $agency) }}">Edit recipient</a>
</article>
@empty<p class="rounded-xl border border-line bg-white p-6">No recipients match. Add a recipient or change the filters.</p>@endforelse
</div><div class="mt-5">{{ $agencies->links() }}</div>
@endsection
