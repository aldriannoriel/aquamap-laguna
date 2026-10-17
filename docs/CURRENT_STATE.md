# Day 5 checkpoint

**2026-10-17 - Complete for the requested Day 5 scope.** Baseline: `36d8a60` on `main`. Days 1-4, source/coordinate caveats, anonymous reporting and all existing data are preserved. No Day 6 feature work was implemented.

## Implemented

- Two access levels only: anonymous public visitors and explicitly provisioned administrators. No registration, community accounts, role-management UI or default passwords.
- Laravel web-session administrator login at `/admin/login`; protected dashboard at `/admin`, with total/pending/verified/rejected/resolved counts. Login requires a real administrator flag, regenerates the session, and gives generic credential failures. Logout invalidates the session/token.
- A guarded, hidden boolean `users.is_admin` defaults false, including existing users. All admin resources/actions require authentication plus the administrator gate. Login is limited to 5 attempts/minute/IP; status actions to 30/minute/administrator. Standard CSRF/origin protection stays enabled.
- Report management supports description/lake search, lake/type/status/inclusive date filters, 15-record pagination and escaped private details. Dates display in the configured timezone (currently UTC). Incident coordinates remain reporter-selected/unverified; lake reference coordinates are not substituted.
- Photos stream only through an authorized report-ID route, with allowed filename/MIME checks, a generic response filename, no-store/private, nosniff and restrictive image CSP. Direct private paths, report contents and contact details never appear on public pages.
- Controlled transitions: pending to verified/rejected; verified to resolved; rejected/resolved terminal. A transaction locks the current report, rejects stale expected status, and saves the status and audit together. Audit records contain the administrator, previous/new states, timestamp and optional 2,000-character note. Unexpected persistence failures roll back and return a generic error without logging SQL/input contents.
- Responsive admin Blade views reuse the environmental theme and public header. Private pages use no-store, same-origin referrer and noindex headers. Review notes and all reporter/evidence text are escaped.
- [RETENTION_POLICY.md](RETENTION_POLICY.md) documents manual minimization, review periods, private copies/backups and recovery. No automatic deletion or public publication exists.

## Initial administrator setup

Run from the project root in an interactive local terminal:

`php artisan admin:create`

The command verifies configured/connected `aquamap_laguna`, asks for a new name/email and a hidden password with confirmation, and refuses existing accounts or unattended execution. Passwords need at least 12 characters, mixed case, a number and a symbol; bcrypt additionally permits at most 72 bytes. Hidden input never falls back to visible entry. The model hashes the chosen password. Never supply passwords as shell arguments or commit them.

**No real administrator was created during development.** The owner must run this command to choose their own credentials, then use the Administrator navigation link. This is the remaining installation step, not an unfinished authentication feature. Public visitors continue using the site without accounts. No reset-password interface is implemented.

## Verification

| Check | Result |
| --- | --- |
| Laravel suite | PASS: 127 tests / 1,003 assertions / zero failures; isolated SQLite `:memory:` |
| New administrator coverage | PASS: 51 cases for login/nonadmin rejection, guest/role denial, session/logout, registration absence, role guarding, bcrypt suffix rejection, setup/password/target safety, counts, filters/search/dates/pagination, private photos and unsafe paths/MIME, complete transition matrix, stale pages, audit identity/escaping/rollback, rate limits and legacy-preserving forward upgrade |
| Formatting and assets | PASS: Pint dirty + explicit new PHP files, production Vite build, Node syntax, Composer validation and Git whitespace checks |
| MySQL forward upgrade | PASS: only `075301_add_is_admin_to_users_table` and `075302_create_report_reviews_table` applied after configured/connected target verification; existing lake/fish/link/report/user record hashes unchanged; zero real users/reports/audits |
| Admin desktop 1440 x 1100 | PASS: admin-only login/logout, guest photo/CSRF rejection, count cards, pagination, combined filters, private details/image stream, verify/resolve, escaped notes, audit history and terminal state |
| Admin mobile 390 x 844 | PASS: login/logout, counts/filters/list/details, rejection/terminal state and audit history; no horizontal overflow |
| Browser safety | PASS: random ephemeral test password; synthetic accounts/reports and 3 audit entries only in temporary SQLite/private storage; real domain/users/reviews and shared-cache hashes unchanged; fixture/profile/server/browser removed; zero JS exceptions |
| Days 1-4 regressions | PASS: GIS/markers/popups/zoom/keyboard pan/reset, historical fish cards/sources/insufficient data, anonymous form/errors/map selection and private 3 MB uploads on desktop/mobile |
| Final code review | No remaining actionable defects in the Day 5 diff/new files after tracing auth/privilege boundaries, private streaming, validation, query binding, transitions/concurrency, atomic audit failure, setup, retention and fixture isolation |

