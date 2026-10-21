# Municipality referrals and Gmail delivery

Updated 2026-10-21 against b167ce5. Seven municipality/city recipients remain provisional entries at aquamaplaguna@gmail.com; these labels do not verify government office identity or jurisdiction. Sender credentials and installed schema are unchanged.

## Diagnosis and verified delivery

Five database jobs on queue referrals had zero attempts, no reservations and no failures. The running worker listened elsewhere, so those jobs never reached SMTP. Installed Laravel/Symfony supports the configured Gmail SMTP/STARTTLS connection on port 587; the transport now requires TLS. Credentials were inspected without printing values.

Fresh approvals use database connection referrals and queue municipality-referrals across dispatch, configuration and startup. All five original jobs (one report, four diagnostics) stay unchanged and paused on referrals. Do not start an old-queue worker, bulk retry or delete them. A report-bound municipality_delivery_confirmed event gates sending. Explicit admin reapproval can queue the old real report snapshot/reference on the new queue without changing its old job. Diagnostics cannot be sent.

After the correct worker started, the user explicitly sent a new report referral and reapproved the old real report snapshot. Both were SMTP accepted on attempt one with no error. The user confirmed Gmail receipt in chat; the reapproved report also records a separate matching in-app receiver-confirmation event and actor. The newer report's optional receipt audit remains unset. All five original job rows are still unchanged. Automated tests use Mail::fake() and do not establish real delivery.

## Start the server and worker

From the project root, choose one process setup. Restart workers after code or mail-configuration changes.

Windows server plus supervised municipality worker:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-local.ps1

If a server is already running, start only the supervised worker:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-local.ps1 -WorkerOnly

Add -Check to inspect commands without starting processes. -Port changes the server port. The script restarts an exited worker and stops its owned children on Ctrl+C. Private logs stay under ignored storage/app/direct-referrals/. The server retains the Laravel router and 5 MB upload / 8 MB request limits.

Alternatively, composer run dev includes the registered municipality worker with the standard development processes. Dedicated foreground worker:

    php artisan queue:work referrals --queue=municipality-referrals --tries=3 --timeout=45

Reservation: 180 seconds; SMTP timeout: 20 seconds. This worker consumes newly approved referrals, leaving the five old jobs alone. Do not use queue:retry all; the report's confirmed safe retry preserves the immutable snapshot, total attempts and audit.

## Gmail configuration

Preserve working credentials in ignored .env. Only if sending fails, check these settings locally; never paste passwords into chat, agency records, logs or Git:

    MAIL_MAILER=smtp
    REFERRAL_MAILER=smtp
    MAIL_SCHEME=smtp
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=587
    MAIL_USERNAME="your-existing-sender@gmail.com"
    MAIL_PASSWORD="your-app-password-without-spaces"
    MAIL_FROM_ADDRESS="your-existing-sender@gmail.com"
    MAIL_FROM_NAME="AquaMap Laguna"

Do not replace working values with these placeholders. Gmail requires an eligible account with 2-Step Verification and an app password; see [Google app-password setup](https://support.google.com/mail/answer/185833?hl=en). An alternative implicit-TLS setup uses MAIL_SCHEME=smtps and port 465. Live referrals require encrypted SMTP and reject log/array/failover sending. Keep APP_KEY stable for encrypted snapshots.

After an intentional correction, run php artisan config:clear and restart the worker. UI readiness never exposes passwords. Authentication, connection/TLS, provider rejection and preparation errors have credential-free remedies. Unknown submission outcomes require provider/inbox investigation before any resend.

## Admin workflow and history

In Manage Reports, review concern, private evidence and incident coordinates. Send to Municipality selects one active recipient for an exact verified municipality, covering multiple bodies without individual assignments. Unknown/boundary localities require manual selection. Verified routing overrides remain available; no jurisdiction is invented.

Review the dialog's actual recipient, complete text, precise location and optional photo permission, then confirm. Pending-report verification, moderation audit, unique encrypted snapshot and database job commit atomically. A fingerprint rejects changed report/recipient/photo content. Repeat approval returns the existing referral. Legacy confirmation preserves the original content/reference/recipient even after directory edits.

Queued means waiting for a worker; Sending means claimed/prepared or submitting. Sent / accepted by SMTP appears only after acceptance; it does not prove inbox receipt or government action. Open report details poll delivery state and show authoritative acceptance/failure. Accepted referrals move from Action required to Processed / Referral History. Rejection/closure records moderation without email under existing terminal-state rules. Email never resolves an environmental concern automatically.

After personally finding the exact reference in the receiver inbox, an administrator can submit Confirm receiver inbox receipt on the report. This creates a separate idempotent audit. The old Referrals & email test page, navigation and live-test endpoint are removed. Preserved diagnostics appear read-only in Manage Reports; no jobs/history are deleted.

Unique keys, locks and claim tokens prevent duplicate processing. Preparation retries are bounded. Explicit SMTP rejection or recognized connection/STARTTLS failure before submission permits confirmed manual retry with the same snapshot; attempts/events survive. Unknown outcomes stay Uncertain and cannot be blindly resent. SMTP cannot guarantee exactly-once delivery after ambiguous acknowledgement. failed_jobs remains available for investigation; report-level errors are sanitized.

## Directory, privacy and recovery

Agency Directory CRUD retains one canonical municipality identity independent of name/active state. Multiple municipalities may share the testing inbox. The explicit agencies:initialize marker never recreates deliberately deleted entries. Initialization/normalization never runs from page requests, migrations or automatic seeders. No directory updates were needed here.

Snapshots/events survive directory edits/deletion. Reporter contacts and public evidence URLs are excluded from mail. Approved attachments use confined private paths, size/type/pixel/hash checks and JPEG re-encoding to strip metadata; original photos stay intact. Coordinates, evidence, contacts and audits remain administrator-only. GPS/manual/drag controls and reporting protections remain unchanged.

CURRENT_STATE.md and ignored storage/app/direct-referrals/ hold validation/recovery evidence. Retained-data hashes and the five original jobs were verified; the user's live approval accounts for new moderation/referral/audit data. Earlier verified SQL/schema backups remain under storage/app/referrals/. No migration, deletion, import or credential update ran here. Keep private artifacts out of Git. Legacy Spring/Resort deletion requires separate approval; Patola and Alpine.js remain outside scope.
