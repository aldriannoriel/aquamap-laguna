# Prototype retention and privacy policy

Effective 2026-10-17 for the school prototype. These are project operating limits, not a statement of legal compliance. The project owner maintains this policy and reassesses retention before public deployment. The application does not automatically delete, redact, export or publish records.

## Purpose and access

Reports support private review and necessary follow-up of community observations. Public users need no account. Only explicitly provisioned administrators can access reports, reporter details, evidence and review history. All statuses remain private. Administrative verification is not a scientific measurement or a public pollution determination.

Collect only the optional contact details and photo the reporter chooses to provide. Do not copy contact information into review notes. Photos can contain embedded location/person information. Avoid downloads, screenshots, shared copies and exports unless necessary; those copies require the same access and retention controls as the application.

Local anti-spam checks add no citizen account, external service or identity fingerprint. An encrypted form token is tied to the existing anonymous session and expires after two hours. The session stores at most ten successful report IDs for repeat warnings; queries consider only those records submitted within the last hour. Older/missing receipts are pruned on the next successful submission or disappear when the session ends. No report contents, names or email addresses are copied into this receipt list. A shared browser session can share its repeat-warning history. Clearing the session removes these hints but does not reset the existing IP rate limit or delete reports.

## Manual review schedule

| Data | Prototype retention review |
| --- | --- |
| Pending reports | Review the queue weekly; reassess necessity and follow-up at 30 days. Do not keep an unattended queue indefinitely. |
| Reporter names/emails and photos | Review for removal or redaction within 30 days after rejection or resolution; retain longer only for a documented, necessary follow-up purpose. |
| Report descriptions and incident coordinates | Review at 90 days after submission, or project closure, whichever comes first. Minimize or anonymize details when the original purpose ends. |
| Audit metadata and notes | Review at 180 days, or project closure, whichever comes first. Prefer the minimum decision record; notes must avoid additional personal information. |
| Administrator accounts | Review access at project closure and whenever a reviewer leaves. Role revocation takes effect on subsequent authenticated requests. No account-management UI is implemented. |

These schedules trigger an owner decision; they are not deletion jobs. There is no deletion UI or purge command in Day 5. The owner must authorize any future, narrowly scoped cleanup with a reviewed recovery plan. Record the purpose, authorization and outcome without repeating erased personal information. Do not use database resets, migration rollback or blanket deletes as retention tools.

Audit foreign keys restrict deletion of reviewed reports and their administrator accounts. A future retention process must preserve necessary audit identity or deliberately anonymize/archive it before changing parent records. Do not bypass those constraints. The owner must handle reporter correction/removal requests privately; no public contact-lookup endpoint exists.

## Storage and recovery

Keep photos outside the web root and grant filesystem/database/backup access only to the project owner or necessary operators. Use HTTPS, secure session cookies and disabled debug output before public deployment; these deployment settings were not changed in this task. Backups and administrator-created copies must have documented expiry and access controls. After recovery from a backup, reapply any approved privacy redactions before reopening access.

Git protects code and documentation, not runtime reports or photos. Do not commit credentials, private evidence, database dumps or personal information. Any future backup/recovery exercise must use isolated synthetic fixtures or separately authorized private backups.