Browser commands: `node tests/browser/admin-smoke.mjs`, `node tests/browser/reports-smoke.mjs`, `node tests/browser/map-smoke.mjs --fish`. Results and inspected screenshots are in ignored `storage/app/day5-browser/`, `day4-browser/` and `day3-browser/`. This is installed Edge/Chromium emulation, not physical-device certification. SQLite covers stale conflicts/atomic rollback; concurrent MySQL load was not exercised.

## Files changed

- Added backend: `app/Console/Commands/CreateAdministrator.php`; `app/Rules/AdministratorPasswordLength.php`; `app/Http/Middleware/AdminPrivacy.php`; `app/Models/ReportReview.php`; `app/Http/Controllers/Admin/{SessionController,DashboardController,ReportController,ReportStatusController}.php`; `app/Http/Requests/Admin/{LoginRequest,ReportFilterRequest,ReviewReportRequest}.php`.
- Added forward migrations: `database/migrations/2026_10_17_075301_add_is_admin_to_users_table.php` and `2026_10_17_075302_create_report_reviews_table.php`.
- Added views: `resources/views/layouts/admin.blade.php`, `resources/views/admin/{login,dashboard}.blade.php`, `resources/views/admin/reports/{index,show,status}.blade.php`.
- Added tests: `tests/Feature/{AdminAuthenticationTest,AdminReportsTest,AdminMigrationTest,CreateAdministratorTest}.php`, `tests/browser/{admin-fixture.php,admin-smoke.mjs}`.
- Updated: `app/Models/{User,PollutionReport}.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `resources/views/partials/header.blade.php`, `resources/js/incident-map.js` (review feedback focus only).
- Updated memory: this checkpoint, `docs/PROJECT_CONTEXT.md`, `.agents/architecture/architecture.md`, `AGENTS.md`; added `docs/RETENTION_POLICY.md`. Existing migrations/seeders, dependency manifests/locks and credentials are unchanged. Generated assets and runtime artifacts stay ignored.

## Operations, recovery and Day 6 handoff

The two new forward migrations have been applied locally. Other installations must apply them before admin login or account setup. Role/audit rollback would discard security/history metadata; recover code using Git and use reviewed forward data fixes instead of resets or destructive rollback.

The local verified Day 5 commit contains this checkpoint; obtain its ID with `git log -1 --oneline`. No push or remote setup. Git protects source, not private runtime data. See the retention policy for backups and approved minimization; foreign keys restrict deletion of reviewed reports and their administrator accounts.

Keep using `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/serve-local.ps1` for process-local 5 MB upload / 8 MB POST limits; machine PHP defaults to 2 MB uploads. Public deployment was not performed: HTTPS, secure-cookie/debug settings, private backups and hosting access controls remain deployment work. Coordinate datum/accuracy, current water-quality measurements and fish compatibility remain unverified and visibly qualified.

**Planned Day 6:** confirm the final integration/demo brief, complete owner account setup, and review deployment/recovery and retention operations. Preserve anonymous access and private review, and avoid introducing scientific conclusions or public report exposure through demonstration shortcuts.

Framework behavior was checked against installed Laravel 13 and official [authentication](https://laravel.com/docs/13.x/authentication), [authorization](https://laravel.com/docs/13.x/authorization) and [query locking](https://laravel.com/docs/13.x/queries#pessimistic-locking) documentation after Boost search was unreachable.
