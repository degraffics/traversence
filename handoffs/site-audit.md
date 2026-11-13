# Hand-off: the site audit (decisions/0145), Phases 3–5 finished

For the Claude session that finishes the site audit. Written 2026-11-13 by the session that built Phases 3 and 5
(staging, the Releases page). Two other sessions are running at the same time:
- the **builder** (`handoffs/0159-the-builder.md`: the site manager, Phase 6, the page builder);
- the **crawler revamp** (`handoffs/crawler-revamp.md`).

Their files and yours may overlap (the zip check blocks a file another session's pending zip carries); check the
delivery log first. **Jason works mostly from his phone.** Keep his steps short, and check the decisions before asking
him anything.

## 1. Read first

- `CLAUDE.md` (every rule, above all every build is a release) and `README.md`.
- `decisions/0145` (the audit and the release process, with its notes).
- `site-audit/README.md` and the phase pages: `phase-3-staging.md`, `phase-4-journeys-and-review.md`,
  `phase-5-releases.md`.
- `site-audit/proposals.md`, `site-audit/paths.md`, `site-audit/routes-check.md`.

## 2. How a build reaches the site now

Phase 6 is live (the builder session built it):
1. `python3 tools/page-audit/send.py <zip> --mirror <traverse-3way-sync checkout> --note "…built on Sync: <time>"`. It
   runs the zip check, records the zip, and copies it into `releases/inbox/`.
2. Commit, PR and merge into `main`. The live site takes it onto staging as a release within 10 minutes.
3. Jason looks with the site manager on staging and publishes.
4. Then run the sync and `deliveries.py verify` (`handoffs/0159-the-builder.md` §3).

**Changes to this repository's own tools** (`tools/page-audit/`, the workflows, `site-audit/`) aren't site releases:
they go to `main` by PR.

## 3. The work, in order

### 1. The scheduled checks don't run reliably

- **What's happening:** GitHub skips scheduled runs when it's busy. On 2026-11-13 these hadn't run since the times
  shown, so the audit's data, Site health and the paths go stale:
  - the daily Site checks (`site-checks.yml`, 09:43 UTC): last on 2026-11-12, 17:44;
  - the Search probe: last on 2026-11-12, 18:21;
  - the Site audit (twice a day): last on 2026-11-13, 00:25.
- **The fix:** start them from something that keeps time. Two options:
  - the site's own scheduled tasks: a job in `Scheduler::JOBS` and `AUTO` that sends `repository_dispatch` with
    `GITHUB_DOCS_TOKEN`, which can write (proven 2026-11-13). Check whether dispatch needs more permission on that token.
  - a small Railway cron.

  The workflows then answer `repository_dispatch` as well as their schedule.
- **Also:** a check that tells Pulse when a workflow hasn't run in 26 hours.

### 2. Phase 3: the 11 staging paths that change something

- **What's there:** `tools/page-audit/paths.mjs` has their steps, but no runners. The paths: sign up, like as a member,
  claim, edit my listing, post on the Marketplace, message a business, join a group, the staff decisions (a report, a
  like check, a held post), and sending an order. The guest like and the comment already have runners.
- **How they run:** on staging, as the audit's test accounts (`CHECK_ACCOUNTS` holds staging's address and sign-ins),
  never on live. Staging's email is held and readable, so sign-up's link can be followed.
- **The way in:** the builder session's releases #5 and #6 changed staging's way in (the pass and the site manager).
  Check how the checks sign in to staging now; they use the viewing password through HTTP Basic (`check-kit.mjs`).

### 3. Three path failures on live (`site-audit/paths.md`, 2026-11-13 04:29 UTC)

1. **Set my place:** the directory's place box gives no distance to set once a place is chosen (J01, a dead end).
2. **A suggested journey's FAQ link didn't answer** (`/journeys/suggested.php?id=8`, J02). Check whether it's real or
   was a slow answer.
3. **A guide journey takes 7 taps to the goal; the measure is 3** (`/journeys/suggested.php?id=40`, J01).

Each is a fix on the site (a release) or a fix to the path (here), whichever is wrong.

### 4. Phase 4: journeys and the review

