# Day 4 checkpoint

**2026-10-17 - Complete for the requested Day 4 scope.** Baseline: `b3c55db` on `main`. Days 1-3, all migrations/seed data, GIS/fish interactions and the visible coordinate limitation are preserved. Day 5 admin features are not implemented.

## Implemented

- Public reporting form at `GET /reports/create`, linked from shared navigation and the GIS page; submissions use `POST /reports`.
- Required existing lake, predefined observation type and description (up to 5,000 characters). Optional reporter name/email, explicit incident coordinate pair and JPEG/PNG/WebP photo up to 5 MB (5,120 KiB).
- Server validation, Laravel web CSRF/origin protection and five POST attempts/hour/IP, including invalid submissions. A friendly 429 includes retry headers; shared networks share the quota.
- Guarded model with explicit attribute assignment and server-only `pending` status. Client status, file paths, IDs and timestamps are ignored. No schema changes were necessary.
- Generated photo filenames on a dedicated private disk outside the web root, no download/list/detail routes, hidden contact/path model attributes and no-store form responses. Pending reports and personal details are never included in the public map or form data.
- Save failure returns a generic error and attempts photo cleanup without logging request data or SQL bindings. Storage unavailability can require operator cleanup of an orphaned private file.
- Validation restores only scalar form fields and an explicitly selected point; all rendered values are escaped. Photos must be reselected. Success uses a generic pending confirmation without report contents or identifiers.
- Responsive incident map: database reference circles, deliberate click/tap selection, keyboard-accessible map-center selection, zoom/pan and clearing. Choosing a lake centers the map without assigning or moving an incident point. Coordinates remain optional and carry no accuracy guarantee.

## Verification

| Check | Result |
| --- | --- |
| Laravel suite | PASS: 76 tests, 615 assertions, zero failures; PHPUnit SQLite `:memory:` guard retained |
| New reporting coverage | PASS: 33 cases covering persistence/relationships, anonymous/optional fields, required/invalid lakes/types/shapes/lengths, paired/bounded coordinates, JPEG/PNG/WebP, 5 MB boundary/oversize, unsafe MIME/extensions, status/path/ID tampering, privacy, escaped safe old input, 429/address separation/expiry and failed-save photo cleanup |
| Formatting/build | PASS: Pint dirty + explicit new PHP paths; `npm.cmd run build`; JS syntax; Composer validation; Git whitespace check; local server script syntax |
| Real MySQL safe check | PASS: configured/connected `aquamap_laguna`, existing pollution columns verified, real reporting GET returns 200 and all three lake names |
| Data integrity | PASS: lake/species/link/report/user hashes unchanged from preflight; real reports remain zero; no migration/seeding/reset or synthetic report writes to MySQL |
| Reporting browser | PASS: Edge desktop 1440 x 1100 and mobile 390 x 844; native/server errors, focused summary, retained text/point, explicit photo reselection, map click/tap/zoom/clear, 3 MB private uploads, pending confirmation and no horizontal overflow |
| Browser isolation | PASS: two synthetic pending reports/photos only in temporary SQLite/private storage; CSRF middleware active, tokenless untrusted POST rejected; real MySQL data and shared cache hashes unchanged; browser/profile/server/fixture cleaned up |
| GIS/fish browser regression | PASS: all lake/marker selections, popups/sources, historical fish cards, insufficient data, zoom/keyboard pan/reset and mobile layout; zero JS exceptions |
| Final code review | No actionable defects found in the Day 4 diff/new files after tracing validation, privacy, server status, upload/cleanup, rate limiting, map behavior and test isolation |

Browser commands (installed Edge/Chromium and Node built-ins, no new packages):

- `node tests/browser/reports-smoke.mjs`
- `node tests/browser/map-smoke.mjs --fish`

Results/screenshots are in ignored `storage/app/day4-browser/` and `storage/app/day3-browser/`; desktop/mobile screenshots were inspected. This is browser/device emulation, not physical-device certification. A fixture cache-ordering defect encountered during verification was corrected before the final successful run; final checks explicitly protect the shared cache.

## Files changed

- Added backend: `app/Http/Controllers/PollutionReportController.php`, `app/Http/Requests/StorePollutionReportRequest.php`.
- Added views: `resources/views/layouts/reporting.blade.php`, `resources/views/partials/header.blade.php`, `resources/views/reports/{create,rate-limited}.blade.php`.
- Added assets/tooling: `resources/js/incident-map.js`, `scripts/serve-local.ps1`.
- Added tests: `tests/Feature/PollutionReportTest.php`, `tests/browser/{report-fixture.php,reports-smoke.mjs}`.
- Updated: `app/Models/PollutionReport.php`, `app/Providers/AppServiceProvider.php`, `config/filesystems.php`, `routes/web.php`, `resources/{css/app.css,js/app.js,views/map/index.blade.php}`.
- Updated memory: this checkpoint, `docs/PROJECT_CONTEXT.md`, `.agents/architecture/architecture.md`, `AGENTS.md`. Dependency manifests/locks, credentials, migrations and seeders are unchanged. Generated assets/runtime artifacts remain ignored.

## Running locally and remaining limits

PHP on this machine defaults to a 2 MB upload limit. Start the local server with `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/serve-local.ps1` (optional `-Port 8001`). This sets 5 MB upload and 8 MB POST limits only for the launched process; it changes no global policy, PHP configuration or credentials. Other web/PHP servers must support those limits. No production deployment was performed.

The system stores observations pending review, without automatic review or email. Reports are not confirmed pollution findings or scientific measurements. Photos remain private but may retain embedded metadata; filesystem administrators/backups require appropriate access protection. Coordinate datum/accuracy, present water-quality observations and habitat compatibility remain unverified. Research references and their limitations remain in [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md).

Framework APIs were checked against installed Laravel and the official [validation](https://laravel.com/docs/13.x/validation), [file storage](https://laravel.com/docs/13.x/filesystem) and [routing](https://laravel.com/docs/13.x/routing) documentation after Boost documentation search was unreachable.

## Git and Day 5 handoff

This checkpoint belongs to the local verified Day 4 commit; obtain its ID with `git log -1 --oneline`. No push or remote setup. All requested checks passed before committing. Recover through Git history or reviewed forward fixes; never reset databases to recover code.

**Planned Day 5:** confirm the admin brief, then implement authenticated/authorized review of pending submissions and protected photo access with explicit status transitions. Decide retention/contact handling and review workflow before exposing any approved public fields. Keep unreviewed contents and personal information private, preserve the coordinate caveat and separate community observations from historical research and compatibility claims.
