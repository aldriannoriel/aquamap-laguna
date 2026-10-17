# AquaMap Laguna

A Laravel + MySQL GIS information system for a six-day school mini hackathon. The initial dataset covers Sampaloc, Pandin, and Yambo lakes associated with San Pablo City, Laguna.

- **Implemented Day 1:** schema/model alignment, sourced geographic seed data, safe repeatable seeding, isolated tests, and project documentation.
- **Implemented Day 2:** public Blade/Tailwind lake explorer at `/map`, database-driven Leaflet markers, OpenStreetMap basemap, responsive selection/details, public-field filtering, and desktop/mobile browser checks. `/` redirects to the explorer.
- **Implemented Day 3:** sourced historical Nile tilapia aquaculture records for the three lakes, general FAO habitat information, nullable study metadata, idempotent fish seeding, and responsive fish cards integrated with map selection. Occurrence is separate from unassessed compatibility.
- **Implemented Day 4:** public reporting form at `/reports/create`, validated/throttled submissions at `POST /reports`, server-assigned pending status, optional explicit incident points, private photos/contact details and generic confirmation. Reports are not publicly readable. Existing schema and research data are unchanged.
- **Constraints:** modify only this project; persistent database work is restricted to `aquamap_laguna`. Never reset databases, expose/change credentials, or fabricate measurements, pollution reports, or habitat conclusions.
- **Implemented Day 5:** administrator-only session login, protected dashboard/counts, report search/filters/pagination/details, private evidence streaming, controlled status transitions and atomic audit history. New admin accounts require the interactive local `admin:create` command; no public registration or community accounts exist. All reports stay private.
- **Implemented local anti-spam:** anonymous reports now have a hidden honeypot and an encrypted, session-bound 3-second server timer (2-hour validity). The existing 5-attempts/hour/IP limiter remains unchanged. Recent same-session lake/type repeats receive a nonblocking warning after saving privately as pending; other people's report history is never queried for warnings.
- **Planned Day 6:** confirm the final integration/demo brief and deployment/recovery requirements. No measurement ingestion, automatic notifications or suitability engine exists. Coordinate accuracy/datum remain unverified and visibly qualified. See [RETENTION_POLICY.md](RETENTION_POLICY.md) for the prototype's manual privacy/retention process; no automatic deletion is implemented.

Read [CURRENT_STATE.md](CURRENT_STATE.md) for the latest checkpoint, [ARCHITECTURE.md](ARCHITECTURE.md) for the verified system map, and [RESEARCH_SOURCES.md](RESEARCH_SOURCES.md) before changing lake data or environmental claims.
