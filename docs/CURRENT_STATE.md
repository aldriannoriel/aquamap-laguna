# AquaMap Laguna checkpoint

**2026-10-21. Approved batch complete: stabilize and validate the existing redesign draft against 5c17898. Stop here; every next batch requires explicit user approval.**

## Verified implementation

- Compact desktop/tablet layout: approximately 32% left sidebar (search, live categories/counts, independently scrolling complete list), 68% right Leaflet map. The large introduction is removed. Mobile keeps all controls/list in a collapsible sidebar.
- All **54 existing records** now have public profiles: 10 lakes, 29 rivers, 13 waterfalls and two qualified resort property references. **42 source-checked map locations**, twelve explicitly unmapped rivers; 27 photos/27 placeholders. Resorts are not verified spring-source locations.
- Selecting a list item, marker or shape opens one nonmodal card immediately. Four fixed bottom sections: Profile, Water Type, Fish & Habitat, Research & Sources. Internal scroll, keyboard arrows/Home/End, Close/Escape, selection highlighting and source-aware fitBounds remain; map dragging/zoom remains available. The duplicate summary and Full profile step are gone.
- Focus/click enables wheel/trackpad zoom (70 ms debounce, 100 px/level, quarter-level snap); leaving/defocusing restores ordinary page scrolling. Plus/minus and default touch zoom remain. ResizeObserver invalidates map size. One fixed, touch-friendly Report a Concern link respects safe areas and profile navigation.
- **No public report query or counter projection.** Reports/counts/statuses/proof/audits remain administrator-only. Public submission stays available. The user explicitly requires a photo for every new report: JPEG/PNG/WebP, up to 5 MB, private storage; historical reports without photos remain valid. CSRF, 5 attempts/hour/IP, honeypot, real 3-second server timer and private same-session duplicate warnings remain.
- Water Type uses reviewed historical freshwater context for Yambo/Pandin/Calibato only, from Bicaldo et al. (2024). Other locations remain unverified; stored classifications remain research context. Historical fish occurrence stays distinct from unassessed suitability; Filipino names remain unverified. Existing citations, image permissions and original GIS/length catalogues are preserved.

## Validation

- **234 tests / 2,904 assertions passed** using guarded SQLite :memory:. New checks cover zero public report queries/projections, mandatory proof with unchanged legacy rows, and evidence identity/URL rejection. After test-name cleanup, the focused Explorer suite passed 25 tests / 279 assertions.
- Pint, production Vite build, JavaScript syntax and Git whitespace checks pass. No dependency changes, database migration/import or persistent write.
- Installed Edge passed all 54 profile selections at 1440/390 px and five representative cases at 1024/320 px: direct cards, four bottom sections, full highlighted extents, source metrics/photos, filters/counts/empty state, list scrolling, wheel zoom with card open, keyboard/Escape and fixed reporting action. Small-screen reading space was refined and all four representative viewport checks passed again; screenshots inspected. No map JavaScript exceptions.
- Desktop/mobile reporting checks passed (private 3 MB proof uploads, validation/reselection, pending status). Administrator checks passed (authorization, private proof, filters, terminal moderation and audit escaping). Real-timer anti-spam checks passed with required photos: early/honeypot rejection, legitimate retry, private advisory repeats and sixth-attempt 429.
- Before/after browser hashes preserve real MySQL/shared cache. Independent hashes match all **54 stored records and seven fish/report/account/audit/cache tables** to the preserved baseline. Configured/connected database names are aquamap_laguna. Browser profiles/fixtures were removed; no unrelated server/process was stopped.

## Recovery and remaining limits

- Current entry: `npm.cmd run test:browser` or `node tests/browser/river-smoke.mjs`; the original map-smoke entry delegates to the same current checks. Use --quick for representative cases. Evidence: ignored storage/app/redesign/browser/ (full), browser/quick/, integrity-results.json; final PHPUnit result is storage/app/redesign-final.json. Reporting/admin evidence remains under day4-browser/day5-browser. Original river evidence remains intact.
- This is the approved draft-stabilization batch, not authorization to start additional research/features or another phase. Obtain the next explicit batch approval before further implementation.
- Twelve river courses and every whole-river total remain unverified; three reviewed historical water-type entries do not establish current salinity elsewhere. No new species, Filipino names, measurements, watershed lines or spring-source claims were fabricated. Physical-device/screen-reader testing, a comprehensive citation-link audit and deployment remain unperformed.
- Local commit only; never push automatically. Use git log -1 --oneline for the resulting checkpoint ID. No user edits were present when this batch began.
