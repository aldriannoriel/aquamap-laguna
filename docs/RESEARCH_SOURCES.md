# Research sources

## Remaining Batch 3: citation audit (2026-10-21)

All **54 profiles** were reviewed against c59862b: **497 source uses → 341 document citations**, consolidating **156 duplicate displays** while retaining each claim, query version, fragment/page, original OSM revision and attribution. Source categories are Geography and GIS; Physical measurements; Water type and hydrology; Fish and habitat; Historical ecological evidence; Photography and licensing. A shared document appears once with cross-references from its other categories. Existing catalogue caveats are explicitly distinguished from additional source findings.

The audit reached **246 of 248 original URLs**, including the local permission declaration; the Senate bill record and AllTrails photo listing still returned HTTP 403 after one retry. They are retained as unresolved access, not declared invalid. No confirmed broken URL or incorrect current-profile assignment was established. Known lake-paper scope now rejects a mismatched-profile citation without changing stored references. **10 citation entries lack supplied author/organization metadata and 318 lack publication years**; these are explicit fallbacks, not fabricated dates. Four existing photo credits still qualify unidentified/unspecified creators. Map revisions, capture dates, plan periods and source-check dates remain separate from publication years.

Metadata enrichment rechecked the publisher pages for [Bicaldo et al. (2024)](https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full), [Mabansag et al. (2019)](https://link.springer.com/article/10.1007/s00128-019-02685-z), the NFRDI publisher/index for the existing Ortiz et al. (2025) reference, and the existing FAO/SEAFDEC sources documented below. Full source titles, first author/organization and known publication dates are presentation metadata in research-source-metadata.json; no water type, fish occurrence, measurement or geometry is changed. Both Plapla references and all photograph/OSM licenses remain.

See [RESEARCH_AUDIT.md](RESEARCH_AUDIT.md) for the 54-profile inventory, source limitations, unresolved URLs and rerun instructions; [research-source-audit.json](research-source-audit.json) contains every dated URL result and per-profile count. The link checker is an explicit guarded maintenance task, never a public request. Batch 4 remains unapproved.

## Remaining Batch 2: Filipino fish names (2026-10-21)

The verified project database contains **one existing species**, Nile tilapia (Oreochromis niloticus), with three historical aquaculture links only (Sampaloc/Pandin/Yambo). **One has a verified Filipino name; zero existing species remain without one.** English/scientific names and all stored species/links remain unchanged. The existing [FAO 2009 fact sheet](https://www.fao.org/fishery/docs/CDrom/aquaculture/I1129m/file/en/en_niletilapia.htm) was rechecked and explicitly pairs the English name Nile tilapia with Oreochromis niloticus.

- **Plapla:** Eguia, R. V., Eguia, M. R. R., and Basiao, Z. U. (2001), *Pagpapalaki ng tilapya*, SEAFDEC/AQD Aquaculture Extension Manual No. 22, revised July edition, printed p. 1 (PDF page 9). The Filipino text explicitly pairs plapla with Oreochromis niloticus. [Primary manual](https://repository.seafdec.org.ph/bitstream/handle/10862/6507/EguiaRV2001-AEM22.pdf?sequence=1#page=9).
- **Independent publication corroboration:** Dela Cruz, J. M. (2019), *SEAFDEC/AQD expert helps identify local food fish*, AQD Matters, January-February, p. 10. Its identification sheet pairs Nile tilapia / Plapla with Oreochromis niloticus. [Primary newsletter](https://www.seafdec.org.ph/wp-content/uploads/2019/01/AQD-Matters_Jan-Feb-2019-issue.pdf#page=10).

`database/data/fish-name-evidence.json` is presentation-only and matched by exact scientific name, reviewed status and Filipino language. A nonempty name/note and safe source reference are required. Cards link the name source; Research & Sources lists both publications with authors/organization, year, page and the supported naming claim. Common names are not unique species identifiers. These sources do not add location-specific occurrence, current presence, water classifications or habitat conclusions. Missing/unreviewed/mismatched names remain Not yet verified; historical aquaculture/wild/incomplete labels and insufficient suitability data remain distinct. Other species mentioned by these publications were not added to the project. General citation cleanup remains outside this batch.

## Approved redesign draft evidence (2026-10-21)

The read-only water-body-evidence.json supplement uses Bicaldo et al. (2024), Frontiers in Microbiology 15:1410666, study-site freshwater context, for Yambo, Pandin and Calibato only. The source is https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full. These are historical hydrological labels, not current salinity measurements or DENR water-quality classes. No type is inferred from river geometry or fish occurrence. Other types remain unverified; Filipino naming evidence is documented above. Original classifications, historical aquaculture studies, source URLs and GIS data are preserved.

The public report aggregate exception is withdrawn by the newer redesign request: no report counts or individual details are projected/queryable through the public map. Administrator-only moderation/evidence remains. New proof photos are required by the owner-confirmed policy; historical photo-less records remain valid.


## Provincial rivers and geographic measurements (2026-10-21)

The current provincial river inventory and all new length/area/geometry evidence are documented in [RIVER_INVENTORY.md](RIVER_INVENTORY.md), including the 27 source rows, three reused profiles, 24 additions and the subsequent all-29 course audit. `river-courses.json` now supplies 173 original OSM ways for 17 connected named courses, extending 15 highlights; twelve courses remain missing. Complete headwater-to-mouth coverage and whole-river totals are not independently verified. All 54 stored records and the prior catalogues below remain unchanged. The supplement retains original node sequences, object versions/dates, source links and ODbL attribution. Geometry is checked by name/alias, locality, baseline-source identity, source-node connectivity, no duplicate edges and no branches; unrelated same-name San Juan/Santa Rosa ways were rejected. WaterwayMap pages discover way IDs only; original OSM way/node APIs supply coordinates, not WaterwayMap length estimates. No municipality clipping is applied.

Mapped lengths use WGS84 ellipsoidal geodesics: [NGA's WGS84 definition](https://earth-info.nga.mil/index.php?action=wgs84&dir=wgs84), semi-major axis 6,378,137 m and inverse flattening 298.257223563; [Vincenty (1975), original equations](https://www.ngs.noaa.gov/PUBS_LIB/inverse.pdf). Every supplied consecutive vertex pair is measured, parts are never bridged and nonconvergence yields unknown. These are calculated source-centreline lengths, not surveyed accuracy or independently verified totals. The metrics supplement retains 26 approximate lengths from the public PDPFP Table 33 transcription, with explicit third-party-copy limits; Pagsanjan/Bumbungan's ambiguous total is withheld. Historical Yambo/Pandin/Calibato surface areas come from Bicaldo et al. (2024), Table 1, with its LLDA (2014) attribution; other areas remain unknown. No environmental measurements, fish associations or reports were synthesized.

The missing-course review includes exact-name and alternate-spelling OSM index checks, LLDA/JICA/municipal evidence, and NAMRIA Geoportal layer metadata. Sources that supplied only points, watershed polygons, ambiguous names, an unverified datum, or restricted downloads were not converted into river courses. The all-29 inventory records specific gaps and access failures; a failed query is not evidence that a river does not exist. Ignored original API snapshots and browser evidence are in `storage/app/river-courses/`.

Reviewed on 2026-10-17 for Days 1-3 and the GIS expansion. The expansion adds source-checked display geography alongside the original published points, without replacing them. No scientific measurements or suitability conclusions are seeded.

## GIS expansion: imported inventory

`database/data/explorer.json` is the original eleven-profile snapshot. The recovered `database/data/missing-water-bodies.json` adds seventeen reviewed profiles. `ExplorerSeeder` reads both explicitly and serially, preserving existing IDs, coordinate columns, descriptions, source URLs and fish links. The original `DatabaseSeeder` remains unchanged. This remains **a subset, not a complete provincial inventory**. See [EXPANSION_PROGRESS.md](EXPANSION_PROGRESS.md) for execution status.

`source_verified` means the named source object and coordinate representation were inspected. It does **not** mean surveyed, field-verified, current, legally authoritative or accurate to the stored decimal places. OSM uses [WGS84](https://wiki.openstreetmap.org/wiki/Converting_to_WGS84). GeoJSON stores longitude, latitude; Leaflet converts for display. The original journal points' datum/precision limitation below remains unresolved.

| Imported profile | Represented locality | Primary geographic source / shape | Revision inspected |
| --- | --- | --- | --- |
| Sampaloc Lake | San Pablo City | Polygon, [OSM relation 1557746](https://www.openstreetmap.org/relation/1557746) | v15, 2026-04-06 |
| Pandin Lake | San Pablo City | Shoreline node 420519219 from [way 24821810](https://www.openstreetmap.org/way/24821810), member of [Pandin relation 10521183](https://www.openstreetmap.org/relation/10521183) | way v17, 2019-12-31 |
| Yambo Lake | San Pablo project locality; existing multi-municipality qualification retained | Polygon, [OSM relation 6476645](https://www.openstreetmap.org/relation/6476645) | v7, 2026-04-06 |
| Bunot Lake | San Pablo City | Polygon, [OSM way 24821711](https://www.openstreetmap.org/way/24821711) | v20, 2026-04-06 |
| Mohicap Lake | San Pablo City | Polygon, [OSM way 25032575](https://www.openstreetmap.org/way/25032575) | v16, 2023-09-03 |
| Bumbungan / Pagsanjan River | Selected reaches in Pagsanjan | MultiLineString, ways [773118744](https://www.openstreetmap.org/way/773118744), [1258614370](https://www.openstreetmap.org/way/1258614370) | v3, 2024-03-07; v2, 2025-09-12 |
| Balanac River | Selected reaches in Pagsanjan; not complete Magdalena coverage | MultiLineString, ways [146487700](https://www.openstreetmap.org/way/146487700), [146487702](https://www.openstreetmap.org/way/146487702), [1258614372](https://www.openstreetmap.org/way/1258614372), [1258681190](https://www.openstreetmap.org/way/1258681190) | v4/v5/v1/v1, 2024-03-07 |
| Pagsanjan / Cavinti / Magdapio Falls | Cavinti | [OSM node 1332879056](https://www.openstreetmap.org/node/1332879056): 14.2625628 N, 121.4999861 E | v6, 2025-08-02 |
| Hulugan Falls | Luisiana | [OSM node 4418310415](https://www.openstreetmap.org/node/4418310415): 14.2107508 N, 121.4801151 E | v4, 2020-07-11 |
| Talay Falls | Luisiana | [OSM node 5682123306](https://www.openstreetmap.org/node/5682123306): 14.2108556 N, 121.4823098 E | v1, 2018-06-11 |
| Al Fresco Springs Resort | Los Baños | Operator's [Location page](https://alfrescosprings.com/location): 14.176666 N, 121.209719 E | Publication date unavailable; checked 2026-10-17 |

Complete OSM outer rings were joined by shared node identity, without invented closing segments or simplification. All referenced nodes were available. Pandin's retrieved way is open; its complete relation was unavailable, so an existing shoreline node is used, explicitly **not a centroid, entrance or full polygon**. The original river ways below came from a small Pagsanjan bounding-box response; the current course supplement extends those reaches without changing this historical catalogue. Polygon/line selection markers use display bounds centers, not surveyed points. Reporting uses a geometry vertex as an orientation reference; lake selection never assigns an incident point.

OSM-derived geographic data in the snapshot is available under the [Open Database License 1.0](https://opendatacommons.org/licenses/odbl/1-0/); retain attribution and applicable share-alike terms. The map links [OSM licensing](https://www.openstreetmap.org/copyright). Revision dates above are editing dates, not scientific publication/sampling dates. Search engines/Mapcarta/Wikipedia helped locate IDs; imported coordinates came from OSM. [Pagsanjan LGU](https://pagsanjan.gov.ph/) corroborates tourism context; [Proclamation 1551, 31 May 1976](https://lawphil.net/executive/proc/proc1976/proc_1551_1976.html) identifies the gorge park in Cavinti. [The falls article](https://en.wikipedia.org/wiki/Pagsanjan_Falls) corroborates aliases, which do not create duplicate records.

### Resort verification and withheld candidates

| Candidate | Reviewed evidence | Publication decision / gaps |
| --- | --- | --- |
| Al Fresco Springs | [Operator overview](https://alfrescosprings.com/) states natural hot spring water without heaters. Its location page publishes matching resort GeoCoordinates and a linked map marker. [Schema.org GeoCoordinates](https://schema.org/GeoCoordinates) specifies WGS84. | Imported as **one resort reference**, not a spring-source marker or three separate villa properties. Hot/geothermal water remains an operator claim, not independent hydrogeological testing. Source location, temperatures, chemistry and safety remain unestablished. The operator cautions about navigation accuracy. |
| Hidden Valley Springs | [Operator](https://www.hiddenvalleyspringsresort.com/) describes a natural springs resort. Current [OSM way 273625694](https://www.openstreetmap.org/way/273625694) has `fixme=polygon shape` and `source=chatGPT;Web browsing`. Saved history shows the 2014 outline predates that source tag. [Node 2784637481](https://www.openstreetmap.org/node/2784637481), version 1, is unchanged since 2014-04-12 and belongs to that original outline. | Recovered catalogue uses only that historical **property-edge reference**, 14.0945693 N, 121.2597885 E. It is not an entrance or spring source; the outline is withheld. The polygon warning dates to 2015; the older point has no surveyed accuracy guarantee. Alaminos/Calauan locality remains qualified. Natural supply is an operator claim; temperature, chemistry and safety remain unknown. |
| Dolcezza Hotspring Resort | [Operator](https://www.dolcezzahotspring.com/) describes natural hot spring water and an address in Lalakay, Los Baños. | **Withheld:** property/source WGS84 point, temperatures, chemistry and photo permission are unverified. |
| Tirta Spring Villa | [Operator resorts](https://www.tirtaresorts.com/resorts) and [villa page](https://www.tirtaresorts.com/spring-villa) describe a natural hot spring pool and Los Baños address. | **Withheld:** embedded metadata contained coordinates outside Los Baños; the site also advertises Batangas properties. They were not used for this villa. Need property-specific geography and image permission. |
| Stellaluna Private Resort | Supplied [booking page](https://stellaluna-altaluna.com/book) could not be retrieved by the research browser. | **Withheld:** natural/geothermal supply, location, classification and image permission remain unverified. A name or swimming pool is insufficient. |

No operator health/therapeutic claims, temperatures, measurements, fish presence or current pollution findings were imported. Operator publication dates are unavailable; review date is not substituted. No proprietary map geometry was scraped. An independent visual Google Maps comparison was not completed.

### Photographs and environmental limits

The original eight downloaded JPEGs are preserved under `public/images/water-bodies/`. Complete credits, source-page and permission links are in the catalogue and displayed with each image. Saved Commons API metadata in `storage/app/inventory-photos.json` and `inventory-photo-candidates.json` corroborates the permissions below. Display may crop images; thumbnails are identified. Resort-site photographs and private report evidence are not reused.

Photo completion on 2026-10-18 added ten reusable Commons photographs through the read-only `database/data/explorer-images.json` supplement, preserving these eight original files/credits and every geographic/scientific record. There are now 18 images and ten placeholders. [IMAGE_INVENTORY.md](IMAGE_INVENTORY.md) records all sources, authors, linked licenses, capture dates, subject checks, optimization and missing-permission/identity outcomes. Portrait Hulugan/Bukal images are fitted without cropping; historical photographs do not establish current conditions.

| Profile / filename | Source and author | Reuse permission |
| --- | --- | --- |
| Bunot / `bunot-20240512.jpg` | [Bunot Lake 20240512](https://commons.wikimedia.org/wiki/File:Bunot_Lake_20240512.jpg), Ian Lopez (Ianlopez1115), photographed 2024-05-12 | [Attribution only](https://commons.wikimedia.org/wiki/User:Ianlopez1115/License_AttributionOnly), not CC BY; original downloaded photograph |
| Sampaloc / `sampaloc.jpg` | [7260San Pablo City Barangays Landmarks 20](https://commons.wikimedia.org/wiki/File:7260San_Pablo_City_Barangays_Landmarks_20.jpg), Judgefloro | CC0, 960 px thumbnail |
| Pandin / `pandin.jpg` | [Lake Pandin](https://commons.wikimedia.org/wiki/File:Lake_Pandin.jpg), Barrera marquez | CC BY 3.0, 960 px thumbnail |
| Yambo / `yambo.jpg` | [Lake Yambo](https://commons.wikimedia.org/wiki/File:Lake_Yambo.jpg), Barrera marquez | CC BY 3.0, 960 px thumbnail |
| Calibato / `calibato.jpg` | [Lake Calibato](https://commons.wikimedia.org/wiki/File:Lake_Calibato.jpg), Barrera marquez | CC BY 3.0, 960 px thumbnail |
| Caliraya / `caliraya.jpg` | [Caliraya Lake Spillway](https://commons.wikimedia.org/wiki/File:Caliraya_Lake_Spillway.JPG), Sonnyboylopez | CC BY-SA 3.0, 960 px thumbnail |
| Laguna de Bay / `laguna-de-bay.jpg` | [Laguna de Bay zoom](https://commons.wikimedia.org/wiki/File:Laguna_de_Bay_zoom.jpg), individual creator not specified | Public domain according to source, 960 px thumbnail; false-color satellite image, explicitly not water-quality visualization |
| Buruwisan / `buruwisan.jpg` | [Buruwisan Falls](https://commons.wikimedia.org/wiki/File:Buruwisan_Falls.jpg), Dionisia.besas | CC BY-SA 4.0, 960 px thumbnail |

Only the original three historical aquaculture associations below are imported. No species were inferred for new locations. Waterfall/spring profiles without fish evidence show **Not Assessed**; compatibility remains `insufficient_data`. New river/waterfall classification is **Not assessed**, without invented salinity or statutory classification. The San Pablo crater lakes' freshwater label follows the existing literature context, not a safety finding.

Public counts include only reports whose **current status is exactly `verified`**; pending, rejected and resolved records are excluded. Contents, dates, types, contacts, incident points, photos and audits remain private. These are reviewed observations, separate from scientific measurements. Zero does not mean clean/safe water. Every profile says **No verified water-quality assessment available**. See [RETENTION_POLICY.md](RETENTION_POLICY.md).

### Recovered inventory checks (2026-10-17)

All sixteen added lake/river/waterfall geometries were compared to saved OSM objects: exact node coordinates for points, and all original undirected way segments for lines/rings, with no missing or invented segments. Rings may start at a different vertex or run in reverse. Caliraya retains 61 island holes, Lumot 48 and Laguna de Bay 28. These checks preserve source geometry; they are not independent survey or topology certification. The historical resort node was checked against `storage/app/osm-hidden-valley.json` and the original outline in `inventory-hidden-history.json`.

| Profile | OSM object | Revision inspected |
| --- | --- | --- |
| Calibato Lake | way 24821818 | v24, 2026-04-06 |
| Palakpakin Lake | relation 6476644 | v7, 2026-04-06 |
| Caliraya Lake | relation 1622400 | v48, 2024-08-20 |
| Lumot Lake | relation 152819 | v23, 2024-08-20 |
| Laguna de Bay | relation 79348 | v173, 2026-09-05 |
| Santa Cruz River, selected reach | way 118768554 | v8, 2024-02-14 |
| San Pedro River, selected reach | way 813191908 | v6, 2023-07-13 |
| Tunasan River, selected boundary-area reach | way 416506946 | v5, 2025-05-01 |
| Taytay / Majayjay Falls | node 317421214 | v6, 2025-07-18 |
| Bunga Twin / Bunga Falls | node 351117095 | v5, 2022-06-19 |
| Buntot Palos Falls | node 2288792649 | v3, 2026-01-16 |
| Aliw / Dream Falls | node 2679392267 | v4, 2023-05-08 |
| Buruwisan Falls | node 4031692862 | v1, 2016-02-28 |
| Lanzones / Lanzonez Falls | node 4031692863 | v1, 2016-02-28 |
| Bukal / Kilangin Falls | node 4229589489 | v4, 2026-05-20 |
| Dampalit Falls | node 5397783523 | v1, 2018-02-08 |

The source links and corresponding revision metadata are stored on every profile. Named river reaches were disambiguated with saved source tags and geocoding results, rather than nearby unnamed streams. Locality labels remain qualified where access and boundary context differ (Aliw, Bukal, Tunasan); Laguna de Bay extends beyond Laguna. Relation revision dates do not date every member node or establish scientific sampling dates. No additional fish evidence or water-quality measurements are inferred.

### Remaining evidence gaps

- Lakes: Pandin's complete polygon, surveyed accuracy, current classification evidence and additional permitted photographs remain incomplete. Mohicap/Mojicap/Muhikap remain aliases of one record.
- Rivers: the current course supplement extends Santa Cruz, San Pedro, Tunasan, Bumbungan and Balanac beyond these original reaches, including available named upstream Balanac mapping toward Magdalena. Full headwater-to-mouth coverage remains unverified; see the current all-29 audit rather than the historical five-river snapshot below.
- Waterfalls: eight additional points and source aliases are recovered; independent municipal boundaries, on-site accuracy and further photo permissions remain incomplete. Pagsanjan/Cavinti/Magdapio remains one identity.
- Springs/resorts: Dolcezza, Tirta and Stellaluna remain withheld. Independent source/hydrogeological verification for both resort references and a wider spring inventory remain missing. No ordinary pool is published as a verified natural spring source.

Overpass returned 504 then 406; direct OSM retrieval later reached 429, at which point requests stopped. No access/rate limit was bypassed. NAMRIA/LGU survey geometry and legal boundaries were not obtained. These are research gaps, not evidence that locations do not exist.

## Geographic seed evidence

| Seed name | Latitude (N) | Longitude (E) | Water type | Project locality |
| --- | --- | --- | --- | --- |
| Sampaloc Lake | 14.0791 | 121.3299 | freshwater | San Pablo City, Laguna |
| Pandin Lake | 14.116 | 121.367 | freshwater | San Pablo City, Laguna |
| Yambo Lake | 14.1186 | 121.366 | freshwater | San Pablo City, Laguna; see boundary limitation below |

Coordinates come directly from section 2.1 (Sampling sites), printed page 300, of Ortiz et al. (2025), *Phytoplankton Dynamics and Ecological Capacity of the Seven Crater Lakes of San Pablo City, Philippines*, The Philippine Journal of Fisheries 32(2), 298–314. [Government publisher PDF](https://nfrdi.da.gov.ph/tpjf/vol32_2/research_article_ortiz_et_al_32220240111.pdf). This is the `source_url` stored on each lake. The PDF was downloaded from the publisher and its coordinate text checked locally after browser retrieval failed.

These are published location points, not independently surveyed centroids, shoreline polygons, entrances, or pollution sampling locations. The coordinate paragraph does not explicitly establish its datum or positional accuracy. Decimal degrees are stored as published; the seven database decimal places add no survey precision. Confirm the datum and accuracy with the publisher or authoritative GIS data before spatial analysis. The Day 2 map assumes WGS84 for display and discloses that assumption in its visible data notes.

Brillo (2025), *Government lake's concept and complications regarding the crater lakes in Laguna and Quezon Provinces*, section 3, identifies these as tropical freshwater maar lakes in Laguna. It places Sampaloc and Pandin in San Pablo City and describes Yambo as involving San Pablo City, Rizal, and Nagcarlan. [Publisher article](https://link.springer.com/article/10.1007/s43832-025-00260-8). Accordingly, Yambo's `municipality` is the project's San Pablo locality label; its description preserves the additional municipalities. This single string is not a legal boundary model. Obtain official LGU/NAMRIA boundaries before jurisdiction-sensitive work.

## Environmental evidence and missing data

Briddon et al., *Changing water quality and thermocline depth along an aquaculture gradient in six tropical crater lakes*, published online 2022, Hydrobiologia 850 (2023), 283–299, reports depth-sensitive and seasonal conditions and includes older LLDA monitoring. [Open-access research](https://link.springer.com/article/10.1007/s10750-022-05065-7). Historical research cannot establish present conditions or lake-wide fish suitability.

| Required evidence | Day 1 status | Needed before use |
| --- | --- | --- |
| Temperature, pH, dissolved oxygen, salinity/conductivity | No measurements imported or independently validated for current use | Lake/station identity, collection date/time, depth, units, instrument/method, quality control, and source |
| Nutrients, turbidity/transparency, chlorophyll-a, contaminants and microbiology | No measurements imported | Original data, detection limits, methods, sampling context, dates and applicable parameter definitions |
| Current water classification, pollution status and bathing/drinking safety | Not established | Current authoritative assessment; freshwater is a water type, not a safety classification |
| Shoreline, administrative polygons and coordinate datum/accuracy | Not independently verified | Authoritative spatial data, license, CRS and version/date |

The current schema has no water-quality measurement table. Missing values must remain unknown, never zero or fabricated defaults. Source papers may contain historical measurements; none were converted into present-day claims or synthetic app records.

The LLDA [Seven Crater Lakes report](https://llda.gov.ph/wp-content/uploads/dox/7lakes/7craterlakes.pdf) and [Pandin management plan](https://llda.gov.ph/wp-content/uploads/dox/pandin-lake-dmp/pandin-lake-mgt-devt-plan.pdf) were located, but full browser access was restricted. They are follow-up sources, not independently reviewed measurement datasets. A micro-watershed reference point must not be substituted for a lake location.

## Fish habitat research requirements for future assessments

- Establish species identity with a scientific name and primary BFAR/NFRDI, FAO, or peer-reviewed references. Distinguish documented occurrence, introduced/cultured species, and habitat suitability.
- Collect species- and life-stage-specific requirements for temperature, pH, dissolved oxygen, salinity, depth/substrate, and seasonal conditions, with units, source context and uncertainty. Do not infer suitability from freshwater type alone.
- Match any assessment to observed lake measurements with location, depth, time, sampling method and provenance. Address stratification and seasonal mixing before extrapolating across a lake.
- Define a transparent, reviewed comparison method only after the required evidence exists. Keep `compatibility_status = insufficient_data` when evidence is missing; do not create positive/negative habitat results to fill the database.
- Keep pollution reports as actual submissions with pending review; historical findings are research references, not invented incident reports.

Day 3 implements the historical fish records below. Days 4-5 added private reporting and administrator review. Scientific water-quality ingestion and suitability calculations remain unimplemented.

## Original Day 2 map display (superseded by expansion geography)

The map reads the existing database coordinates; no new coordinates or scientific findings were introduced. Leaflet 1.9.4 uses its standard map projection for display, with the unconfirmed WGS84 assumption noted above. Markers represent reference locations only; Yambo's full multi-locality description remains available.

Implementation references: [Leaflet quick start](https://leafletjs.com/examples/quick-start/) and [API reference](https://leafletjs.com/reference.html). The browser requests the standard HTTPS OpenStreetMap tile endpoint, displays contributor attribution, sends the normal origin referrer, and performs no tile prefetching/offline downloads. See the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Tiles depend on network/service availability; a failure notice preserves access to markers and lake details.

## Day 3: verified fish evidence

### Historical occurrence: Mabansag et al. (2019)

Mabansag CJA, Paraso MGV, Marcelino RT, Clavecillas AA, Lola MSEG. *A Preliminary Survey of Estrogenic Effects in Cultured Adult Male Nile Tilapia (Oreochromis niloticus) in the Seven Lakes of San Pablo City, Philippines*. Bulletin of Environmental Contamination and Toxicology 103, 400-404. DOI: [10.1007/s00128-019-02685-z](https://link.springer.com/article/10.1007/s00128-019-02685-z).

The publicly accessible publisher abstract explicitly names Sampaloc, Pandin and Yambo among the seven lakes and says fish were collected from aquaculture operations in each lake during **July-September 2017**. This supports Nile tilapia associations with each target lake, classified as **historical aquaculture**, not wild occurrence. The sampled population was cultured adult males.

The publisher gives online publication **27 July 2019**, with a September 2019 issue. The [UPLB repository abstract](https://www.ukdr.uplb.edu.ph/journal-articles/636/) corroborates the collection context/period, although its recommended citation labels the repository item 2021. Use 2019 as the article year and 2017 as sampling year. The full paper is subscription content; only public abstract/metadata were reviewed. No numeric study results or current pollution/health conclusions are imported.

| Lake | Species | Evidence type | Sampling period | Article year | Compatibility |
| --- | --- | --- | --- | --- | --- |
| Sampaloc Lake | Nile tilapia, *Oreochromis niloticus* | aquaculture | July-September 2017 | 2019 | insufficient_data |
| Pandin Lake | Nile tilapia, *Oreochromis niloticus* | aquaculture | July-September 2017 | 2019 | insufficient_data |
| Yambo Lake | Nile tilapia, *Oreochromis niloticus* | aquaculture | July-September 2017 | 2019 | insufficient_data |

These records do not establish present presence/abundance, wild populations, successful reproduction, native status, safety, or habitat suitability. This is a limited sourced dataset, not a complete fish inventory. No additional lake/species association was inferred from generic references to tilapia or aquaculture structures.

### General habitat: FAO

[FAO (2009), Cultured aquatic species fact sheet: Oreochromis niloticus](https://www.fao.org/fishery/docs/CDrom/aquaculture/I1129m/file/en/en_niletilapia.htm), text by J. E. Rakocy, identifies Nile tilapia as *Oreochromis niloticus* (Linnaeus, 1758). The Habitat and biology section describes a tropical, shallow-water species and omnivorous grazing. It reports a **preferred temperature range of 31-36 degrees Celsius**. The seed preserves that context and explicitly states that the preference is not a lake-specific suitability threshold. It is not an observation of any Laguna lake's temperature or a complete life-stage requirement set.

[FAO (2013), On-farm feeding and feed management in aquaculture, Technical Paper 583](https://www.fao.org/4/i3481e/i3481e00.htm), abstract and case-study list, places Nile tilapia in freshwater aquaculture contexts, including the Philippines. The species `water_type = freshwater` records that context, not an exclusive salinity tolerance or evidence that freshwater alone makes any lake suitable.

The species `source_url` points to the 2009 biology profile; each lake association's `source_url` points separately to the 2019 occurrence study. No numerical pH, dissolved-oxygen or salinity thresholds were invented.

### Other supplied starting sources and access limits

- [FishBase: Oreochromis niloticus](https://fishbase.org/summary/Oreochromis-niloticus.html): returned HTTP 403. Its specific habitat/temperature claims could not be independently reviewed here and were not used for seed values. No access controls were bypassed; FAO supplies the accessible general reference.
- Brillo (2023), [Looking from above the Seven Crater Lakes, Arrangement, and Limitation of Aquaculture Structure](https://philjournalsci.dost.gov.ph/looking-from-above-the-seven-crater-lakes-arrangement-and-limitation-of-aquaculture-structure/), Philippine Journal of Science 152(3), 1095-1109, June 2023, DOI 10.56899/152.03.28: the indexed publisher abstract concerns fish-cage layout/limits across the named lakes. Direct full-page retrieval returned 403, so review was limited to indexed abstract metadata. Used as background only, not as an additional species occurrence or suitability source.

### Evidence storage and remaining gaps

The existing unique lake/species pair stores one representative study reference. Three nullable additions (`evidence_type`, `sampling_period`, `publication_year`) preserve old rows as unknown; no legacy metadata is backfilled. Sampling uses a month-level text interval to avoid inventing collection days. Seeders run serially; an ambiguous existing species identity or missing/duplicate target lake aborts the fish import.

All seeded links retain `compatibility_status = insufficient_data`. The Day 3 public display always communicates insufficient data because no validated assessment pipeline or current measurements exist, even if an unsupported legacy status says otherwise. Incomplete evidence metadata is labeled incomplete; a missing link does not imply fish absence.

Still needed: current lake/station observations with dates, depths, methods and units; validated species/life-stage requirements for pH, dissolved oxygen, salinity and seasonal conditions; evidence for any further species or wild occurrence; authoritative coordinate datum/precision. Multiple studies per lake/species would require a future evidence history design rather than overwriting this representative record. No predictive scores, stocking recommendations, pollution incidents or measurements were created.


## Requested waterfall inventory and two additions (2026-10-20)

Read-only database inspection verified both the configured and connected schema as `aquamap_laguna`. The database had 28 records: 10 lakes, 5 rivers, 11 waterfalls and 2 spring-associated resort references. The original catalogue/seed data and database agree. All original names, aliases, scientific records and metadata were retained.

| Requested name | Existing canonical database record | Inventory outcome |
| --- | --- | --- |
| Hulugan Falls | Hulugan Falls, Luisiana | Already present |
| Aliw Falls | Aliw Falls, qualified Luisiana/Cavinti access locality | Already present; locality caveat retained |
| Talay Falls | Talay Falls, Luisiana | Already present |
| Buruwisan Falls | Buruwisan Falls, Siniloan | Already present |
| Lanzones Falls | Lanzones Falls, Siniloan | Already present |
| Bukal / Kilangin Falls | Bukal Falls, Majayjay / Liliw access area | Already present; existing alias retained |
| Majayjay / Taytay Falls | Taytay Falls, Majayjay | Already present; name preserved |
| Cavinti / Pagsanjan Falls | Pagsanjan Falls, Cavinti | Already present; existing aliases retained |
| Balon-Balon Falls | None | Added as Balon-Balon Falls, Cavinti |
| Buntot Palos Falls | Buntot Palos Falls, Pangil | Already present |
| Ambon-Ambon Falls | None | Added as Ambon-Ambon Falls, Pangil |
| Bunga Falls | Bunga Twin Falls, Nagcarlan | Already present; name preserved |
| Dampalit Falls | Dampalit Falls, Los Ba?os | Already present |

The reviewed additions live in `database/data/waterfall-expansion.json`. `waterfalls:import` imports only those two records through the guarded transactional catalogue importer. It checks canonical/alias identities in both directions and refuses conflicting existing metadata. Repeating the import preserves values and timestamps. No migration or destructive database operation is required. The full catalogue remains importable for isolated fixtures through ExplorerSeeder. Final inventory is 30 stored records, 28 public map profiles (10 lakes, 5 rivers, 13 waterfalls), and 30 reporting choices including the two spring resorts. Springs remain off the public map; reporting inclusion follows the user's newer explicit category request.

### Balon-Balon Falls, Cavinti

- [Laguna University's first-party community field visit](https://lu.edu.ph/lu-partners-with-cavinti-community-for-eco-friendly-tourism-training/) identifies Balon-Balon, Kipot and Porosa Falls in Tibatib, Cavinti. The described event occurred 2025-09-29; a publication date is not substituted for the event date.
- [J. P. Leo Castillo's original Flickr record, Balon-Balon Falls, Left Section](https://www.flickr.com/photos/jpleo/53951884343) supplies public photo geolocation metadata: **14.264828 N, 121.518805 E**. Taken 2024-08-12, uploaded 2024-08-27. The local source-page snapshot records those exact values. The [firsthand field account](https://shoestringdiary.wordpress.com/2024/08/18/the-tri-falls-of-tibatib-part-1-balon-balon-falls/) independently identifies the falls in Tibatib. The geographic reference uses the web-map coordinate convention; it is a photographer-published reference near the falls, not a surveyed waterfall centroid. The source photograph is separately marked all rights reserved; using its factual geographic reference does not license its image. The displayed document photograph has the project owner's separate permission declaration.
- Precision of the published digits is not positional accuracy. The photo-location/reference distinction is visible in the overview and geographic limits. No river basin, legal boundary, fish presence, measurements or pollution condition was inferred.

### Ambon-Ambon Falls, Pangil

- The [Senate Legislative Reference Bureau's House Bill 2749 record](https://issuances-library.senate.gov.ph/bills/house-bill-no-2749-15th-congress-republic), filed 2010-08-24, explicitly names Ambon-Ambon Falls in Pangil. A bill record is historical locality evidence, not proof of enactment or environmental status.
- The [Pangil Wikivoyage listing, revision 5322962](https://en.wikivoyage.org/w/index.php?title=Pangil&oldid=5322962), supplies the named waterfall's map coordinates **14.412799 N, 121.484128 E**. Its HTML listing parameters and linked map marker were checked directly. Credit: Wikivoyage contributors, CC BY-SA; coordinate precision is not surveyed accuracy. The Pangil site must not be confused with the identically named falls in Bagac, Bataan or Laurel, Batangas, or with the coordinates of the access road or eco-park entrance.
- No independent survey accuracy is established. A community-listed geographic reference is shown with explicit limitations; municipality labels are not certified boundaries. No fish occurrence or environmental condition is inferred.

OpenStreetMap/Nominatim checks did not supply a named Balon-Balon falls feature and returned access-road results for Ambon-Ambon rather than a waterfall. Overpass endpoints had TLS/timeouts; the primary OSM API returned a rate-limit response for the Balon area. Those road/failed-query results were not used as waterfall coordinates and no rate limit was bypassed. Published source references above were retained instead. Geography check dates use Laguna's Asia/Manila calendar, while the application timestamp timezone remains unchanged; future check dates are still rejected.

### Supplied photographs

The Google Doc was directly accessible through its DOCX export. All ten embedded photographs were inspected and nine matched the requested inventory; Hidden Falls is outside the requested additions and Balisong Falls has no photo. [IMAGE_INVENTORY.md](IMAGE_INVENTORY.md) records all nine associations, source publishers, permission basis, optimization and preserved previous assets. Inclusion in the document was not treated as permission: the project owner explicitly confirmed ownership/permission for all images after being asked about local display, resizing and attribution. The public permission page records that declaration without claiming an independently verified or unrestricted public license. Unidentified photographers remain explicitly unidentified.
