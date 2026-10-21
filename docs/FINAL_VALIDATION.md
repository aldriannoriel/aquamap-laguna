# Redesign final validation

2026-10-21; approved Remaining Batch 4 against **04d4623** complete within available automated/browser coverage. One confirmed map regression fixed; other changes are regression coverage and recovery documentation. No schema, dependencies or source data changed. Physical-device acceptance remains unverified.

Resizing during an active Leaflet zoom previously lost the selected-course fit request, leaving the river clipped below the map. Before the fix, the focused 1440-to-1360 px case placed Balanac's bottom at 1037.56 px below the map's 947.63 px bottom. `resources/js/app.js` now defers that fit until `zoomend`; clearing selection cancels pending work. Ordinary zoom does not trigger an automatic refit. The focused regression passed afterward.

## Seventeen-phase status

Statuses describe the approved redesign scope. Scientific unknowns and unavailable physical-device checks remain explicit below.

| Phase | Status | File-based evidence |
| --- | --- | --- |
| 1. Architecture analysis | COMPLETED | `docs/ARCHITECTURE.md`, `.agents/architecture/architecture.md`; routes/controller/model flow retained |
| 2. Remove Introduction | COMPLETED | `resources/views/map/index.blade.php`, `DashboardRedesignTest`: direct map-first entry |
| 3. Left sidebar / right map | COMPLETED | `resources/css/app.css`: desktop/tablet grid; browser layout assertions |
| 4. Compact searchable sidebar | COMPLETED | `map/index.blade.php`, `resources/js/app.js`: all 54 rows, category counts, aliases, empty state, independent scrolling |
| 5. Leaflet map and zoom | COMPLETED | `app.js`, `tests/browser/river-smoke.mjs`: selected extents, wheel, small deltas, simulated pinch and live resizing |
| 6. Map Context placement | COMPLETED | `map/index.blade.php`: context directly follows selection instruction below the map |
| 7. Remove duplicate information panel | COMPLETED | `map/index.blade.php`, `DashboardRedesignTest`: one active nonmodal profile; no `map-selection` panel |
| 8. Compact profile / four bottom sections | COMPLETED | `map/index.blade.php`, `app.js`, `app.css`: Profile, Water Type, Fish & Habitat, Research & Sources |
| 9. Measurements and unknown fallbacks | COMPLETED | `resources/views/map/profile.blade.php`, `app/Support/WaterBodyMetrics.php`, `RiverCourseCatalogueTest` |
| 10. Evidence-based Water Type | COMPLETED | `MapController.php`, `ExplorerProfile.php`, `DashboardRedesignTest`: one evidence result for JSON/profile; unknown stays null |
| 11. Fish & Habitat bilingual evidence | COMPLETED | `profile.blade.php`, `fish-name-evidence.json`, `FishMapTest`: Nile tilapia / Plapla / scientific name; historical occurrence distinct from suitability |
| 12. Administrator-only reporting information | COMPLETED | `routes/web.php`, `DashboardRedesignTest`, `AdminAuthenticationTest`, `AdminReportsTest`; no public report queries/counts |
| 13. Research & Sources | COMPLETED | `ResearchSources.php`, `map/research-sources.blade.php`, `ResearchSourcesTest`, `docs/RESEARCH_AUDIT.md`: six groups, deduplication, qualified metadata and intact attribution |
| 14. Fixed reporting action | COMPLETED | `map/index.blade.php`, `.report-fab` in `app.css`; browser visibility/overlap checks |
| 15. Responsive and accessibility checks | COMPLETED | `app.css`, browser screenshots/assertions: four viewport widths, labels, keyboard focus, targets and metadata contrast |
| 16. Regression verification | COMPLETED | Full PHP suite/Pint/build/syntax and map/reporting/admin checks passed; unavailable physical input and inconsistent 320 px pinch simulation explicitly qualified |
| 17. Safety and recovery checkpoint | COMPLETED | `tests/TestCase.php`, confined browser routers, database/cache hash assertions and this recovery record |

## Automated results

- `php artisan test --log-junit storage/app/final-validation/phpunit.xml`: **260 tests / 3,647 assertions**, zero errors, failures or skips; final rerun after the fix: 96.04 seconds. One unit test and 259 feature tests use guarded SQLite `:memory:`.
- Full `php vendor/bin/pint --test --format agent` and required `--dirty --format agent`: passed. Production `npm.cmd run build`: passed. PowerShell blocks `npm.ps1`; using the installed `.cmd` wrapper needs no policy change.
- `node --check` passed for all eight JavaScript files in `resources/js`, `tests/browser` and `scripts`. `git diff --check` passed.
- Integrity/privacy coverage includes `ResearchSourcesTest`, `DashboardRedesignTest`, `MapTest`, `ExplorerTest`, `RiverCourseCatalogueTest`, `FishMapTest`, `PollutionReportTest`, `ReportSpamProtectionTest`, `AdminAuthenticationTest` and `AdminReportsTest` within the full suite.

## Browser verification

