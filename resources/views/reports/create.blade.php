@extends('layouts.reporting')

@section('content')
    <div class="mb-7 max-w-2xl">
        <p class="text-sm font-medium text-water">Community observations</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Help care for our waters.</h1>
        <p class="mt-3 text-sm leading-6 text-muted">Share a pollution concern you have observed. Your report stays private and pending review; it is not a verified pollution finding.</p>
    </div>

    @if (session('report_received'))
        <section id="report-success" role="status" tabindex="-1" class="mb-6 rounded-xl border border-moss/30 bg-white p-5">
            <h2 class="text-lg font-semibold text-moss">Your report has been received.</h2>
            <p class="mt-2 text-sm leading-6">Status: pending review. Your report, photo and contact details are not published. Review and response times are not guaranteed.</p>
            @if (session('possible_duplicate'))
                <div id="duplicate-warning" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    <h3 class="font-semibold">Possible repeat submission</h3>
                    <p class="mt-1">You recently submitted a report for this water body and observation type in this browser session. This report has still been saved privately for administrator review. Different incidents can have similar details.</p>
                </div>
            @endif
            <a href="{{ route('map.index') }}" class="mt-3 inline-block font-semibold text-water underline underline-offset-4">Return to the water explorer</a>
        </section>
    @endif

    @if ($errors->any())
        <div id="form-errors" role="alert" tabindex="-1" class="mb-6 rounded-xl border border-red-300 bg-white p-5 text-sm">
            <h2 class="font-semibold text-red-800">Please check your report</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->messages() as $field => $messages)
                    <li><a href="#{{ in_array($field, ['submission', 'website', 'report_form_token']) ? 'report-form' : (in_array($field, ['latitude', 'longitude']) ? 'incident-map' : $field) }}" class="underline underline-offset-4">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
            <p class="mt-3 text-muted">Your text and selected point have been kept. Please select your required photo again.</p>
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <form id="report-form" action="{{ route('reports.store') }}" method="POST" enctype="multipart/form-data" class="min-w-0 space-y-6 rounded-2xl border border-line bg-white p-5 shadow-sm sm:p-7">
            @csrf
            <input type="hidden" name="report_form_token" value="{{ $reportFormToken }}">
            <div hidden aria-hidden="true">
                <label for="website">Leave this field blank</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <fieldset class="space-y-5">
                <legend class="mb-5 text-xl font-semibold">What did you observe?</legend>
                <p class="text-xs text-muted">Fields marked * are required.</p>
                @php
                    $selectedCategory = old('water_body_category') ?: $lakes->firstWhere('id', (int) old('water_body_id'))?->category;
                @endphp
                <div>
                    <label for="water_body_category" class="form-label">Category <span aria-hidden="true">*</span></label>
                    <p id="water-category-help" class="mb-2 text-xs leading-5 text-muted">Choose a category, then choose the water body where you observed the concern.</p>
                    <select id="water_body_category" name="water_body_category" class="form-input" aria-controls="water_body_id" aria-describedby="water-category-help water-category-status water_body_category-error" aria-invalid="{{ $errors->has('water_body_category') ? 'true' : 'false' }}">
                        <option value="">Choose a category</option>
                        @foreach ($categories as $category => $label)
                            <option value="{{ $category }}" @selected($selectedCategory === $category) @disabled(!$lakes->contains('category', $category))>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="water_body_category-error" class="form-error">@error('water_body_category'){{ $message }}@enderror</p>
                    <p id="water-category-status" class="mt-2 text-xs text-muted" role="status" aria-live="polite">Water bodies are grouped by category below.</p>
                    <noscript><p class="mt-2 text-xs text-muted">Without JavaScript, use the category headings in the water body list. The category filter is optional.</p></noscript>
                </div>
                <div>
                    <label for="water_body_id" class="form-label">Water body *</label>
                    <select id="water_body_id" name="water_body_id" class="form-input" required aria-invalid="{{ $errors->has('water_body_id') ? 'true' : 'false' }}" aria-describedby="water_body_id-error">
                        <option value="">Choose a water body</option>
                        @foreach ($lakes->groupBy('category') as $category => $waterBodies)
                            <optgroup label="{{ $categories[$category] }}">
                                @foreach ($waterBodies as $lake)
                                    <option value="{{ $lake->id }}" data-category="{{ $category }}" @selected((string) old('water_body_id') === (string) $lake->id)>{{ $lake->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p id="water_body_id-error" class="form-error">@error('water_body_id'){{ $message }}@enderror</p>
                    @if ($lakes->isEmpty())<p class="mt-2 text-sm text-muted">No water body records are available. Please try again later.</p>@endif
                </div>
                <div>
                    <label for="pollution_type" class="form-label">Observation type *</label>
                    <select id="pollution_type" name="pollution_type" class="form-input" required aria-invalid="{{ $errors->has('pollution_type') ? 'true' : 'false' }}" aria-describedby="pollution_type-error">
                        <option value="">Choose a type</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('pollution_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="pollution_type-error" class="form-error">@error('pollution_type'){{ $message }}@enderror</p>
                </div>
                <div>
                    <label for="description" class="form-label">Description *</label>
                    <p id="description-help" class="mb-2 text-xs leading-5 text-muted">Describe visible concerns such as plastic waste, floating garbage or suspected illegal dumping: what you saw, when it happened and nearby landmarks. Avoid including other people's personal details. Maximum 5,000 characters.</p>
                    <textarea id="description" name="description" rows="5" maxlength="5000" required class="form-input resize-y" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-help description-error">{{ old('description') }}</textarea>
                    <p id="description-error" class="form-error">@error('description'){{ $message }}@enderror</p>
                </div>
            </fieldset>

            <fieldset class="border-t border-line pt-5">
                <legend class="pr-3 text-lg font-semibold">Incident location <span class="text-sm font-normal text-muted">(optional)</span></legend>
                <p id="incident-help" class="mt-2 text-xs leading-5 text-muted">Choose a water body to center the map, then tap or click where you observed the incident. Blue circles are water body references, not incident locations. They use source-checked WGS84 reference geometry; positional precision remains unconfirmed. They do not identify spring sources. You can leave the incident point blank.</p>
                <div class="mt-3 overflow-hidden rounded-xl border border-line">
                    <div id="incident-map" class="h-72 w-full sm:h-80" role="region" aria-label="Choose incident location. Pan with arrow keys; use the button below to select the map center." aria-describedby="incident-help incident-status"></div>
                </div>
                <p id="incident-status" role="status" class="mt-2 text-xs leading-5 text-muted">No incident location selected.</p>
                <p id="incident-tiles" class="mt-2 text-xs text-muted" role="status" hidden>Map tiles could not load. Leave the point blank if you cannot identify the location.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="use-map-center" class="secondary-button" disabled>Use map center as incident point</button>
                    <button type="button" id="clear-incident" class="secondary-button" disabled>Clear incident point</button>
                </div>
                @foreach (['latitude', 'longitude'] as $coordinate)
                    <input type="hidden" id="{{ $coordinate }}" name="{{ $coordinate }}" value="{{ old($coordinate) }}">
                    <p id="{{ $coordinate }}-error" class="form-error">@error($coordinate){{ $message }}@enderror</p>
                @endforeach
                <noscript><p class="mt-3 text-sm text-muted">Enable JavaScript to select a point. You can submit without a location and describe nearby landmarks instead.</p></noscript>
            </fieldset>

            <div class="border-t border-line pt-5">
                <label for="photo" class="form-label">Photo proof <span class="font-normal text-muted">(required)</span></label>
                <p id="photo-help" class="mb-3 text-xs leading-5 text-muted">Photo proof is required for every new report. JPEG, PNG or WebP, up to 5 MB. Photos remain private. Avoid identifiable people and sensitive information; photos may contain location metadata.</p>
                <input id="photo" name="photo" type="file" required accept="image/jpeg,image/png,image/webp" class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-mist file:px-3 file:py-2 file:font-medium file:text-estuary" aria-invalid="{{ $errors->has('photo') ? 'true' : 'false' }}" aria-describedby="photo-help photo-error">
                <p id="photo-error" class="form-error">@error('photo'){{ $message }}@enderror</p>
            </div>

            <fieldset class="space-y-4 border-t border-line pt-5">
                <legend class="pr-3 text-lg font-semibold">Your details <span class="text-sm font-normal text-muted">(optional)</span></legend>
                <p class="text-xs leading-5 text-muted">You may submit anonymously. Contact details are stored privately with your report. No automatic email is sent.</p>
                @foreach (['reporter_name' => ['Name', 'text', 'name', 255], 'reporter_email' => ['Email', 'email', 'email', 254]] as $field => [$label, $type, $autocomplete, $max])
                    <div>
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" class="form-input" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $field }}-error">
                        <p id="{{ $field }}-error" class="form-error">@error($field){{ $message }}@enderror</p>
                    </div>
                @endforeach
            </fieldset>
            <div class="border-t border-line pt-5">
                <button id="submit-report" type="submit" class="min-h-12 w-full rounded-xl bg-estuary px-6 py-3 text-sm font-semibold text-white hover:bg-water disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto" @disabled($lakes->isEmpty())>Submit private report</button>
                <p class="mt-3 text-xs leading-5 text-muted">Submissions are limited to 5 attempts per hour per internet address to reduce abuse.</p>
            </div>
        </form>
        <aside class="space-y-5" aria-label="Reporting guidance">
            <section class="rounded-2xl bg-estuary p-6 text-white">
                <h2 class="text-lg font-semibold">Observe. Share. Protect.</h2>
                <p class="mt-3 text-sm leading-6 text-white/85">Small observations can help build a clearer picture of our waters.</p>
                <ol class="mt-5 space-y-4 text-sm leading-6">
                    <li><span class="font-semibold">01 / Share what you saw.</span><br><span class="text-white/85">Use clear details and a nearby landmark.</span></li>
                    <li><span class="font-semibold">02 / Keep a safe distance.</span><br><span class="text-white/85">Do not enter the water or touch suspected waste.</span></li>
                    <li><span class="font-semibold">03 / Await review.</span><br><span class="text-white/85">Reports remain pending and private. This is not an emergency service.</span></li>
                </ol>
            </section>
            <section class="rounded-xl border border-line p-5 text-xs leading-6 text-muted">
                <h2 class="font-semibold text-estuary">An observation, not a measurement</h2>
                <p class="mt-2">A report does not establish water quality, pollution severity or fish suitability. Historical research stays separate from community reports.</p>
                <a href="{{ route('map.index') }}" class="mt-3 inline-block font-semibold text-water underline underline-offset-4">Explore the water body research</a>
            </section>
        </aside>
    </div>
    <script id="incident-lakes" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
@endsection
