# Email referrals and private report locations

Updated 2026-10-21 against 4826f5e. Seven existing provisional recipients were normalized to municipality/city names without changing their testing email aquamaplaguna@gmail.com, locality, notes, active state or routing. These entries do not verify government office identity or jurisdiction. No new schema or recipients were added to the installed database.

## Missing-email diagnosis

The inspected database had one pending and one verified report, zero referrals, zero queued jobs and zero failed jobs. Sanitized referral failure markers were absent from the application log. Record decision only saves moderation and audit history: it never dispatches email. No recorded SMTP attempt exists to diagnose as an authentication/provider failure. Configuration presence was checked without printing values; it does not verify credentials.

The installed framework's standard composer dev worker listens to the default queue, while approved referrals use the dedicated referrals connection/queue. AppServiceProvider now registers a separate development worker. Existing dev sessions must be restarted to include it. An externally managed/local server still needs the dedicated worker below. No real worker or live email was started during this fix.

## Gmail sender setup

Use an account you control. Enable 2-Step Verification and create an app password if the account supports it. Gmail app passwords require 2-Step Verification and may be unavailable under account policy; do not weaken that policy. See [Google's app-password instructions](https://support.google.com/mail/answer/185833?hl=en) and [Google SMTP setup](https://support.google.com/a/answer/176600?hl=en). Verify any alternate From address with the sending provider; the testing recipient is not automatically a verified sender.

Set these values only in the local ignored .env, substituting the actual sender. Never paste credentials into chat, an agency record, a ticket, logs or source control:

    MAIL_MAILER=smtp
    REFERRAL_MAILER=smtp
    MAIL_SCHEME=smtps
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=465
    MAIL_USERNAME="your-sender@gmail.com"
    MAIL_PASSWORD="your-new-app-password-without-spaces"
    MAIL_FROM_ADDRESS="your-sender@gmail.com"
    MAIL_FROM_NAME="AquaMap Laguna"

An SMTP provider with a verified sender can also supply the smtp host, scheme, port and credentials. Live referrals explicitly use the configured SMTP mailer and reject log/array/failover sending. The UI reports configuration presence only; it does not validate credentials or prove delivery.

A native dotenv parser displayed an unquoted local app password during setup. The quoting was fixed without printing it again. Revoke that password and replace it with a new app password before live use. No credential value is recorded in this document or committed. Keep APP_KEY unchanged: approved referral content is encrypted with the application key.

After configuration, run php artisan config:clear and restart the dedicated local worker:

    php artisan queue:work referrals --queue=referrals --tries=3 --timeout=45

Keep it running while processing approved mail, or restart composer dev to include the registered referral worker alongside the standard worker/server/Vite. Starting a worker processes already approved queued messages; it does not approve reports. The dedicated database queue reserves jobs for 180 seconds; SMTP timeout is 20 seconds. No live worker was started by automated verification.

## Explicit inbox verification

1. Sign in as an existing administrator, then open Referrals & email test.
2. Check the explicit approval box and press Send live test email. This queues one test addressed only to aquamaplaguna@gmail.com, without report details or evidence.
3. Let the dedicated worker process it. Accepted by transport means SMTP acknowledgement, not inbox receipt.
4. Check that Gmail inbox and Spam for the exact reference. Only after locating it, enter the reference and confirm receipt in the admin page. This records administrator-confirmed receipt separately from transport acceptance.
5. Provider/inbox receipt never means an agency read, investigated or acted. No report is automatically resolved.

**Actual inbox delivery is not verified by automated tests.** Tests use Mail fakes or an isolated null transport. No live test is sent by migrations, initialization or tests. Do not infer actual receipt from browser-test screenshots.

## Agency directory and referrals

Agency CRUD is administrator-only. A canonical municipality identity hash uses the existing unique database index, independent of display name or active state. One municipality has one editable recipient; inactive entries are edited/enabled instead of duplicated. Supported choices are exact locality labels present in retained data; boundary/qualified labels cannot become invented municipality entries. The same test email can serve multiple municipalities. Explicit supported water-body assignments are optional routing overrides, not proof of jurisdiction. Deletion needs confirmation; snapshots/history survive. Deleting a recipient does not retract or cancel an already approved email.

The guarded agencies:initialize command's durable marker prevents repeated execution from recreating deleted entries. Fresh, uninitialized installations use exact supported municipalities, all provisionally addressed to the test receiver. Existing initialized installations are not repopulated. The explicit --normalize option only renames legacy provisional entries and recalculates their municipality keys; it aborts on duplicate/ambiguous locality or barangay scope and never creates entries. It was applied twice after a private directory backup; the second run changed nothing. Neither action runs from migrations, DatabaseSeeder or page requests.

Review a report privately and mark it Verified through Record decision. Its confirmation explicitly states that no email was sent. The report page suggests one active recipient only for an exact municipality match, covering multiple bodies without individual pivots. Unknown/cross-boundary labels leave the select blank and require deliberate manual selection. Explicit verified routing overrides remain available. Preview the recipient, content and optional photo; Approve & Send then requires a second confirmation. A fingerprint rejects changed report/recipient/photo content.

Delivery follows Pending review / Ready for referral approval, Approved for referral / queued, Sending, then Sent or Failed. Moderation status and its audit remain separate: email never automatically resolves an observation. Queued confirmation says Referral queued for sending. Refresh delivery status after the worker runs to see SMTP acceptance or a sanitized failure. Sent acknowledgement explicitly distinguishes inbox confirmation. Manage reports opens Action required, which includes queued/failed/uncertain outcomes and excludes accepted mail. Processed / referral history contains accepted referrals; All reports and private details retain the complete record. Terminal moderation never hides an unresolved delivery failure.

A unique per-report referral and transaction locks prevent repeated approval from queueing another email. Referral snapshot and database queue insertion commit in the same database transaction; the worker cannot see the uncommitted job. Queue payloads contain the referral ID only. Recipient snapshots, encrypted content, approving admin, timestamps, message ID when available and sanitized event codes remain private.

Only safe preparation failures retry automatically, up to three total attempts with backoff. Explicit SMTP 4xx/5xx rejection is Failed, shows a credential-free remedy and permits confirmed manual retry; authentication rejection is distinguished. Total attempt count and timestamped events survive retries. Network/unknown transport exceptions remain Uncertain and cannot resend through the normal action. SMTP cannot guarantee exactly-once delivery after ambiguous acknowledgement. Check provider records/inbox before further action; accepted/uncertain/sending records cannot use safe retry. Interrupted preparation can be reclaimed after its lease, but stale claim tokens cannot send. Button disabling supplements report/referral locks and database uniqueness; refresh/double approval never queues another report email.

Approved photos use confined private paths, size/type/pixel checks and a source hash. JPEG re-encoding strips embedded metadata; original private evidence is retained unchanged. Reporter name/email and public evidence URLs are never included. The admin must review user-written descriptions and photo-sharing permission before approval.

## Locations and privacy

Public reporters may tap/click a map, drag the problem pin, or select the map center by keyboard. Use My Current Location requests GPS only after a click. GPS first shows a candidate and device accuracy; another explicit confirmation assigns it as the problem point. Denial, timeout, missing GPS or insecure context preserves manual selection. GPS requires HTTPS or localhost. Changing the selected water body never substitutes its reference point for the incident point.

Coordinates, optional landmark/barangay, observation date and device accuracy are validated on the server. Unknown observation date stays unknown; no barangay is inferred. Location metadata is excluded from model serialization and the public map never queries reports. Precise pins appear only in authorized admin report views and approved private referrals. All required photo, timer, honeypot, rate-limit, session-duplicate and moderation protections remain.

## Recovery and validation

A verified schema/row-count MySQL dump and original-column table hashes were preserved under ignored storage/app/referrals/ before two forward-only additive migrations. Restore was not tested. Existing reports gained nullable metadata columns; no prior values were backfilled. The five new tables are agencies, agency_water_body, agency_directory_initializations, referrals and referral_events. New foreign keys restrict retained water-body/report deletion; removing an agency nulls its referral link while keeping snapshots/events.

The initial feature's backup remains under storage/app/referrals/. This fix's directory backup, integrity/preservation proof, diagnosis, JUnit and fake-mail browser results are under ignored storage/app/referral-fix/. Only seven agency names/identity keys/update timestamps changed; retained domain data, routes, email settings and initialization marker remained identical. No migration or deletion occurred. Current verification results are in CURRENT_STATE.md.

Implementation references: [Laravel Mail](https://laravel.com/framework/docs/13.x/mail), [Laravel queues](https://laravel.com/framework/docs/13.x/queues). No dependency was added. Springs/Resorts remain stored but excluded; their deletion still requires separate approval. Patola and Alpine.js are outside this feature.
