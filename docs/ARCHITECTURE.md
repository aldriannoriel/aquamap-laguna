# Architecture

The current verified system map is maintained in [project-local architecture memory](../.agents/architecture/architecture.md).

The approved 2026-10-21 redesign stabilization retains the reviewed river-course catalogue and WGS84 ellipsoidal measurements. The public map now lists all 54 existing profiles, including qualified resort references, with a left sidebar and a right Leaflet map. Selection opens one nonmodal profile with four bottom sections; public report aggregate queries/projections are removed. New reports require private photo proof; legacy reports remain valid. No database migration or stored-data change is needed. See CURRENT_STATE.md for this batch and RIVER_INVENTORY.md for unchanged geographic limits.

Remaining Batch 1 against f165b0b shares one validated Water Type evidence result between map JSON and profiles. Unknown JSON values are null; legacy database classifications remain unchanged and qualified in Research. Sidebar metadata uses the existing estuary color for AA text contrast without layout changes.

Remaining Batch 2 against 30a77d5 adds a read-only fish-name-evidence.json supplement, validated by exact scientific name, reviewed status, Filipino language and safe citations. Existing fish cards display sourced Plapla for Oreochromis niloticus or Not yet verified; both new naming citations appear in Research & Sources. Species/occurrence rows, classification and suitability semantics are unchanged.

Remaining Batch 3 against c59862b adds ResearchSources, a pure projection of existing public evidence into six groups with one citation per document/profile and accessible cross-references. It retains distinct claims, query versions, page/table locations, revisions and licensing. Reviewed metadata enriches existing citations only; missing metadata uses explicit fallbacks. The separate guarded maintenance link checker performs no public-request network calls. RESEARCH_AUDIT.md and research-source-audit.json record all 54 profiles and dated link outcomes.

See [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) for geographic provenance and scientific limitations, and [CURRENT_STATE.md](CURRENT_STATE.md) for execution results and planned work.
