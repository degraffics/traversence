# 0173: One lifecycle for every outside service: add, activate, pause, retire, remove

Status: **accepted** (Jason, 2026-11-19, draft 2, approved with the decision pop-up; reviewed by the session manager).
His answers: Railway's keys are kept on Railway (§3); the Proposed stage is kept (§2). The Control Board session wrote
it at Jason's request, relayed by the session manager. **Building:** release 1, One home, sent as
`service-register-r1.zip` (live as #209); release 2, the key on its card, sent as `service-keys-r2.zip` (built on
Sync 22:49 UTC; its writer is named `EnvSettings::setKey()`, since `setSecret()` already writes the secrets the site
makes); next, release 3, defined once.
Builds on:
- 0152: Outside services by company.
- 0163: the service register (§1), the switches (§9), switching back on (§10).
- 0165: staging in safe mode.
- 0167 §4: secrets written by the site, never shown.
- 0169 §5–6: one lifecycle for every app, each defined once.
- 0171: the System Control Board.

Amends (each marked **Note for Jason** where it happens):
- 0152 §1: Outside services' cards move into the Service register; their address becomes a redirect.
- 0163 §1: the register becomes the one home. A key is entered on its card, not in cPanel.
- 0167 §4: its writer extends to every service's key.

Nothing else is reversed. Checked against the live mirror at "Sync: 2026-11-19 21:49 UTC".

## What Jason asked for

Jason, 2026-11-19, relayed by the session manager:
- "Based on osm not being available its made me think about how we add, activate, deactivate, and remove a service. We
  need a module that handles this. Look at the system session and how we can build this."
- "See how api is reference is here. Same issue for add a service."
- "Put it in the Control Board's Service register… this would change outside services right?"

## What exists (checked in the live code)

- **Two pages show the same 44 services.** Both are keyed by `OutsideServices::COMPANIES`:
  - **Outside services** (`/admin/config/services`, `tv_outside_services()` in `app/ui/outside-services.php`): one card
    per company. It holds what we use it for, Is it working, Set in other sections, What it costs, Its settings
    (`.env` rows through `EnvSettings`), In our admin, On the company's own site, Terms and who to ask, Waiting on them,
    the servers (add, pause, resume) and the month's report.
  - **Service register** (`/admin/config/register`, `tv_admin_mod_register()` in `app/admin/operations-console.php`):
    one fold per service. It holds the status summary, Watching Railway and the sync, Used by, Its key (named, never
    shown), Known price, This month, Produced, its open issue with the buttons that fix it (`tv_ops_issues`), and Caps and
    paces.
  - Both use the anchor `#svc-<key>` with the same keys, so one address can serve every link.
- **Links to them: 37 in 18 files.**
  - 15, in 8 files, go to `/admin/config/register…`: the board's alarms, Operations' caps, Geofabrik's notice.
  - 22, in 12 files, go to `/admin/config/services…`: Pulse's cases, Pre-flight, Automation, Communications, Sources,
    job lines, the monthly email (`Services.php:151`) and section-about.
  - One is broken today: Communications' "Amazon's card" points at `#amazon`, but the card is `#svc-aws`.
