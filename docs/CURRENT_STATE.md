# AquaMap Laguna checkpoint

**2026-10-21. Remaining Batch 3 complete against c59862b: Research & Sources cleanup only. Stop pending explicit approval for Batch 4.**

## Verified implementation

- All 54 existing profiles remain: 10 lakes, 29 rivers, 13 waterfalls and two qualified resort property references. There are 42 source-checked map locations, 12 explicitly unmapped rivers and 27 photos/27 placeholders. Whole-river totals and independent spring-source identities remain unverified.
- Existing map-first sidebar, complete search/filter list, one nonmodal four-section profile, deliberate wheel zoom, fixed reporting action, keyboard controls and responsive layouts remain. Water Type classifications and estuary metadata contrast are unchanged.
- ResearchSources now projects existing public evidence into six groups: Geography and GIS, Physical measurements, Water type and hydrology, Fish and habitat, Historical ecological evidence, Photography and licensing. One document is cited once per profile, with accessible internal cross-references for its other purposes. Distinct claims, query versions, page/table fragments, revisions, catalogue caveats and licensing remain.
- The 54-profile audit consolidates 497 source uses into 341 document entries: 156 duplicate citation displays removed. No incorrectly assigned current-profile reference was established; known lake-paper scopes quarantine a mismatched reference without changing its stored row. Safe URLs with missing metadata remain visible; malformed/unsafe references stay rejected. Source-check/revision/plan/capture dates are never substituted for publication years.
- All original OSM ways/nodes/relations and ODbL attribution, PDPFP approximate lengths, historical lake areas/hydrology, ecological studies, fish evidence, FAO habitat references, both SEAFDEC Plapla references and photo/permission credits remain. Existing fish species/occurrences, measurements, geometry and classifications are unchanged.
- One existing species remains Nile tilapia / Oreochromis niloticus, with sourced Filipino name Plapla and three historical aquaculture links only (Sampaloc/Pandin/Yambo). Suitability remains unassessed; naming/habitat sources never create local occurrence evidence.
- Public reports, counts, contacts, proof and audits remain administrator-only. New reports require private JPEG/PNG/WebP proof up to 5 MB; historical photo-less reports remain valid. CSRF, throttling, honeypot, encrypted timer, pending status and moderation rules are unchanged.

## Validation

- Complete Batch 3 regression checkpoint: **260 tests / 3,647 assertions passed**, guarded SQLite :memory:. Focused citation/explorer checks also passed 32 / 828. Coverage includes all 54 profiles, six groups, deduplication, page/query preservation, scope rejection, missing metadata, malformed/unsafe URL rejection, escaping, unchanged records and report privacy.
- Pint, production Vite build, JavaScript syntax and whitespace checks passed. No dependency changes, migrations, persistent imports or writes.
- Isolated Edge passed **11 representative profiles at 1440/390 px**: six groups, unique document links, unique selected citation IDs, expanding cross-reference navigation with source-link focus, all three historical fish profiles/Plapla citations, image/ODbL attribution, map controls/layout/privacy and existing contrast. Desktop/mobile screenshots inspected; no JavaScript exceptions. The click helper now targets an actual inline line rectangle rather than the whitespace between wrapped lines.
- Browser before/after hashes preserve real aquamap_laguna records and shared cache; confined fixtures/profiles removed. All scientific/geometry/photo source catalogues are unchanged. The complete suite was run as the Batch 3 regression checkpoint; Batch 4-specific validation work was not begun.

## Audit evidence and recovery

- **246/248 URLs reachable**, including the local permission declaration. The Senate bill record and AllTrails photo listing remain HTTP 403 after two attempts; retained as unresolved access, not invalid sources. Missing metadata: 0 titles, 10 author/organization fields, 318 publication years across profile citation entries; four existing photographic creator identities remain unidentified/unspecified. Unknowns remain explicit.
- Durable inventory and methods: docs/RESEARCH_AUDIT.md and docs/research-source-audit.json. Original snapshots, initial/retried link responses, browser results/screenshots and phpunit-final.json remain under ignored storage/app/research-audit/. Prior redesign/river/reporting/admin evidence is preserved in its original directories.
- Link maintenance: node scripts/check-research-links.mjs --export-only refreshes the guarded public inventory without network calls; normal execution checks links in batches of 12, three concurrent GETs; --retry checks only unresolved URLs. These checks never run in public requests. Browser entry: node tests/browser/river-smoke.mjs --quick --fish --research.
- Link reachability is dated and does not establish source claim validity. The provincial plan remains a third-party transcription; original government facsimile is still unresolved. Missing map courses/totals, physical-device/screen-reader checks and deployment remain outstanding.
- Commit locally only; no automatic push. Use git log -1 --oneline for the checkpoint ID. No user edits were present at batch start. **Batch 4 requires explicit approval; stop here.**
