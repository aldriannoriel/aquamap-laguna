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

`database/data/river-expansion.json` stores WGS84 longitude/latitude geometry, exact original way-node sequences, source URLs, way versions/dates, coverage notes and ODbL attribution. Every new line was checked against the original **OpenStreetMap API way and nodes**, requiring the expected name (or explicit alias), a river/stream waterway tag, complete node references and plausible Laguna-region coordinates. Multiple ways remain separate MultiLineString parts. No simplification, arbitrary connector, municipal centroid, watershed outline or synthetic river course was created.

The public [WaterwayMap Laguna directory](https://waterwaymap.org/river/region/PH-Laguna/) was used only to discover candidate **node** IDs, then the OSM node/ways and way/full endpoints established the actual geometry. Directory IDs must not be interpreted as way IDs: a candidate Santa Rosa ID resolved to an unrelated road and was rejected. No WaterwayMap geometry or calculated directory length is imported. A bulk Overpass request failed (TLS/HTTP 406); it was abandoned. Government centreline data with a reviewed license was not obtained.

Derived OSM geographic data is credited to [OpenStreetMap contributors](https://www.openstreetmap.org/copyright), under [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/). Original source objects and their metadata are retained. The application shows attribution and license links. These are community-mapped reference reaches, not surveyed, current-certified or legally authoritative lines. OSM's separate `boundary` tags do not make the river geometry a watershed or certified boundary.

Raw source snapshots, rejected candidates, hashes and preparation evidence are in ignored `storage/app/river-expansion/`; the committed catalogue and table below retain durable provenance without contributor account metadata. Existing source catalogues remain unchanged.

## Length and area semantics

`database/data/water-body-metrics.json` is an exact-name/locality presentation supplement. It never overwrites database records. Twenty-six approximate river lengths are source-checked against the transcription; Bumbungan/Pagsanjan is withheld as an ambiguous total, and San Pedro/Tunasan have no verified source total. Future corrections require reviewed evidence.

`WaterBodyMetrics::mappedLength` calculates only validated LineString/MultiLineString parts using great-circle distances on the IUGG mean-radius sphere (6,371,008.8 m), rounded to 0.001 km for display. This is a spherical geodesic approximation, not an ellipsoidal survey; vertex precision and rounding do not imply positional accuracy. It never bridges disconnected parts or substitutes a mapped segment length for a source total. All current river courses are partial.

Nine existing lake polygons support highlighting: Sampaloc, Yambo, Bunot, Mohicap, Calibato, Palakpakin, Caliraya, Lumot and Laguna de Bay. Pandin retains its source-checked shoreline point; a complete shoreline is still unavailable. No lake geometry was fabricated or replaced.

Historical source-reported lake surface areas are **Yambo 30.50 ha, Pandin 24.00 ha, Calibato 43.00 ha**, from [Bicaldo et al. (2024), Table 1](https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full#T1), which attributes these areas to LLDA (2014). Conversion is square metres ÷ 10,000. These figures are not computed from OSM polygons or presented as current surveys. Published estimates differ (the indexed Nagcarlan municipal account gives 28.5 ha for Yambo); the selected source and historical context remain visible. Other lake areas stay unknown. The Frontiers paper is [CC BY](https://creativecommons.org/licenses/by/4.0/); no laboratory measurements, microbial results or new fish associations are imported.

## Import and recovery

1. Confirm both configured and connected database names are `aquamap_laguna` and preserve recovery hashes before any persistent operation.
2. Apply only the forward `allow_unmapped_water_body_coordinates` migration. It makes latitude/longitude nullable so unmapped rivers need no fake coordinates. Existing values are preserved; automatic rollback is deliberately refused because it could discard unknown-coordinate profiles.
3. Run `php artisan rivers:import --no-interaction` serially. It validates the reviewed river catalogue, then calls the existing guarded transactional `ExplorerSeeder::import`. It adds only missing names/aliases and refuses identity/metadata conflicts. Run it again to verify unchanged values and timestamps. It never runs migrations, broader seeders or resets.
4. Existing `ExplorerSeeder::run` retains the original 30-profile baseline. New environments explicitly run the additive river command after that baseline; this avoids silently expanding prior recovery fixtures. The dedicated browser fixture imports rivers in memory only when `AQUAMAP_BROWSER_RIVERS=1`, then copies to confined disposable SQLite.

Unmapped profiles stay selectable and available in Report a Concern. A point marker is retained when a verified point exists; absent point evidence means no marker. The selection summary explicitly says “River course not yet mapped.” The blue highlight and fitBounds apply to verified river lines/lake polygons only. Clear selection resets emphasis. Full environmental profiles remain available through the separate Full profile button; ordinary selection leaves pan/zoom usable.

The test database guard, private report/photo/contact handling, server-assigned pending status, timing/honeypot/throttle protections and administrator review rules remain unchanged. See `CURRENT_STATE.md` for final execution evidence and `EXPANSION_PROGRESS.md` for the recovery checkpoint.

## Reviewed inventory

The rows below correspond to the 27 provincial entries. “Partial” always means selected named reaches, not full river coverage. All remaining rivers need independent point/centreline evidence.

<!-- Inventory rows are generated from the reviewed committed catalogues below. -->

| Provincial entry / existing canonical profile | Reference locality | Approx. source km | Geometry |
| --- | --- | ---: | --- |
| San Isidro River | San Pedro City | 4.90 | Not yet mapped |
| Biñan River | Biñan City | 7.10 | Partial; [26949260](https://www.openstreetmap.org/way/26949260) |
| Santa Rosa River | Santa Rosa City | 11.05 | Partial; [90984742](https://www.openstreetmap.org/way/90984742), [1446694906](https://www.openstreetmap.org/way/1446694906) |
| San Cristobal River | Calamba City | 18.90 | Partial; [27371773](https://www.openstreetmap.org/way/27371773), [110617725](https://www.openstreetmap.org/way/110617725) |
| San Juan River | Calamba City | 14.80 | Partial; [549175324](https://www.openstreetmap.org/way/549175324) |
| Bay River | Bay | 5.80 | Partial; [413242115](https://www.openstreetmap.org/way/413242115) |
| Calo River | Bay | 15.10 | Partial; [413242117](https://www.openstreetmap.org/way/413242117) |
| Prinza River | Calauan | 11.20 | Not yet mapped |
| Banca-Banca River | Victoria | 9.10 | Not yet mapped |
| Pila River | Pila | 2.70 | Not yet mapped |
| Santa Cruz River | Santa Cruz | 11.00 | Partial; [existing sources](RESEARCH_SOURCES.md) |
| Balanac River | Pagsanjan | 10.70 | Partial; [existing sources](RESEARCH_SOURCES.md) |
| Bumbungan River | Pagsanjan | Unverified (table: 4.00) | Partial; [existing sources](RESEARCH_SOURCES.md) |
| Pangil River | Pangil | 7.00 | Partial; [1428178449](https://www.openstreetmap.org/way/1428178449), [1428178453](https://www.openstreetmap.org/way/1428178453) |
| Siniloan River | Siniloan | 6.10 | Partial; [211563245](https://www.openstreetmap.org/way/211563245), [679990142](https://www.openstreetmap.org/way/679990142) |
| Romero River | Famy | 3.20 | Not yet mapped |
| San Antonio River | Santa Maria / Mabitac | 8.80 | Not yet mapped |
| Tigas River | Alaminos | 8.90 | Not yet mapped |
| Makpongo River | San Pablo City | 3.90 | Not yet mapped |
| Balatuin River | San Pablo City | 9.10 | Partial; [113962651](https://www.openstreetmap.org/way/113962651) |
| Malaunod River | San Pablo City | 9.45 | Partial; [476317798](https://www.openstreetmap.org/way/476317798), [1455171120](https://www.openstreetmap.org/way/1455171120) |
| Onipa River | San Pablo City | 6.00 | Not yet mapped |
| Tarak River | San Pablo City | 5.80 | Not yet mapped |
| Maimpis River | Nagcarlan | 9.00 | Not yet mapped |
| Dalitiwan River | Majayjay | 11.60 | Partial; [97211396](https://www.openstreetmap.org/way/97211396) |
| Botocan River | Luisiana | 3.90 | Partial; [131583391](https://www.openstreetmap.org/way/131583391) |
| Matikiw River | Pakil | 5.00 | Not yet mapped |
