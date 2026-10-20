# AquaMap Laguna checkpoint

**2026-10-21 ? River-course update against baseline 0119109. Local commit only; no push.**

## Current implementation

- **54 stored records / 52 public profiles:** 10 lakes, 29 rivers, 13 waterfalls; two spring resorts reporting-only. All records, IDs, timestamps, original geographic/environmental metadata, fish links, photos and reports are unchanged from baseline.
- **15 river highlights extended**, using 173 original OSM ways across 17 connected named courses. Bi?an/Dalitiwan have no additional attributable segments in the reviewed sources. Twelve other rivers remain explicitly unmapped. Complete headwater-to-mouth coverage and whole-river lengths remain unverified for all 29.
- Balanac now highlights **19.272 km**, versus the former 0.741 km Pagsanjan reach. Reference municipality labels do not clip geometry. The provincial 10.70 km figure remains approximate with unspecified extent; neither value is promoted to a certified total.
- Read-only river-courses.json matches canonical name, locality and baseline source URL. Runtime validation rejects disconnected, branched, duplicated, wrongly named or malformed source geometry and falls back to the original sanitized reach. No new database migration/import/write or runtime network request is needed.
- Mapped lengths use WGS84 ellipsoidal geodesics, without connectors across parts; nonconvergence stays unknown. Twenty-six provincial approximate figures remain separate. Bumbungan/Pagsanjan's ambiguous 4 km total and San Pedro/Tunasan totals stay unverified. Source links, revisions, attribution and missing-course reasons are visible.
- Blue highlight/fitBounds, interactive map selection, full environmental tabs, nine lake polygons, Pandin's point, historical lake areas, photographs and private reporting/admin boundaries remain. Unmapped profiles stay searchable/reportable without invented points.

## Verification

- **231 tests / 2,671 assertions passed** in guarded SQLite :memory:. Added source identity/topology failure cases, all-29 audits, original-record preservation, upstream Balanac coverage and independent ellipsoidal distance examples. The final suite includes malformed legacy alias/note rendering; all 14 focused course tests also passed.
- Production Vite build, Pint, JavaScript syntax and Git whitespace checks pass. All 173 course ways match original API node sequences and revision metadata exactly. Dependencies, prior catalogues and photo assets are unchanged.
- Installed Edge passed all 52 selections at 1440/390 px plus representative 320 px cases: extended geometry fits bounds, source lengths stay distinct, missing-course fallbacks, selection/clear/search/tabs and 29 river reporting choices. No JavaScript exceptions. Desktop/mobile screenshots were inspected. Reporting and administrator browser regressions also passed.
- Browser fixtures use confined disposable SQLite/photo/session/cache directories and preserve real MySQL/shared caches. Separate hashes match **all 54 water-body records and seven fish/report/account/audit/cache tables to baseline 0119109**. Both configured/connected database names were verified as aquamap_laguna. Zero persistent writes in this update.

## Evidence and remaining work

- [RIVER_INVENTORY.md](RIVER_INVENTORY.md): all-29 before/after distances, source/licensing decisions and the twelve specific evidence gaps. [ARCHITECTURE.md](ARCHITECTURE.md): current data flow. [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md): scientific/geographic limits.
- Read-only audit: `php artisan rivers:audit-courses --json --no-interaction`. Original snapshots, rejected candidates, query/access failures, audit/integrity results and screenshots are under ignored storage/app/river-courses/; earlier river-expansion/ evidence is preserved. Existing reporting/admin artifacts remain under day4-browser/day5-browser. Temporary browser profiles/fixtures were removed.
- Still needed: attributable/licensed centreline data for twelve unmapped rivers; independent whole-course endpoint/length evidence; the original provincial facsimile and clarification of conflicting extents. Geoportal restricted downloads, point gazetteers and basin polygons were not substituted. Physical-device/screen-reader checks, Pandin's full shoreline and deployment remain unperformed. Do not fill gaps with invented geometry, measurements or reports.
