@extends('layouts.reporting')
@section('title', 'Please try again later')
@section('content')
    <section class="mx-auto max-w-xl rounded-2xl border border-line bg-white p-7">
        <h1 class="text-2xl font-semibold">Please try again later.</h1>
        <p class="mt-3 text-sm leading-6 text-muted">This internet address has reached the limit of 5 reporting attempts per hour. Please wait before submitting again. People using the same network share this limit.</p>
        <p class="mt-3 text-sm leading-6 text-muted">This attempt was not saved. Use your browser's Back button to return to your form; a photo may need to be selected again.</p>
        <a href="{{ route('map.index') }}" class="mt-5 inline-block font-semibold text-water underline underline-offset-4">Return to lake explorer</a>
    </section>
@endsection
