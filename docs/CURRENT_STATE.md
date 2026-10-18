# GIS expansion checkpoint

**2026-10-18. Compact tabbed profile built on `cd14ba2` on `main`.** See [EXPANSION_PROGRESS.md](EXPANSION_PROGRESS.md) for recovery history and the current UI checkpoint. No push.

## Complete

- Days 1-5: sourced lake/fish information, public explorer, anonymous private reporting, administrator-only review/audit and local anti-spam. No public registration or default administrator exists.
- Expanded catalogue imported explicitly and serially: **28 profiles** (10 lakes, 5 rivers, 11 waterfalls, 2 spring-associated resort references). Both JSON catalogue files are retained; original IDs, attributes, metadata and fish links are preserved.
- Added profiles cover Laguna de Bay, Calibato, Palakpakin, Caliraya, Lumot; selected Santa Cruz/San Pedro/Tunasan reaches; Taytay, Bunga Twin, Buntot Palos, Aliw, Buruwisan, Lanzones, Bukal and Dampalit falls; and a qualified historical Hidden Valley property-edge point.
- Compact native profile dialog: desktop width is half the map, clamped to 352-608 px and centered over it; mobile uses 8 px screen margins. Height is at most 640 px and always fits the viewport. Header, close control and segmented tabs stay visible while content scrolls.
- Five tabs: Overview (reset on every selection), Water Classification, Fish & Habitat, Pollution, Research & Sources. Existing fish cards, eight attributed images, source metadata and accuracy limits are reused. Pollution separates verified community counts from unknown science; individual summaries remain private. All reference sections remain readable without JavaScript.
- Tabs use linked ARIA roles, one keyboard tab stop, Left/Right with wrapping, Home/End, focusable panels and native button activation. Native dialog focus handling/Escape/return-to-opener remain intact. Search, geometry selection and reporting are preserved; schema, seeders, coordinates, catalogue, research records and dependencies are unchanged.
- Configured and connected database verified as `aquamap_laguna`. Existing migrations were inspected and already applied; **no migrations were run during recovery**. Import repeat preserved every value/timestamp. Unrelated fish/report/user/review/cache/migration records stayed unchanged.
- Fixed a live MySQL failure exposed by larger geometry: map and reporting-location queries sort the small retrieved catalogue in memory instead of sorting geometry JSON in MySQL. No schema, dependency, credential or server-setting change.

## Verification

- Final complete isolated suite: **171 tests / 1,672 assertions**. Focused map/catalogue suite: 35 / 570, including separate classification/fish/pollution/scientific/research content and non-JavaScript availability.
- Geometry compared to saved OSM node/segment data, preserving all lake island holes and selected river segments; Hidden Valley node checked against its 2014 outline. Eight photo permissions checked against saved Commons metadata. Research inventory updated.
- Vite production build, JavaScript syntax, Pint, Composer validation and Git whitespace checks pass. Final code review found no remaining actionable defects in this scope.
- Final tabbed-profile Edge run passed with zero JavaScript exceptions: all 28 selections, five panels, default Overview, stable selection/document, linked accessibility-tree roles, keyboard controls, wheel/content scrolling and map regressions. Desktop widths 1024/1440/1920 px and mobile 320/390 px portrait plus 640 px landscape passed. Different-resort checks retained operator limitations and unknown fish/science. Live reporting GET submitted nothing; domain records, users, audits, migrations and shared cache stayed unchanged. Screenshots inspected; actual assistive-technology and physical-device testing remain unperformed.
- Current browser artifacts: ignored `storage/app/tabbed-profile-browser/`. Recovery copies/integrity artifacts under `storage/app/expansion-recovery/`, saved `storage/app/inventory-*`, photographs and prior screenshots remain intact. These local artifacts are not a private runtime backup.

## Outstanding

- Dolcezza, Tirta and Stellaluna remain withheld for incomplete or inconsistent property-specific evidence. No additional source verification should be invented to fill the catalogue.
- Pandin still uses a source shoreline node; its complete polygon is missing. River lines cover selected reaches, not entire rivers. Municipal labels and resort property references are not legal boundaries or actual spring-source positions.
- Original journal-point datum/precision remains unconfirmed and visibly qualified. Independent survey accuracy, Google visual comparison, spring chemistry/source verification and current water-quality science remain missing. No suitability engine, measurements or pollution percentages exist.
- Day 6 integration/demo brief and deployment remain unperformed. Provision real administrators explicitly with `php artisan admin:create`; no real account exists. Local upload-capable server: `scripts/serve-local.ps1`.

## Safety and recovery

Reports always start server-assigned pending. Individual contents, contacts, photos and audit history remain private; only counts of currently verified reports are public. Five attempts/hour/IP, honeypot, encrypted session-bound server timer, private advisory repeats and atomic terminal-state review rules remain intact. Retention is manual; no automatic deletion is authorized.

Use reviewed forward fixes. Never reset, roll back populated metadata, rerun original seeders casually, overwrite conflicts or create duplicate aliases. A fresh intended database can follow the documented original lake/fish setup and explicit ExplorerSeeder import; this installation already has the current catalogue. Scientific provenance and remaining gaps are in [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md).
