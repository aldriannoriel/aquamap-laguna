@extends('layouts.reporting')

@section('content')
    <div class="mb-7 max-w-2xl">
        <p class="text-sm font-medium text-water">Community observations</p>
        <h1 id="report-page-title" tabindex="-1" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Report a Water Problem</h1>
        <p class="mt-3 text-sm leading-6 text-muted">Tell us about a pollution concern you saw. Your report stays private and awaits review. It does not confirm pollution.</p>
    </div>

    @if (session('report_received'))
        <section id="report-success" x-data="{ dismissed: false }" x-show="!dismissed" role="status" tabindex="-1" class="mb-6 rounded-xl border border-moss/30 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-moss">Your report has been received.</h2>
                <button type="button" x-on:click="dismissed = true; $nextTick(() => document.getElementById('report-page-title').focus())" aria-label="Dismiss report confirmation" class="rounded text-muted hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-estuary">
                    <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15"/></svg>
                </button>
            </div>
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
                <legend class="mb-5 text-xl font-semibold">What problem did you notice?</legend>
                <p class="text-xs text-muted">Fields marked * are required.</p>
                @php
                    $selectedCategory = old('water_body_category') ?: $lakes->firstWhere('id', (int) old('water_body_id'))?->category;
                @endphp
                <div>
                    <label for="water_body_category" class="form-label">Category <span aria-hidden="true">*</span></label>
                    <p id="water-category-help" class="mb-2 text-xs leading-5 text-muted">Choose a category, then the water body where you saw the problem.</p>
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
                    <label for="pollution_type" class="form-label">Type of problem *</label>
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
                    <p id="description-help" class="mb-2 text-xs leading-5 text-muted">Tell us what you saw, when you saw it and any nearby landmarks. Examples include plastic waste, floating garbage or suspected illegal dumping. Do not include other people's personal details. Maximum 5,000 characters.</p>
                    <textarea id="description" name="description" rows="5" maxlength="5000" required class="form-input resize-y" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-help description-error">{{ old('description') }}</textarea>
                    <p id="description-error" class="form-error">@error('description'){{ $message }}@enderror</p>
                </div>
            </fieldset>

            <fieldset class="border-t border-line pt-5">
                <legend class="pr-3 text-lg font-semibold">Problem location <span class="text-sm font-normal text-muted">(optional)</span></legend>
                <p id="incident-help" class="mt-2 text-xs leading-5 text-muted">Choose a water body to center the map, then tap or click where you saw the problem. Blue circles are water body references, not incident locations. They use source-checked WGS84 reference geometry; positional precision remains unconfirmed. You can leave the incident point blank.</p>
                <div class="mt-3 overflow-hidden rounded-xl border border-line">
                    <div id="incident-map" class="h-72 w-full sm:h-80" role="region" aria-label="Choose incident location. Pan with arrow keys; use the button below to select the map center." aria-describedby="incident-help incident-status latitude-error longitude-error location_accuracy_m-error"></div>
                </div>
                <p id="incident-status" role="status" class="mt-2 text-xs leading-5 text-muted">No incident location selected.</p>
                <p id="incident-tiles" class="mt-2 text-xs text-muted" role="status" hidden>Basemap tiles are unavailable. Your selected coordinates and pin are kept; manual pin and GPS controls remain available. Leave the point blank if you cannot identify the location.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="use-current-location" class="secondary-button">Use My Current Location</button>
                    <button type="button" id="use-gps-location" class="secondary-button" hidden>Use this GPS point as the problem location</button>
                    <button type="button" id="use-map-center" class="secondary-button" disabled>Use map center as problem location</button>
                    <button type="button" id="clear-incident" class="secondary-button" disabled>Clear problem location</button>
                </div>
                <p id="gps-status" role="status" class="mt-3 text-sm text-muted">GPS requires HTTPS or localhost and is requested only when you press the location button. Your current location may differ from the problem location.</p>
                <p id="incident-coordinates" class="mt-3 text-sm font-semibold">No coordinates selected.</p>
                <input type="hidden" id="location_accuracy_m" name="location_accuracy_m" value="{{ old('location_accuracy_m') }}">
                <p id="location_accuracy_m-error" class="form-error">@error('location_accuracy_m'){{ $message }}@enderror</p>
                <div class="mt-4"><label for="landmark" class="form-label">Nearby landmark or barangay (optional)</label><input id="landmark" name="landmark" aria-invalid="{{ $errors->has('landmark') ? 'true' : 'false' }}" aria-describedby="landmark-error" maxlength="200" class="form-input" value="{{ old('landmark') }}"><p class="mt-2 text-xs text-muted">Enter only a location you know. Barangay names are never inferred from coordinates.</p><p id="landmark-error" class="form-error">@error('landmark'){{ $message }}@enderror</p></div>
                <div class="mt-4"><label for="observed_on" class="form-label">Observation date (optional)</label><input id="observed_on" name="observed_on" aria-invalid="{{ $errors->has('observed_on') ? 'true' : 'false' }}" aria-describedby="observed_on-error" type="date" max="{{ now('Asia/Manila')->toDateString() }}" class="form-input" value="{{ old('observed_on') }}"><p id="observed_on-error" class="form-error">@error('observed_on'){{ $message }}@enderror</p></div>
                @foreach (['latitude', 'longitude'] as $coordinate)
                    <input type="hidden" id="{{ $coordinate }}" name="{{ $coordinate }}" value="{{ old($coordinate) }}">
                    <p id="{{ $coordinate }}-error" class="form-error">@error($coordinate){{ $message }}@enderror</p>
                @endforeach
                <noscript><p class="mt-3 text-sm text-muted">Enable JavaScript to select a point. You can submit without a location and describe nearby landmarks instead.</p></noscript>
            </fieldset>

            <div class="border-t border-line pt-5">
                <label for="photo" class="form-label">Photo of the problem <span class="font-normal text-muted">(required)</span></label>
                <p id="photo-help" class="mb-3 text-xs leading-5 text-muted">Add a photo of the problem. A photo is required for every new report. JPEG, PNG or WebP, up to 5 MB. Photos remain private. Avoid identifiable people and sensitive information. Photos may include location metadata.</p>
                <input id="photo" name="photo" type="file" required accept="image/jpeg,image/png,image/webp" class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-mist file:px-3 file:py-2 file:font-medium file:text-estuary" aria-invalid="{{ $errors->has('photo') ? 'true' : 'false' }}" aria-describedby="photo-help photo-error">
                <p id="photo-error" class="form-error">@error('photo'){{ $message }}@enderror</p>
            </div>

            <fieldset class="space-y-4 border-t border-line pt-5">
                <legend class="pr-3 text-lg font-semibold">Your details</legend>
                <p class="text-xs leading-5 text-muted">Your name is optional. An email address is required only to verify your report submission. Your contact details remain private.</p>
                <div>
                    <label for="reporter_name" class="form-label">Name (optional)</label>
                    <input id="reporter_name" name="reporter_name" type="text" autocomplete="name" maxlength="255" value="{{ old('reporter_name') }}" class="form-input" aria-invalid="{{ $errors->has('reporter_name') ? 'true' : 'false' }}" aria-describedby="reporter_name-error">
                    <p id="reporter_name-error" class="form-error">@error('reporter_name'){{ $message }}@enderror</p>
                </div>
                <div>
                    <label for="reporter_email" class="form-label">Email address *</label>
                    <input id="reporter_email" name="reporter_email" type="email" autocomplete="email" maxlength="254" required value="{{ old('reporter_email') }}" class="form-input" aria-invalid="{{ $errors->has('reporter_email') ? 'true' : 'false' }}" aria-describedby="reporter_email-error otp-status">
                    <p id="reporter_email-error" class="form-error">@error('reporter_email'){{ $message }}@enderror</p>
                </div>
                <div id="report-email-verification" role="group" aria-labelledby="report-otp-heading" class="rounded-xl border border-line bg-mist p-4 space-y-3">
                    <h3 id="report-otp-heading" class="font-semibold text-estuary">Verify your email</h3>
                    <p id="report-otp-help" class="text-xs leading-5 text-muted">Request a 6-digit code, then enter it below. Codes expire after 5 minutes. You have 5 attempts per code.</p>
                    <button id="send-report-otp" type="button" class="secondary-button">Send verification code</button>
                    <div>
                        <label for="report-otp-code" class="form-label">6-digit verification code</label>
                        <input id="report-otp-code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" aria-describedby="report-otp-help otp-status" class="form-input" placeholder="Enter 6-digit code">
                    </div>
                    <button id="verify-report-otp" type="button" class="secondary-button">Verify code</button>
                    <p id="otp-status" role="status" aria-live="polite" class="text-sm text-muted">Email not yet verified.</p>
                </div>
            </fieldset>
            <div class="border-t border-line pt-5">
                <button id="submit-report" type="submit" class="min-h-12 w-full rounded-xl bg-estuary px-6 py-3 text-sm font-semibold text-white hover:bg-water disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto" @disabled($lakes->isEmpty())>Submit private report</button>
                <p class="mt-3 text-xs leading-5 text-muted">One successful report per verified email every 5 hours. Anti-abuse submission limits also apply.</p>
            </div>
        </form>
        <aside class="space-y-5" aria-label="Reporting guidance">
            <section class="rounded-2xl bg-estuary p-6 text-white">
                <h2 class="text-lg font-semibold">Observe. Share. Protect.</h2>
                <p class="mt-3 text-sm leading-6 text-white/85">Small observations can help build a clearer picture of our waters.</p>
                <ol class="mt-5 space-y-4 text-sm leading-6">
                    <li><span class="font-semibold">01 / Share what you saw.</span><br><span class="text-white/85">Use clear details and a nearby landmark.</span></li>
                    <li><span class="font-semibold">02 / Keep a safe distance.</span><br><span class="text-white/85">Do not enter the water or touch suspected waste.</span></li>
                    <li><span class="font-semibold">03 / Wait for review.</span><br><span class="text-white/85">Reports remain pending and private. This is not an emergency service.</span></li>
                </ol>
            </section>
            <section class="rounded-xl border border-line p-5 text-xs leading-6 text-muted">
                <h2 class="font-semibold text-estuary">A report is not a water test</h2>
                <p class="mt-2">A report does not confirm water quality, how serious pollution is or whether a place is suitable for fish. Historical research stays separate from community reports.</p>
                <a href="{{ route('map.index') }}" class="mt-3 inline-block font-semibold text-water underline underline-offset-4">Explore the water body research</a>
            </section>
        </aside>
    </div>
    <script id="incident-lakes" type="application/json">{!! \Illuminate\Support\Js::encode($lakes) !!}</script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const email = document.getElementById('reporter_email');
            const code = document.getElementById('report-otp-code');
            const sendButton = document.getElementById('send-report-otp');
            const verifyButton = document.getElementById('verify-report-otp');
            const status = document.getElementById('otp-status');
            const submitButton = document.getElementById('submit-report');
            const reportForm = document.getElementById('report-form');
            const csrf = reportForm.querySelector('input[name="_token"]').value;
            let verifiedEmail = null;
            let busy = false;
            let sendingReport = false;
            const baseDisabled = submitButton.disabled;

            function normalizedEmail() {
                return email.value.trim().toLowerCase();
            }
            function updateSubmit() {
                submitButton.disabled = baseDisabled || busy || verifiedEmail !== normalizedEmail();
            }
            function setStatus(message) {
                status.textContent = message;
            }
            function setBusy(value) {
                busy = value;
                sendButton.disabled = value;
                verifyButton.disabled = value;
                updateSubmit();
            }
            async function post(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload)
                });
                let data = {};
                try { data = await response.json(); } catch (_) { /* Unexpected response. */ }
                if (!response.ok) {
                    throw new Error(data.message || 'Request failed. Please try again.');
                }
                return data;
            }

            email.addEventListener('input', () => {
                if (verifiedEmail !== normalizedEmail()) {
                    verifiedEmail = null;
                    setStatus('Email not yet verified.');
                }
                updateSubmit();
            });
            sendButton.addEventListener('click', async () => {
                if (!email.reportValidity()) return;
                verifiedEmail = null;
                setBusy(true);
                setStatus('Sending verification code...');
                try {
                    const data = await post(@json(route('reports.otp.send')), { email: normalizedEmail() });
                    setStatus(data.message || 'Code sent. Check your email.');
                } catch (error) {
                    setStatus(error.message);
                } finally {
                    setBusy(false);
                }
            });
            verifyButton.addEventListener('click', async () => {
                if (!email.reportValidity()) return;
                if (!/^[0-9]{6}$/.test(code.value.trim())) {
                    setStatus('Enter the complete 6-digit code.');
                    return;
                }
                const submittedEmail = normalizedEmail();
                setBusy(true);
                setStatus('Checking verification code...');
                try {
                    const data = await post(@json(route('reports.otp.verify')), {
                        email: submittedEmail,
                        code: code.value.trim()
                    });
                    if (data.verified === true && normalizedEmail() === submittedEmail) {
                        verifiedEmail = submittedEmail;
                        setStatus('Email verified. You can now submit your report.');
                    } else {
                        setStatus('Verification was not completed.');
                    }
                } catch (error) {
                    setStatus(error.message);
                } finally {
                    setBusy(false);
                }
            });
            reportForm.addEventListener('submit', (event) => {
                if (verifiedEmail !== normalizedEmail() || busy || sendingReport) {
                    event.preventDefault();
                    if (!sendingReport) setStatus('Please verify your email before submitting.');
                    return;
                }
                sendingReport = true;
                submitButton.disabled = true;
            });
            updateSubmit();
        });
    </script>
@endsection