- **Live:** Site health in Pulse (`SiteHealth.php`, switch `site_health`), `/api/guide-paths.php`, the findings, the
  page map, and the review skill (`.claude/skills/page-review/`).
- **Still to do:** run the review over the findings once items 1–3 give fresh data, append the proposals, and walk
  Jason through them (his approval is per proposal).
- Then **"Done when"** in `phase-4-journeys-and-review.md`.

### 5. P6: routes against the page tree (`site-audit/routes-check.md`)

**Waiting on Jason's approval of the calls below** (2026-11-13). When he approves, or changes a call:
- put each page into the page tree as live, or into `archive.md` (archived, or removed);
- archive `routes.md` once every route in its §1 is settled (P6: "the page tree becomes the source of truth, without
  losing a route");
- fix `tools/page-audit/routes-check.py` so it knows the pages reached by rewrite (routers, `api.php` endpoints,
  sitemaps). It suggested removing them because no file links to them.

**§1. Routes in `routes.md` with no page on the server (20).** Three are already in `archive.md`. Proposed calls for the
other 17:

| Route | Proposed |
|---|---|
| `/eng/directoryengine.php` | archive: gone, nothing refers to it |
| `/map` | archive: replaced by Explore's map (`/discovery/`, `TvMap`) |
| `/privacy/dsar`, `/api/privacy/dsar-verify.php` | coming: decision 0026's data export isn't built (deleting an account is: `/data-deletion.php`, `/user/delete-account.php`) |
| `/api/documents/upload.php`, `/admin/api/documents/view.php` | archive: replaced by the one uploader (0127) |
| `/listing/api/managers/invite.php` | archive: replaced by the business portal's Team |
| `/listing/api/referral/create.php` | check: only `referral/confirm.php` exists (0015) |
| `/dmca-notice.php`, `/api/dmca/counter-notice.php`, `/admin/dmca.php`, `/admin/dmca-settings.php`, `/admin/api/dmca/agent-info.php` | coming: the admin's DMCA queue is a "later wave" (manifests); takedowns today go through `/takedown.php` (0133) |
| `/admin/review-queue.php` | archive: replaced by Listings → Review |
| `/admin/hubs.php`, `/admin/clusters.php` | archive: replaced by Cluster tools |
| `/admin/users.php` | archive: replaced by People |

**§2. Pages on the server in neither list (70).** Proposed:
- **52 live, as suggested:** linked from other pages and mostly in menus.
- **12 with one link, live:** `business/files.php`, `business/pay-connect.php`, `email/unsubscribe.php` (linked from
  emails), `gallery/api.php`, `gallery/drop.php`, `market/ticket.php`, `pages/form.php`, `promo.php`, `q-file.php`,
  `user/staff-link.php`, `user/transfer.php`, `verify-listing.php`.
- **5 that "nothing links to", live, not removed:** `admin/router.php` and `business/router.php` (they draw the admin and
  the portal, reached by rewrite), `admin/api.php` and `business/api.php` (their endpoints), and `pages/sitemap.php`
  (for search engines).
- **1 archive:** `gallery/join.php` (it only forwards).

### 6. Phase 5's remaining checks (`phase-5-releases.md` "Done when")

Coordinate with the builder session: it may already own these.
- A test release **turned off and rolled back**, with the audit confirming the site is as before.
- A real release with a switch taken **through its steps**.
- Releases shown **in Pulse and the morning report**.

## 4. Where things are

- **The audit's tools:** `tools/page-audit/` (`audit.py`, `rendered.mjs`, `paths.mjs`, `check-kit.mjs`,
  `findings.py`, `page-map.py`, `routes-check.py`, `review.py`, `zipcheck.py`, `deliveries.py`, `send.py`).
- **The workflows:** `.github/workflows/` (`site-audit.yml`, `site-checks.yml`, `search-probe.yml`).
- **The live site's code:** the mirror `degraffics/traverse-3way-sync`. Add it with `add_repo` and clone it shallow.
- **Secrets on GitHub:** `CHECK_ACCOUNTS`, `MIRROR_READ_TOKEN`, `HEALTH_TOKEN`.
