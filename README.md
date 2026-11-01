# Traversence

The record of Traversence's governance and technical documentation. `main` is the one current copy.

The site's code isn't here. It runs on the server and in OneDrive, and its live mirror is
[`degraffics/traverse-3way-sync`](https://github.com/degraffics/traverse-3way-sync) (`sync-storage/` = `website/`,
`sync-app/` = `app/`). Build rules for Claude sessions are in `CLAUDE.md`.

## Start here

| File | What it is |
|---|---|
| `charter.md` | Mission and vision |
| `prd.md` | Functional spec and feature roadmap |
| `architecture.md` | System architecture and operational spec (living) |
| `traversence-platform-spec.md` | The live master technical spec: an append-only phase log |
| `decisions/` | Architecture Decision Records, numbered `0001`–`0133`. A newer decision can replace an older one. |

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
| `admin-architecture.md` | The admin: modules, widgets, workflows |
| `admin-map-v2.md`, `workspace-map.md`, `admin-rebuild-batch-plan.md` | Admin structure proposals and the rebuild plan |
| `business-portal-architecture.md` | The business portal (the admin, scoped to a business) |
| `marketplace-architecture.md` | The marketplace |
| `toolkit.md` | The Traversence toolkit |
| `faq.md` | The site's FAQ, shipped as `app/ui/faq.md` |
| `archive.md` | Register of archived and removed site pages |

## Process

| File | What it is |
|---|---|
| `workflow.md` | How the documentation is governed and kept |
| `CLAUDE.md` | Rules every Claude session follows (loaded automatically) |
| `readme-history.md` | The original README's change log, Sept–Oct 2026 |

## Workers

| Folder | What it is |
|---|---|
| `workers/crawler/` | The guide-crawl worker (Railway) |
| `workers/lexicon/` | Builders for the site's word data (from WordNet) |
