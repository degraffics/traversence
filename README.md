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
| `decisions/` | Architecture Decision Records, numbered `0001`–`0163`. A newer decision can replace an older one. |

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
| `design/` | Reference mockups: `community-landing-mockup.png` (to build to); `marketplace-landing-mockup.png` (Jason, 2026-11-02: the Marketplace landing page to review for a cleaner, more functional design); `css-shells-brief.md` (2026-11-04: the page shells and class names for the CSS rework) |

## Process

| File | What it is |
|---|---|
| `workflow.md` | How the documentation is governed and kept |
| `research/` | Research notes: `state-tourism-apis.md` (2026-11-12): each state tourism office's listings and events data, any API or feed, and its terms; `osm-overpass-2026-11-13.md`: whether OpenStreetMap's Overpass blocks us (a 406 user-agent ban on overpass-api.de), the evidence, and a statement for the OSM community; `crawler-review-2026-11-13.md`: the crawler's full scope vetted (finding businesses, sources, upkeep, learning and the admin, guides, the rules), the fixes in order of risk; `crawler-how-it-runs-2026-11-14.md`: automation operations for review: the crawler, the website's workflows and the services they use (where we stand, the layers phase → workflow → task → route → service, the gaps, the switches, register, board, warnings, watching, build order); then how the crawler runs today; `knowledge-engine-architecture-2026-11-14.md`: Jason's Knowledge Engine (Phases 1–6) in his structure, with the session manager's improvements and concerns, mapped onto what exists, rural businesses without websites (licences, open place datasets, Gemini grounded search, corroboration) and ten decisions for him (draft, to become an amendment to 0133) |
| `site-audit/` | The site audit and phased releases (decisions/0145): phases, punch list, rules, scorecard, page tree, paths, page map, proposals |
| `tools/page-audit/` | The audit's code checks, the zip check (it also blocks a zip that drops earlier work), check-out for site files (`checkout.py`, claims in `releases/checkouts.json`, decisions/0161; tests in `test_checkout.py`) and the delivery log (twice a day, `.github/workflows/site-audit.yml`), the browser checks and the paths (`rendered.mjs`, `paths.mjs`, sharing `check-kit.mjs`; daily, `site-checks.yml`), the findings for Pulse's Site health and the page map (`findings.py`, `page-map.py`), routes against the register `site-audit/pages.md` (`routes-check.py`), live files changed without a release (`drift.py`, after each sync; `site-audit/drift-ignore.txt`), the decisions for the admin's search and its Decisions page (`decisions-index.py`), the review's inputs (`review.py`), and the search probe and the phone layout probe (`search-probe.mjs`, `layout-probe.mjs`, daily, `search-probe.yml`) |
| `.claude/skills/page-review/` | The page review (Phase 4): findings to verdicts and proposals in `site-audit/proposals.md` |
| `handoffs/` | Hand-offs from one Claude session to the next: `migration-safety.md` (decisions/0162, finished: migrations at publish, copies first, leftovers and unused schema in Pulse; live as releases #45 and #47); `0159-the-builder.md` (build decisions/0159: step 4 done; next, step 5); `crawler-revamp.md` (the crawler revamp on decisions/0160: stop the damage, close the exposure, the crawler service, each workflow onto it, the old data brought into line); `crawler-revamp-pending/` (its next releases, built, tested and approved, as patches to send in order); `automation-switches.md` (the master switch, workflows, tasks and services, shipped paused; decisions/0163 §9); `session-manager.md` (the session manager's job, checks and rules, for a fresh one); `site-audit.md` (the audit's Phases 3–5 finished: scheduled checks that run, the staging paths, the live path failures, the review, routes against the page tree) |
| `deliveries/` | Every zip and whether each file reached the live site (generated); `deliveries/releases/`: each release's record, written by the site's Releases page |
| `releases/inbox/` | Claude's finished builds as release zips (put there by `tools/page-audit/send.py`); the live site takes each onto staging (decisions/0159 §5, Phase 6) |
| `CLAUDE.md` | Rules every Claude session follows (loaded automatically) |
| `readme-history.md` | The original README's change log, Sept–Oct 2026 |

## Workers

| Folder | What it is |
|---|---|
| `workers/crawler/` | The guide-crawl worker (Railway) |
| `workers/video/` | The video worker: 720p and 1080p copies of posted videos (Railway, ffmpeg, decisions/0153 §8) |
| `workers/storefront-edge/` | The edge for businesses' own domains on their storefronts (Fly.io, decisions/0139) |
| `workers/lexicon/` | Builders for the site's word data (from WordNet) |
| `extension/vault/` | The Traversence Vault browser extension (Chrome and Edge, staff, decisions/0156) |
