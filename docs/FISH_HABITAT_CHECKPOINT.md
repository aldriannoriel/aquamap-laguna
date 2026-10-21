# Fish & Habitat crash recovery checkpoint

Updated: 2026-10-21T14:09:12.441Z. Project: AquaMap Laguna only.

## Verified state

- Phase: audit/research complete; implementation has not yet started. Preserve the existing audit and source research; do not restart.
- Current local Git commit: 4f4f1b2 (baseline). Working tree was clean before these recovery documents.
- Last successful operation: re-read configured/connected aquamap_laguna; all fourteen table hashes and .env match the saved pre-task audit.
- Inventory: 53 supported records = 11 lakes, 29 rivers, 13 waterfalls; all supplied names match. Physical total is 55 because legacy IDs 11/28 remain hidden/stored. Do not add/delete water bodies.
- Water Type reviewed/display coverage: 3/53. Existing database freshwater values cover 7/53; other stored values are Not assessed. Do not confuse stored labels with reviewed evidence.
- Possible Fish reviewed/assignment coverage: 0/53. Existing fish data: one Nile tilapia species and three historical aquaculture links; these are documented evidence, not possible-fish assessments.
- Fish image coverage: 0 installed photographs / 9 planned candidate species. Commons metadata is saved, including selected identity/license-checked alternatives; no public assets downloaded yet.
- Habitat explanation coverage: 0/53. Nine-species draft habitat/reference research has been collected; no per-water-body assignments exist yet.
- Research: existing citations preserved. Reviewed FAO/SEAFDEC/UPLB and primary papers support candidate biology and Laguna de Bay variable salinity. Bibliography/catalogue still needs writing.
- Database changes already applied by this task: none. No migration, seeder, importer, decision, email, credential or GIS change has run.
- Last successful tests: baseline commit passed full 327 tests / 4,607 assertions, final 72 relevant tests / 820 assertions, Pint/build and desktop/mobile checks. No new implementation tests run yet.
- Modified files at this checkpoint: docs/FISH_HABITAT_CHECKPOINT.md, docs/CURRENT_STATE.md, docs/EXPANSION_PROGRESS.md.

## Completed and pending water bodies

Pre-existing reviewed Water Type entries (preserve unchanged): #12 Calibato Lake; #2 Pandin Lake; #3 Yambo Lake.
No water body has a completed new Possible Fish assessment. All 53 remain pending for that feature.

| ID | Water body | Water Type review | Possible Fish review |
| --- | --- | --- | --- |
| 23 | Aliw Falls | Pending | Pending |
| 30 | Ambon-Ambon Falls | Pending | Pending |
| 10 | Balanac River | Pending | Pending |
| 47 | Balatuin River | Pending | Pending |
| 29 | Balon-Balon Falls | Pending | Pending |
| 39 | Banca-Banca River | Pending | Pending |
| 36 | Bay River | Pending | Pending |
| 32 | Biñan River | Pending | Pending |
| 53 | Botocan River | Pending | Pending |
| 26 | Bukal Falls | Pending | Pending |
| 9 | Bumbungan River | Pending | Pending |
| 21 | Bunga Twin Falls | Pending | Pending |
| 5 | Bunot Lake | Pending | Pending |
| 22 | Buntot Palos Falls | Pending | Pending |
| 24 | Buruwisan Falls | Pending | Pending |
| 12 | Calibato Lake | Existing reviewed freshwater | Pending |
| 14 | Caliraya Lake | Pending | Pending |
| 37 | Calo River | Pending | Pending |
| 52 | Dalitiwan River | Pending | Pending |
| 27 | Dampalit Falls | Pending | Pending |
| 7 | Hulugan Falls | Pending | Pending |
| 16 | Laguna de Bay | Pending | Pending |
| 25 | Lanzones Falls | Pending | Pending |
| 15 | Lumot Lake | Pending | Pending |
| 51 | Maimpis River | Pending | Pending |
| 46 | Makpongo River | Pending | Pending |
| 48 | Malaunod River | Pending | Pending |
| 54 | Matikiw River | Pending | Pending |
| 4 | Mohicap Lake | Pending | Pending |
| 49 | Onipa River | Pending | Pending |
| 6 | Pagsanjan Falls | Pending | Pending |
| 13 | Palakpakin Lake | Pending | Pending |
| 2 | Pandin Lake | Existing reviewed freshwater | Pending |
| 41 | Pangil River | Pending | Pending |
| 40 | Pila River | Pending | Pending |
| 38 | Prinza River | Pending | Pending |
| 43 | Romero River | Pending | Pending |
| 1 | Sampaloc Lake | Pending | Pending |
| 44 | San Antonio River | Pending | Pending |
| 34 | San Cristobal River | Pending | Pending |
| 31 | San Isidro River | Pending | Pending |
| 35 | San Juan River | Pending | Pending |
| 18 | San Pedro River | Pending | Pending |
| 17 | Santa Cruz River | Pending | Pending |
| 33 | Santa Rosa River | Pending | Pending |
| 42 | Siniloan River | Pending | Pending |
| 55 | Tadlac Lake | Pending | Pending |
| 8 | Talay Falls | Pending | Pending |
| 50 | Tarak River | Pending | Pending |
| 20 | Taytay Falls | Pending | Pending |
| 45 | Tigas River | Pending | Pending |
| 19 | Tunasan River | Pending | Pending |
| 3 | Yambo Lake | Existing reviewed freshwater | Pending |

## Recovery evidence and issues

- Ignored storage/app/fish-habitat/before.json contains the complete original public water/species/link rows, protected-table hashes and environment hash. Never commit ignored recovery artifacts or .env. commons.json preserves public source/license metadata; reject the Mindanao file labelled Clarias macrocephalus because its description identifies C. gariepinus. The alternative Judgefloro native-hito file has matching species metadata and CC0 licensing.
- MySQL SELECT * plus database-side sorting of large geography rows returned error 1038. Unsorted reads and PHP sorting succeeded. Use minimal selected columns and PHP sorting; do not alter server configuration.
- No draw.io file was found in tracked project files; use the supplied exact three-column design and existing profile palette/scroll/navigation.
- Preliminary educational estimates are authorized by the latest user request. Label them explicitly; never convert them into confirmed occurrence or measured suitability.

## Exact next action and automatic save protocol

1. Add a guarded, read-only checkpoint command that derives coverage from actual files/database and writes this document atomically; validate it before relying on it.
2. Build the reviewed species catalogue/importer and compact gallery infrastructure. Reuse existing tables/pivots; preserve original occurrence rows, reject identity conflicts, and make repeat runs unchanged.
3. Implement/validate batches of 3?5 named bodies. First proposed batch: #5 Bunot Lake, #14 Caliraya Lake, #16 Laguna de Bay, #15 Lumot Lake. Existing three reviewed entries must not be overwritten.
4. After EVERY completed batch: inspect actual rows, save completed IDs/names and exact next batch to this on-disk checkpoint, update expansion progress, run targeted checks, and commit validated milestones locally. Never push.
5. On recovery: read this file, git status/diff and actual database first; database/files override stale checkpoint claims. Do not automatically run migrations or repeat an applied import. Preserve all uncommitted work.
