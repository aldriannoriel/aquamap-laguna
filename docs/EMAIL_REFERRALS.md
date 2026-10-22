# Municipality referrals and Gmail delivery

Updated 2026-10-22 against f7511c4. New admin sends and safe retries are synchronous. Existing queues, failed-job handling, reports, snapshots, delivery events, recipients and credentials remain stored.

## Confirmed cause and current behavior

Read-only inspection found two municipality-referrals jobs with zero attempts and five older referrals jobs, also unattempted. No PHP queue worker was running. No failed job or SMTP rejection explained the current backlog: it had not reached the transport. Gmail SMTP on port 587, required TLS, a 20-second socket timeout and credential presence were verified without printing credentials. No connection/authentication probe or real mail was sent.

The normal admin request now prepares its immutable referral transaction, then invokes the existing SendReferral handler immediately after commit. It inserts no queue job and has no queue-driver/worker dependency. SMTP acceptance and its audit event are saved before a pending report becomes internally verified / visibly Forwarded. Failure leaves a new report internally Pending; uncertain acknowledgement stays blocked against resend. These Laravel mail APIs are documented in [Laravel 13 mail](https://laravel.com/docs/13.x/mail#sending-mail).

All seven existing jobs must remain preserved. Do not start a worker to flush them, bulk retry them, or delete them. SendReferral releases actual queued executions without sending; an old real report can send only through a newly reviewed explicit admin action. Diagnostics remain hidden and cannot send or retry. Other job classes and application queue configuration are unchanged.

## Simple admin workflow

Review Report Information, evidence and the saved incident point. Select the incident municipality from the Agency Directory and enter a short selection reason. No verified municipal boundary layer exists, so coordinates, the water body's locality and nearest-town guesses never select jurisdiction automatically. The point fingerprint, municipality, reason and administrator are audited. Eight current directory entries are preserved, including Magdalena; municipality labels alone do not verify a government office.

Press Send Email, review the generated message and saved recipient in one confirmation, then confirm sending. No manual referral message or separate approval form is required. Photo inclusion remains an explicit permission choice. The confirmation authorizes necessary sharing of the displayed incident description/coordinates/evidence; reporter contact fields and public evidence URLs are excluded. Preview cancellation records only the routing review and sends nothing. Current point/recipient/content hashes protect stale forms. Missing, inactive or invalid recipients block sending.

The normal interface shows Not Sent, Sending, Sent (with SMTP acceptance explained), or Failed. A transport-uncertain result appears Failed with an explicit investigation message and no retry control. SMTP acceptance never proves inbox receipt, agency action or resolution. Historical attempts/snapshots/events are expandable; a separately verified receiver-receipt audit remains optional and collapsed.

Reject Report requires a reason, sends nothing and safely cancels unsent work. Forwarded cases can Record Municipality Update with required remarks, change Under Review / In Progress, Mark as Resolved with private supporting file uploads and an explicit evidence-based confirmation (remarks optional), or Close Case with a distinct reason and optional follow-up attempts. Updates append audits without changing delivery or resolving automatically. Closed cases may reopen to Under Review with a new-information reason; Rejected and Resolved remain final.

## Failure handling and safety

Authentication rejection, definitive provider rejection and recognized connection/TLS failure permit an explicitly confirmed safe retry. Retry uses the original snapshot/reference, retains total attempts/events, checks the report remains eligible and returns an immediate result. Unknown submission outcomes cannot be retried automatically; investigate provider/inbox records first. Row claims, unique referral keys and terminal-state checks prevent duplicate clicks/interleaved calls from sending twice. Exactly-once SMTP delivery is not promised after an ambiguous acknowledgement. Raw transport exceptions and secrets are not displayed or stored in error summaries.

Approved private attachments retain path, size, type, pixel and source-hash checks and JPEG re-encoding; original photos remain unchanged. Recipient snapshots/history survive directory edits or deletion. Private middleware, authorization, no-store responses and existing report/location protections remain.

## Local setup

From the project root, run the Laravel server normally:

    php artisan serve --host=127.0.0.1 --port=8000

A referral worker is unnecessary for Send Email. For five-file evidence uploads, restart with `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-local.ps1 -ServerOnly`; it sets 5 MB/file and 32 MB/request limits without starting a worker. Uploaded resolution/follow-up files stay private and are not added to previously sent email snapshots. Existing composer/dev and scripts/serve-local.ps1 worker configuration remains for compatibility; it does not authorize flushing stored jobs. Other background jobs can retain their own workers.

Preserve the existing ignored .env and APP_KEY. Only if an explicit send fails, check locally that MAIL_MAILER/REFERRAL_MAILER select smtp; MAIL_SCHEME is smtp with smtp.gmail.com:587 and TLS, or smtps with port 465; MAIL_USERNAME and MAIL_FROM_ADDRESS match the existing sender; MAIL_PASSWORD is its Gmail app password. Never paste or commit the password. See [Google app-password setup](https://support.google.com/mail/answer/185833?hl=en). After an intentional correction, run php artisan config:clear and reload/restart the local server. UI readiness checks configuration presence, not actual authentication.

## Validation and prior live evidence

Automated tests use Mail::fake or a mocked SMTP transport, confined SQLite and synthetic browser data; they never send real mail. Previous user-initiated referrals had SMTP acceptance and user-confirmed Gmail receipt (2026-10-21). This change does not claim a new real SMTP/inbox test. A new live verification requires the admin's explicit send and separate receiver confirmation.

Current test/browser results and preservation hashes are in CURRENT_STATE.md and ignored storage/app/direct-mail/. Earlier recovery artifacts/backups are retained. No existing decision, recipient, credential or sent snapshot is changed by evidence uploads. A separately approved additive evidence-table migration is documented in CURRENT_STATE.md; no deletion, import or live resend occurs.
