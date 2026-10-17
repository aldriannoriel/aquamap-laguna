# GIS expansion checkpoint

**2026-10-17. Recovered from commit `5c3d69e` plus its preserved uncommitted work.** See [EXPANSION_PROGRESS.md](EXPANSION_PROGRESS.md) for the recovery inventory and verification status. No push.

## Complete

- Days 1-5: sourced lake/fish information, public explorer, anonymous private reporting, administrator-only review/audit and local anti-spam. No public registration or default administrator exists.
- Expanded catalogue imported explicitly and serially: **28 profiles** (10 lakes, 5 rivers, 11 waterfalls, 2 spring-associated resort references). Both JSON catalogue files are retained; original IDs, attributes, metadata and fish links are preserved.
- Added profiles cover Laguna de Bay, Calibato, Palakpakin, Caliraya, Lumot; selected Santa Cruz/San Pedro/Tunasan reaches; Taytay, Bunga Twin, Buntot Palos, Aliw, Buruwisan, Lanzones, Bukal and Dampalit falls; and a qualified historical Hidden Valley property-edge point.
- Detailed profile dialog and eight locally bundled attributed images recovered intact. Profiles retain classification, sources, positional limits, historical fish evidence and explicit unknown scientific assessment. Search/category counts include only source-checked map locations; all research profiles remain available below.
- Configured and connected database verified as `aquamap_laguna`. Existing migrations were inspected and already applied; **no migrations were run during recovery**. Import repeat preserved every value/timestamp. Unrelated fish/report/user/review/cache/migration records stayed unchanged.
- Fixed a live MySQL failure exposed by larger geometry: map and reporting-location queries sort the small retrieved catalogue in memory instead of sorting geometry JSON in MySQL. No schema, dependency, credential or server-setting change.

## Verification

- Final complete isolated suite: **170 tests / 1,637 assertions**, passing after both query fixes. Focused map/catalogue and reporting/anti-spam checks also pass; details are in EXPANSION_PROGRESS.
- Geometry compared to saved OSM node/segment data, preserving all lake island holes and selected river segments; Hidden Valley node checked against its 2014 outline. Eight photo permissions checked against saved Commons metadata. Research inventory updated.
- Vite production build, JavaScript syntax, Pint, Composer validation and Git whitespace checks pass. Final code review found no remaining actionable defects in this scope.
- Final isolated Edge desktop/mobile browser run passes with zero JavaScript exceptions: all 28 profiles, image loading/credits, modal focus/close/Escape/focus return, filters/counts/search, keyboard geometry, fish evidence and responsive layout. Live reporting GET loads all 28 choices without submission. Domain records, users, audits, migrations and shared database/file cache stayed unchanged. Screenshots inspected; emulation is not physical-device certification.
- Recovery copies and integrity artifacts: ignored `storage/app/expansion-recovery/`. Saved `storage/app/inventory-*` and earlier expansion/browser evidence remain intact. These local artifacts are not a private runtime backup.

## Outstanding

- Dolcezza, Tirta and Stellaluna remain withheld for incomplete or inconsistent property-specific evidence. No additional source verification should be invented to fill the catalogue.
- Pandin still uses a source shoreline node; its complete polygon is missing. River lines cover selected reaches, not entire rivers. Municipal labels and resort property references are not legal boundaries or actual spring-source positions.
- Original journal-point datum/precision remains unconfirmed and visibly qualified. Independent survey accuracy, Google visual comparison, spring chemistry/source verification and current water-quality science remain missing. No suitability engine, measurements or pollution percentages exist.
- Day 6 integration/demo brief and deployment remain unperformed. Provision real administrators explicitly with `php artisan admin:create`; no real account exists. Local upload-capable server: `scripts/serve-local.ps1`.

## Safety and recovery

Reports always start server-assigned pending. Individual contents, contacts, photos and audit history remain private; only counts of currently verified reports are public. Five attempts/hour/IP, honeypot, encrypted session-bound server timer, private advisory repeats and atomic terminal-state review rules remain intact. Retention is manual; no automatic deletion is authorized.

Use reviewed forward fixes. Never reset, roll back populated metadata, rerun original seeders casually, overwrite conflicts or create duplicate aliases. A fresh intended database can follow the documented original lake/fish setup and explicit ExplorerSeeder import; this installation already has the current catalogue. Scientific provenance and remaining gaps are in [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md).
