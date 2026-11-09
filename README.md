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
| `decisions/` | Architecture Decision Records, numbered `0001`–`0150`. A newer decision can replace an older one. |

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
| `routes.md` | Page structure and routing |
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
| `site-audit/` | The site audit and phased releases (decisions/0145): phases, punch list, rules, scorecard, page tree, proposals |
| `tools/page-audit/` | The audit's code checks, the zip check and the delivery log (hourly, `.github/workflows/site-audit.yml`), and the browser checks (`rendered.mjs`, daily, `site-checks.yml`) |
| `deliveries/` | Every zip and whether each file reached the live site (generated) |
| `CLAUDE.md` | Rules every Claude session follows (loaded automatically) |
| `readme-history.md` | The original README's change log, Sept–Oct 2026 |

## Workers

| Folder | What it is |
|---|---|
| `workers/crawler/` | The guide-crawl worker (Railway) |
| `workers/storefront-edge/` | The edge for businesses' own domains on their storefronts (Fly.io, decisions/0139) |
| `workers/lexicon/` | Builders for the site's word data (from WordNet) |
