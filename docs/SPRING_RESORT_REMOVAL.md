# Springs and resorts: removal and deletion approval plan

2026-10-21; baseline `468f50c`. **Stored-record deletion is not authorized or executed.** Safe application cleanup supports only lakes, rivers and waterfalls. No production migration, seeder, importer, reset or database write is needed.

## Observed scope and dependencies

Inspection covered routes, models/schema, public/admin controllers, report validation, Blade/JavaScript/CSS, catalogues/importers, tests and project memory. `/map` projects allowlisted public JSON and inert profile templates; `/reports/create` supplies choices and `POST /reports` validates the selected ID/category. There is no water-body creation API or UI. New model records and the shared catalogue importer now reject unsupported categories/property metadata. Existing administrative report history remains readable; its location dropdown lists supported bodies only. Leaflet selection, geometry, metrics and zoom logic are unchanged.

Read-only inspection verified both configured and connected schemas as `aquamap_laguna`, with 55 stored records. All category/site-type labels and names were inspected; the only out-of-scope identities are:

| ID | Exact name | Locality | Category / site type / subtype | Related rows |
| --- | --- | --- | --- | --- |
| 11 | Al Fresco Springs Resort | Los Baños | spring / resort / geothermal_hot_spring | 0 fish links, 0 pollution reports, 0 reviews, 0 private report photos |
| 28 | Hidden Valley Springs | Alaminos / Calauan area | spring / resort / natural_spring | 0 fish links, 0 pollution reports, 0 reviews, 0 private report photos |

Hidden Valley's alias is `Hidden Valley Spring Resort`; Al Fresco has no stored alias. No additional alternate Spring/Resort category labels occur in the database. Regression cases also cover plural/alternate labels and resort metadata on an otherwise allowed category.

Deleting these rows would lose their individual geometry, descriptions, identity/source fields and embedded research arrays (two references for Al Fresco, three for Hidden Valley). Both stored image fields are null. Hidden Valley's inactive `explorer-images.json` supplement and `public/images/water-bodies/hidden-valley-springs.jpg` remain preserved; Al Fresco has no authorized photograph. Shared citation metadata/audit ledgers, photo assets, and every retained catalogue entry are preserved. Only the two obsolete record-creating catalogue entries were removed, preventing reseeding.

Verified foreign keys: `water_body_fish.water_body_id` and `pollution_reports.water_body_id` cascade from water bodies; `report_reviews.pollution_report_id` restricts report deletion. Fish-species links cascade from the species side, but no species deletion is proposed. Review administrator references restrict user deletion; no user deletion is proposed. Cascades could destroy later-added links/reports, so the zero-dependency observation must be rechecked immediately before any approved deletion.

## Verified backup and recovery evidence

Ignored, non-public `storage/app/springs-removal/aquamap_laguna-before-removal.sql` contains the complete single-transaction MySQL dump, including schema, rows, triggers, routines and events. Temporary connection options were removed; no credentials or private rows are committed. Backup size: 1,538,129 bytes. SHA-256: `6ce5dc5ee83858e41ea9ed91290387b633b027f4e76a04fca95c321617c499ba`.

Verification checked all 14 table definitions and every table's INSERT-row count, including 55 water bodies, against read-only database snapshots before/after dumping. All hashes/counts matched and the database was unchanged. `backup-verification.json` records table hashes/counts and the dump checksum; `inventory.json` records the reviewed candidates and foreign keys. **A database restore has not been tested.** Keep the backup private and intact; no restore or alternate-database operation is authorized by this cleanup.

## Proposed deletion, awaiting separate approval

1. Obtain approval specifically for water-body IDs 11 and 28 with the exact names above. No automatic deletion command, job or migration is introduced.
2. Verify the configured/connected database again, validate backup checksum, and refresh the backup if the database changed. Recheck candidate row hashes and dependency counts under transaction locks.
3. Abort if names/IDs changed or any linked fish rows, pollution reports, reviews or photos now exist. Preserve every report; any new dependency requires a revised plan and separate approval rather than accepting cascades.
4. If approved and still dependency-free, delete only those two water-body rows in one transaction. Compare all retained records/relationships and all other tables with the pre-deletion snapshot before committing; roll back any unexpected difference.
5. Keep every shared citation and photo asset, the inactive image supplement, private evidence and backup. Asset deletion is outside this approval scope. Verify 53 stored supported bodies, 41 mapped locations and unchanged retained research/GIS/fish/report data, then record a separate local recovery checkpoint.

Until approval, the database remains at 55 stored records; public map/report choices expose only 53 (11 lakes, 29 rivers, 13 waterfalls). Reverting the code commit restores prior presentation without a database restore. Current validation results and artifact locations are in `CURRENT_STATE.md`.
