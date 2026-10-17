# AquaMap Laguna architecture

Updated 2026-10-17 for Day 4 against code, MySQL metadata, isolated Laravel tests and local Edge checks. Day 4 starts from `b3c55db` on `main`; all Day 1-3 records, relationships and migrations are preserved. Scientific provenance remains the verified Day 3 evidence set.

## System and request flow (Observed)

- PHP 8.4, Laravel 13.37, MySQL; Composer manifest/lock define backend dependencies. Laravel Boost is a development dependency installed as required by the original `AGENTS.md`; it generated agent guidelines, skills and MCP configuration.
- `public/index.php` enters `bootstrap/app.php`. `routes/web.php` redirects `/` to `GET /map`, handled by `MapController@index`. Public `GET /reports/create` and throttled `POST /reports` now use `PollutionReportController@create/store`. Laravel's `/up` health route remains configured in `bootstrap/app.php`. There is no public report listing/detail/download API or admin interface.
- `config/database.php` defines connections; the current default and connected schema were verified as `aquamap_laguna` on MySQL without recording credentials. Session and infrastructure tables exist. There are no queued domain jobs, domain events, or scheduled domain tasks.

## Data model (Observed)

| Entity/table | Relationships and constraints | Evidence |
| --- | --- | --- |
| `WaterBody` / `water_bodies` | Name, locality label, water type, decimal coordinates, optional description/source; has many fish links and pollution reports; belongs to many species | `app/Models/WaterBody.php`; `database/migrations/2026_10_17_053259_create_water_bodies_table.php` |
| `FishSpecies` / `fish_species` | Common/scientific names, water type, optional habitat text/source; belongs to many water bodies and has many links | `app/Models/FishSpecies.php`; `database/migrations/2026_10_17_053300_create_fish_species_table.php` |
| `WaterBodyFish` / `water_body_fish` | Explicit table override; belongs to water body and species; unique pair; both foreign keys cascade; status defaults to `insufficient_data`; nullable evidence type, sampling period and publication year | `app/Models/WaterBodyFish.php`; original link migration and `2026_10_17_065926_add_evidence_metadata_to_water_body_fish_table.php` |
| `PollutionReport` / `pollution_reports` | Belongs to water body; foreign key cascades; optional reporter/photo/coordinates; status defaults to `pending` | `app/Models/PollutionReport.php`; `database/migrations/2026_10_17_053302_create_pollution_reports_table.php` |

Many-to-many methods explicitly name the existing pivot table/keys and expose its status, explanation, provenance, study metadata and timestamps. `WaterBody` permits only its metadata fields for mass assignment and casts coordinates to decimal strings. Other domain models remain guarded; the fish seeder explicitly force-fills trusted constants. `WaterBodyFish` casts publication year to integer. Original foreign keys/cascades and unique pairs are preserved. The Day 3 migration only adds three nullable columns, without backfilling unknown evidence or changing old rows.

`User` and Laravel infrastructure tables remain separate from the domain: no user foreign key is declared on pollution reports. There is no water-quality measurement entity or table.

## Community reporting flow (Observed)

The shared `resources/views/partials/header.blade.php` and a map-page link lead to the reporting form. `PollutionReportController@create` selects only lake IDs/names/reference coordinates from existing `WaterBody` records; it never loads reports. `resources/views/reports/create.blade.php` uses the existing environmental theme, labeled fields, accessible error summaries, mobile stacking and no-store responses.

`StorePollutionReportRequest` validates an existing lake, a predefined observation type (`PollutionReport::TYPES`), description up to 5,000 characters, optional bounded coordinate pair, optional name/email and optional JPEG/PNG/WebP up to 5,120 KiB. Upload content and filename extension are checked. Standard web middleware provides CSRF/origin protection. `AppServiceProvider` defines the named IP limiter: five POST attempts per hour, including invalid submissions, with a friendly 429 and retry headers. Users sharing an internet address share the limit.

The controller explicitly assigns validated attributes to the guarded model and always sets `status = pending`; client status/path/ID/timestamps are ignored. Photos receive generated names on the dedicated `pollution-reports` disk, rooted outside `public`, with private visibility and no serving route. Contact attributes and photo paths are also hidden from model serialization. There are no report read/download routes. Hidden attributes are defense in depth, not authorization for future APIs.

