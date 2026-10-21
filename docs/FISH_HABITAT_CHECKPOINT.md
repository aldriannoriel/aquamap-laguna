# Fish & Habitat crash recovery checkpoint

Updated: 2026-10-21T16:24:51+00:00

## Actual state

- Phase: Incremental implementation; completed batches retained.
- Git HEAD when saved: 1c4033d. A later checkpoint commit may contain this file; git log -1 is authoritative.
- Last successful operation: inspected actual catalogue/relationships and saved this checkpoint.
- Water Type reviewed/display coverage: 11/53; possible fish coverage: 11/53; habitat explanation coverage: 11/53.
- Inventory: 53 supported; {"waterfall":13,"river":29,"lake":11}; 55 stored including two preserved hidden legacy properties.
- Fish: 8 stored species; 59 reviewed possible assignments; 56 new potential-only pivots. Original occurrence rows are preserved separately.
- Fish photographs installed: 4/9 species. Missing photos use labelled placeholders, never another species image.
- References: 9 species-habitat references reviewed; every complete candidate has an explanation, requirements, limitations and source links. Existing research/naming/occurrence citations remain.
- Database changes: only explicit additive habitat batches and missing water_type labels. No water body added/deleted, schema operation, report/agency/referral/GIS/photo modification or live email.
- Last successful tests: 8 habitat tests / 1411 assertions passed for batch 3; 2026-10-22. Current catalogue matches recorded feature validation.

## Completed and pending IDs/names

| ID | Water body | Water Type | Possible Fish | Photos |
| --- | --- | --- | --- | --- |
| 23 | Aliw Falls | Pending | Pending | 0/0 |
| 30 | Ambon-Ambon Falls | Pending | Pending | 0/0 |
| 10 | Balanac River | Pending | Pending | 0/0 |
| 47 | Balatuin River | Pending | Pending | 0/0 |
| 29 | Balon-Balon Falls | Pending | Pending | 0/0 |
| 39 | Banca-Banca River | Pending | Pending | 0/0 |
| 36 | Bay River | Pending | Pending | 0/0 |
| 32 | Biñan River | Pending | Pending | 0/0 |
| 53 | Botocan River | Pending | Pending | 0/0 |
| 26 | Bukal Falls | Pending | Pending | 0/0 |
| 9 | Bumbungan River | Pending | Pending | 0/0 |
| 21 | Bunga Twin Falls | Pending | Pending | 0/0 |
| 5 | Bunot Lake | Reviewed | 5 candidates | 4/5 |
| 22 | Buntot Palos Falls | Pending | Pending | 0/0 |
| 24 | Buruwisan Falls | Pending | Pending | 0/0 |
| 12 | Calibato Lake | Reviewed | 5 candidates | 4/5 |
| 14 | Caliraya Lake | Reviewed | 6 candidates | 4/6 |
| 37 | Calo River | Pending | Pending | 0/0 |
| 52 | Dalitiwan River | Pending | Pending | 0/0 |
| 27 | Dampalit Falls | Pending | Pending | 0/0 |
| 7 | Hulugan Falls | Pending | Pending | 0/0 |
| 16 | Laguna de Bay | Reviewed | 8 candidates | 4/8 |
| 25 | Lanzones Falls | Pending | Pending | 0/0 |
| 15 | Lumot Lake | Reviewed | 6 candidates | 4/6 |
| 51 | Maimpis River | Pending | Pending | 0/0 |
| 46 | Makpongo River | Pending | Pending | 0/0 |
| 48 | Malaunod River | Pending | Pending | 0/0 |
| 54 | Matikiw River | Pending | Pending | 0/0 |
| 4 | Mohicap Lake | Reviewed | 4 candidates | 3/4 |
| 49 | Onipa River | Pending | Pending | 0/0 |
| 6 | Pagsanjan Falls | Pending | Pending | 0/0 |
| 13 | Palakpakin Lake | Reviewed | 5 candidates | 4/5 |
| 2 | Pandin Lake | Reviewed | 5 candidates | 3/5 |
| 41 | Pangil River | Pending | Pending | 0/0 |
| 40 | Pila River | Pending | Pending | 0/0 |
| 38 | Prinza River | Pending | Pending | 0/0 |
| 43 | Romero River | Pending | Pending | 0/0 |
| 1 | Sampaloc Lake | Reviewed | 6 candidates | 4/6 |
| 44 | San Antonio River | Pending | Pending | 0/0 |
| 34 | San Cristobal River | Pending | Pending | 0/0 |
| 31 | San Isidro River | Pending | Pending | 0/0 |
| 35 | San Juan River | Pending | Pending | 0/0 |
| 18 | San Pedro River | Pending | Pending | 0/0 |
| 17 | Santa Cruz River | Pending | Pending | 0/0 |
| 33 | Santa Rosa River | Pending | Pending | 0/0 |
| 42 | Siniloan River | Pending | Pending | 0/0 |
| 55 | Tadlac Lake | Reviewed | 4 candidates | 4/4 |
| 8 | Talay Falls | Pending | Pending | 0/0 |
| 50 | Tarak River | Pending | Pending | 0/0 |
| 20 | Taytay Falls | Pending | Pending | 0/0 |
| 45 | Tigas River | Pending | Pending | 0/0 |
| 19 | Tunasan River | Pending | Pending | 0/0 |
| 3 | Yambo Lake | Reviewed | 5 candidates | 4/5 |

