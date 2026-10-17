# Day 3 checkpoint

**2026-10-17 - Complete for the requested Day 3 scope.** Baseline: `0cc06e7` on `main`. Day 1 lake data and original migrations, Day 2 GIS interactions, and the visible coordinate caveat are preserved. No Day 4/5 features are implemented.

## Implemented

- One sourced profile: Nile tilapia (`Oreochromis niloticus`), with general FAO habitat information and a separate species reference.
- Three lake associations: Sampaloc, Pandin and Yambo, classified as historical **aquaculture**, sampled **July-September 2017**, article published **2019**. No wild-occurrence records were added. This is a limited evidence set, not a complete fish inventory.
- `FishSpeciesSeeder` imports trusted metadata in one transaction, reuses species/link identities, refuses missing/ambiguous target lakes and duplicate species identities, and independently verifies the configured and connected `aquamap_laguna` database. Serial repeat runs preserve IDs/timestamps and unrelated records.
- The existing `water_body_fish` table gains only nullable `evidence_type`, `sampling_period`, and `publication_year` columns. Original rows are preserved as unknown. Month-level sampling text avoids inventing exact collection days.
- Selecting a lake/marker on `/map` now displays responsive fish cards with common/scientific names, general habitat, study period/year and two distinct references. Documented occurrence and potential habitat compatibility have separate sections. All seeded links and public compatibility displays remain `insufficient_data`; no assessment engine, predictive score or measurement is present.
- Missing/unsafe sources, missing habitat text, incomplete evidence and absent links have honest fallbacks. No link does not establish absence. Escaped server-rendered cards are cloned into the selection panel; the existing eight-field lake JSON contract is unchanged. No new dependencies or endpoints were added.

## Research and limitations

[RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) records exact references, claims and access limits. The UPLB and publisher abstracts explicitly support cultured Nile tilapia collected in each target lake. Publisher metadata establishes 27 July 2019 publication; the repository's recommended citation year is 2021, which is not used as the study year. FAO 2009 supplies general biology, including a preferred 31-36 degrees Celsius range; this is not a lake threshold or lake measurement. FAO 2013 supports the freshwater aquaculture context.

FishBase returned 403; its numerical claims were not used. The supplied Philippine Journal of Science reference was reviewed only through indexed publisher abstract metadata because direct access was restricted; it provides aquaculture-management background, not additional lake/species claims. The occurrence paper's full body is subscription content; only public abstract/metadata were reviewed.

Still missing: present lake observations with dates, station/depth/method/units, complete species/life-stage requirements, additional species/wild evidence, and authoritative geographic datum/precision. These limitations remain visible; historical aquaculture does not establish current presence, wild populations, safety or suitability.

## Verification

| Check | Result |
| --- | --- |
| `php artisan test --compact` | PASS: 43 tests, 329 assertions, 0 failures; isolated SQLite `:memory:` |
| New fish tests | PASS: identities, reciprocal links, source/date/type/status metadata, root wiring, repeatability across time, refused targets/duplicates/missing lakes, transaction rollback |
| Forward-upgrade test | PASS: applied original migrations, inserted a legacy fixture, then added nullable columns without losing original attributes/records; no resets/rollback commands |
| Map safety/integration tests | PASS: scoped fish associations, exact public fields, incomplete/missing data, suppressed unsafe URLs, HTML escaping, no unsupported compatibility promotion |
| Pint | PASS: `--dirty` plus explicit new PHP paths |
| Production build | PASS: `npm.cmd run build` |
| JS / manifest / routes | PASS: Node syntax checks for app/browser script, Composer validation, existing public map/home routes |
| Edge desktop 1440 x 1100 | PASS: all three lake selections show fish, historical period/type, both references and insufficient data; marker selection, zoom, keyboard panning and reset preserved |
| Edge mobile 390 x 844 | PASS: touch selection, readable stacked fish card, popup, references, reset and no horizontal overflow |
| Browser integrity | PASS: zero JS exceptions; lake/species/link record hashes unchanged by browser tests; temporary browser/profile/server closed |
| MySQL deployment | PASS: verified `aquamap_laguna`, applied only the new nullable-column migration, seeded only fish data twice, confirmed 1 species/3 links, stable fish IDs/timestamps, unchanged lake attributes and unrelated counts |
| `code-reviewer` | No actionable defects found in the Day 3 diff/new files; reviewed schema preservation, transaction/target guards, evidence separation, field allowlists, sources, escaping and GIS regression checks |

Run browser checks with `node tests/browser/map-smoke.mjs --fish` (installed Edge/Chromium, Node built-ins, no packages). Existing Day 2 mode still works without `--fish`. Results and inspected screenshots are under ignored `storage/app/day3-browser/`: `results.json`, `desktop-fish.png`, `mobile-fish.png`, plus overview screenshots. This is local browser/mobile emulation, not physical-device certification.

## Files changed

- Added: `database/migrations/2026_10_17_065926_add_evidence_metadata_to_water_body_fish_table.php`, `database/seeders/FishSpeciesSeeder.php`, `resources/views/map/fish-cards.blade.php`, `tests/Feature/{FishSpeciesSeederTest,FishMapTest,FishEvidenceMigrationTest}.php`.
- Updated: `app/Http/Controllers/MapController.php`, `app/Models/{WaterBody,FishSpecies,WaterBodyFish}.php`, `database/seeders/DatabaseSeeder.php`, `resources/js/app.js`, `resources/views/map/index.blade.php`, `tests/browser/map-smoke.mjs`.
- Adjusted `WaterBodySeederTest` to test the lake-only seeder directly; the root seeder now includes fish and is separately tested. Original lake assertions are preserved.
- Updated memory: this checkpoint, `docs/PROJECT_CONTEXT.md`, `docs/RESEARCH_SOURCES.md`, `.agents/architecture/architecture.md`, `AGENTS.md`. Generated assets remain ignored. Composer/npm manifests and lockfiles are unchanged.

## Git and safety

This document belongs to the local Day 3 checkpoint after successful checks; use `git log -1 --oneline` for its ID. No automatic push or remote setup. Credentials and local/runtime databases remain excluded. No credentials were read into output or changed. Persistent writes were limited to the verified evidence migration and fish seeding; no lake reseeding or destructive commands were used. Ordinary browser requests may write Laravel sessions.

For another installation, apply forward migrations before serving the new model relationships, then run the root seeder or the fish seeder after the three lakes exist. The evidence migration's `down()` would drop study metadata; do not use routine rollback/reset as a data-safe recovery method. Lake/species identities lack individual unique indexes: keep seed runs serial; pair uniqueness is enforced. One representative study per pair is supported; multiple-study history needs a future design.

## Planned Day 4 handoff

Confirm the pollution-reporting brief before implementation. Build on the existing `PollutionReport` model with real submissions, validation, privacy for reporter details, and clear pending-review behavior. Keep historical research separate from actual incidents. Preserve fish-source distinctions, the unassessed compatibility state and visible coordinate caveat. Admin features remain outside this checkpoint and reserved for a later request.
