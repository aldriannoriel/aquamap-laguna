# AquaMap Laguna architecture

Updated 2026-10-17 for Day 2 against this project's code, installed packages, isolated Laravel tests and local Edge browser checks. Day 1 schema/model relationships and seed records are preserved.

## System and request flow (Observed)

- PHP 8.4, Laravel 13.37, MySQL; Composer manifest/lock define backend dependencies. Laravel Boost is a development dependency installed as required by the original `AGENTS.md`; it generated agent guidelines, skills and MCP configuration.
- `public/index.php` enters `bootstrap/app.php`. `routes/web.php` redirects `/` to the public named `GET /map` route, handled by `MapController@index` and `resources/views/map/index.blade.php`. Laravel's `/up` health route remains configured in `bootstrap/app.php`. There are no domain APIs or write routes.
- `config/database.php` defines connections; the current default and connected schema were verified as `aquamap_laguna` on MySQL without recording credentials. Session and infrastructure tables exist. There are no queued domain jobs, domain events, or scheduled domain tasks.

## Data model (Observed)

| Entity/table | Relationships and constraints | Evidence |
| --- | --- | --- |
| `WaterBody` / `water_bodies` | Name, locality label, water type, decimal coordinates, optional description/source; has many fish links and pollution reports; belongs to many species | `app/Models/WaterBody.php`; `database/migrations/2026_10_17_053259_create_water_bodies_table.php` |
| `FishSpecies` / `fish_species` | Common/scientific names, water type, optional habitat text/source; belongs to many water bodies and has many links | `app/Models/FishSpecies.php`; `database/migrations/2026_10_17_053300_create_fish_species_table.php` |
| `WaterBodyFish` / `water_body_fish` | Explicit table override; belongs to water body and species; unique pair; both foreign keys cascade; status defaults to `insufficient_data` | `app/Models/WaterBodyFish.php`; `database/migrations/2026_10_17_053301_create_water_body_fish_table.php` |
| `PollutionReport` / `pollution_reports` | Belongs to water body; foreign key cascades; optional reporter/photo/coordinates; status defaults to `pending` | `app/Models/PollutionReport.php`; `database/migrations/2026_10_17_053302_create_pollution_reports_table.php` |

Many-to-many methods explicitly name the existing pivot table/keys and expose its status, explanation, provenance and timestamps. `WaterBody` permits only its metadata fields for mass assignment and casts coordinates to decimal strings. Other domain models retain guarded mass assignment. Relationships and all three domain foreign keys, cascades, and the pair index were checked against live MySQL metadata. No schema changes were needed.

`User` and Laravel infrastructure tables remain separate from the domain: no user foreign key is declared on pollution reports. There is no water-quality measurement entity or table.

## Public map flow and browser boundary (Observed)

`MapController@index` queries `WaterBody` for Sampaloc, Pandin and Yambo with the project's San Pablo City locality label, ordered by name. The explicit response allowlist is `id`, `name`, `municipality`, `water_type`, `latitude`, `longitude`, `description`, `source_url`. Only those fields are selected and copied into arrays, excluding model appends, timestamps, and relationships. Blank descriptions become null; only valid HTTP(S) source URLs are passed through.

Blade renders escaped lake cards and an always-available reference section; `Js::encode` safely embeds the allowlisted records as JSON. `resources/js/app.js` parses them and creates Leaflet markers using database coordinates. Dynamic popup/panel text uses DOM `textContent`; safe research links open with `noopener noreferrer`. No dynamic database strings enter popup HTML. Selection synchronizes markers, sidebar `aria-pressed` state, live status and the detail panel. Reset derives bounds from loaded records; no geographic center is hardcoded. Invalid coordinates are not mapped, and empty data invents no fallback location.

`resources/css/app.css` supplies Tailwind v4 theme tokens and small Leaflet overrides. Vite bundles Leaflet 1.9.4 and its CSS locally, alongside the existing Instrument Sans font integration. `package-lock.json` locks the frontend installation. The only new runtime dependency is Leaflet; no UI framework, icon library or browser-testing package was added.

The browser loads HTTPS OpenStreetMap tiles with visible attribution, plus zoom/scale controls. No geolocation, spatial calculations, tile prefetch, offline caching or scientific inference is implemented. Network tile failures show a separate notice while lake information remains available. The map assumes WGS84 for display, with datum/accuracy limitations visible in the sidebar.

## Seeding and safety boundary (Observed)

`DatabaseSeeder` calls only `WaterBodySeeder`, replacing the skeleton's test-user insertion. The lake seeder checks the resolved connection configuration before connecting: MySQL database name must be exactly `aquamap_laguna`. It then checks `SELECT DATABASE()` on the write connection before writes. The only exception is SQLite `:memory:` while the application is testing. Reads during seeding use that same write connection.

Three sourced lake metadata records are upserted in one transaction using `(name, municipality)` as identity. Repeat serial runs retain IDs and timestamps when values are unchanged. Other tables are not seeded. The existing schema has no unique index on this lake identity: simultaneous seeders or manual duplicate rows are not prevented by a database constraint. Run seeders serially; no new migration was added for this Day 1 task.

The locality field is a label, not a jurisdiction polygon. Yambo's description records San Pablo City, Rizal, and Nagcarlan. Published geographic points have an unconfirmed datum/accuracy and are not surveyed centroids. See `docs/RESEARCH_SOURCES.md`.

## Verification and boundaries (Observed / Unknown)

- `phpunit.xml` forces testing with SQLite `:memory:` and clears `DB_URL`; cache and sessions use arrays. `tests/TestCase.php` refuses persistent databases before test traits/setups run.
- Database feature tests run only forward `migrate` against a new in-memory database. They use no database refresh/reset traits or destructive migration commands. Synthetic fish/report fixtures exist only in isolated tests.
- Tests cover Day 1 seed safety/relationships plus the public map route, exact JSON field exposure, database sourcing/scoping, missing/unsafe optional data, escaping, and empty data. Laravel tests stub Vite; real asset behavior is checked in Edge.
- Day 2 MySQL checks and map queries are read-only apart from normal HTTP session activity. Browser verification confirms both configured and connected schema names before navigation and compares lake-record hashes afterward. No credentials are written or documented.
- `tests/browser/map-smoke.mjs` uses Node built-ins and an installed Edge/Chromium browser in a separate headless profile. It starts/closes only its own local PHP server/browser, checks desktop and mobile interactions, captures screenshots under ignored storage, and removes its temporary profile. Browser ports are dynamically allocated. `AQUAMAP_BROWSER` optionally selects an installed Chromium executable.
- Vite production assets were built and desktop/mobile screenshots inspected. OpenStreetMap tiles, selection, zoom, keyboard panning, reset, mobile touch input and layout were verified. No production deployment was performed.
- Scientific measurements, current water quality, habitat suitability, legal boundary geometry, and production deployment configuration remain unknown/unimplemented.
