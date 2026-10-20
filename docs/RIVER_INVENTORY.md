# Provincial rivers and map coverage

Reviewed 2026-10-21 (Asia/Manila). The 27 provincial inventory entries resolve to three existing profiles plus 24 additions. All five original river profiles, their IDs, photographs, fish links, reports and stored metadata are preserved. The resulting inventory contains **29 rivers**, including the existing San Pedro and Tunasan profiles outside this particular provincial list; 54 stored locations and 52 public profiles.

## Source and identity decisions

The initial source is the Provincial Government of Laguna's *PDPFP 2021–2030, Volume I*, Table 33, printed page 92, credited to PENRO/CENRO/PG-ENRO. Its publicly readable [document transcription](https://www.scribd.com/document/844066862/Laguna-Pdpfp-2021-2030-Volume-i) was inspected directly, including the table heading **approximate length (kms)**. This is a third-party-hosted copy, not an independently authenticated government facsimile. [DEPDev's government announcement](https://calabarzon.depdev.gov.ph/2022/12/07/calabarzon-rluc-endorses-batangas-and-lagunas-pdpfps/) corroborates the plan's existence and period, not each river length. Obtaining the original government table/map remains a research gap. We transcribe factual inventory values, not the plan's protected narrative or maps; no license to redistribute the full document is asserted.

The table's location column is a reference locality, not an exhaustive list of municipalities traversed. Geographic extent is verified only for the published segments; full upstream/downstream coverage remains unknown. New records explicitly say that missing location-specific fish and environmental evidence has not been imported.

- **Reuse Santa Cruz River**, Balanac River and Bumbungan River. Provincial “Sta. Cruz” means the existing Santa Cruz profile. Provincial Pagsanjan is represented by Bumbungan's existing Pagsanjan alias; no duplicate is added.
- **Pagsanjan/Bumbungan length remains unverified as a whole-river total.** The table's 4.00 km row refers to Pagsanjan/Lumban, whereas the existing named Bumbungan profile and mapping can include a different extent. The number is retained as an unverified research lead only; it is never displayed as a verified total or replaced by a calculated segment length.
- **San Isidro is distinct from San Pedro.** [DENR's first-party account](https://calabarzon.denr.gov.ph/news-events/icymi-denr-calabarzon-leads-joint-response-to-massive-fire-and-oil-spill-in-san-pedro-city-laguna/) identifies San Isidro as a tributary of San Pedro; preserve San Pedro and add San Isidro separately. The account is identity evidence, not an imported pollution report.
- Santa Rosa/Sta. Rosa and the named Silang–Santa Rosa system are represented once, with searchable aliases. Selected upstream OSM reaches include Cavite; the provincial Santa Rosa City locality is not a boundary. [PIA](https://pia.gov.ph/news/luzon/laguna-govt-san-miguel-corp-ink-agreement-on-laguna-lake-dredging/) also identifies the named Silang–Santa Rosa waterway.
- Balanac, Botocan and Dalitiwan are connected at a confluence but separately named watercourses in the provincial list and OSM. Their distinct named ways are retained; a shared endpoint is not grounds for merging records or joining missing reaches.

## Geometry provenance and licensing

The initial additive records in `database/data/river-expansion.json` remain unchanged. The current `database/data/river-courses.json` presentation supplement audits all **29** existing rivers, retaining original database records and matching each entry by exact canonical name, reference locality and baseline geometry source. Its **173 original OSM ways** provide **17 connected named courses**; **15 extend farther** than baseline 0119109. Bi?an and Dalitiwan have no additional attributable connected named ways in the retrieved sources. Twelve other rivers remain unmapped. No mapped course has independently verified full headwater-to-mouth coverage.

WaterwayMap's public river pages expose candidate way IDs in their JOSM object list; the directory's numeric identifiers are node IDs and must never be interpreted as way IDs. Original [OSM way/node APIs](https://wiki.openstreetmap.org/wiki/API_v0.6) then supplied full way metadata and every original node coordinate. Twenty discovery pages produced 203 candidate ways and 5,364 nodes. Name/explicit-alias, waterway type and region checks preceded connectivity review against existing source ways. Twenty-two disconnected same-name San Juan ways and eight disconnected Santa Rosa ways were excluded. An unrelated Taraka River candidate was in Lanao del Sur and was rejected. WaterwayMap geometry and calculated lengths are not imported.

Each geometry part exactly preserves its original way-node sequence; no simplification, connector, municipal clipping, inferred headwater, basin polygon or synthetic point is added. The application additionally validates shared-node coordinates, unique way IDs, no repeated undirected edges, connectivity, exactly two terminal nodes and no branching. This prevents a tributary network being summed as a single course. Original source IDs, revisions, timestamps, node IDs, source snapshot hashes and rejected candidate IDs remain in the committed supplement. Sanitized public source lists omit contributor account metadata. Original-way revisions do not date every node or certify positional accuracy.

Derived geographic data is credited to [OpenStreetMap contributors](https://www.openstreetmap.org/copyright), under [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/), with attribution/license links in the map. These are community-mapped named courses, without survey/legal certification. The supplement and its source-node metadata make the derived data available; source object links cover every imported way. Original API snapshots and preparation evidence are retained in ignored `storage/app/river-courses/`; earlier `river-expansion/` evidence is preserved.

## Length and area semantics

`database/data/water-body-metrics.json` is an exact-name/locality presentation supplement. It never overwrites database records. Twenty-six approximate river lengths are source-checked against the transcription; Bumbungan/Pagsanjan is withheld as an ambiguous total, and San Pedro/Tunasan have no verified source total. Future corrections require reviewed evidence.

The map labels **mapped course length** separately from the **provincial reported figure (approximate; extent unspecified)**. `WaterBodyMetrics::mappedLength` sums WGS84 ellipsoidal geodesics between consecutive source vertices, using [Vincenty's original inverse method](https://www.ngs.noaa.gov/PUBS_LIB/inverse.pdf) and [NGA WGS84 constants](https://earth-info.nga.mil/index.php?action=wgs84&dir=wgs84): semi-major axis 6,378,137 m, inverse flattening 298.257223563. Parts are never bridged, and a nonconvergent segment yields unknown. Distances are rounded to 0.001 km for display; rounding and coordinate digits do not imply survey accuracy. No calculated distance is promoted to a verified whole-river total or used to estimate a coverage percentage. A longer mapped distance than a provincial figure indicates an unresolved extent/source difference, not an automatic correction of that figure. Balanac now has 19.272 km mapped versus its original 0.741 km reach and the table's approximate 10.70 km of unspecified extent.

Nine existing lake polygons support highlighting: Sampaloc, Yambo, Bunot, Mohicap, Calibato, Palakpakin, Caliraya, Lumot and Laguna de Bay. Pandin retains its source-checked shoreline point; a complete shoreline is still unavailable. No lake geometry was fabricated or replaced.

Historical source-reported lake surface areas are **Yambo 30.50 ha, Pandin 24.00 ha, Calibato 43.00 ha**, from [Bicaldo et al. (2024), Table 1](https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full#T1), which attributes these areas to LLDA (2014). Conversion is square metres ÷ 10,000. These figures are not computed from OSM polygons or presented as current surveys. Published estimates differ (the indexed Nagcarlan municipal account gives 28.5 ha for Yambo); the selected source and historical context remain visible. Other lake areas stay unknown. The Frontiers paper is [CC BY](https://creativecommons.org/licenses/by/4.0/); no laboratory measurements, microbial results or new fish associations are imported.

## Import and recovery

1. Confirm both configured and connected database names are `aquamap_laguna` and preserve recovery hashes before any persistent operation.
2. Apply only the forward `allow_unmapped_water_body_coordinates` migration. It makes latitude/longitude nullable so unmapped rivers need no fake coordinates. Existing values are preserved; automatic rollback is deliberately refused because it could discard unknown-coordinate profiles.
3. Run `php artisan rivers:import --no-interaction` serially. It validates the reviewed river catalogue, then calls the existing guarded transactional `ExplorerSeeder::import`. It adds only missing names/aliases and refuses identity/metadata conflicts. Run it again to verify unchanged values and timestamps. It never runs migrations, broader seeders or resets.
4. Existing `ExplorerSeeder::run` retains the original 30-profile baseline. New environments explicitly run the additive river command after that baseline; this avoids silently expanding prior recovery fixtures. The dedicated browser fixture imports rivers in memory only when `AQUAMAP_BROWSER_RIVERS=1`, then copies to confined disposable SQLite.

Unmapped profiles stay selectable and available in Report a Concern. A point marker is retained when a verified point exists; absent point evidence means no marker. The selection summary explicitly says “River course not yet mapped.” The blue highlight and fitBounds apply to verified river lines/lake polygons only. Clear selection resets emphasis. Full environmental profiles remain available through the separate Full profile button; ordinary selection leaves pan/zoom usable.

The test database guard, private report/photo/contact handling, server-assigned pending status, timing/honeypot/throttle protections and administrator review rules remain unchanged. See `CURRENT_STATE.md` for final execution evidence and `EXPANSION_PROGRESS.md` for the recovery checkpoint.

## All-29 course audit against baseline 0119109

Reviewed 2026-10-21. Before and after distances below use the same WGS84 ellipsoid, separating geometry changes from the former spherical calculation. These distances describe supplied mapping only. **Whole-river length: unverified for every row.** The source count links to the first original way; the [complete catalogue](../database/data/river-courses.json) lists every way and revision. Reference localities are not clipping boundaries. Bumbungan's provincial 4.00 km lead remains withheld as a whole-river figure; San Pedro/Tunasan have no verified reported totals.

| River | Reference locality | Before km | Mapped km | Provincial approx. km | Source ways |
| --- | --- | ---: | ---: | ---: | --- |
| Balanac River | Pagsanjan | 0.741 | 19.272 | 10.70 | 20; [first way](https://www.openstreetmap.org/way/118529511) |
| Balatuin River | San Pablo City | 0.167 | 8.252 | 9.10 | 8; [first way](https://www.openstreetmap.org/way/25423124) |
| Banca-Banca River | Victoria | Unknown | Unknown | 9.10 | Not yet mapped |
| Bay River | Bay | 0.544 | 4.560 | 5.80 | 6; [first way](https://www.openstreetmap.org/way/26451207) |
| Biñan River | Biñan City | 4.980 | 4.980 | 7.10 | 1; [first way](https://www.openstreetmap.org/way/26949260) |
| Botocan River | Luisiana | 0.782 | 8.567 | 3.90 | 5; [first way](https://www.openstreetmap.org/way/99663609) |
| Bumbungan River | Pagsanjan | 2.501 | 38.150 | Unverified | 21; [first way](https://www.openstreetmap.org/way/114040110) |
| Calo River | Bay | 1.442 | 12.677 | 15.10 | 7; [first way](https://www.openstreetmap.org/way/37779871) |
| Dalitiwan River | Majayjay | 2.518 | 2.518 | 11.60 | 1; [first way](https://www.openstreetmap.org/way/97211396) |
| Maimpis River | Nagcarlan | Unknown | Unknown | 9.00 | Not yet mapped |
| Makpongo River | San Pablo City | Unknown | Unknown | 3.90 | Not yet mapped |
| Malaunod River | San Pablo City | 1.096 | 3.434 | 9.45 | 11; [first way](https://www.openstreetmap.org/way/476317798) |
| Matikiw River | Pakil | Unknown | Unknown | 5.00 | Not yet mapped |
| Onipa River | San Pablo City | Unknown | Unknown | 6.00 | Not yet mapped |
| Pangil River | Pangil | 0.453 | 11.690 | 7.00 | 5; [first way](https://www.openstreetmap.org/way/109868348) |
| Pila River | Pila | Unknown | Unknown | 2.70 | Not yet mapped |
| Prinza River | Calauan | Unknown | Unknown | 11.20 | Not yet mapped |
| Romero River | Famy | Unknown | Unknown | 3.20 | Not yet mapped |
| San Antonio River | Santa Maria / Mabitac | Unknown | Unknown | 8.80 | Not yet mapped |
| San Cristobal River | Calamba City | 2.362 | 10.562 | 18.90 | 8; [first way](https://www.openstreetmap.org/way/27371773) |
| San Isidro River | San Pedro City | Unknown | Unknown | 4.90 | Not yet mapped |
| San Juan River | Calamba City | 0.537 | 9.873 | 14.80 | 13; [first way](https://www.openstreetmap.org/way/123544407) |
| San Pedro River | San Pedro City | 0.840 | 19.555 | Unverified | 26; [first way](https://www.openstreetmap.org/way/77319896) |
| Santa Cruz River | Santa Cruz | 1.853 | 15.605 | 11.00 | 21; [first way](https://www.openstreetmap.org/way/30705665) |
| Santa Rosa River | Santa Rosa City | 1.067 | 11.834 | 11.05 | 6; [first way](https://www.openstreetmap.org/way/90546142) |
| Siniloan River | Siniloan | 3.647 | 4.526 | 6.10 | 4; [first way](https://www.openstreetmap.org/way/211563245) |
| Tarak River | San Pablo City | Unknown | Unknown | 5.80 | Not yet mapped |
| Tigas River | Alaminos | Unknown | Unknown | 8.90 | Not yet mapped |
| Tunasan River | San Pedro / Muntinlupa boundary area | 1.596 | 11.598 | Unverified | 10; [first way](https://www.openstreetmap.org/way/91237596) |

## Evidence gaps for the twelve unmapped profiles

- **San Isidro:** [DENR](https://calabarzon.denr.gov.ph/news-events/icymi-denr-calabarzon-leads-joint-response-to-massive-fire-and-oil-spill-in-san-pedro-city-laguna/) identifies it as a San Pedro tributary. That does not license assigning San Pedro's differently named centreline to San Isidro.
- **Prinza:** [JICA's locality evidence](https://openjicareport.jica.go.jp/pdf/11750908_01.pdf) is a research lead; no reusable source-checked river line was retrieved. Barangay coordinates are not a course.
- **Banca-Banca / Pila:** [LLDA's Pila sub-basin material](https://llda.gov.ph/wp-content/uploads/2019/09/Vol_15.Pila-SB.pdf) distinguishes the systems. Watershed outlines and total drainage-network lengths are not individual named centrelines or whole-river lengths. LLDA direct retrieval was restricted; indexed passages were reviewed only.
- **Romero:** [LLDA's Siniloan-Famy report](https://llda.gov.ph/wp-content/uploads/2019/09/Vol_10.Siniloan-SB.pdf) uses Mayor/Romelo/Romero naming in the Siniloan system; [its 2017 annual report](https://llda.gov.ph/wp-content/uploads/dox/ar/ar17.pdf) also calls Siniloan River Romelo. An alternate-spelling OSM search retrieved [Romelo way 545851314](https://www.openstreetmap.org/way/545851314) in Siniloan. Its assignment to the provincial Famy entry, independently of the existing Siniloan profile, remains unresolved. No course was duplicated under a guessed identity.
- **San Antonio:** [BSWM's Laguna material](https://www.bswm.da.gov.ph/wp-content/uploads/Laguna.pdf) provides Santa Maria/Mabitac river/floodplain context; no attributable named line was retrieved.
- **Tigas:** Alaminos government's [Climate and Disaster Risk Assessment, River Systems map](https://alaminoslaguna.com/wp-content/uploads/2020/07/Climate-and-Disaster-Risk-Assessment.pdf) labels Tigas but uses GCS Luzon 1911 and mixed source mapping. A reusable georeferenced centreline, licensed vector data and verified transformation were not obtained. The map was not traced into guessed WGS84 coordinates.
- **Onipa:** a named GeoNames gazetteer point is a discovery lead only. It supplies no upstream/downstream line and is not substituted for a river course.
- **Makpongo / Tarak:** the provincial table establishes names/localities; possible Makampongo/Tarac spellings remain unverified identity leads. Exact/alternate OSM-index searches retrieved no attributable line. The unrelated Taraka waterway in Lanao del Sur was rejected.
- **Maimpis:** [JICA's geography/geology report](https://openjicareport.jica.go.jp/pdf/11197951_01.pdf) identifies the river and regional drainage. Maimpes/Manipis gazetteer spellings were checked as leads, with no attributable line retrieved. Neighboring Santa Cruz/Liliw/Nagcarlan courses are not substitutes.
- **Matikiw:** the provincial entry/locality does not identify which channel is the named river. Barangay boundaries, irrigation canals and lake-shore references were not assigned a river identity.

Public Nominatim searches were spaced at least 1.1 seconds apart: all twelve exact-name queries returned no result; five alternate spellings also returned none, while Romelo returned the ambiguous Siniloan candidate above. An index miss does not prove that no geographic data exists. The main Overpass endpoint failed TLS; the officially listed public Private.coffee instance timed out without a response. No access controls or rate limits were bypassed. [NAMRIA Geoportal](https://geoportal.gov.ph/) metadata lists waterbody 1:10,000/1:50,000 and Manila Bay river-network layers with downloads disabled; its available major-basin polygons do not supply named centreline geometry. A legacy NREL river WFS host failed DNS resolution. Restricted downloads/captcha flows were not bypassed. These access limits and raw query results are preserved in ignored evidence storage.

The application records each gap and leaves missing course/point values unknown. To extend coverage later, obtain attributable original centrelines with a usable license, declared CRS/datum and enough name/locality evidence to distinguish aliases and connected tributaries. A map image, an approximate source length, or a nearby unnamed stream is insufficient.

## Audit and recovery of the course supplement

After the existing baseline/initial additive import, no new persistent migration or seeding is needed: MapController applies the reviewed supplement on read, without changing any of the 54 records, IDs, timestamps, descriptions, points, environmental links, photos or reports. Original reach notes remain available as historical provenance in Research & Sources. An identity/source or geometry validation failure falls back to the original sanitized reach.

Run `php artisan rivers:audit-courses --json --no-interaction` to inspect all stored rivers, baseline/mapped distances, source counts, coverage notes and unknown whole-river totals. The command is read-only and refuses any persistent database other than configured and connected aquamap_laguna. Normal migrations/imports for the earlier expansion remain as described above. Future edits to stored identity/source metadata require re-review before the supplement applies.
