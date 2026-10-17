# GIS expansion checkpoint

**2026-10-17. Implemented and imported: a verified-first subset of the requested catalogue.** Baseline: `0c55ea9` on `main`. Days 1-5 and free anonymous anti-spam are preserved. Full provincial coverage and public deployment remain incomplete.

## Implemented

- `/map` now explores lakes, rivers, waterfalls and hot/natural springs, with searchable category filters, live counts, alternate-name/locality search, empty states, keyboard selection, zoom/pan/reset and responsive profiles. Mobile controls include a jump to the map.
- Eleven database profiles: Sampaloc, Pandin, Yambo, Bunot and Mohicap lakes; selected Bumbungan/Pagsanjan and Balanac river reaches; Pagsanjan/Cavinti, Hulugan and Talay falls; one Al Fresco Springs resort reference.
- Four lake polygons, two sets of river reaches and five point features. Pandin uses an OSM shoreline node because its complete polygon was unavailable. Al Fresco locates the resort, not a spring source. OSM geometry and owner-published resort GeoCoordinates are WGS84; source-checking is not survey certification.
- Eight additive metadata columns preserve original lake IDs, coordinates, descriptions, source URLs and relationships. `ExplorerSeeder` explicitly imports the reviewed `database/data/explorer.json`, refuses ambiguous identities/conflicting metadata, and is idempotent on SQLite and MySQL. Root seeders remain unchanged.
- Details show geography/accuracy notes, classification, existing historical fish evidence, source titles/publishers/publication dates or missing-date text, official website when present, photo attribution/placeholder, and pollution context. No new fish occurrence, suitability results or measurements are invented.
- Public pollution disclosure is restricted to aggregate counts of **currently verified** reports; pending/rejected/resolved reports, individual contents and all personal/evidence/audit information remain private. Counts are not scientific measurements, pollution percentages or safety ratings.
- Public reporting supports all existing water bodies. Its orientation markers now use only source-checked geometry. Selection still never assigns an incident point. CSRF, private uploads, server-assigned pending status, 5 attempts/hour/IP, honeypot, encrypted timer and private advisory repeat warnings are preserved.
- No permitted photo asset could be downloaded, so every live profile displays a placeholder. Bunot photo permission/credit is documented without pretending that a missing asset is displayed.

## Verification

- Focused expansion tests: 22 passed, including idempotency, original data preservation, malformed/unverified geometry, future/invalid dates, alias ambiguity, source/image handling, private counts and reporting integration.
- Full isolated suite: **167 tests / 1,353 assertions / zero failures**. Pint dirty and explicit new PHP paths, production Vite build, Composer validation, Node syntax checks and Git whitespace checks passed. Final code-reviewer review traced data/source projection, escaping, photo confinement, aggregate privacy, import conflicts/transactions, migration safety and regression coverage; no remaining actionable defects found in the changed scope.
- Installed Edge desktop (1440 x 1100) and mobile (390 x 844): map selections for all eleven profiles, category counts, alias search, empty state, layer removal, zoom/pan/reset, keyboard shape selection and existing fish labels passed; zero JavaScript exceptions. Screenshots inspected.
- Existing reporting, anti-spam and admin browser suites passed: validation, 3 MB photos, incident selection/clear, server timing, advisory repeats, rate limit, authentication/authorization, filtering, private photo streaming, transitions, audit and logout. Synthetic accounts/reports use confined disposable SQLite/storage only.
- Artifacts: `storage/app/expansion-browser/`, `storage/app/day4-browser/` (including `spam-*`) and `storage/app/day5-browser/`. Browser snapshots verified unchanged real MySQL domain records and shared cache. Temporary browser profiles/servers/fixtures were removed. Device emulation is not physical-device certification.
- Configured and connected `aquamap_laguna` verified before persistence. Only the additive explorer migration ran. Import ran twice with identical second-run hashes. Original three lake attributes and existing fish/links/reports/users/reviews were preserved. Database now has 11 water bodies, one fish species, three fish links and zero reports/users/reviews.

## Changed files

- Added `app/Support/ExplorerProfile.php`, explorer metadata migration, `database/seeders/ExplorerSeeder.php`, `database/data/explorer.json`, `resources/views/map/profile.blade.php`, `tests/Feature/ExplorerTest.php`.
- Updated map/report controllers, reporting request copy, WaterBody casts/categories, map/report/header views, map/incident JavaScript, CSS, map/report feature tests, browser map/report runners and isolated fixtures.
- Updated research inventory, privacy policy, this checkpoint, project context, architecture memory and AGENTS guidance. No dependency, credential, authentication, audit-transition or anti-spam configuration change.

## Limitations and next steps

- Research/import remaining requested locations and four withheld resorts. The exact backlog, sources, OSM revision dates, access limits and photo permission are in `docs/RESEARCH_SOURCES.md`. Hidden Valley source quality and inconsistent Tirta metadata prevented publication. No rate limit/access restriction was bypassed.
- Source points/shapes retain unconfirmed positional precision. Original journal coordinate datum remains unresolved and visibly qualified. River coverage is partial, Pandin is a shoreline reference, municipal strings are not legal boundaries, and no independent Google visual comparison or NAMRIA survey verification was completed.
- Current water-quality assessment, additional fish evidence and independent spring-source/chemistry verification are missing. No suitability engine, scientific measurement ingestion or pollution percentage exists.
- Provision real administrators explicitly with `php artisan admin:create`; no default account/password exists. Local 5 MB upload launcher: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/serve-local.ps1`. Deployment remains unperformed.

## Recovery

This document belongs to the local GIS expansion Git checkpoint; use `git log -1 --oneline` for its commit ID. No push. For a fresh intended database, apply forward migrations, seed the original lake/fish data if absent, then run `php artisan db:seed --class=ExplorerSeeder --no-interaction` serially. Existing installations must not rerun original seeders casually because they update lake values. Back up runtime data privately through an owner-approved process; Git is not a report/photo backup. Never reset, wipe or roll back populated metadata. Prefer reviewed forward fixes. Conflicting metadata or aliases require manual review, not forced overwrite.
