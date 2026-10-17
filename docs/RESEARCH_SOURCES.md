# Research sources

Reviewed on 2026-10-17 for Days 1-3. Lake geographic metadata and one sourced fish species with three historical aquaculture associations are seeded. No lake measurements or suitability conclusions are seeded.

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

Day 3 implements the limited historical fish records below. Pollution workflows, water-quality ingestion, and suitability calculations remain outside the implemented scope.

## Day 2 map display

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
