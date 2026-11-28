# Traversence

The record of Traversence's governance and technical documentation. `main` is the one current copy.

The site's code isn't here. It runs on the server and in OneDrive, and its live mirror is
[`degraffics/traverse-3way-sync`](https://github.com/degraffics/traverse-3way-sync) (`sync-storage/` = `website/`,
`sync-app/` = `app/`, `sync-trvgo/` = `trvgo/`). Build rules for Claude sessions are in `CLAUDE.md`.

## Start here

| File | What it is |
|---|---|
| `charter.md` | Mission and vision |
| `prd.md` | Functional spec and feature roadmap |
| `architecture.md` | System architecture and operational spec (living) |
| `traversence-platform-spec.md` | The live master technical spec: an append-only phase log |
| `decisions/` | Architecture Decision Records, numbered `0001`–`0181`. A newer decision can replace an older one. |

## Business and brand

| File | What it is |
|---|---|
| `commercial.md` | Pricing tiers, partner model, revenue and cost projections |
| `brand.md` | Brand identity and UI/UX guidelines |
| `regions.md` | Pilot geography: geo-hub zones, micro-clusters, towns |
| `phases.md` | Phased development and phase gates |
| `future-considerations.md` | Parked, speculative ideas (not scheduled) |
| `claude-docs/` | Exports of the Claude Docs: Traversence BRD and FRD, SJ Portables BRD and FRD, competitive positioning |

## Site structure and admin

| File | What it is |
|---|---|
| `site-audit/pages.md` | Page structure and routing: every page, live or coming (it replaced `routes.md`, archived in `site-audit/archive/`, P6) |
| `master-aos.md` | Master Administrative & Operational Specification (Traversence and TRVGO) |
| `aos-gap-review.md` | What's built and what's left against the AOS |
| `ux-review.md` | UI/UX check of the visitor path, start to finish, against the build standards (2026-11-01) |
| `admin-architecture.md` | The admin: modules, widgets, workflows |
| `admin-map-v2.md`, `workspace-map.md`, `admin-rebuild-batch-plan.md` | Admin structure proposals and the rebuild plan |
| `business-portal-architecture.md` | The business portal (the admin, scoped to a business) |
| `marketplace-architecture.md` | The marketplace (§8: the hand-off for storefronts and Connect your domain) |
| `market360-build-briefs.md` | The seven build briefs for Market 360° (decisions/0141, 0142), one zip each, in order |
| `community-scope.md` | Community build: what's live and proposed, how it compares (Nextdoor, Google, Meta, Messenger, AI chats), its decisions |
| `toolkit.md` | The Traversence toolkit |
| `faq.md` | The site's FAQ, shipped as `app/ui/faq.md` |
| `archive.md` | Register of archived and removed site pages |
| `design/` | Reference mockups and the design directives (`css-theme-brand.md`, `table-standard.md`); index in its README |

## Process

