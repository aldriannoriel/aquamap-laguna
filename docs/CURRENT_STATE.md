# Anonymous reporting anti-spam checkpoint

**2026-10-17 - Complete.** Baseline: Day 5 commit `c85aa52` on `main`. Days 1-5, anonymous access, private evidence/admin review and all existing domain records are preserved. No Day 6 features or schema changes were introduced.

## Implemented protections

- The existing limit remains **five POST attempts per hour per IP**, including invalid attempts. Its route/provider configuration is unchanged; shared networks still share the quota.
- A `website` honeypot is hidden, excluded from keyboard/screen-reader interaction and autocomplete, and validated server-side. Nonempty/invalid values prevent report/photo persistence. The trap value is not repopulated or logged.
- The required form token contains a purpose tag, server millisecond timestamp and session-CSRF fingerprint, encrypted/authenticated using Laravel's existing key. The server enforces **3,000 ms minimum** and **two-hour validity**, rejecting missing, changed, malformed, future-dated and cross-session tokens. Client timestamp fields cannot override it. No new secret, package, service, API or citizen account is needed.
- Timing works without JavaScript. Valid token age survives validation retries, so correcting a field does not restart the wait. Invalid/expired tokens are replaced on the redirected form; safe text/point inputs remain available and photos must be reselected. The token is reusable within its validity, not a single-use challenge.
- Potential-repeat detection considers only the current anonymous session's last ten successful receipt IDs and actual records submitted in the **last hour**, matching lake and pollution type. Only IDs/lake IDs/types are queried; request fields cannot choose this history. Old/missing receipts are pruned on the next successful submission.
- A matching repeat still saves normally as **pending**, then the private confirmation shows an advisory warning. It does not expose prior report contents, contacts, IDs or other people's submission history. Similar lake/type observations may describe different legitimate incidents.
- CSRF, upload validation/private storage, server-controlled status, administrator authorization and audit transitions remain intact. [RETENTION_POLICY.md](RETENTION_POLICY.md) covers the small session receipt list; no report is automatically deleted or changed.

## Verification

| Check | Result |
| --- | --- |
| Full Laravel suite | PASS: **145 tests / 1,173 assertions / zero failures**, isolated SQLite `:memory:` |
| New anti-spam coverage | PASS: 18 cases covering 2,999/3,000 ms boundary, safe retry, malformed/tampered/oversized tokens, session binding, expiry/replacement, authenticated invalid/future payloads, honeypot values/uploads, no-JavaScript submission, advisory pending repeats, other-person privacy, lake/type separation and receipt expiry/bounding/order |
| Existing reporting tests | PASS: all 33 cases retained; positive fixtures now obtain a real form token and advance the isolated clock instead of bypassing protection |
| Formatting/build | PASS: Pint dirty + explicit new PHP paths, production Vite build, Node syntax checks, Composer validation and Git whitespace checks |
| Anti-spam Edge desktop/mobile | PASS: immediate POST rejected, hidden/nonfocusable honeypot rejects filled values, text-safe retry succeeds, repeat warnings remain readable/nonblocking at 1440 x 1100 and 390 x 844; three synthetic reports remain pending; sixth POST returns 429 |
| Existing reporting browser | PASS: desktop/mobile errors, incident selection/clear/retention, private 3 MB uploads, photo reselection and pending confirmation with real server timing enabled |
| Administrator browser | PASS: login/nonadmin/guest/CSRF checks, counts, filters/pagination, private photo/detail access, verify/resolve/reject, audit history, terminal states and logout on desktop/mobile |
| Browser integrity | PASS: zero JS exceptions, isolated SQLite/photo/session/cache directories and browser profiles, unchanged real MySQL/domain/users/review hashes and shared cache; temporary fixtures/profiles/servers/browsers removed |
| Database safety | PASS: configured and connected `aquamap_laguna` verified; preflight record checksums unchanged; no migrations, seeding or synthetic submissions on MySQL |
| Final code review | No remaining actionable defects found in the anti-spam diff/new files after reviewing cryptographic/session binding, validation, private history scope, persistence order, rate limiting, accessibility and regression checks |

Browser commands:

- `node tests/browser/reports-smoke.mjs --spam`: `storage/app/day4-browser/spam-results.json`, `spam-desktop-warning.png`, `spam-mobile-warning.png`.
- `node tests/browser/reports-smoke.mjs`: existing reporting results/screenshots in the same ignored directory.
- `node tests/browser/admin-smoke.mjs`: administrator regression artifacts in ignored `storage/app/day5-browser/`.

Desktop/mobile screenshots were inspected. Tests use installed Edge/Chromium and Node built-ins without new packages. Device emulation is not physical-device certification. A stale-document navigation race in the admin browser runner was corrected to wait for the new loader before interacting; application authentication code was unchanged.

## Files changed

- Added `app/Rules/ReportFormToken.php` and `tests/Feature/ReportSpamProtectionTest.php`.
- Updated `app/Http/Controllers/PollutionReportController.php`, `app/Http/Requests/StorePollutionReportRequest.php`, `resources/views/reports/create.blade.php`.
- Updated `tests/Feature/PollutionReportTest.php`, `tests/browser/reports-smoke.mjs`, `tests/browser/admin-smoke.mjs`.
- Updated memory: this checkpoint, `docs/PROJECT_CONTEXT.md`, `docs/RETENTION_POLICY.md`, `.agents/architecture/architecture.md`, `AGENTS.md`.
- Existing migrations, models, seeders, routes/rate-limit configuration, dependency manifests/locks and credentials are unchanged. No scientific or coordinate values were added.

## Limitations and operations

These are modest abuse controls, not proof of a human or a reliable trolling classifier. Determined bots can leave the trap blank, fetch a token and wait. Repeat warnings are coarse and advisory, work only within the same anonymous session, and can be missed after session resets or simultaneous requests. Shared browser sessions can share hints. No global duplicate-report lookup, identity fingerprint or automatic rejection/verification was added. Administrator review remains essential.

The normal form session cookie is required, as for existing CSRF protection. Form/session expiry may require opening a fresh form. No paid CAPTCHA, external anti-spam endpoint or citizen login is involved. Coordinates/datum accuracy, current water quality and habitat compatibility remain unverified and visibly qualified.

The owner still provisions real administrators explicitly with `php artisan admin:create`; no default account/password exists. Use the existing local launcher `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/serve-local.ps1` for process-local 5 MB upload / 8 MB POST limits; default machine PHP upload limit is 2 MB. Public deployment was not performed.

## Recovery and handoff

This document belongs to the local verified anti-spam Git checkpoint; obtain its ID with `git log -1 --oneline`. No push or remote setup. Source recovery uses Git; never reset databases or run destructive rollback for this code-only change. Runtime reports/photos are not Git backups.

**Planned:** confirm the Day 6 integration/demo and deployment/recovery brief. Keep public access anonymous, all reports private/pending on submission, and scientific uncertainty visible. Larger abuse controls or durable cross-session deduplication need a separate privacy and operational design.

Framework APIs were checked against installed Laravel and the official [encryption](https://laravel.com/docs/13.x/encryption) and [custom-validation](https://laravel.com/docs/13.x/validation#custom-validation-rules) documentation after Boost documentation search was unreachable.
