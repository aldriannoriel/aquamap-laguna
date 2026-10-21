# Fish & Habitat crash recovery checkpoint

Updated: 2026-10-21T16:26:06+00:00

## Actual state

- Phase: Incremental implementation; completed batches retained.
- Git HEAD when saved: fd86f0c. A later checkpoint commit may contain this file; git log -1 is authoritative.
- Last successful operation: inspected actual catalogue/relationships and saved this checkpoint.
- Water Type reviewed/display coverage: 40/53; possible fish coverage: 40/53; habitat explanation coverage: 40/53.
- Inventory: 53 supported; {"waterfall":13,"river":29,"lake":11}; 55 stored including two preserved hidden legacy properties.
- Fish: 9 stored species; 155 reviewed possible assignments; 152 new potential-only pivots. Original occurrence rows are preserved separately.
- Fish photographs installed: 4/9 species. Missing photos use labelled placeholders, never another species image.
- References: 9 species-habitat references reviewed; every complete candidate has an explanation, requirements, limitations and source links. Existing research/naming/occurrence citations remain.
- Database changes: only explicit additive habitat batches and missing water_type labels. No water body added/deleted, schema operation, report/agency/referral/GIS/photo modification or live email.
- Last successful tests: 8 habitat tests / 2112 assertions passed for batch 10; 2026-10-22. Current catalogue matches recorded feature validation.

## Completed and pending IDs/names

| ID | Water body | Water Type | Possible Fish | Photos |
| --- | --- | --- | --- | --- |
| 23 | Aliw Falls | Pending | Pending | 0/0 |
| 30 | Ambon-Ambon Falls | Pending | Pending | 0/0 |
| 10 | Balanac River | Reviewed | 4 candidates | 2/4 |
| 47 | Balatuin River | Reviewed | 3 candidates | 2/3 |
| 29 | Balon-Balon Falls | Pending | Pending | 0/0 |
| 39 | Banca-Banca River | Reviewed | 3 candidates | 2/3 |
| 36 | Bay River | Reviewed | 4 candidates | 2/4 |
| 32 | Biñan River | Reviewed | 4 candidates | 2/4 |
| 53 | Botocan River | Reviewed | 2 candidates | 0/2 |
| 26 | Bukal Falls | Pending | Pending | 0/0 |
| 9 | Bumbungan River | Reviewed | 3 candidates | 1/3 |
| 21 | Bunga Twin Falls | Pending | Pending | 0/0 |
| 5 | Bunot Lake | Reviewed | 5 candidates | 4/5 |
| 22 | Buntot Palos Falls | Pending | Pending | 0/0 |
| 24 | Buruwisan Falls | Pending | Pending | 0/0 |
| 12 | Calibato Lake | Reviewed | 5 candidates | 4/5 |
| 14 | Caliraya Lake | Reviewed | 6 candidates | 4/6 |
| 37 | Calo River | Reviewed | 3 candidates | 1/3 |
| 52 | Dalitiwan River | Reviewed | 2 candidates | 0/2 |
| 27 | Dampalit Falls | Pending | Pending | 0/0 |
| 7 | Hulugan Falls | Pending | Pending | 0/0 |
| 16 | Laguna de Bay | Reviewed | 8 candidates | 4/8 |
| 25 | Lanzones Falls | Pending | Pending | 0/0 |
| 15 | Lumot Lake | Reviewed | 6 candidates | 4/6 |
| 51 | Maimpis River | Reviewed | 3 candidates | 2/3 |
| 46 | Makpongo River | Reviewed | 3 candidates | 2/3 |
| 48 | Malaunod River | Reviewed | 3 candidates | 2/3 |
| 54 | Matikiw River | Reviewed | 3 candidates | 1/3 |
| 4 | Mohicap Lake | Reviewed | 4 candidates | 3/4 |
| 49 | Onipa River | Reviewed | 3 candidates | 2/3 |
| 6 | Pagsanjan Falls | Pending | Pending | 0/0 |
| 13 | Palakpakin Lake | Reviewed | 5 candidates | 4/5 |
| 2 | Pandin Lake | Reviewed | 5 candidates | 3/5 |
| 41 | Pangil River | Reviewed | 4 candidates | 2/4 |
| 40 | Pila River | Reviewed | 4 candidates | 3/4 |
| 38 | Prinza River | Reviewed | 4 candidates | 2/4 |
| 43 | Romero River | Reviewed | 3 candidates | 2/3 |
| 1 | Sampaloc Lake | Reviewed | 6 candidates | 4/6 |
| 44 | San Antonio River | Reviewed | 3 candidates | 2/3 |
| 34 | San Cristobal River | Reviewed | 4 candidates | 2/4 |
| 31 | San Isidro River | Reviewed | 4 candidates | 2/4 |
| 35 | San Juan River | Reviewed | 4 candidates | 2/4 |
| 18 | San Pedro River | Reviewed | 3 candidates | 2/3 |
| 17 | Santa Cruz River | Reviewed | 5 candidates | 3/5 |
| 33 | Santa Rosa River | Reviewed | 4 candidates | 2/4 |
| 42 | Siniloan River | Reviewed | 4 candidates | 2/4 |
| 55 | Tadlac Lake | Reviewed | 4 candidates | 4/4 |
| 8 | Talay Falls | Pending | Pending | 0/0 |
| 50 | Tarak River | Reviewed | 2 candidates | 2/2 |
| 20 | Taytay Falls | Pending | Pending | 0/0 |
| 45 | Tigas River | Reviewed | 2 candidates | 1/2 |
| 19 | Tunasan River | Reviewed | 3 candidates | 2/3 |
| 3 | Yambo Lake | Reviewed | 5 candidates | 4/5 |