A failed database save attempts to delete its newly stored photo and returns a generic error. Cleanup/storage failures log only generic context and exception class, without request contents or SQL bindings. If storage is unavailable, orphan cleanup can require operator intervention. Only scalar form fields are flashed on validation/save failure; Blade escapes them. Photos must be reselected. Success redirects to a generic pending confirmation without report IDs, contents, paths or contact details.

`resources/js/incident-map.js` initializes a separate conditional Leaflet map using database reference points. Lake selection centers the map without assigning or changing incident coordinates. Explicit map clicks/taps or the keyboard-accessible map-center button select an orange incident point; blue circles remain lake references. Clearing, optional omission and restoration after validation work independently. No device geolocation or incident accuracy claim is made. Coordinate datum/precision caveats remain visible on both maps; community observations do not become scientific measurements, suitability conclusions or confirmed pollution findings.

No migration, dependency, seed-data change or admin feature was needed. `scripts/serve-local.ps1` starts a local-only PHP server with process-local 5 MB upload / 8 MB POST limits; the machine's default PHP upload limit is 2 MB. Other servers must configure equivalent limits. Credentials/global PHP settings are untouched.

## Public map flow and browser boundary (Observed)

`MapController@index` queries `WaterBody` for Sampaloc, Pandin and Yambo with the project's San Pablo City locality label, ordered by name. The explicit response allowlist is `id`, `name`, `municipality`, `water_type`, `latitude`, `longitude`, `description`, `source_url`. Only those fields are selected and copied into arrays, excluding model appends, timestamps, and relationships. Blank descriptions become null; only valid HTTP(S) source URLs are passed through.

Blade renders escaped lake cards and an always-available reference section; `Js::encode` safely embeds the allowlisted records as JSON. `resources/js/app.js` parses them and creates Leaflet markers using database coordinates. Dynamic popup/panel text uses DOM `textContent`; safe research links open with `noopener noreferrer`. No dynamic database strings enter popup HTML. Selection synchronizes markers, sidebar `aria-pressed` state, live status and the detail panel. Reset derives bounds from loaded records; no geographic center is hardcoded. Invalid coordinates are not mapped, and empty data invents no fallback location.

`resources/css/app.css` supplies Tailwind v4 theme tokens and small Leaflet overrides. Vite bundles Leaflet 1.9.4 and its CSS locally, alongside the existing Instrument Sans font integration. `package-lock.json` locks the frontend installation. The only new runtime dependency is Leaflet; no UI framework, icon library or browser-testing package was added.

The browser loads HTTPS OpenStreetMap tiles with visible attribution, plus zoom/scale controls. No geolocation, spatial calculations, tile prefetch, offline caching or scientific inference is implemented. Network tile failures show a separate notice while lake information remains available. The map assumes WGS84 for display, with datum/accuracy limitations visible in the sidebar.

## Fish evidence flow (Observed)

The controller eagerly loads each scoped lake's `fishSpecies` in one additional query. It builds a separate `fishByLake` view array with six species fields (`id`, common/scientific names, water type, habitat requirements and source URL), plus occurrence URL, evidence type, sampling period, publication year and public compatibility status. No raw model relationships, private assessment explanation, or timestamps are rendered. Both reference URLs use the same HTTP(S) allowlist as lake sources.

`resources/views/map/fish-cards.blade.php` escapes these fields and distinguishes documented occurrence, general species habitat and potential compatibility. Evidence requires a recognized type plus a source, sampling period and publication year; incomplete legacy records are labeled incomplete. Missing associations do not imply absence. Cards are rendered in the non-JavaScript reference section and cloned from that escaped DOM into the selected-lake panel. The eight-field lake JSON contract and marker popup behavior are unchanged.

Current data is one Nile tilapia profile with three historical aquaculture links: July-September 2017 sampling, article year 2019. FAO general biology has its own species source, separate from the lake-specific study. All seeded links and all Day 3 public compatibility displays remain `insufficient_data`: there are no validated current lake measurements or assessment engine. Wild occurrence is supported as an evidence label, but no wild records were seeded.