- Installed Edge, isolated profiles, disposable SQLite/session/cache/private-photo fixtures; no synthetic submissions to MySQL. Final combined map command: `node tests/browser/river-smoke.mjs --final --fish --research`.
- Final combined checks passed at 1440 x 1100, 1024 x 900, 390 x 844 and 320 x 740, with zero JavaScript exceptions. All 54 profiles were selected at 1440/390; tablet/320 used 11 representatives, including all historical fish profiles and research edge cases. Simulated pinch passed at 390; the final 320 attempt received no touch events and is **not verified**. Other 320 checks passed. Full results: `storage/app/final-validation/browser/full-results.json`; focused results retained separately.
- Verified direct profiles, blue river/lake geometry and fitBounds, waterfall/property markers, clear selection, all category counts (54/10/29/13/2), search/empty/reset, independent sidebar scrolling, one card/four bottom sections, correct context placement and fixed reporting action. No horizontal overflow; screenshots inspected at desktop/tablet/mobile sizes.
- Browser-dispatched wheel events include eight **-1.5 pixel deltas**; marker separation verifies zoom while the profile remains open. Clear-map horizontal two-finger touch sequences verify simulated pinch on mobile, with multi-touch emulation enabled and trusted map-target touches checked. Live width changes during active zoom check the Leaflet SVG renderer size and full selected extent. Shape Enter activation, tab arrow navigation, visible focus, Escape, accessible control names and at least 24 px primary targets are checked.
- Computed sidebar metadata contrast: **11.55:1 normal; 10.67:1 hovered/selected** at all four widths. This validates the repaired text; it is not a blanket WCAG certification.
- JSON/profile Water Type agreement, historical fish names/status/unassessed suitability, both SEAFDEC citations, six research groups, document deduplication, unique selected IDs, expanding/focused cross-references, OSM/ODbL text, photos and qualified measurements/fallbacks are covered.
- Zoom tests measure marker separation because Leaflet clips SVG paths. Pinch starts on unobstructed map tiles; the native CDP vertical pinch placed a finger on the profile at 320 px, so the runner uses a horizontal gesture within the exposed map. Physical-device verification remains unavailable.
- At 320 px, some runs still misroute simulated touches despite clear DOM coordinates. Those attempts are explicitly logged **NOT VERIFIED**; only trusted map-target starts can establish pinch success, and genuine map-target input that fails to zoom still fails the test. Focused 320 px pinch runs passed, but narrow-mobile simulation is not consistently reliable.

## Reporting privacy and security

- `node tests/browser/reports-smoke.mjs`, the same command with `--spam`, and `node tests/browser/admin-smoke.mjs`: **passed**, desktop and 390 px mobile, zero JavaScript exceptions. Results remain in ignored `storage/app/day4-browser/{results,spam-results}.json` and `storage/app/day5-browser/results.json`.
- Required private proof, server validation/focused error summary, optional contacts/incident coordinates, CSRF rejection, generated private photo storage and generic pending confirmation passed. Full PHP tests also reject unsafe/oversized files, public record/photo access and client-controlled privileged attributes; legacy photo-less rows remain intact.
- Administrator authorization, private evidence streaming, no public counts, controlled moderation and atomic audit history passed. Real encrypted minimum-time protection, honeypot, nonblocking same-session repeat warning and sixth-attempt HTTP 429 passed. Tests modify only disposable fixtures.

## Preservation, limitations and recovery

- Guarded read-only checks confirm **54 water bodies, one fish species and three occurrence links** in `aquamap_laguna`. Seven complete table hashes and all original report rows match baseline; GIS/source/photo catalogues are unchanged. Browser before/after comparisons cover domain/admin/report/migration/database-cache rows and shared file cache and passed within every isolated run. Validation performs no persistent imports/report writes or unrelated project operations.
- The independent whole-batch snapshot detected added reports created after the last browser result, without synthetic fixture markers; their origin is unverified. All original reports remain byte-identical in the sorted-row comparison. Database cache contents also differ, so global cache invariance across the batch cannot be claimed. The live additions/cache are retained untouched; this is not a reason to restore/delete data. Counts, timestamps and diagnostics remain private in ignored `preservation-{before,after,review}.json`, not public JSON or committed report inventories.
- Physical trackpad, physical multi-touch devices, other browsers and assistive-technology/screen-reader use were not tested. Browser-dispatched gestures are simulation. Manual device acceptance and deployment remain separate work.
- Existing evidence limits remain: 12 unmapped rivers, unverified whole-river totals/headwater-to-mouth completeness, no verified waterfall heights, qualified resort-property points, unknown classifications/suitability, and 27 photo placeholders. None is filled with invented data.
- Batch 3's dated link audit is retained without research expansion: 246/248 URLs reachable; Senate/AllTrails access remains unresolved (HTTP 403). Ten author/organization fields, 318 publication-year fields and four photographic creator identities remain qualified unknowns; all existing citations/credits remain.
- Local evidence: `storage/app/final-validation/` contains JUnit/logs, preservation snapshots and browser results/screenshots. Earlier research and security artifacts remain in their original directories. Fixture/profile directories are removed by each runner with confined-path checks.
- Recover by reading `docs/CURRENT_STATE.md`, this file and `git log -1 --oneline`; check `git status --short` before work. Rebuild with `npm.cmd run build`; run the commands above from the inner repository. `--quick --final --fish --research --width=320` isolates narrow-mobile checks; omit width/quick for the full run. Never reset/import the persistent database for validation. No push/deployment or further batch is authorized.