Pending Possible Fish: #23 Aliw Falls; #30 Ambon-Ambon Falls; #10 Balanac River; #47 Balatuin River; #29 Balon-Balon Falls; #39 Banca-Banca River; #36 Bay River; #32 Biñan River; #53 Botocan River; #26 Bukal Falls; #9 Bumbungan River; #21 Bunga Twin Falls; #22 Buntot Palos Falls; #24 Buruwisan Falls; #37 Calo River; #52 Dalitiwan River; #27 Dampalit Falls; #7 Hulugan Falls; #25 Lanzones Falls; #51 Maimpis River; #46 Makpongo River; #48 Malaunod River; #54 Matikiw River; #49 Onipa River; #6 Pagsanjan Falls; #41 Pangil River; #40 Pila River; #38 Prinza River; #43 Romero River; #44 San Antonio River; #34 San Cristobal River; #31 San Isidro River; #35 San Juan River; #18 San Pedro River; #17 Santa Cruz River; #33 Santa Rosa River; #42 Siniloan River; #8 Talay Falls; #50 Tarak River; #20 Taytay Falls; #45 Tigas River; #19 Tunasan River

## Photo gaps and known issues

- #5 Bunot Lake: Native hito
- #12 Calibato Lake: Tank goby
- #14 Caliraya Lake: Native hito, Tank goby
- #16 Laguna de Bay: Native hito, Kanduli, Tank goby, Bangus
- #15 Lumot Lake: Native hito, Tank goby
- #4 Mohicap Lake: Native hito
- #13 Palakpakin Lake: Native hito
- #2 Pandin Lake: Native hito, Tank goby
- #1 Sampaloc Lake: Native hito, Tank goby
- #3 Yambo Lake: Tank goby

Wikimedia returned HTTP 429 after five originals; no further image requests were made. Missing or unusable photos retain labelled placeholders. The misleading Mindanao catfish file was rejected; the downloaded native-hito photograph was also excluded after visual review because prepared fish portions do not provide useful species identification. Originals are preserved in ignored recovery storage. The written three-column specification is used because no draw.io file is present. Large geography SELECT * with SQL sorting hit MySQL error 1038; use minimal columns/PHP sorting.

## Exact next action

Next batch: Balanac River; Balatuin River; Banca-Banca River; Bay River. Validate, apply, checkpoint; preserve all prior batches.

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