| File | What it is |
|---|---|
| `workflow.md` | How the documentation is governed and kept |
| `research/` | Research notes and audits, dated; index in its README |
| `site-audit/` | The site audit and phased releases (decisions/0145): phases, punch list, rules, scorecard, page tree, paths, page map, proposals |
| `tools/page-audit/` | The site audit's code checks, the zip check, check-out (`checkout.py`), the delivery log, the browser and path checks, the search and layout probes, Piece 3's before-and-after check; index in its README |
| `.github/workflows/release-ping.yml` | Tells the live site the moment a build lands in `releases/inbox/` (`/api/releases-pull.php`), so it's taken within a minute |
| `.github/workflows/live-read.yml` | A build's live read (Jason, 2026-11-23): Run workflow signs in as the test Executive, reads only the named admin pages (`tools/page-audit/live-read.mjs`), keeps them one day as the run's artifact, never committed |
| `tools/batch/` | `batch.py`: non-urgent analysis sent to Claude's Batch API at half price, from the API credit, never the site's $40 (Jason, 2026-11-28): submit with a `--max-usd` limit, status, fetch the answers and the real cost into `research/ai-results/`; needs `BATCH_API_KEY` in the environment; every job names its Work-queue ticket, or it's refused (`test_batch_ticket.py`; Jason, 2026-11-28) |
| `tools/page-audit/ticket.py` | No work without a ticket (Jason, 2026-11-28): `send.py` refuses a release.json without `"ticket"` (its Work-queue id, or a seeded ticket's exact title), `tools/batch/batch.py` a job without one (`test_send_ticket.py`) |
| `tools/task-seed-tests/` | The Work queue's seeds on MariaDB: `seed4.php` (the goals, 20 checks), `seed5.php` (every current item a ticket at its real phase, 24 checks), `seed6.php` (one ticket per agent in the CEO's wave order and the session manager's tickets, 20 checks) |
| `tools/w0-tests/` | W0 Indexer's tests: `frontier.php` (the frontier on a real MySQL or MariaDB: normalizing, batches and receipts, junk, the machine's call, priority, pages' answers, verdicts and Undo, skip rules, the size guard) |
| `tools/control-board-tests/` | decisions/0171's tests, one file per Control Board release; index in its README |
| `tools/notify-tests/` | decisions/0172's tests (the one dispatcher, mail, push, Pulse); index in its README |
| `tools/design-tests/` | The design releases' tests (`design/css-theme-brand.md`); index in its README |
| `tools/trip-tests/` | Trips' tests (decisions/0174); index in its README |
| `tools/dispatcher-tests/` | decisions/0176's tests (the alarm thread, Fix it, uploads); index in its README |
| `tools/order-tests/` | Orders' tests (order management, ordering releases, the rulings); index in its README |
| `tools/place-tests/` | Places' tests (Wikidata, the Census backup); index in its README |
| `tools/listing-tests/` | Business listings' tests (claims, review, hidden listings); index in its README |
| `tools/team-tests/` | Piece 2's tests (decisions/0179); index in its README |
| `tools/search-tests/` | Search's tests (the launch plan's C.1, C.4); index in its README |
| `tools/event-tests/` | Events' tests (decisions/0134's minimum and participants): `events.php` and `portal.cjs` (E1, the host's form), `events-public.php` and `event-page.cjs` (E2, the event page), `events-parts.php` and `portal-parts.cjs` (P1, invitations and offers), `event-parts-page.cjs` (P2, Taking part on the event page) |
| `tools/posts-tests/` | `pdo-error.php`: user/api/posts.php's database errors logged, never shown (8 checks, no database) |
| `tools/path-checker-tests/` | The path checker's own tests (the launch plan's C.2); index in its README |
| `tools/release-tests/` | The release pipeline's record in git: `record.php` (a release record refused as stale is read and written again; one that won't save is said on its card and saved by the 10-minute job; Release #301's missing Live, 2026-11-26; 15 checks, a fake GitHub) |
| `tools/notify-founder-tests/` | notify-founder's test: `founder.php` (the site emails the Founder itself: today's batch, a Tier 3 decision and the session manager's messages, each once, to the Founder alone, never from staging; 15 checks on MariaDB) |
| `tools/config-rail-tests/` | config-rail's test (Jason, 2026-11-25: System Configuration's pages in the side menu): `config-rail.cjs` with `router.php` (the real shell and menu at 1280 and 390: order, current page, greyed with why, 20 checks) |
| `tools/piece3-tests/` | Piece 3's tests (decisions/0179 rule 2); index in its README |
| `tools/staging-tests/` | decisions/0165's tests (staging in safe mode); index in its README |
| `.claude/settings.json` | Claude Code permissions for sessions here: archiving a finished session (Jason, 2026-11-18), and emailing or pushing to Jason (2026-11-25), are allowed without a prompt; CLAUDE.md's Standing OKs lists the rest |
| `.claude/skills/page-review/` | The page review (Phase 4): findings to verdicts and proposals in `site-audit/proposals.md` |
| `handoffs/` | Hand-offs from one Claude session to the next (`session-manager.md`, `builder-playbook.md`, `decided-not-live.md` and each build's record); index in its README |
| `deliveries/` | Every zip and whether each file reached the live site (generated); `deliveries/releases/`: each release's record, written by the site's Releases page |
| `releases/batch.json` | Today's batch for the Founder's OK, written by the session manager (decisions/0179 rule 1; P2-5); `gauges.py` carries it, and `releases/gauge-pickups.json`'s emailed answers, into `gauges.json` |
| `releases/inbox/` | Claude's finished builds as release zips (put there by `tools/page-audit/send.py`); the live site takes each onto staging (decisions/0159 §5, Phase 6) |
| `CLAUDE.md` | Rules every Claude session follows (loaded automatically) |
| `readme-history.md` | The original README's change log, Sept–Oct 2026 |

## Workers

| Folder | What it is |
|---|---|
| `workers/crawler/` | The guide-crawl worker (Railway; Common Crawl's bulk reads from its URL Index since 2.15.0, `tests/test_cc_columnar.py`), and W0 Indexer (`indexer.py`, always on, its own Railway service from `railway.indexer.json`; decisions/0164 §1.4) |
| `workers/video/` | The video worker: 720p and 1080p copies of posted videos (Railway, ffmpeg, decisions/0153 §8) |
| `workers/storefront-edge/` | The edge for businesses' own domains on their storefronts (Fly.io, decisions/0139) |
| `workers/lexicon/` | Builders for the site's word data (from WordNet) |
| `extension/vault/` | The Traversence Vault browser extension (Chrome and Edge, staff, decisions/0156) |
