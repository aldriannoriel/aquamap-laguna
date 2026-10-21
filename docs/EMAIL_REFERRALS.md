# Email referrals and private report locations

Implemented 2026-10-21 against 80967b9. Initial setup added seven provisional test routes, with existing exact locality/water-body assignments for Los Banos, San Pablo City, Cavinti, Pangil, Luisiana, Siniloan and Majayjay. Every initial email is aquamaplaguna@gmail.com. These names describe test routes, not verified official agencies or barangay responsibilities.

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

Keep it running while processing approved mail. The dedicated database queue reserves jobs for 180 seconds; SMTP timeout is 20 seconds. No live worker was started by automated verification.

## Explicit inbox verification

1. Sign in as an existing administrator, then open Referrals & email test.
2. Check the explicit approval box and press Send live test email. This queues one test addressed only to aquamaplaguna@gmail.com, without report details or evidence.
3. Let the dedicated worker process it. Accepted by transport means SMTP acknowledgement, not inbox receipt.
4. Check that Gmail inbox and Spam for the exact reference. Only after locating it, enter the reference and confirm receipt in the admin page. This records administrator-confirmed receipt separately from transport acceptance.
5. Provider/inbox receipt never means an agency read, investigated or acted. No report is automatically resolved.

**Actual inbox delivery is not verified by automated tests.** Tests use Mail fakes or an isolated null transport. No live test is sent by migrations, initialization or tests. Do not infer actual receipt from browser-test screenshots.

## Agency directory and referrals

Agency CRUD is administrator-only. Office/locality identity keys prevent accidental duplicate records; the same test email can serve multiple provisional routes. Active state, verification status, notes and multiple supported water bodies are editable. Associations do not establish legal jurisdiction. Deletion needs explicit confirmation; recipient/agency snapshots and referral history survive deletion. Deleting a directory entry does not retract mail or cancel an already approved snapshot.

The guarded agencies:initialize command ran once after the additive migrations. Its durable initialization marker prevents repeated execution from recreating deleted entries. It is not called by normal migrations, DatabaseSeeder or page requests.

Review a report privately and mark it Verified through the existing moderation controls. The report page suggests active recipients by explicit water-body association and shows their locality/verification status. Change the selection and optionally approve a supporting photo, then preview the exact report content. Approve & Send requires a second explicit review checkbox. A preview fingerprint rejects changed report/recipient/photo content.

A unique per-report referral and transaction locks prevent repeated approval from queueing another email. Referral snapshot and database queue insertion commit in the same database transaction; the worker cannot see the uncommitted job. Queue payloads contain the referral ID only. Recipient snapshots, encrypted content, approving admin, timestamps, message ID when available and sanitized event codes remain private.

Only safe pre-send failures retry automatically, up to three attempts with backoff. A network/SMTP exception after sending begins is recorded as uncertain and is not retried automatically. SMTP cannot guarantee exactly-once delivery after an ambiguous acknowledgement. Check provider records/inbox before any further action; accepted/uncertain/sending records cannot use the safe-retry action. Interrupted preparation can be reclaimed after a lease expires, but a stale worker's claim token prevents it from sending.

Approved photos use confined private paths, size/type/pixel checks and a source hash. JPEG re-encoding strips embedded metadata; original private evidence is retained unchanged. Reporter name/email and public evidence URLs are never included. The admin must review user-written descriptions and photo-sharing permission before approval.

## Locations and privacy

Public reporters may tap/click a map, drag the problem pin, or select the map center by keyboard. Use My Current Location requests GPS only after a click. GPS first shows a candidate and device accuracy; another explicit confirmation assigns it as the problem point. Denial, timeout, missing GPS or insecure context preserves manual selection. GPS requires HTTPS or localhost. Changing the selected water body never substitutes its reference point for the incident point.

Coordinates, optional landmark/barangay, observation date and device accuracy are validated on the server. Unknown observation date stays unknown; no barangay is inferred. Location metadata is excluded from model serialization and the public map never queries reports. Precise pins appear only in authorized admin report views and approved private referrals. All required photo, timer, honeypot, rate-limit, session-duplicate and moderation protections remain.

## Recovery and validation

A verified schema/row-count MySQL dump and original-column table hashes were preserved under ignored storage/app/referrals/ before two forward-only additive migrations. Restore was not tested. Existing reports gained nullable metadata columns; no prior values were backfilled. The five new tables are agencies, agency_water_body, agency_directory_initializations, referrals and referral_events. New foreign keys restrict retained water-body/report deletion; removing an agency nulls its referral link while keeping snapshots/events.

The initial full PHP run exceeded the local 128 MB process limit. The full suite passed with a CLI-only 512 MB limit; no php.ini or application memory setting changed. JUnit, browser results/screenshots, backup manifest and preservation proof remain under storage/app/referrals/. Current results are in CURRENT_STATE.md.

Implementation references: [Laravel Mail](https://laravel.com/framework/docs/13.x/mail), [Laravel queues](https://laravel.com/framework/docs/13.x/queues). No dependency was added. Springs/Resorts remain stored but excluded; their deletion still requires separate approval. Patola and Alpine.js are outside this feature.
