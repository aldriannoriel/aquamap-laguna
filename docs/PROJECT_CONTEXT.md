# AquaMap Laguna

A Laravel + MySQL GIS information system for a six-day school mini hackathon. The initial dataset covers Sampaloc, Pandin, and Yambo lakes associated with San Pablo City, Laguna.

- **Implemented Day 1:** schema/model alignment, sourced geographic seed data, safe repeatable seeding, isolated tests, and project documentation.
- **Implemented Day 2:** public Blade/Tailwind lake explorer at `/map`, database-driven Leaflet markers, OpenStreetMap basemap, responsive selection/details, public-field filtering, and desktop/mobile browser checks. `/` redirects to the explorer.
- **Implemented Day 3:** sourced historical Nile tilapia aquaculture records for the three lakes, general FAO habitat information, nullable study metadata, idempotent fish seeding, and responsive fish cards integrated with map selection. Occurrence is separate from unassessed compatibility.
- **Implemented Day 4:** public reporting form at `/reports/create`, validated/throttled submissions at `POST /reports`, server-assigned pending status, optional explicit incident points, private photos/contact details and generic confirmation. Reports are not publicly readable. Existing schema and research data are unchanged.
- **Constraints:** modify only this project; persistent database work is restricted to `aquamap_laguna`. Never reset databases, expose/change credentials, or fabricate measurements, pollution reports, or habitat conclusions.
- **Planned Day 5:** confirm the admin brief, then authenticated/authorized review and private photo handling with safe status transitions. No admin interface, measurement ingestion, automatic notifications or suitability engine exists yet. Coordinate accuracy/datum remain unverified and visibly qualified.

Read [CURRENT_STATE.md](CURRENT_STATE.md) for the latest checkpoint, [ARCHITECTURE.md](ARCHITECTURE.md) for the verified system map, and [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) before changing lake data or environmental claims.
