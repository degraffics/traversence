# Admin rebuild: batch processing overview

Status: Draft (2026-10-21). Inputs: the Master AOS v34.0 (October 2026), Jason's 11 workspaces, decisions 0102 and 0103,
and what's built. The build runs through the Message Batches API: half price, asynchronous, jobs in parallel.

## 1. AOS review: what it means for the build

The AOS is the **platform and business specification**: what Traversence sells, to whom, and how accounts relate. The
11 workspaces are the **admin that runs it**. Each workspace has to administer a part of the AOS, so the AOS gives
the build its data model.

**Fits what's built or decided:**
- the two domains: traversence.com as the hub, and trvgo.us for short codes and QR (0094, 0096);
- the commercial tiers and prices (`commercial.md`);
- 0% platform fee on merchant sales;
- the sacred-site quarantine (0066);
- the inode ceiling, which confirms **Bluehost shared hosting** and settles workspace #11: Redis, replicas and vector
  search show as "coming", not as controls.

**Conflicts to settle before the spec freeze** (a batch job can't ask; it would guess):

| # | AOS says | Built or decided | Decision needed |
|---|---|---|---|
| C1 | Stripe Connect for payments | QuickBooks Payments for Traversence's own sales (0098, 0101) | Proposed: QuickBooks for our sales and services; Stripe Connect for merchants' storefront sales. Confirm. |
| C2 | Listing roles OWNER, MANAGER, EDITOR, CLERK | 0102: Owner, Manager, Accountant, Sales, Support, Creator, Developer | Pick one set; 0102's matrix gets rewritten to match |
| C3 | table `listing_memberships` | 0102: `team_members` | Proposed: use the AOS name |
| C4 | 6-level funnel (Continental → Regional/State → Geo-Hub → Micro-Commerce → Anchor → Sub-Group) | Workspace #3: 5 tiers (Macro Hub → Geo-Hub Zone → Micro-Cluster → Anchor → Sub-Group); code has hubs, geo-hubs, clusters | One list of level names, mapped to the existing tables |
| C5 | Parent/Child, Co-Op and Location Groups | Nexus (0075) groups locations | Is a Location Group a Nexus, or separate? |
| C6 | C2C trades with trust scores | the enforcement ladder has trust flags | Do trade trust points feed the same trust record? |

**New in the AOS, with nothing built yet** (each needs a workspace home):
- personal storage plans and AI access per plan;
- commercial tier entitlements: storage, communication volume, AI quotas, and the overage packs;
- Parent/Child allocation, Co-Ops and their membership tiers, volume discounts;
- the C2C marketplace with dual confirmation;
- HITL staging for AI articles;
- EXIF/GPS stripping;
- the inventory ledger: raw materials, work in progress, finished goods;
- custom domain storefronts.

## 2. Can it be batched? Yes, with three conditions

1. **A frozen spec.** That means the AOS, the workspace map (homes and uses for every tool), the definitions of the new
   terms, and answers to C1–C6. Anything left open gets guessed.
2. **The foundations built first, in-session.** These are the shared pieces every job depends on:
   - the workspace shell;
   - the tool registry;
   - `Team::can()` with the role tables;
   - the shared admin components.

   If parallel jobs each invented their own, the results wouldn't fit together.
3. **Verification here after each batch:** lint, run locally, check at 390px, fix, and send back failures with their
   errors. A batch writes the code; this session proves it.

## 3. The pipeline

| Step | Where | What |
|---|---|---|
| 1. Spec freeze | Jason + session | the AOS, the workspace map, the definitions, C1–C6 signed off |
| 2. Foundations | session | the shell, registry, permissions, shared components, migrations conventions; committed |
| 3. Job packets | session (script) | one JSON request per job (section 4) |
| 4. Submit | script | `messages.batches.create`; results usually within the hour, at most 24 hours |
| 5. Collect | script | results matched by `custom_id`; files written to a staging copy |
| 6. Verify | session | `php -l`, migration check (MySQL-safe), local run, Playwright at 390px and desktop, rules check (section 5) |
| 7. Rework | batch | failed jobs resent with the errors and the files as they stand; repeat until clean |
| 8. Deliver | session | a zip of changed files, the FAQ, an ADR addendum, push |

## 4. A job packet

The parts that are the same for every job come first and are cached once per batch, so each job pays only for its own
part:
1. **Rules** (the same for every job): `CLAUDE.md`'s standards (folders, phone layout, no browser dialogs, password
   boxes, tvSafe, content page standard, nations' land, MySQL-safe migrations) and the coding style.
2. **Contracts** (the same for every job): the foundations' interfaces (shell, registry, `Team::can()`, components),
   the database schema, and the AOS.
3. **The job** (different each time):
   - its workspace section and tool list from the map;
   - the current source of every file it changes;
   - the files it may create;
   - its acceptance checklist.
4. **Output:** structured JSON listing each file's path, its full contents, and whether it's new or changed. Also any
   migration SQL, the FAQ entries, and the open questions it found. A job may only write the files on its list.

Settings: Opus 5.5, high effort. A job sized to a page, a library, or a section of a workspace stays inside one
response's output limit.

## 5. Acceptance checks for every job

- `php -l` clean; pages reach `app/` by relative path; nothing from the backend in `website/`.
- Migrations re-runnable and MySQL-safe (information_schema guards).
- No `alert`/`confirm`/`prompt`; `data-confirm` or TvAsk used instead; fetch bodies through `tvSafe`.
- Phone layout: 3px card edges, card anatomy, `tv_icon()` icons, no sideways scroll at 390px.
- Every page and API checked with `Team::can()` (or the staff area gate); the action log written for staff actions.
- A tool's settings live only in its home; its uses embed it, filtered by context.
- FAQ entries added for anything visible.

## 6. Waves (each wave is one batch; jobs within a wave run in parallel)

| Wave | Jobs | Depends on |
|---|---|---|
| 0 | Foundations: workspace shell (11 workspaces), tool registry, `listing_memberships` and the role tables, `Team::can()`, action log, shared components | in-session |
| 1 | One frame job per workspace (1–11): its sections, existing tools moved into their homes and uses, "coming" placeholders for the rest | 0 |
| 2 | #1 Dashboard and Quick Actions; #2 Pulse Stream with split drawers | 1 |
| 3 | #3 Directory & Places (the taxonomy levels from C4, 20-mile clustering, Single Home Rule); #7 Sales & Growth; #6 Community (charters, transfer terms, deactivation from 0102 and 0103) | 1 |
| 4 | #9 Back-Office: plans and entitlements (tiers, storage, quotas, overage packs), Parent/Child and Co-Op allocation, volume discounts, inventory ledger, consent hub, DMCA queue, verification vault | 1, C1, C5 |
| 5 | #5 Market: C2C with dual confirmation and trust points, Storefront Studio, custom domains, the 250-item cap | 4, C6 |
| 6 | #4 Explore (guides, waypoints, GPX/KML, Chameleon Filter); #8 White Glove (onboarding board, OCR and menu parsing, HITL staging); #10 Insights; #11 Configuration | 1 |

Waves 3 and 6 can run alongside 2 once wave 1 is verified. Wave 4 before 5 because Market draws on plans and
allocations.

## 7. What Jason provides

1. **The decisions on C1–C6** (section 1).
2. **The workspace map:** I draft it from what's built, using your names, and you correct it.
3. **Definitions** for the new terms:
   - Pulse filters and "critical";
   - Chameleon Filter;
   - Post-and-Connect;
   - Storefront Studio;
   - 3-tier moderation;
   - Events;
   - White Glove stages;
   - OCR input and output;
   - the vault;
   - the consent hub;
   - DMCA;
   - the 5 W's parser;
   - emergency mapping.
4. **An Anthropic API key with billing,** added as the environment secret `ANTHROPIC_API_KEY`, plus network access to
   `api.anthropic.com`.
5. **Sign-off at the spec freeze,** and a look at each wave's delivery before the next one is sent.
