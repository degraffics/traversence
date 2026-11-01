# Admin rebuild: batch processing overview

Status: Draft (2026-10-21). Inputs: the Master AOS v40.0 (`master-aos.md`), Jason's 11 workspaces, decisions 0102 and 0103,
and what's built. The build runs through the Message Batches API: half price, asynchronous, jobs in parallel.

## 1. AOS review (v40.0, `master-aos.md`)

The AOS is the **platform and operations specification**. Its §8 holds the 11 workspaces, so the AOS is the build's
data model and its admin map in one document.

**Settled by v40** (no longer open):
- **Payments:** a payment processor ledger with any gateway (Stripe, Square, NMI, Authorize.Net) and 0% commission.
  The QuickBooks Payments already built for our own sales becomes one gateway on that ledger.
- **Location Groups:** each location keeps its own subscription, all on the same tier, with the volume discount.
- **Roles:** 11 platform staff roles (§9A) and 9 business roles (§9B), linked through `listing_memberships`.
- **Hosting:** Bluehost shared (§14). In workspace #11, Redis, database replicas and vector search show as "coming".

**Conflicts to settle before the spec freeze** (a batch job can't ask; it would guess):

| # | AOS v40 says | Built or decided | Decision needed |
|---|---|---|---|
| C1 | §10: support access needs the Owner's grant per ticket; 1-hour token | 0102: a standing grant to a named agent until revoked, plus a ticket queue with timed access, no owner approval | Which model? If the AOS, 0102 §5 is rewritten |
| C2 | §9B: 9 business roles, and the **TRVGO Agent is a business role** (QR and short codes) | 0102: 7 roles; "agent" meant Traversence support | Rewrite 0102's role table to the AOS's 9 |
| C3 | §9A: 11 staff roles (Executive, Tier-1/2/3 support, vendors…) | 0100: Owner, Manager, Editor, Accountant, Executive Assistant, Content Creator, General | Map the old roles to the new, and say which areas each opens |
| C4 | §11.3: 30-day soft delete, then a hard purge | 0103: Notice → Hidden → Archived (1 year) → Removed; nations' data never deleted (0066) | Which wins? Proposed: 0103's stages for accounts, listings and groups; the 30-day purge only for things the owner deleted |
| C5 | §4: 6 funnel levels; §8 #3: 5 tiers (Macro Hub → Geo-Hub Zone → Micro-Cluster → Anchor → Sub-Group) | hubs, geo-hubs, clusters in the code | One list of level names, mapped to the existing tables |
| C6 | §11.4: under 5 minutes of data loss, under 2 hours to recover, automatic failover, containers | Bluehost shared (§14) | Not possible on shared hosting. Proposed: daily encrypted backups now; the RPO/RTO targets wait for a VPS |
| C7 | §11.3: media in S3/R2; §9A: Datadog, CI/CD, JWTs, GraphQL | files on the server; no such services | Mark as "coming" or name the services |
| C8 | §7: C2C trust scores | the enforcement system already keeps trust flags | Do trade trust points feed the same trust record? |
| C9 | Location Groups | Nexus (0075) groups locations | Is a Location Group a Nexus, or separate? |

**New in the AOS, with nothing built yet** (each needs a workspace home):
- personal storage plans and AI access per plan;
- commercial tier entitlements: storage, communication volume, AI quotas, overage packs;
- Location Groups and volume discounts;
- Co-Op Master Hubs;
- the C2C marketplace with dual confirmation;
- the import engine with staging and its preview (§11.1);
- export packages and streaming to outside systems (§11.2);
- the 72-hour verification vault;
- HITL staging for AI articles;
- EXIF/GPS stripping;
- the inventory ledger and COGS;
- custom domain storefronts.

## 2. Can it be batched? Yes, with three conditions

1. **A frozen spec.** That means the AOS, the workspace map (homes and uses for every tool), the definitions of the new
   terms, and answers to C1–C9. Anything left open gets guessed.
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
| 1. Spec freeze | Jason + session | the AOS, the workspace map, the definitions, C1–C9 signed off |
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
| 4 | #9 Back-Office: plans and entitlements (tiers, storage, quotas, overage packs), Location Groups and Co-Ops, volume discounts, the payment ledger, import and export, inventory ledger, consent hub, DMCA queue, verification vault | 1, C4, C9 |
| 5 | #5 Market: C2C with dual confirmation and trust points, Storefront Studio, custom domains, the 250-item cap | 4, C8 |
| 6 | #4 Explore (guides, waypoints, GPX/KML, Chameleon Filter); #8 White Glove (onboarding board, OCR and menu parsing, HITL staging); #10 Insights; #11 Configuration | 1 |

Waves 3 and 6 can run alongside 2 once wave 1 is verified. Wave 4 before 5 because Market draws on plans and
allocations.

## 7. What Jason provides

1. **The decisions on C1–C9** (section 1).
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
