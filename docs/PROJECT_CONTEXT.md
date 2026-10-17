# AquaMap Laguna

A Laravel + MySQL GIS information system for a six-day school mini hackathon. The initial dataset covers Sampaloc, Pandin, and Yambo lakes associated with San Pablo City, Laguna.

- **Implemented Day 1:** schema/model alignment, sourced geographic seed data, safe repeatable seeding, isolated tests, and project documentation.
- **Implemented Day 2:** public Blade/Tailwind lake explorer at `/map`, database-driven Leaflet markers, OpenStreetMap basemap, responsive selection/details, public-field filtering, and desktop/mobile browser checks. `/` redirects to the explorer.
- **Implemented Day 3:** sourced historical Nile tilapia aquaculture records for the three lakes, general FAO habitat information, nullable study metadata, idempotent fish seeding, and responsive fish cards integrated with map selection. Occurrence is separate from unassessed compatibility.
- **Constraints:** modify only this project; persistent database work is restricted to `aquamap_laguna`. Never reset databases, expose/change credentials, or fabricate measurements, pollution reports, or habitat conclusions.
- **Planned:** confirm the Day 4 brief before extending the application. No domain write API, pollution-reporting workflow, admin interface, measurement ingestion, or suitability engine is implemented yet.

Read [CURRENT_STATE.md](CURRENT_STATE.md) for the latest checkpoint, [ARCHITECTURE.md](ARCHITECTURE.md) for the verified system map, and [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) before changing lake data or environmental claims.
