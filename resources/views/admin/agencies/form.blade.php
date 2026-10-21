@extends('layouts.admin')
@section('title', $agency->exists ? 'Edit recipient' : 'Add recipient')
@section('content')
<a href="{{ route('admin.agencies.index') }}" class="text-water underline">Back to directory</a>
<h1 class="mt-4 text-3xl font-semibold">{{ $agency->exists ? 'Edit recipient' : 'Add recipient' }}</h1>
<form method="POST" action="{{ $agency->exists ? route('admin.agencies.update', $agency) : route('admin.agencies.store') }}" class="mt-6 max-w-3xl space-y-5 rounded-xl border border-line bg-white p-5 sm:p-7">
@csrf @if($agency->exists) @method('PUT') @endif
@foreach(['name'=>'Office / agency name','municipality'=>'Municipality / city','barangay'=>'Barangay (optional)','email'=>'Recipient email'] as $field=>$label)
<div><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-input" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $agency->$field) }}" @required($field !== 'barangay') maxlength="{{ ['name'=>160,'municipality'=>160,'barangay'=>120,'email'=>254][$field] }}"></div>
@endforeach
<div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label" for="active">Status</label><select class="form-input" id="active" name="active"><option value="1" @selected((string)old('active', $agency->exists ? (int)$agency->active : 1) === '1')>Active</option><option value="0" @selected((string)old('active', $agency->exists ? (int)$agency->active : 1) === '0')>Inactive</option></select></div>
<div><label class="form-label" for="verification_status">Verification</label><select class="form-input" id="verification_status" name="verification_status">@foreach(['provisional','verified'] as $status)<option value="{{ $status }}" @selected(old('verification_status', $agency->verification_status ?? 'provisional') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div></div>
<p class="text-sm text-muted">Mark verified only after checking the office identity, address and responsibilities. A water-body assignment does not establish jurisdiction.</p>
<fieldset><legend class="form-label">Associated water bodies</legend><div class="mt-2 max-h-72 space-y-2 overflow-y-auto rounded-lg border border-line p-3">
@foreach($waterBodies as $water)<label class="flex items-start gap-3 py-1 text-sm"><input class="mt-1" type="checkbox" name="water_body_ids[]" value="{{ $water->id }}" @checked(in_array($water->id, old('water_body_ids', $selected)))><span>{{ $water->name }} <span class="text-muted">/ {{ $water->municipality }}</span></span></label>@endforeach
</div></fieldset>
<div><label class="form-label" for="notes">Verification notes (optional)</label><textarea class="form-input" id="notes" name="notes" rows="4" maxlength="2000">{{ old('notes', $agency->notes) }}</textarea></div>
<button class="primary-button">Save recipient</button>
</form>
@if($agency->exists)
<form method="POST" action="{{ route('admin.agencies.destroy', $agency) }}" class="mt-6 max-w-3xl rounded-xl border border-red-200 bg-white p-5">
@csrf @method('DELETE')
<h2 class="font-semibold">Delete this recipient</h2><p class="mt-2 text-sm text-muted">Referral snapshots and history remain intact. Deletion does not retract email or cancel an already approved referral.</p>
<label class="mt-4 flex gap-3 text-sm"><input type="checkbox" name="confirm_delete" value="1" required><span>I confirm deletion of {{ $agency->name }} from the directory.</span></label>
<button class="secondary-button mt-4">Delete recipient</button>
</form>@endif
@endsection