Pending Possible Fish: #23 Aliw Falls; #30 Ambon-Ambon Falls; #29 Balon-Balon Falls; #26 Bukal Falls; #21 Bunga Twin Falls; #22 Buntot Palos Falls; #24 Buruwisan Falls; #27 Dampalit Falls; #7 Hulugan Falls; #25 Lanzones Falls; #6 Pagsanjan Falls; #8 Talay Falls; #20 Taytay Falls

## Photo gaps and known issues

- #10 Balanac River: Tank goby, Loach goby
- #47 Balatuin River: Tank goby
- #39 Banca-Banca River: Native hito
- #36 Bay River: Native hito, Tank goby
- #32 Biñan River: Native hito, Tank goby
- #53 Botocan River: Loach goby, Tank goby
- #9 Bumbungan River: Loach goby, Tank goby
- #5 Bunot Lake: Native hito
- #12 Calibato Lake: Tank goby
- #14 Caliraya Lake: Native hito, Tank goby
- #37 Calo River: Native hito, Tank goby
- #52 Dalitiwan River: Loach goby, Tank goby
- #16 Laguna de Bay: Native hito, Kanduli, Tank goby, Bangus
- #15 Lumot Lake: Native hito, Tank goby
- #51 Maimpis River: Native hito
- #46 Makpongo River: Tank goby
- #48 Malaunod River: Native hito
- #54 Matikiw River: Loach goby, Tank goby
- #4 Mohicap Lake: Native hito
- #49 Onipa River: Tank goby
- #13 Palakpakin Lake: Native hito
- #2 Pandin Lake: Native hito, Tank goby
- #41 Pangil River: Loach goby, Tank goby
- #40 Pila River: Native hito
- #38 Prinza River: Native hito, Tank goby
- #43 Romero River: Tank goby
- #1 Sampaloc Lake: Native hito, Tank goby
- #44 San Antonio River: Native hito
- #34 San Cristobal River: Native hito, Tank goby
- #31 San Isidro River: Native hito, Tank goby
- #35 San Juan River: Native hito, Tank goby
- #18 San Pedro River: Tank goby
- #17 Santa Cruz River: Loach goby, Tank goby
- #33 Santa Rosa River: Loach goby, Tank goby
- #42 Siniloan River: Loach goby, Tank goby
- #45 Tigas River: Native hito
- #19 Tunasan River: Tank goby
- #3 Yambo Lake: Tank goby

Wikimedia returned HTTP 429 after five originals; no further image requests were made. Missing or unusable photos retain labelled placeholders. The misleading Mindanao catfish file was rejected; the downloaded native-hito photograph was also excluded after visual review because prepared fish portions do not provide useful species identification. Originals are preserved in ignored recovery storage. The written three-column specification is used because no draw.io file is present. Large geography SELECT * with SQL sorting hit MySQL error 1038; use minimal columns/PHP sorting.

## Exact next action

Next batch: Aliw Falls; Ambon-Ambon Falls; Balon-Balon Falls; Bukal Falls. Validate, apply, checkpoint; preserve all prior batches.

## Recovery protocol

Read this file, git status/diff and actual rows before resuming. Files/database override stale text. Never discard uncommitted work or automatically rerun migrations/imports. habitat:import previews by default; --apply accepts only 3–5 names, locks the shared inventory, preserves existing species/occurrence rows and writes this checkpoint after success. Repeating a batch must change no rows/timestamps. Save after every batch and commit validated milestones locally; never push. Original public rows and protected-table/.env hashes are in ignored storage/app/fish-habitat/before.json; source metadata, originals and validation evidence remain ignored. Never commit .env or recovery artifacts.

## Modified files when saved

```text
M app/Console/Commands/HabitatCheckpoint.php
 M app/Support/HabitatCatalogue.php
 M database/data/fish-habitat.json
 M database/data/water-body-evidence.json
 M docs/EXPANSION_PROGRESS.md
 M docs/FISH_HABITAT_CHECKPOINT.md
 M tests/Feature/HabitatAssessmentTest.php
```