- **A service's definition is spread over at least 9 constants:**
  - `OutsideServices::COMPANIES`, `HOSTS`, `NOTICES`, `CONTACT` and `LEDGER`;
  - `Operations::KEYS`, `CAPS` and `ADDED_OFF`;
  - `StagingGate::ANSWERS`;
  - `Thresholds::GATES`, `ServiceHealth`'s probes, and `Preflight`'s line and `serviceTest`.

  Adding one means finding all of them. Retiring Overpass (#204) took 11 files and still left 5 entries behind:
  `NOTICES['osm']`, `HOSTS['osm']`, `CONTACT['osm']`, `Operations::CAPS['osm']` and `StagingGate::ANSWERS['osm']`.
- **Keys are added by hand.** `Operations::whereToAdd()` sends a person to cPanel's File Manager or to Railway's
  Variables. `EnvSettings::EDITABLE` refuses secrets.
  - The one writer that takes secrets is 0167's `EnvSettings::setMailSecret()`, which covers only `MAIL_SECRETS`. It
    keeps the old value as a dated `# OLD` line, then calls `putBack()` and `forgetOld()`.
  - Its guards (owner-only, never on staging) are in its callers, not in the writer.
- **Switching a service off or on** is Automation's pause: `svc_off` and `svc_on`, and `svc_on` passes 0163 §10's gate.
  - Services ship paused (`Operations::ADDED_OFF`, Jason, 2026-11-15).
  - The board can't pause a service, only show its trips.
  - Nothing records "retired" or "removed" except comments and a FAQ entry.
- **A bug found in the survey:** `StagingGate::here()` was private, so the staging guards in `CommsSetup`, `Mailboxes`
  and `SesInbound` never held. It is fixed in release `staging-guard` (PR #1110, under 0165), not here.

## Decision

### 1. One home: the Service register

**The Service register** (`/admin/config/register`) is the one page for every outside service, opened from the Control
Board. Each service is one card, under its company's group row in the admin's one table (`design/table-standard.md`).
Its stage shows in one column, with ⋯ for the stage's buttons.

**Note for Jason** (0152 §1): Outside services becomes part of it. What happens to each part:

| Kept on the card, from Outside services | Kept, from the register | Moved | Dropped |
|---|---|---|---|
| What we use it for; Is it working; What it costs; Its settings; In our admin; On the company's site; Terms and who to ask; Waiting on them; its servers | Status with its reason; Used by; Its key; Known price; This month; Produced; its open issue and buttons; Caps and paces | The month's totals and the month's report go to the register's top. Watching Railway and the sync goes to the Control Board's Watch. | "Set in other sections": the card's buttons do it in place (Pre-flight, Automation, Communications open in the drawer, `at_hand`). Two status lines saying the same thing become one. |

- **Addresses.**
  - `/admin/config/register` stays.
  - `/admin/config/services` stays as a redirect that keeps the query and the `#svc-<key>` anchor. A fragment never
    reaches the server, so the redirect is the page's own `location.replace(…)`, with a plain link for no-script.
  - The 22 links are changed to the register in the same release, `#amazon` fixed with them. The redirect is there for old emails and bookmarks.
  - `peek-anchor.cjs` must still land on the one card, through the redirect too.
- Access stays as it is: `config.operations` to see, `config.services.notes` and `config.services.servers` for their
  forms. The key field (§3) is owner-only.

### 2. The stages, with 0169's words where they fit

| Stage | 0169 §5 | What it means | Its button, and the way back |
|---|---|---|---|
| **Proposed** | Choose | Named on the register (what for, link, terms, cost, why). No code calls it. | **Propose a service** (name, link, why). Back: **Drop it**. A proposal is a build request for a session, not a running service. |
| **Added, off** | Install | A release shipped its definition (§4). It arrives paused, as `ADDED_OFF` does today. | Comes with the release. Back: the release's own Withdraw. |
| **Key in** | Configure | Its key is entered on the card (§3) and **Check** proves it with the service's own pre-flight test. | **Add its key** / **Replace** / **Remove the key**. Back: **Put the old one back** until the next Check passes. |
| **Active** | Use | Called by its workflows, inside its caps and paces. | **Activate**: Automation's `svc_on`, through 0163 §10's gate. Back: **Pause**. |
| **Paused** | (Update's pause) | Nothing calls it. Its key stays, and the reason is shown with who and when. | **Pause**: `svc_off`, and on the board too. Back: **Activate**, through the gate. |
| **Retired** | Remove | Kept as a record. The code sends nothing, the budget is 0, its alarms are settled (the way `retireOsm()` does it today), and the card says why and what replaced it. | **Retire**, with "why" and "what replaces it". Back: **Bring it back**, which returns it to Paused and needs a release if code was removed. |
| **Removed** | Remove | Its code and key are gone; the record stays on the register's **Removed** list. | Done in a release: dead code is dropped with `--drops` after Jason's OK, and the key goes through §3's writer. Back: the sync history and the kept `# OLD` line. |

- Each stage change is logged (who, when, why) in a `service_stages` table, and the card shows its history.
- Data is never deleted at Retire. Every press runs the Admin's test first, as the board's buttons do.
- The board's process for a service gains **Pause** and **Activate** beside Check and start again.
- Pulse stays trips only: a stage change is never a Pulse case. A failed Check is the card's own issue, with its fix.

### 3. A service's key, on its own card

**Note for Jason** (0163 §1 and 0167 §4): the "add it in cPanel" step ends for `.env` keys.
- **Add its key** opens the site's password box on the card (Show/Hide, nothing shown back). The value goes through one
  writer, `EnvSettings::setSecret()`. It is 0167's writer extended to every key a service's definition names as
  `.env`, and `MAIL_SECRETS` become some of them.
- **The guards move into the writer**, so no caller can skip them:
  - owner-only (`Access::can`, `min: owner`);
  - never on staging, using the public `Staging::here()`;
  - the value is never shown, logged or put in a report (the log names who, when and which key);
  - the old value is kept as `# OLD <key> <date>` until Check passes.
- **Check** runs at once: the service's `Preflight::serviceTest` with the new key. Pass: the old value is forgotten, and
  the stage is Key in. Fail: the card says what the service answered and offers **Put the old one back**.
- **Remove the key** goes through the same writer: kept as `# OLD`, then gone at the next passing Check of the rest, or
  by **Forget it**.
- **Keys kept on Railway** (Google Places, Tavily, Brave, RIDB, the Hugging Face token): **kept on Railway** (Jason,
  2026-11-19, in the pop-up: "Keep on Railway"). The two choices he had:
  - **Kept on Railway:** he enters them on Railway's Variables page, an outside trip like the cPanel one he objected
    to. The card gives the link and Check confirms the key from the worker's key report (`worker:keys`).
  - **Written from the card:** every key in one place, through Railway's API (`variableUpsert`, with the
    `RAILWAY_API_TOKEN` the site already holds for watching). Each save redeploys the crawler, about a minute. A run in
    progress is cut short and retried at its next turn.
  - No decision forbids the site redeploying Railway. 0171 (§ Consequences) says only that the board doesn't change
    Railway's cron or restart policy. Keeping the site's hands off Railway has been this session's working habit.
- **The first user is the National Park Service:** `NPS_API_KEY` from `https://api.data.gov/signup/`, for the stock
  photos (`StockLibrary`). It's added on its card, then checked.

### 4. Each service defined once

As in 0169 §6, each service is defined in one place: `app/lib/service-defs.php`, one entry per service. It holds:
- its company, what it's for and its price;
- its hosts, keys (with where each lives), notices, contact and ledger names;
- its caps (the gates);
- its staging answer;
- its probe and pre-flight test;
- its stage on shipping (`added_off`, with the reason).

The 9 constants become reads of it, one release at a time, and nothing else changes behaviour.

**Note (2026-11-19, release 3; the session manager, as the decision's intent):** PHP's class constants can't be computed
from a file, so the lists aren't read from `service-defs.php` at run time: they're **generated** from it by
`tools/page-audit/service-defs.php` (`--write`), with the same values, and the zip check (`--check`) blocks any release
whose lists differ from the file, saying to run it. One place to edit, no hunt across files; no caller changed. The
retired records (`ServiceStages::RETIRED`) are generated from it too. A new service is one
entry and its connector. A retired one keeps its entry with `stage: retired`, so its notice and its words come from one
place.

**Overpass, OpenStreetMap's live query server, is the register's first Retired record**: why (its community's
request on the forum, #204, 2026-11-19), what replaced it ("our own copy from Geofabrik"), and when. It gets its own
entry, `overpass`. Today it shares the `osm` key with the rest of OpenStreetMap.
- The 5 leftovers move into Overpass's entry: its hosts (`overpass-api.de` and the mirrors) out of `HOSTS['osm']`, the
  `overpass` contact, its gates out of `Operations::CAPS['osm']`, and its notice and staging answer.
- **OpenStreetMap stays Active**: our Geofabrik copy, the map tiles and the credit "© OpenStreetMap contributors" (0065,
  0066) are unchanged.

### 5. What doesn't change

- Caps and paces stay the register's one setting (0163 §1).
- Outside calls still pass `StagingGate::hold()`, and no test reaches a live gateway (0145).
- Nothing new costs money: a Check is one call inside the service's own free allowance, or none where it has no test.
- Apps that accounts install (0169) keep their own lifecycle and their sealed tokens. This decision covers only our
  platform's own services and their `.env` keys.

## Build order (each a release Jason reviews; sizes are estimates)

1. **One home**, about 6 files:
   - the cards merged into the register, and `/admin/config/services` as a redirect;
   - the 22 links changed (and `#amazon` to `#svc-aws`);
   - the stage column and its log (`service_stages`, with a migration and its staging class);
   - Pause and Activate on the board;
   - Overpass as the first Retired record (OpenStreetMap stays Active);
   - FAQ;
   - tests: `peek-anchor.cjs` through the redirect, plus a `register.php` test for the stages and the way back.
2. **The key on its card**, about 5 files:
   - `EnvSettings::setSecret()` with its guards;
   - Add, Replace, Remove, Put back and Check on the card;
   - the Railway keys' link to Railway's Variables page (kept there, §3), with their Check from `worker:keys`;
   - NPS first;
   - tests: the writer's guards (staging, a non-owner, nothing logged) and Undo.
3. **Defined once**, about 10 files:
   - `service-defs.php`, with the constants turned into reads of it;
   - Overpass's own entry, with its 5 leftovers moved into it;
   - Propose a service, Retire and Bring it back;
   - the Removed list;
   - tests: every constant reads the same values as before.

## Consequences

- One page and one address for a service. An alarm's Open its card, a Pulse case and Pre-flight all land on the same card.
- Adding a `.env` key no longer needs cPanel, and it's proven at once with a way back. Railway's keys stay on Railway
  (§3), with their link and Check.
- Retiring a service is one press and one record, not 11 files and a search for leftovers.
- Risk: the merged card is long. It folds by section (`h2.hb-h`), so the issue and the stage come first.
