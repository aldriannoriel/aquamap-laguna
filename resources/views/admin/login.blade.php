@extends('layouts.admin')
@section('title', 'Administrator sign in')
@section('content')
    <section class="mx-auto max-w-md rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-medium text-water">Private review workspace</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Administrator sign in</h1>
        <p class="mt-3 text-sm leading-6 text-muted">Only authorized administrators can review community reports here. You do not need an account to view water body information or report a concern.</p>
        <form id="admin-login" method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="email" class="form-label">Administrator email</label>
                <input id="email" name="email" type="email" autocomplete="username" required maxlength="254" class="form-input" value="{{ is_string(old('email')) ? old('email') : '' }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="form-errors" @endif>
            </div>
            <div>
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="1024" class="form-input" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->has('password')) aria-describedby="form-errors" @endif>
            </div>
            <button id="admin-sign-in" type="submit" class="min-h-12 w-full rounded-xl bg-estuary px-5 py-3 text-sm font-semibold text-white hover:bg-water">Sign in</button>
        </form>
        <p class="mt-5 text-xs leading-5 text-muted">Accounts are created by the project owner through a secure local setup process. There is no public registration.</p>
    </section>
@endsection
