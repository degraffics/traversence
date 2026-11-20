# Restart outline (2026-11-20, for Jason's review before any new session starts)

Jason, 2026-11-20: "every session is held… hand off to new session so we can reduce token use without logic loss", and
"before we start new session I want to see what they work outline is." Every building session is held; every hand-off is
in `main`. Nothing below starts until Jason approves this outline.

## Proposed sessions (at most three building at once, CLAUDE.md)

Recommended: merge the Control Board and dispatcher areas into one **Operations** session (they share ControlAlarms,
ServiceHealth, OutsideServices and the one-panel drawer, and today need the session manager to sequence them), and fold
business listings into the session manager (one check on Monday). That is three building sessions plus the session
manager.

### 1. Operations (0175 and 0176): hand-offs `control-board-2026-11-20.md`, `dispatcher.md`
In order, each its own release and pop-up:
1. **0175 R2a, the board as the one place to act** (scope cleared; 11 items, each sourced). Every alarm opens its fix in
   the side panel, one panel at a time (built, unsent: `claude/0175-r2a-wip`); a live "paused now" line at the top of the
   board and of Automation (replacing "Everything is on"); Diagnostics once active, Pre-flight only before; a backup is a
   working state, never Holding; no Acknowledge on a warning; the trend strips say what they count. **Question:** when a
   system hour turns red.
2. **0176 R2, service issues as threads** (held by Jason's word): the card's "Is it working" box in the one shape, no ×.
3. **0175 R2b, one control board in the menu:** System Control Board (with tabs), Modules, Workflows; every old address
   redirects; nothing dropped.
4. **The Census backup** ("Yes, build it"): the crawler calls the Census when Bluehost's connection fails.
5. **0176 R3, Fix it routing** (reuses R2a's panel). **0176 R4, uploads** (photos post first with a staff thread; the
   order file check waits for Jason's limits).
6. **0175 R3–R4:** the eight-stage strip on each card; one definition per module. Then the App store (0169) builds on
   them.
Watch: crawler 2.15.0's first Common Crawl map; Geofabrik's first night load and the database size.

### 2. Design and trips: hand-off `design.md` (and its linked area files)
In order:
1. **Hero tidy** (approved, built, unsent: `claude/hero-tidy`): ⋯ starts Link to, Claim, Add to a trip; Link icon hides
   while ⋯ is open; see-through stock pop-up; one-line default place; the stray hub text; same-named areas once.
2. **The admin kit release:** one header per view at 390; contrast fixes.
3. **T5:** the vault, cost tab, outside services and domains on the table standard.
4. **Release 3's areas, one at a time:** 3d Community and the member dashboard; 3e Marketplace, checkout and storefronts;
   3f sign-in and the rest.
5. **The map release:** the route colour and the map's colours (before routing).
6. **0174 R5, routes on our own site** (openrouteservice's free tier): read its terms first.

### 3. Orders: hand-off `order-processing.md`
Starts only after **Jason reviews the order system map** (`research/order-system-map-2026-11-19.md`) and answers its six
"intended?" items. Then: improvements on top of the map as a decision (extending 0101), the first release including the
chargeFor fix ("Fix in first release"), and the order file check with the dispatcher's R4 once Jason gives its limits.

### The session manager (fresh): hand-off `session-manager.md`
Reviews every summary line by line against its sources; runs the syncs and records deliveries; reads Monday's "Edit my
listing" path check (`business-listings.md`); keeps the work queue.

## Waiting on Jason (none of these is built until he answers)
- Approve this outline (sessions and order).
- When a system hour on the board turns red (0175 R2a).
- 0176 R2's restart.
- The order system map's review and its six items; the order file check's limits.
- The area data plan (ten "Albuquerque" clusters, Abilene under Ancient Borderlands): find the cause, preview in Data
  cleanup, Jason decides.
- The dashboard-ideas discussion.
- A screenshot of https://traversence.com/admin/config/scheduler#job-control_tick (the board's watch).
- The Park Service key on its card (photos): https://traversence.com/admin/config/register
- Queued, when he chooses: widening the OSM copy; OSM as crawler leads; giving back to OSM (parked); visitor counts
  (people vs automated); more health-care and tour sources; the .ai two-year fix and Name.com's reseller enrolment; Pulse's
  load-time warning.