Decision: reuse the unique lake/species pair for one representative occurrence study, with nullable type/month-level period/year rather than inventing exact collection days or adding a study-history subsystem. Multiple studies per pair remain a future design requirement. Primary research claims, access limitations and missing evidence are in `docs/RESEARCH_SOURCES.md`.

## Seeding and safety boundary (Observed)

`DatabaseSeeder` calls `WaterBodySeeder` followed by `FishSpeciesSeeder`; neither creates test users. Each seeder checks resolved connection configuration before connecting: MySQL database name must be exactly `aquamap_laguna`, then `SELECT DATABASE()` is checked on the write connection before writes. The only exception is SQLite `:memory:` while testing. Seeder reads use the verified write connection.

Lake seeding upserts three geographic metadata records in one transaction using `(name, municipality)` as identity. Fish seeding has a separate transaction, requires exactly one existing record per target lake, refuses ambiguous duplicate scientific names, and reuses the species by scientific name and links by their unique pair. It updates the seeded profile/evidence/status fields but leaves unrelated species and lake attributes alone. Day 3 live seeding ran only `FishSpeciesSeeder`, preserving lake IDs/timestamps and every attribute.

Repeat serial runs retain IDs and timestamps when values are unchanged. Neither lake identity nor species scientific name has a database unique constraint; run seeders serially. The existing pair constraint prevents duplicate links. No destructive migrations or resets were run. Reversing the new migration would drop study metadata, so routine rollback is not a data-safe recovery strategy; prefer reviewed forward fixes.

The locality field is a label, not a jurisdiction polygon. Yambo's description records San Pablo City, Rizal, and Nagcarlan. Published geographic points have an unconfirmed datum/accuracy and are not surveyed centroids. See `docs/RESEARCH_SOURCES.md`.

## Verification and boundaries (Observed / Unknown)

- `phpunit.xml` forces testing with SQLite `:memory:` and clears `DB_URL`; cache and sessions use arrays. `tests/TestCase.php` refuses persistent databases before test traits/setups run.
- Database feature tests run only forward `migrate` against a new in-memory database. They use no database refresh/reset traits or destructive migration commands. Synthetic fish/report fixtures exist only in isolated tests.
- Tests cover Day 1-3 plus reporting persistence/relationships, anonymous/optional fields, allowed image formats/size boundaries, unsafe content/extensions, validation shapes/lengths, paired coordinates, server-controlled status, privacy, scalar-only escaped old input, rate limits/expiry/address separation and failed-save photo cleanup. The Day 4 suite passes 76 tests / 615 assertions. Laravel tests stub Vite; real asset behavior is checked in Edge.
- Day 3 persistent writes were limited to the new nullable-column migration and verified fish metadata seeding on `aquamap_laguna`. Map queries are read-only apart from normal HTTP sessions. Browser verification checks configured/connected schema names before navigation and hashes water-body, fish-species and link records before/after. No credentials are written or documented.
- `tests/browser/map-smoke.mjs` uses Node built-ins and an installed Edge/Chromium browser in a separate headless profile. It starts/closes only its own local PHP server/browser, checks desktop and mobile interactions, captures screenshots under ignored storage, and removes its temporary profile. Browser ports are dynamically allocated. `AQUAMAP_BROWSER` optionally selects an installed Chromium executable.
- Vite assets were built and desktop/mobile screenshots inspected. `node tests/browser/map-smoke.mjs --fish` adds fish-card/source/date/status checks to all map regressions, with results/screenshots under ignored `storage/app/day3-browser/`. No production deployment was performed.
- `node tests/browser/reports-smoke.mjs` uses an isolated profile, temporary SQLite file and private photo/session/cache directories under ignored `storage/app/day4-browser/fixture-*`. The test-only router `tests/browser/report-fixture.php` applies isolation before providers resolve the cache/limiter; it is not mounted by application routes. Existing seeders run only in memory, then their records are copied into the new disposable file through forward migrations/inserts. Real CSRF middleware stays active. Desktop/mobile checks verify errors, map selection/clear, 3 MB uploads, photo reselection and pending confirmation. Two synthetic reports/photos exist only in the fixture, which is removed afterward. Before/after hashes verify unchanged real MySQL records and shared application cache. PHPUnit retains its strict in-memory guard.
- Scientific measurements, current water quality, habitat suitability, legal boundary geometry, and production deployment configuration remain unknown/unimplemented.
