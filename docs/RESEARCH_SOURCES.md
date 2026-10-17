# Research sources

Verified on 2026-10-17 for Day 1. Only lake identity, locality, water type, and published geographic points are seeded. No environmental measurements or suitability conclusions are seeded.

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

## Fish habitat research requirements (Planned)

- Establish species identity with a scientific name and primary BFAR/NFRDI, FAO, or peer-reviewed references. Distinguish documented occurrence, introduced/cultured species, and habitat suitability.
- Collect species- and life-stage-specific requirements for temperature, pH, dissolved oxygen, salinity, depth/substrate, and seasonal conditions, with units, source context and uncertainty. Do not infer suitability from freshwater type alone.
- Match any assessment to observed lake measurements with location, depth, time, sampling method and provenance. Address stratification and seasonal mixing before extrapolating across a lake.
- Define a transparent, reviewed comparison method only after the required evidence exists. Keep `compatibility_status = insufficient_data` when evidence is missing; do not create positive/negative habitat results to fill the database.
- Keep pollution reports as actual submissions with pending review; historical findings are research references, not invented incident reports.

Fish records, habitat links, pollution workflows, water-quality ingestion, and suitability logic are outside this Day 1 implementation.

## Day 2 map display

The map reads the existing database coordinates; no new coordinates or scientific findings were introduced. Leaflet 1.9.4 uses its standard map projection for display, with the unconfirmed WGS84 assumption noted above. Markers represent reference locations only; Yambo's full multi-locality description remains available.

Implementation references: [Leaflet quick start](https://leafletjs.com/examples/quick-start/) and [API reference](https://leafletjs.com/reference.html). The browser requests the standard HTTPS OpenStreetMap tile endpoint, displays contributor attribution, sends the normal origin referrer, and performs no tile prefetching/offline downloads. See the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Tiles depend on network/service availability; a failure notice preserves access to markers and lake details.
