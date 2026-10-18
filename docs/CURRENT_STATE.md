# GIS expansion checkpoint

**2026-10-18. Photo completion built on `dc8366a` on `main`.** See [EXPANSION_PROGRESS.md](EXPANSION_PROGRESS.md) for recovery history and [IMAGE_INVENTORY.md](IMAGE_INVENTORY.md) for all 28 photo outcomes. No push.

## Complete

- Days 1-5: sourced lake/fish information, public explorer, anonymous private reporting, administrator-only review/audit and local anti-spam. No public registration or default administrator exists.
- Expanded catalogue imported explicitly and serially: **28 profiles** (10 lakes, 5 rivers, 11 waterfalls, 2 spring-associated resort references). Both JSON catalogue files are retained; original IDs, attributes, metadata and fish links are preserved.
- Added profiles cover Laguna de Bay, Calibato, Palakpakin, Caliraya, Lumot; selected Santa Cruz/San Pedro/Tunasan reaches; Taytay, Bunga Twin, Buntot Palos, Aliw, Buruwisan, Lanzones, Bukal and Dampalit falls; and a qualified historical Hidden Valley property-edge point.
- Compact native profile dialog: desktop width is half the map, clamped to 352-608 px and centered over it; mobile uses 8 px screen margins. Height is at most 640 px and always fits the viewport. Header, close control and segmented tabs stay visible while content scrolls.
- Five tabs: Overview (reset on every selection), Water Classification, Fish & Habitat, Pollution, Research & Sources. Existing fish cards, source metadata and accuracy limits are reused. Pollution separates verified community counts from unknown science; individual summaries remain private. All reference sections remain readable without JavaScript.
- **18 local images / ten placeholders:** eight original files and attribution records preserved, ten verified reusable Commons photographs added. Each new image has author, capture date, original/permission links and a resizing notice. New progressive JPEGs have a maximum 960-pixel edge (102,468-238,645 bytes each); portrait Hulugan/Bukal images fit without cropping. The read-only `database/data/explorer-images.json` supplement matches exact canonical name and municipality only when the database image is null. No migration, database import, original catalogue or scientific-data change was needed.
- Tabs use linked ARIA roles, one keyboard tab stop, Left/Right with wrapping, Home/End, focusable panels and native button activation. Native dialog focus handling/Escape/return-to-opener remain intact. Search, geometry selection and reporting are preserved; schema, seeders, coordinates, catalogue, research records and dependencies are unchanged.
- Configured and connected database verified as `aquamap_laguna`. Existing migrations were inspected and already applied; **no migrations were run during recovery**. Import repeat preserved every value/timestamp. Unrelated fish/report/user/review/cache/migration records stayed unchanged.
- Fixed a live MySQL failure exposed by larger geometry: map and reporting-location queries sort the small retrieved catalogue in memory instead of sorting geometry JSON in MySQL. No schema, dependency, credential or server-setting change.

## Verification

- Final complete isolated suite: **174 tests / 1,808 assertions** using SQLite `:memory:`. Focused photo/profile suite: **7 / 404**, including original metadata precedence, exact-name/locality matching, unchanged stored records, optimized assets, allowlisted portrait fit and placeholders. Existing classification/fish/privacy/reporting/admin/anti-spam coverage passed.
- Earlier geometry checks preserved all lake island holes and selected river segments; Hidden Valley node was checked against its 2014 outline. Original eight photo SHA-256 hashes rechecked unchanged; ten new image identities/licenses checked against Commons pages/metadata and visual inspection. Full photo inventory includes source links, photographers, licenses, historical dates, optimization and ten unresolved gaps.
- Vite production build, JavaScript syntax, Pint, Composer validation and Git whitespace checks pass. Final code review found no remaining actionable defects in this scope.
- Five panels, default Overview, stable selection/document, linked accessibility-tree roles, keyboard controls and wheel/content scrolling remain verified. Screenshots of new landscape/portrait photos and placeholders inspected at desktop/mobile sizes. Actual assistive-technology and physical-device testing remain unperformed. Boost had no browser log file; the isolated Edge runner recorded zero JavaScript exceptions directly.
- Photo-completion Edge run passed for **all 28 profiles on desktop and 390px mobile**: 18 images load with exact paths/alt/author/license/source links and responsive sizing; ten explicit placeholders; two portraits use contain. All prior tab/map/focus/scroll regressions and 1024/1440/1920px desktop, 320/390px portrait and 640px landscape checks passed, with zero JavaScript exceptions. No reports submitted; before/after hashes confirmed unchanged domain records, users, audits, migrations and shared database/file cache.
- Current browser/research artifacts: ignored `storage/app/photo-completion/`. Earlier `storage/app/tabbed-profile-browser/`, recovery copies/integrity artifacts under `storage/app/expansion-recovery/`, saved `storage/app/inventory-*`, photographs and screenshots remain intact. These local artifacts are not a private runtime backup.

## Outstanding

- Ten photo placeholders remain: Palakpakin Lake, San Pedro River, Talay, Bunga Twin, Buntot Palos, Aliw, Lanzones and Dampalit falls, Al Fresco Springs Resort and Hidden Valley Springs. Identity or photo-specific reuse permission could not be established; see the inventory before further research. Official website availability is not permission.
- Dolcezza, Tirta and Stellaluna remain withheld for incomplete or inconsistent property-specific evidence. No additional source verification should be invented to fill the catalogue.
- Pandin still uses a source shoreline node; its complete polygon is missing. River lines cover selected reaches, not entire rivers. Municipal labels and resort property references are not legal boundaries or actual spring-source positions.
- Original journal-point datum/precision remains unconfirmed and visibly qualified. Independent survey accuracy, Google visual comparison, spring chemistry/source verification and current water-quality science remain missing. No suitability engine, measurements or pollution percentages exist.
- Day 6 integration/demo brief and deployment remain unperformed. Provision real administrators explicitly with `php artisan admin:create`; no real account exists. Local upload-capable server: `scripts/serve-local.ps1`.

## Safety and recovery

Reports always start server-assigned pending. Individual contents, contacts, photos and audit history remain private; only counts of currently verified reports are public. Five attempts/hour/IP, honeypot, encrypted session-bound server timer, private advisory repeats and atomic terminal-state review rules remain intact. Retention is manual; no automatic deletion is authorized.

Use reviewed forward fixes. Never reset, roll back populated metadata, rerun original seeders casually, overwrite conflicts or create duplicate aliases. A fresh intended database can follow the documented original lake/fish setup and explicit ExplorerSeeder import; this installation already has the current catalogue. Scientific provenance and remaining gaps are in [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md).
