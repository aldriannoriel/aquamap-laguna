# Day 2 checkpoint

**2026-10-17 - Complete for the requested Day 2 scope.** Day 1 schema, models, relationships, seeders and live lake records are preserved. No Day 3-5 features are implemented.

## Implemented

- Public `GET /map` (`map.index`) renders the responsive lake explorer; `/` redirects there. `MapController@index` reads Sampaloc, Pandin and Yambo through `WaterBody`, restricted to the existing San Pablo City project locality label.
- The browser receives only `id`, `name`, `municipality`, `water_type`, `latitude`, `longitude`, `description`, and `source_url`. No timestamps, reporter information or model relationships are exposed.
- Leaflet 1.9.4 with OpenStreetMap tiles, visible attribution, keyboard/pointer controls, zoom buttons, panning, scale, linked markers/sidebar selections, full lake details and a Show all lakes reset derived from database coordinates.
- Tailwind v4 environmental dashboard with Instrument Sans, teal/water/mist palette, desktop sidebar and mobile map-first layout. Selection state, focus styling, live status, safe research links, reduced-motion behavior, and non-JavaScript reference details are present.
- Missing descriptions/sources have explicit fallbacks. Unsafe URL schemes are suppressed. Empty/invalid locations produce no invented map center. Tile failure notices preserve access to lake information.
- Published points are displayed with an explicit WGS84 assumption and datum/precision/boundary limitations. No lake coordinates, environmental measurements or suitability results are hardcoded into the map implementation.

## Final checks

| Check | Result |
| --- | --- |
| `php artisan test --compact` | PASS: 25 tests, 152 assertions, 0 failures; SQLite `:memory:` only |
| Map feature tests | PASS: public route, exact serialized fields, database values/scoping, missing/unsafe optional data, HTML/JSON escaping, empty state |
| Existing Day 1 tests | PASS: seed safety, idempotency, transactions, relationships, constraints and health behavior |
| Pint on four changed PHP files | PASS; explicit file paths were used before Git initialization |
| `npm.cmd run build` | PASS: production JS/CSS and local fonts generated; no optional font package added |
| JS syntax / Composer manifest | PASS: `node --check` for app/browser script; `composer validate --no-check-publish` |
| Installed Edge, desktop 1440 x 1100 | PASS: three live markers/cards, loaded OSM tiles, all sidebar selections, pointer marker click, popup/details/source synchronization, zoom, keyboard panning, reset, no horizontal overflow |
| Installed Edge, mobile 390 x 844 | PASS: stacked map-first layout, touch selection, visible popup title, reset and no horizontal overflow |
| Browser runtime / database integrity | PASS: zero JS exceptions; all live lake attributes, IDs and timestamps unchanged |
| Final `code-reviewer` inspection | No actionable defects found in Day 2 changes; traced route/query, public fields, JSON/HTML/DOM escaping, links, map interactions and responsive popup behavior |

Browser smoke script: `npm.cmd run test:browser` (Node built-ins plus an installed Chromium browser; no test packages/downloads). It verifies the configured and connected `aquamap_laguna` target before navigation, compares lake-record hashes afterward, and starts/stops only its own PHP server and separate headless browser/profile. Set `AQUAMAP_BROWSER` to an installed Chromium executable if Edge is not in its usual Windows location. Ports are dynamically allocated; no `.env` changes are needed.

Browser results and inspected screenshots are under ignored `storage/app/day2-browser/`: `results.json`, `desktop.png`, and `mobile.png`. Temporary browser profiles were removed; no preview server is left running. Browser coverage is local Edge with mobile emulation, not physical-device or cross-browser certification.

## Files created or modified

- Created: `app/Http/Controllers/MapController.php`, `resources/views/map/index.blade.php`, `tests/Feature/MapTest.php`, `tests/browser/map-smoke.mjs`, `package-lock.json`.
- Modified: `routes/web.php`, `resources/js/app.js`, `resources/css/app.css`, `package.json`, `vite.config.js`, `tests/Feature/ExampleTest.php` (home redirect expectation).
- Updated memory: `AGENTS.md`, `docs/PROJECT_CONTEXT.md`, `docs/RESEARCH_SOURCES.md`, this checkpoint, and `.agents/architecture/architecture.md`. `docs/ARCHITECTURE.md` remains the pointer to the current map.
- Generated local assets: ignored `public/build/`; installed existing frontend tooling plus the requested Leaflet runtime dependency. No Composer dependency changes were needed for Day 2.

No destructive database commands, persistent migrations, lake reseeding, or credential edits were performed. MySQL lake queries were read-only; ordinary browser requests may create Laravel session records.

## Git checkpoint

Git was initialized locally on branch `main` after Day 2. The initial checkpoint contains the completed source, tests, migrations/seeders, dependency lockfiles and project documentation. Environment files and variants are ignored except the reviewed template `.env.example`; dependencies, runtime storage, local databases and generated build files are excluded by project ignore rules. Use `git log -1 --oneline` to identify this checkpoint. No remote backup is configured.

The map's open data-notes panel still discloses the unconfirmed datum/precision and WGS84 display assumption. Preserve that visible limitation until authoritative verification is documented; no application or geographic data changes were needed for this checkpoint.

## Known limits and Day 3 handoff

1. Confirm the Day 3 brief before building more features. No domain CRUD/API, fish browser, pollution submission, measurement ingestion or suitability engine exists yet.
2. Geographic datum/accuracy and official boundary geometry remain unverified. WGS84 is an explicitly disclosed display assumption; Yambo's full description preserves San Pablo City, Rizal and Nagcarlan. Do not use these markers as surveyed centroids or legal boundaries.
3. Current scientific measurements remain absent. Obtain sourced, dated lake observations and species/life-stage habitat requirements before assessments; retain `insufficient_data` when evidence is missing.
4. OSM tiles require network/service availability. Initial font builds may also need access to the existing Bunny font provider; runtime fonts and Leaflet assets are local. The sandbox initially blocked font retrieval; the authorized network-enabled build succeeded.
5. The existing lake seed identity has no unique constraint; keep any future seed runs serial. No new Day 2 schema change is required.

See [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) for data provenance and [ARCHITECTURE.md](ARCHITECTURE.md) for implemented architecture.
