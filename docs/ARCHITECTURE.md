# Architecture

The current verified system map is maintained in [project-local architecture memory](../.agents/architecture/architecture.md).

The approved 2026-10-21 crash recovery adds only verified Tadlac Lake through `lakes:import`, retaining the reviewed river-course catalogue and WGS84 ellipsoidal measurements. The public map lists 55 profiles, including qualified resort references, with a 25% sidebar and 75% map at widths of at least 1100 px; tablet/mobile layouts retain their existing breakpoints. Selection clones one inert Blade profile template into the nonmodal four-section card. The public all-profile reference library is removed while individual Research & Sources tabs and underlying data remain intact. The importer reuses guarded transactional canonical/alias checks; no migration is required. Patola remains withheld without confirmed location evidence. See CURRENT_STATE.md and RESEARCH_SOURCES.md.

Remaining Batch 1 against f165b0b shares one validated Water Type evidence result between map JSON and profiles. Unknown JSON values are null; legacy database classifications remain unchanged and qualified in Research. Sidebar metadata uses the existing estuary color for AA text contrast without layout changes.

Remaining Batch 2 against 30a77d5 adds a read-only fish-name-evidence.json supplement, validated by exact scientific name, reviewed status, Filipino language and safe citations. Existing fish cards display sourced Plapla for Oreochromis niloticus or Not yet verified; both new naming citations appear in Research & Sources. Species/occurrence rows, classification and suitability semantics are unchanged.

Remaining Batch 3 against c59862b adds ResearchSources, a pure projection of existing public evidence into six groups with one citation per document/profile and accessible cross-references. It retains distinct claims, query versions, page/table locations, revisions and licensing. Reviewed metadata enriches existing citations only; missing metadata uses explicit fallbacks. The separate guarded maintenance link checker performs no public-request network calls. RESEARCH_AUDIT.md and research-source-audit.json record all 54 profiles and dated link outcomes.

Remaining Batch 4 against 04d4623 fixes a reproduced zoom/resize race: app.js tracks public Leaflet zoomstart/zoomend events and defers a requested selection fit until zoom ends. Clear selection cancels pending work; normal wheel/pinch zoom does not refit automatically. The final browser runner checks live resizing during active zoom, small wheel deltas and trusted horizontal multi-touch within the exposed map. No data/schema/dependency changes are required. See [FINAL_VALIDATION.md](FINAL_VALIDATION.md) for phase evidence, results and physical-device limits.

See [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) for geographic provenance and scientific limitations, and [CURRENT_STATE.md](CURRENT_STATE.md) for execution results and planned work.
