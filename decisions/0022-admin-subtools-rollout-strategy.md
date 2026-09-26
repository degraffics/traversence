# ADR 0022: Admin Sub-Tools Rollout Strategy — Stubs, Auth-First, Internal Sequencing

**Status:** Accepted (2026-09-24).

## Context

`decisions/0020` grouped the remaining, still-unbuilt admin sub-tools (hub/geo-hub management, cluster management, the automated-seed review queue, pending-listing review, user management) into a single bucket, Tier 2.6, with only a general reasoning ("no hard dependency, but urgency scales with how much of Tier 2.1–2.3 is live") and no internal order or build practice. Per direction, four concrete tactics close that gap for exactly this bucket — what the original spec's own phase numbering calls "Phase 7" (`decisions/0017` already referenced "Phase 7's reviewing side" before `decisions/0016` built the first real piece of it, the Claims queue).

**The fast-track-Claims tactic is already done, and worth naming as validation rather than new work:** the claim entry point and the admin Claims queue were already bumped to Tier 1 in `decisions/0020` precisely because a closed-loop MVP needs both halves together — the exact reasoning this message applies to the rest of Phase 7. That's not a coincidence; it's the same "No Split Loops" thinking from `decisions/0021` showing up again at a smaller scale, one admin view at a time instead of one platform-wide loop.

## Decision

### 1. Stub scaffolded routes with a shared "Coming Soon" template, never a hard 404
Every admin sub-tool still unbuilt (`admin/hubs.php`, `admin/clusters.php`, `admin/review-queue.php`, `admin/listings.php`, `admin/users.php`) gets pointed at one shared placeholder template or modal rather than a dead link. `routes.md` already flagged the underlying risk once, generically, for the admin portal itself ("the dashboard already links to it, so it will 404 until it exists") — this makes the fix a standing practice for every scaffolded tile, not something noticed once and left unfixed elsewhere. The shared template states plainly which tools are active versus pending, which keeps `admin/adminportal.php` navigable for internal testers instead of training them to expect broken links from the admin panel specifically.

### 2. Role-based access guards before the logic behind them exists, never after
Every sub-tool route — including a bare stub showing nothing but "Coming Soon" — sits behind the exact same `Auth::requireHubAdmin()` / `Auth::requireSuperAdmin()` check its finished version will use, from the first commit that creates the route at all. Two concrete reasons this can't wait for "real" logic to land: a stub is still capable of leaking something (which tools exist, hub/cluster names, counts) to anyone who can reach the URL if it's unguarded; and retrofitting an auth check onto a page that's been reachable without one is a real regression risk (the check gets forgotten, or added after the page already saw unauthenticated traffic) that simply doesn't exist if the guard was there from the stub's first line of code.

### 3. Internal sequencing within Tier 2.6, replacing the flat "no hard dependency" treatment
Not all five remaining sub-tools carry the same urgency, and two of them tie directly into prerequisites this document set already named elsewhere rather than being ranked from scratch here:

- **2.6.1 — `admin/listings.php` (pending-listing review), first.** This isn't just "a real operational bottleneck" in the abstract — it's the specific standing prerequisite `decisions/0021` §3 already identified: the crawler-ingestion pipeline cannot be turned on for genuinely new content until this exists, or doing so would recreate the exact split-loop problem that ADR was written to prevent. Building it first clears that gate rather than leaving it as a known blocker no one's scheduled against.
- **2.6.2 — `admin/review-queue.php` (automated-seed cluster review), second.** Same HITL family as 2.6.1, and the other half of `decisions/0021`'s hard coupling condition on Tier 2.3 — if the AI article pipeline is ever pulled forward, this is the piece that has to ship with it.
- **2.6.3 — `admin/hubs.php` (hub/geo-hub management), third.** Structural, no content-safety dependency riding on it; needed once there's real multi-hub operational complexity to manage, not before.
- **2.6.4 — `admin/clusters.php` (cluster management), fourth.** Same reasoning as 2.6.3, one level down the hierarchy — can safely wait, per direction, since it manages structure rather than gating content or claims.
- **2.6.5 — `admin/users.php` (user management), last.** The platform's real moderation levers (`users.is_suspended`, `dmca_strike_count`, `listing_access`/`admin_access` grants) already work today through existing endpoints built for their specific ADRs; a dedicated general-purpose user-management console is a convenience layer over mechanisms that already function, not a missing capability.

### 4. Incremental rollout: one sub-tool fully built and deployed before the next starts
Phase 7's sub-tools ship in the 2.6.1→2.6.5 order above, each one taken to a real, working state before the next begins — not five sub-tools half-built in parallel. This is the same principle `decisions/0021` applied to platform-wide loops, scaled down to individual admin views: a half-built `admin/hubs.php` sitting alongside a half-built `admin/clusters.php` provides less real value than one of the two being genuinely finished, for the same reason a half-shipped claim/review loop would have.

## Consequences

`decisions/0020`'s Tier 2.6 bullet is superseded by the 2.6.1–2.6.5 ordering above; `prd.md` and `routes.md` are updated to match. The stub-template and auth-first rules apply to any future scaffolded admin route this document set adds, not just the five named here — worth checking against whenever a new admin tile gets added to `admin/adminportal.php` ahead of its own sub-tool being built.
