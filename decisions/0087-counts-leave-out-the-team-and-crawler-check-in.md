# 0087. Insights leave out the team's own use; the crawler's status reads its latest check-in

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0058 §13 (platform counts), 0084 and 0085 (the worker's usage reports)

## Context

Jason asked whether Insights (207 visits, 135 searches) showed real visitors or his own use. Until now, only staff
pages and known bots were left out, so an admin browsing the public site was counted like anyone else. Counts store no
"who", so earlier days can't be split afterwards. He also asked why "Searches by place" was empty. The 3-visit rule
hides every term, and while the site is new no term reaches 3 visits.

The dashboard showed the crawler amber ("Last checked in 1 h ago · 0 jobs queued"). The status read only the last
crawl job, so it went stale whenever nothing was queued, even though the worker reported every run.

## Decision

**The team's own use isn't counted** (`PlatformCounts::isTeam`, used by `/api/count.php` and `/api/out.php`):
- A signed-in admin isn't counted.
- Any browser an admin has used isn't counted either, even after signing out. A cookie, `tv_nocount`, is kept for a
  year and holds nothing about who.
- **A listing's own people** (an active owner, manager or other grant on it) aren't counted on that listing's views or
  taps.
- **Insights says so**, with a link, "Count this browser", that lets an admin test as a visitor for the session, and
  one to stop.

**What the 3-visit rule holds back is shown as numbers only.** For example: "Held back: 101 searches for 63
term-and-place pairs…". The words still show only at 3 visits.

**The crawler's "last checked in"** is now the latest of:
- its last job;
- its web search report (`websearch_seen`);
- its Google check report (`places_seen`).

## Not done

- Bots that pretend to be browsers. Those are left to the analytics service, if it's turned on.
- Counting team use separately. It's dropped, not kept apart.
