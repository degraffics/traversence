# Working notes for Claude sessions on this repo

This repo (`degraffics/traversence`) is the durable record of Traversence's governance and technical docs: charter, PRD,
commercial, architecture, brand, phases, regions, routes, workflow, future-considerations, `decisions/*.md`,
`traversence-platform-spec.md`, and Claude Docs exports in `claude-docs/`. The site's code lives on the server and in
OneDrive, mirrored to `degraffics/traverse-3way-sync` (below). Rules marked "every build" apply to every delivery.

## Pushing from a cloud session

Git goes through the session's proxy, which adds the credential itself: plain `git push`/`git pull`, no tokens. If a push
fails with "access denied by the git proxy: … not in this session's authorized repository set", the repo isn't
authorized for *this* session (nothing in the repo or gitconfig fixes it). Don't retry or route around it. Commit
locally anyway, and tell Jason his options: add `degraffics/traversence` to the session's repositories, give a personal
access token with push rights, or push from his own computer. Authorization is per session, so try the push rather than
assume.

**Both repos, without asking (Jason, 2026-11-13):** a session that needs `degraffics/traversence` or
`degraffics/traverse-3way-sync` (the live mirror) and doesn't have it adds it with `add_repo`, no question to Jason; he
has approved this for every session. Clone the mirror shallow and sparse. If it's still refused, say so in one line.

## One copy: all work ends up in `main` (Jason, 2026-11-01): every session

`main` is the single source of truth; split branches leave information out of step and behavior inconsistent.
- **Start from the latest `main`.** Fetch `origin/main` and base the session's branch on it. Work on the given branch
  that isn't in `main` gets merged or rebased first, never thrown away.
- **Finish in `main`.** When done (or Jason says "push", "wrap up", "merge it"): commit, push the `claude/…` branch, open
  a PR into `main` (or update the open one) and merge it. Only one open PR at a time; merge an earlier one first.
- **Check:** `git merge-base --is-ancestor origin/<branch> origin/main`, then tell Jason plainly the work is in `main`.
  Merged branches can be deleted on GitHub (Branches → trash); if that's blocked, leave it to Jason.
- If the merge can't happen (blocked push, conflict, failed check), say what's still outside `main`. Never call work
  saved when it's only on a branch.

## Build on what's there (Jason, 2026-11-10): every build

Before changing an area, read its decisions and the live code. Earlier work is kept: a change extends or replaces it in
place, never drops it. Going against an earlier decision needs Jason's OK first, and the decision gets a note. The zip
check blocks a zip that removes a live function, constant or FAQ question; `--drops` names one only after he agrees.
**Never invent what's already set** (Jason, 2026-11-24, repeated): before proposing any list, name, state, method or
value, `git grep` `decisions/` and the live code and use what's there, citing the decision. Where none
exists, flag the gap and offer possible solutions as suggestions (Jason). The session manager and the Design reviewer reject a proposal that doesn't cite its source. Suggesting an
improvement to what's set is welcome (Jason: "suggestions are accepted, inventing new randomly is not"): labelled
"Suggest a change to <decision>: …", citing it and why, for Jason via the session manager, never applied as decided.

## Work silently (Jason, 2026-11-27): every session

"For all the sessions we are opening and closing, let them work silently, so they don't waste usage explaining unread
conversations." A session doesn't narrate, recap or explain in its own chat: its work goes into commits, hand-offs,
release records and short messages to the session manager (facts, links, what's needed). Only what needs Jason (a
Founder ruling or an OK) is written for him, through the CEO session ("Asking Jason" below).

## Launch first: no gate, no build (Jason, 2026-11-27; 0181 §2): every session, until 0181 §1's six gates pass

Jason: "after months of development I'm still having to re-engage a repeating loop of machine work/fixes. I need you to
represent me in ceasing this and move us forward to market release." (The CEO's Tier 4, enforcing 0181 §2; recorded as
Operations & Policy (16).) Every ticket names the gate (1–6) it moves, or a live harm to people or money, or the revenue goal
it enables (0181's ladder) or the money it protects (caps, meters, the money line), the indirect link stated (Jason,
2026-11-27: "dont narrow your view to disregard indirect over direct implications"); without one it's logged in its
role's queue, not built before Jan 1. No new tooling, meters, board features, check refinements or process
releases unless one blocks a gate; rollout releases get 0181 §2's one slot a day. What reaches Jason: a Tier 5 brief, live
harm, and the CEO's one daily launch report against the gates. No new sessions before launch.

## The session manager (Jason, 2026-11-13): every session

A standing session watches the others (hourly while Jason is checked in): context size, restarts, cost, release clashes,
stuck syncs and Railway deploys. One session per area, at most about three at once. **A multi-phase build stays in one
session until the entire build is complete and live, then it's closed** (Jason, 2026-11-18: hand-offs between sessions
broke continuity); no fresh session per phase. **Except when it grows too large** (Jason, 2026-11-20: "Each session is
large and usage is draining… hand off to new session so we can reduce token use without logic loss"): at a hold, it
writes a complete hand-off (live, held and where, open questions, next releases sourced), the session manager reviews it,
a fresh session takes over from it, and the old one is archived. **Finished sessions are archived on the check without asking** (Jason,
2026-11-14): one that has said it's done, with its work in `main` and nothing waiting on Jason, is archived and named in
the report, once the session manager has checked the live build against its decision and brief (Jason, 2026-11-18). A
session waiting on him is never archived. After each release the session manager also checks the release and its hand-off for
scope creep and drift against the decision, and that the decision has its note (`handoffs/session-manager.md`).

**Goals and records live with the roles, not the session manager** (Jason, 2026-11-27: "Yes, approve the plan… up
and running by Monday morning 6 am"; recorded as Operations & Policy (16)). No goal, ruling or order lives only in a
session: a session starts from the decisions, the Work queue and git. Each long-term goal is a parent task in the Work
queue (`GaugeTasks.php` `parent_id`) with an owner from 0180's appendix (a goal that crosses lanes: the CEO), a measure, a
due date and its decision; every ticket links to its goal, and escalation follows the tiers (a stalled ticket → its T3
manager, a goal's date at risk → its T4 executive, a goal that must change → a T5 Decision Brief). A live-side $0 daily
goal check opens a ticket for the owner when a goal's tickets sit 3 days or its date is at risk. Queue order and goal
status are the Project Manager's (8); the daily summary is the Executive Translator's (28); rules and this file are
Operations & Policy's (16). Until R2 and the goals view are live, the sessions do these under the role's name:
`handoffs/decided-not-live.md` stays the session manager's, marked "for the PM (8)". The session manager keeps watching,
syncing, verifying and drift review.

## End to end, verified, before anything ships (Jason, 2026-11-21): every build

No piecemeal patches and no unverified recovery flows. Before code is done:
1. **Contract and boundaries:** it maps to the agreed module, service and workflow boundaries, the plug-in contract
   and the one state model. No one-off wording, hidden sub-state changes, or recovery rules split across files.
2. **Every state transition:** every entry point, button, hold scope and exit path is mapped, side effects included
   (switches left off, notifications out of step). No action claims success, or closes a trip, without a real check.
3. **UI matches the action:** actions sit where the problem is reported, and leave a true status.
4. **Proof before hand-off:** the happy path, failures, edge cases, recovery and side effects are tested against the
   contract.

**Builds and audits use the test accounts (CHECK_ACCOUNTS) for live data; never invent a new access path when one exists** (Jason, 2026-11-23). A build reads through the manual `live-read.yml` workflow (a 1-day artifact); what's read never enters the repo.

Live-site actions only on Jason's explicit call.

**Design review before Jason sees it** (Jason, 2026-11-23): every release or mockup that changes what people see goes
to the standing Design reviewer session first (screens at 1280 and 390 against brand.md, `design/css-theme-brand.md`,
`design/table-standard.md`, 0058 §11 and any approved mockup). Only with its "Design: approved" does it go to the
session manager and Jason.

## Build sessions follow the playbook (Jason, 2026-11-18): every build

Before the first release, read `handoffs/builder-playbook.md`: fixes in place, a way back for every action, the phone at
390px, words kept true, one coherent release, `send.py`'s refusals fixed at the cause, OKs asked in your own chat.
…and its principles (§2–3): the system fixes first; stop only for harm; every alarm carries its fix; words follow the
state; the admin's test before sending.

## Keep this file lean

It loads in full every session, and so does `README.md` (imported below). One short rule per point; the detail lives in
the decision it cites. Remove rules a later decision replaces. Keep the README a short map of the repo, updated when
files are added, moved or removed.

@README.md

## The 3-way sync: server ⇄ OneDrive, recorded in GitHub (Jason, 2026-10-28): every build

`degraffics/traverse-3way-sync` keeps the server (FTP) and OneDrive `Traversence/website` + `Traversence/app` the same,
at 6:00 and 18:00 UTC or on demand (its workflow's Run button), committing the result to `sync-storage/` (website),
`sync-app/` (app) and `sync-trvgo/` (trvgo.us, since 2026-11-01).
- **What's live** is those folders on that repo's `main`, not a copy from an earlier session. Compare before building.
- **Every build is a release, sent through git** (Jason, 2026-11-13; decisions/0159 §5): its zip (with a `release.json`:
  title, why, proposals, switch) goes in with `tools/page-audit/send.py <zip> --mirror <checkout> --note …` (zip check,
  delivery log, `releases/inbox/`); merge it into `main` once Jason has approved it (in the daily batch, decisions/0179). The live site takes it onto
  staging within 10 minutes, runs its checks, and publishes it by itself when they pass (Jason, 2026-11-13; kept by
  decisions/0165's correction, 2026-11-15). The site manager's **Publish** is for what Jason makes on staging: its data
  and zips he uploads there, merged into live. `"review": true` in `release.json`, only when he asked to see it, sends it for review: an email and a
  Pulse case take him to staging, where UI, UX and Functional each need Approve before Publish opens; his "needs changes"
  notes come back in its record (`deliveries/releases/`). Watch for its
  "Release #N: Live" record commit, then run the sync. Upload on the site manager is the fallback.
- **Staging is live in safe mode** (decisions/0165): every new table names its class in `app/lib/staging-tables.php`
  (zip check R14) and every new outside call asks `StagingGate::hold()` first (R13); tests in `tools/staging-tests/`.
- **Delivering:** the release's publish writes to the server; the sync updates OneDrive. Since 2026-11-13 only releases change the
  site (`SYNC_HOME=git`): a server or OneDrive edit not from a release is put back, the edit kept in the sync's `conflicts/`. A zip holds **only files that differ byte for byte from the live mirror**, never a blanket upload.
  Dotfiles (`.htaccess`) aren't mirrored, so name them separately when they change.
- **Several sessions at once** (Jason, 2026-11-01): current first, so nothing is rebuilt.
  - **Claim when you start writing a release** (decisions/0161): `tools/page-audit/checkout.py claim <path>[::<fn>] --add|--change
    --why "…"` (`--page <route>` for a page's files). Add is shared; change is exclusive per function or entry, for that
    one release. Refused: build the new version beside the old, or work on something else. `send.py` claims what you
    forgot, the zip check blocks a clash or a broken live caller, and the claim ends when the release is verified live.
  - **Before writing any file for a zip**, fetch the mirror and start every file from its live copy, never from a copy
    held earlier in the session. If a release was published since the newest sync, run the sync first.
  - **Just before zipping**, fetch once more. A file changed there since gets only this build's change re-applied
    (most often `faq.md`, `manifests.php`, `tools.php`, `Pulse.php`, `section-rails.php`, `portal.php`, the router),
    and no other release in flight (Planned/On staging) touches a file you carry; if one does, wait for its sync and
    rebuild that file (2026-11-23: #270 overwrote #269's lines in three files merged a minute apart).
  - **A zip blocked only by `faq.md`** in another session's pending zip (Jason, 2026-11-13): wait for that release to go
    live, re-apply only your own entries on the live copy, and send. FAQ edits only add, so don't ask Jason.
  - The hand-off names the sync it's built on ("built on 17:44 UTC") and lists the zip's files. Never build on another
    session's zip that isn't live yet. One release at a time, the sync run after each publish.
  - **Before a zip goes out** (decisions/0145): `tools/page-audit/zipcheck.py <zip> --mirror <checkout>` must pass (no
    new blocking failures), and `tools/page-audit/deliveries.py record` logs it, so a missed file shows as pending. It
    also blocks a mirror behind its `main` and a file another session's pending zip carries (Jason, 2026-11-11).
  - **Claude runs the sync** (Jason, 2026-11-01): when Jason says he's published, run the workflow (`sync.yml` on `main`),
    wait for its "Sync: …" commit, and check the mirror matches the zip byte for byte.
  - **One sync at a time, never a pile-up** (Jason, 2026-11-11, after a 35-minute stall): GitHub keeps one waiting run
    per group, and each new request cancels the one already waiting, so sessions requesting at once cancel each other.
    Only the session whose release was published starts the sync. First list the runs: one queued or running already covers
    the publish (wait for its commit; never start another). One running over 15 minutes with its job finished is stuck:
    cancel it; if it still shows running, rename `concurrency.group` in `sync.yml` (PR, merge) and start one run. No
    commit after 10 minutes: list the runs again before anything else.
- **Deleting** on one side deletes on the other (OneDrive recycle bin; server copy in the sync repo's `deleted/`).
  Claude can delete in OneDrive via the Microsoft 365 connector. Never re-send a deleted file. More than max(10, 5%)
  deletions in a run wait for `allow_mass_delete`.
- **Never in a zip:** `bootstrap-super-admin.php` (retired) and anything under the archive register's "Removed".
- **Never synced:** dotfiles (`.env`, `.htaccess`), `app/storage/`, `_notes`, `_SUPPORT`, `_pending`, `_concepts`,
  `error_log`, `bootstrap-super-admin.php`.

## Files used once, and the archive (Jason, 2026-10-28): every build

- **Migrations run themselves** (2026-10-30): ship the file in a release; the site runs new ones oldest first within the hour, or
  when System Configuration → Database updates opens, logs them (`db_updates_log`) and deletes them. A failed one holds
  the rest until replaced. So each must be safe to run twice and need no person; zips carry only new ones; no phpMyAdmin
  steps in delivery notes. A release's migrations run first at its Publish, each copied first (decisions/0162); one with
  `UPDATE`/`DELETE` needs `"data_reason"` in `release.json`; a drop only of a rollback's leftover, named under `"removes"`.
  Until the runner's fix (0162's 2026-11-25 note): a migration that writes rows creates nothing that already exists, or it's
  cleared unrun as "already there" (#284); check its rows on live after it publishes.
- Test files, dumps and one-off scripts never go in `website/` or `app/`; delete them once used.
- **Archived pages** (out of menus, still reachable) are listed in `archive.md` with what replaced them. Remove one only
  when Jason clears it, then move it to the register's "Removed" in the same build.
- **Dead code** (nothing calls it, checked against the live mirror) is deleted; the sync history keeps old versions.

## The crawler, research and guides: decisions/0164 rules: every build

`decisions/0164` (the Knowledge Engine; it took in 0133's rule book, R1–R19) is the one rule book for the crawler and what
it feeds (listings, landmarks, dossiers, research, guides, stories): curators, not controllers (no topic left out for
being sensitive; only exposure and representation managed); "tell the story, never the way in"; never overwrite what a
person entered; AI acts on a person's words only when called; the licence decides the use; nothing private to Gemini's
free tier. Its phases run on `decisions/0163` (operations), the one home of its numbers and settings. Where older crawler records
disagree, 0164 wins.

## Folders: website/, app/, trvgo/ (decisions/0069, 0094)

- `website/` is the web root: pages, endpoints (`api/`, `api/crawl/`, `api/auth/`, `user/api/`, `listing/api/`), `js/`,
  `image/`, `uploads/`. Never backend code, SQL, notes or dumps.
- `app/` is never served: `lib/` classes, `data/` word data, `ui/` shared page parts (shell, header, engage bar,
  `faq.md`), `bootstrap.php`, `middleware/`, `migrations/`, `scripts/`.
- `trvgo/` is the trvgo.us document root: the router (`index.php`, `.htaccess`) for short codes, GO Codes and businesses'
  storefronts (decisions/0139); storefront pages are drawn by `app/ui/storefront-view.php`. Mirrored to `sync-trvgo/`.
- Pages reach `app/` by relative path (`__DIR__ . '/../app/ui/header.php'` from the root). Zips hold the folders at real
  paths. Notes folders (`_notes`, `_SUPPORT`, `_concepts`, `_pending`) go in neither.

## The admin (decisions/0109): every build

10 modules (Pulse is part of the Dashboard since 2026-11-12) at `/admin/<module>/<section>[/<id>]`, drawn by `website/admin/router.php` from `app/admin/manifests.php`, one
endpoint `/admin/api/<module>/<tool>`.
- No admin page outside the manifests: a new tool is a section there (a list also a tool in `app/admin/tools.php`),
  built from widgets (table, pipeline, metrics, detail drawer, quick task).
- Stages are workflows (System Configuration → Workflows), not hard-coded; money and data moves go through the owning
  library (`app/admin/workflows.php`).
- Access: `Access::can()` / `Modules::canTool()`. Disallowed actions are greyed out with the reason, never silently hidden.
- Another module's tools open in a drawer (`at_hand`), never by leaving the page. Old pages are archived, not deleted.

## Record pages: the command strip (decisions/0129): every admin record page

`tv_command_strip()` (`app/ui/command-strip.php`, `js/tv-strip.js`) replaces the title and breadcrumbs: one slim line
in the admin command bar's look that stays at the top as you scroll (Jason, 2026-11-12).
- Left: Back and the short name ("Order #9"), no pills. Right: **Save** (with a count) when changed, else **Close**, ▾
  for Save and close, Send, Close; **☰** for other actions and Shortcuts (they trigger the page's own buttons).
- Where the record stands is a notices box under the strip (`news`), an informational notice closes with ×, back only when it changes; a state line carrying a task never closes. Nothing
  shown there is repeated below.
- Each `h2.hb-h` section folds with a ▾ on its heading (the heading toggles it; remembered), and shows an unsaved dot. Section buttons name the section ("Save work order"); never a
  bare "Save and close" inside one. `'new'` forms and `data-confirm` buttons wait for their own button; searches `'skip'`.

## Keep the FAQ current (decisions/0057): every build

`faq.md` ships as `app/ui/faq.md` and renders at `/faq.php`. Every build adds or updates entries for what changed, from a
visitor's view, saying "coming" for what isn't built; include it in the zip. Anchors come from the question wording, so
check `grep -rn "faq.php#"` before rewording one.

## Phone layout and visual standard (decisions/0058 §11): every page

- **Edge to edge below 768px:** cards and panels 3px from each side and each other, `.5rem` corners; text keeps a small
  margin. `app/ui/app-shell.php` handles known card classes; others add `class="tv-bleed"`. No per-page negative margins.
- **Card anatomy:** the name is the link, top left, brand brown `#92400E` underlined; Link button top right; actions
  (Call, Directions, See on map, Website, Contact, Peek) as 30px round icon buttons in a row on the name's line, left of
  Link (under the name on phones), with `title`/`aria-label`, no text, icons `#B45309`; details across the card below.
  Get Local's cards keep Call and Directions round, then ⋯ with Email, Website and Peek in words (decisions/0058 note,
  2026-11-19). Card grids page by whole rows (`js/tv-pager.js`).
- **Icons** are the brand's flat SVGs (`tv_icon()`), never emoji. Active: sage `#8FBCA8` with a soft glow; rest: cream
  with a brown ring.
- **No breadcrumbs** on content pages (the way back up is `js/tv-trail.js`'s route, 2026-11-12); kind, category, agency, name and activity pills search for more
  (`TvSearch.open('<words> near <place>')`).
- **Filters** live in the search (foot of Quick Search, §39), showing only active ones with ×. Get Local is the
  exception: its own search bar, then **Filters ▾**, **Arrange ▾** and **Sort** on the line over the list (decisions/0058 note, 2026-11-19). Filters on a list or queue
  are one dropdown button like Shortcuts (icon, current choice, count, ▾), not rows of pills.
- **Phone menus:** ☰ tools drop-down left, profile and role right.
- **Count outbound taps:** `data-out="call|directions|website|contact" data-e="<listing id>"` (counts only, never who).
- Check every page at 390px: 3px card edges, no sideways scroll.

## Content pages (decisions/0058 §20, §21): every build

Listings, outdoor places, experiences, places, journeys and any new content kind:
- **Hero** (`tv_explore_hero()`) flush under the tool bar for places, listings and profiles (pass `heading`, `slides`,
  `manage` for the owner).
- **Header:** kind or category (searches), the title (not a link), the place, Link button top right (`data-link` +
  `js/link-button.js`). No breadcrumbs.
- **Engage bar** under it: `tv_engage_bar($pdo, $kind, $ref, ['title' => …])` (`app/ui/engage-ui.php`).
- **Comments** at the end: `tv_engage_comments($pdo, $kind, $ref)` (public, 18+, reportable).
- **Views:** `tv_count_item('<kind>:<id>')`. Maps opened as their own view pass `panel: true` to `TvMap.open()`.

## Standing OKs: never asked again (Jason, 2026-11-25): every session

Jason, 2026-11-25: "when I say send me email that should now it is allowed to send me an email and not need to authorize
use of what ever tool it needs". An OK he has given once is not asked again: not by the session, not as a tool prompt.
New standing OKs go here in the session that hears them; the tool they need goes in `.claude/settings.json`'s allow list.
- **Email and push to Jason:** when he asks (or a rule here says to tell him), send it, to his address only, through
  the site's own email (`releases/batch.json` "messages", notify-founder; Jason, 2026-11-27: "Use system email") and a
  push notification; reaching him by email when he's offline stays allowed (Jason, 2026-11-27: "Email used for reaching
  me when I am offline is ok too"), with the Microsoft 365 send only as the fallback if the site's email fails. Otherwise
  degraffics.com is out of system mail ("mail only, OneDrive stays"): no agent reads his degraffics.com inbox, and nothing
  routes system mail there; the 3-way sync's OneDrive use is unchanged. No email accounts
  for agents: they talk through the role queues on the platform (jason-spec §7). No other recipient without asking.
- **Adding both repos**, **archiving finished sessions**, **merging your own approved PR**: as their sections say.
- **Piece 2 to running, over the holiday** (Jason, 2026-11-25: "publish yes as long as we can roll back… I want all of
  this up and running by the time I am back… make your best judgement calls based on the adr's and the outlined plan…
  If you must reach me email me"): P2-1 to P2-5 publish once staging's checks pass, each with Roll back; the build
  session may switch on staging's Team runner, Staging runs its own jobs and Keep staging caught up. Judgement calls
  follow the decisions and `handoffs/piece2-team.md`; a Founder ruling (Tier 5, decisions/0180) still comes to him, by email.
- **The organization runs now** (Jason, 2026-11-26; decisions/0180's note "run it now"): every one of the 32 roles is
  active, R1–R5 ship as each passes its checks, reach stays Backend only until he widens it, and the hard money line,
  $0 spend and Tier 5 triggers hold. Approving the decision tree and raising the stage ceiling are his: his press, or his own written words for a
  one-time setup (2026-11-26, R1's tree at Implementation); a later rule change clears it and needs him again.

## Asking Jason (Jason, 2026-11-18; routed through the CEO, 2026-11-27): every session

**Publish authority (decisions/0179, Jason, 2026-11-25): every release.** Until the before-and-after checks exist,
nothing that needs approval goes live until Jason approves the **daily batch** (the session manager's daily summary);
then (Jason, 2026-11-25, A) Tier 1 and Tier 2 publish alone after the before-and-after check, with Undo in the daily summary.
**Five tiers (decisions/0180, Jason, 2026-11-26):** Tier 3 middle managers and Tier 4 executives resolve what's in
their lanes; a change touching a high-risk area needs its Tier 4 review recorded before live. Only a **Founder ruling
(Tier 5)** comes to him as a Decision Brief: a rule, tier, permission or fence change, an ADR amendment or new policy,
an irreversible act, a deadlock, or real money moving (never autonomous; refunds per 0178).

**Questions for Jason go through the CEO session** (Jason, 2026-11-27: "Yes, route their questions through you"). A
session that needs Jason's OK (a PR, a decision, a key) sends the question (or the release summary and PR) to the
session manager, never to Jason in its own chat. The session manager reviews it against the decision, the brief and
these rules (scope creep, drift, conflicts, anything already decided); once it's clear to ask, or its fixes are made, it
sends it to the CEO session. **The CEO answers what's within its authority** (Tiers 1–4, the Prior Ruling Check, and
whatever an ADR or his past rulings settle, cited; Jason, 2026-11-27: "I wont be looking at other session, just this one…
if there is a question I need you to answer them as best you can, if you cant email me the questions"). What it can't
(Tier 5, real money, his own presses) goes to him as a Decision Brief through the site's own email (`releases/batch.json`
"messages") and push, **written for him and Gemini to review, never a pop-up** (0179), never a Pulse case, noting
"reviewed by the session manager"; never answered or approved in his name. Jason reads only the CEO session. Not forwarded in
10 minutes, the asking session reminds the session manager; in 30, it goes to the CEO marked "not reviewed". The CEO sends
his answer, in his words, to the session manager and the asking session. The session manager never asks the same
question again; it lists what's still open at the top of its replies as **"Waiting for your OK:"** (with each session's
name). **Sessions merge their own PR** once Jason approves it (Jason, 2026-11-18), then tell the session manager the
merge; it runs the sync and records deliveries.

**His stated direction is the ruling** (Jason, 2026-11-27: "this is another issue: always drawing me back into the
decisions I've already made"). When Jason states a direction, that statement is the ruling: the CEO records it and
proceeds, never asking him to confirm what he's said. A question goes to him only for a choice his words don't settle.
The session manager's review checks a recording against his words; it never brings the decision back to him.

**Decision or release: said every time** (Jason, 2026-11-24: "make the decisions vs work to live very clear"). Every
question's first words say which it is: **"Decision (nothing changes on the site yet): goes live in <release>, <its
place in the queue>"** or **"Release (live about 10 minutes after merge): <what you'll see, and where>"**. Replies say
"live" only for what's synced and byte-checked. The session manager keeps `handoffs/decided-not-live.md` (each
decision, its release, its place) current with every approval and every release, and its top line in each reply.

## A link for every place Jason acts (Jason, 2026-11-16): every reply

When a reply sends Jason somewhere to do something (an admin page, a console, a settings screen, a form), give its full
URL, e.g. `https://traversence.com/admin/config/settings`, with an anchor where the page has one, never only a path of
menus. Where the address is his own (a cPanel host, an account id), give the sign-in URL and the clicks from there.
This includes the comments in `.env` blocks and setup steps: every "get it from" line carries its URL.

## Pulse (Jason, 2026-11-01): every build

A step doable from the case is a button that does it (`['do', …]` / `['form', …]` → `Pulse::manage`), not a link. A step
needing an outside service (Railway, QuickBooks, AWS, Cloudflare…) carries its link (`Pulse::SERVICES`, the step's
`ext`); never "go to Railway" without it. One case per kind of chore, a step per item.

## Every action is a task: see it, do it, confirmed, next (Jason, 2026-11-23): every page

Anything that needs someone to act follows four steps: 1. **See the task** (the issue and its evidence, in place);
2. **Do the task** (one button that does it, where it's shown); 3. **Done and confirmed** (the result checked, the
status shown in place of the form; an issue clears itself when its check passes, never by a "mark done"
or sign-off); 4. **The next step**, only within a process (Jason, 2026-11-23: "Don't convolute processes or tasks"): a single task
(one alarm, one payment) ends at its confirmation, the lists and counts updating; a process (placing an order, an
order's life) is single tasks chained by a workflow, so its next step, routed by the choices made, is its own button. No
accounting-only buttons, no form left open after it's done, no notice repeating the new state.
**More than one way forward** (Jason, 2026-11-23: "tool kit for moving something to where it needs to be or edit icon
to modify what has happened so it can get back on track"): each task or state line offers its suggested action, ✎ to
correct what happened (with History), and ⋯ for every other valid move from its state (greyed with the reason when not
allowed).
**Edit or Undo** (Jason, 2026-11-23: "Undo is for processes that must be rolled back as if they were never done"):
a record someone entered (an email, an id, an address, a note, a setting) shows its value with **Edit**, reopening it
in place, the old value kept in History, never Undo. **Undo** is only for an action that did something (a split, a work
order sent, Fix it, a scope approved) and can be rolled back as if never done; where it can't (an email delivered), say
so instead. Money never has Undo: a payment read from QuickBooks shows ↗ to open it there; one recorded here and not
processed has Cancel the record; one processed (charged or posted to QuickBooks) has Refund…, with Jason's OK
(decisions/0178).
**Icons for inline actions** (Jason, 2026-11-23: "action icons with tool tips over text saying action urls"): a
record's or row's inline actions (Edit ✎, Copy, Open in a service ↗, Evidence, History, ⋯) are 30px round `tv_icon()`
buttons in the card style, `title` and `aria-label` naming the action and target ("Open Google Cloud billing as …").
The view's one primary action (Fix it, Take a payment, Place an order) and state sentences stay words.
**Outside links open in the browser** (Jason, 2026-11-24: "Sure", to "Should the outside urls open in browser vs in
app?"): services that need a sign-in (QuickBooks, Railway, Google Cloud, Cloudflare, AWS…) open in the browser
(`target="_blank" rel="noopener"`, an ↗ icon whose title names the service and the account); our own pages open in Peek.
On phones, the first outside tap shows one dismissible line: "Opens in your browser. If it asks you to sign in, sign in
there once and choose 'keep me signed in'." (Android decides how an installed app opens them; we control the marking.)
**A button's verb says what happens** (Jason, 2026-11-23: "fix it sounds like an action to be done right now"): Fix it
(or any verb of doing) only where pressing it acts now, a spinner then the real result in place; anything queued for a
person, a build or a later run says so ("Request a fix", "Request the check"), its line naming who has it and what
comes next.
**What the system knows is applied, not asked** (Jason, 2026-11-23, Order #12: "an intelligent system that helps me
process orders"): facts we hold (QuickBooks, the work order, dates, history) are shown, never asked again; only what
can't be known is asked, as a task with its best guess filled in; stale or irrelevant blocks are hidden; an item that
has aged raises its own question ("Ready 21 days. Picked up?").
**Lines that carry tasks have no ×** (2026-11-23, confirmed by the session manager; decisions/0129 note): a record's
state lines (an order's Order · Money · Delivery) hold its tasks, so they stay; × is for notices that only inform.
**A list or a process?** (Jason, 2026-11-23) It's a process if step B needs step A's result, or they all build to one
outcome; a list if they could sensibly be done in any order. A list is a queue (what needs you first, filtered by its
tiles), each row its own cycle, counts dropping as items clear, a batch action only where one fix applies to many. A
process is a guided flow: finished steps fold to a line with Edit, one "next" button, branches by choice. A process may
create list items (proofs, a balance); a workflow may report a list's progress without making it a sequence.

## No browser dialogs; one look for notices (`js/tv-ask.js`): every page

Never `alert()`, `confirm()` or `prompt()`.
- **Everything that pops open closes** (Jason, 2026-11-19: "anything that pops open should have a way to close it"): a
  visible ×, Esc and a click outside, on every pop-up, panel, menu and drawer.
- Needs a yes: `data-confirm="Hide it from the site?" data-yes="Hide it"` (+ `data-danger` for removing or hiding); in
  code `if (!TvAsk.gate(btn, 'Remove this comment?', {yes: 'Remove', danger: true})) return;`. Also
  `TvAsk.confirm(msg, {anchor})`, `TvAsk.ask(label, value)` (one-field form).
- **Notices:** `TvAsk.say(msg, {anchor, bad})`, green ✓ "Success:" / red ! "Error:" beside `anchor`, else a centered
  pop-up. After a form post, the page's message carries `data-tv-flash="ok|error"`. No other success/error boxes.
- **Status lines** are `<p data-tv-status="ok|info|bad" data-tv-label="All clear:">…</p>`, never plain colored text.
- **Peek** at a page without leaving: `<a href="…" data-peek-frame>` or `TvAsk.peek(url, title)`.
- **Busy** (Jason, 2026-11-02): any click that doesn't finish at once shows a spinner on what was clicked,
  `TvAsk.busy(btn, true)` before the request and `false` when it answers, then the answer as the site's notice
  (`TvAsk.say`: beside it, or the centered pop-up when there's no room, as in a Pulse case). Never a button that greys out
  and looks dead.

## Words and lists (decisions/0144, Jason, 2026-11-04): every form

Fields of words (names, descriptions, notes) get spell check and the site's suggestions: `data-spell` on an input (a
textarea gets them unless `spellcheck="false"`); codes, amounts and dates don't. A list whose choices need explaining
carries each choice's meaning (`[key, name, what it is]`), shown by the kit's ⓘ.

## Never a dead end (Jason, 2026-11-09): every page

Wherever a link, code or step stops working (expired, replaced, over a limit, an email that failed), the page offers a
way forward: send it again, and ask our team (a form to our inbox), with our email address. Never a lock-out.
Never offer someone what their account can't use, or should use elsewhere (Jason, 2026-11-11): show the version for
who they are (staff, a business, a member, a guest), or point straight to where theirs is.

## Times where they happened (Jason, 2026-11-10): every page that shows a time

Store the moment and the device's time zone (`Intl…timeZone`, sent with the action). Show it in that zone, always labeled
("Nov 9, 10:00 AM PST"), with `<time datetime="ISO" data-tv-time data-tz="…">` or `TvTime.fmt()` (`js/tv-time.js`);
with no zone stored, in the viewer's. Rolling out page by page from Scan cards.

## Places in search (Jason, 2026-11-10): every build

A city named in a search is always found in the locations data (`zip_coordinates`) and becomes the place, with or
without our data or a hub there: every search is demand. Older rules that hid out-of-area towns ("strict", "names
something here") no longer apply to cities; an unknown word is never dropped before the place is read. One exception: the
whole search is the exact name of something we have ("Four Corners Monument"). While typing, a name of two words or more that
starts a name we have is suggested wherever it is, even when the words also read as a town.
Places come from sources, never hand lists in code: a name searched and not found is looked up (decisions/0148).
OpenStreetMap is its own layer (decisions/0065): its own tables, credited, never copied into our records, so nothing is
shared back; within it, show all it gives. Nothing of ours in reach: its places at once, and the town to the crawler (0149). Its places come only from our copy (Geofabrik's states); Overpass is retired, so outside them there are none (Jason, 2026-11-19).
"Near me" is the nearest first, no cap, with miles; fewer than 3 within 5 mi (30 for the outdoors) says results are lacking
and tells the team (decisions/0060).

## No test moves real money (Jason, 2026-11-10): every build

Test accounts, test orders ("TEST · …" copies, sandbox orders) and test posts never reach a live gateway: anything that
charges, invoices or records a payment goes through `QuickBooksPay::guardReal()` / `testHere()` (decisions/0145).

## Password boxes (`js/tv-password.js`, Jason, 2026-10-16): every page

Every password box gets Show/Hide; new-password boxes (`autocomplete="new-password"`, `minlength`, optional
`data-pw-not`) get a live checklist, and the confirm box `data-pw-match="#id"`. The form won't send until it's ticked.
One-time secrets stay hidden until asked for, with an offer of a new one.

## One uploader (decisions/0127): every build

Every upload goes `js/tv-upload.js` → `/api/upload.php` → `app/lib/Uploads.php`. A new place to upload is a target in
`app/lib/upload-targets.php` (`check`: who, kinds, size, staging; `done`: the owning library handles the file). In pages:
`TvUpload.send(files, {target, ctx, el})` or `TvUpload.bind(input, …)`. Forms with other fields send the file first, then
the form with its id. Before delivering, `grep -rn '\$_FILES\|is_uploaded_file\|move_uploaded_file' app website` may
match only `app/lib/Uploads.php` and `website/api/upload.php`.
Every file input opens the Gallery panel (`js/tv-art.js`, decisions/0143). Staff have no personal Gallery (Jason,
2026-11-05): on admin pages files belong to the record (an order, a business), never to the staff member.

## Links past Bluehost's ModSecurity: every request

A field holding a link (`…?id=…`) gets "Not Acceptable!", so links travel wrapped. POST forms: automatic (`tv-ask.js`
wraps, `app/bootstrap.php` unwraps). JSON `fetch`: `JSON.stringify(window.tvSafe ? tvSafe(body) : body)`, read with
`Response::jsonBody()`. Endpoints reading `php://input` themselves pass it through `Response::unwrap()`.

## Tribal nations' land (decisions/0066): every build

- **Public:** boundaries, key resources and infrastructure, public listings and businesses, publicly known places, with
  the nation's name (`Nations::label()`) and visitor rules (`Nations::rules()`).
- **Held, never shown:** names that may mark sacred, burial or protected sites (`Landmarks::SENSITIVE`) and what's
  specific to the nation (`Landmarks::NATION_SENSITIVE_CLASSES`), until the nation's own tools exist
  (`Landmarks::NATION_TOOLS`) and it says. A place held only by a word, off a nation's land, with its own Wikipedia article moves
  only to story only (named and told, no pin or coordinates); it shows fully only when the managing authority's page says
  it's open to visitors. Archaeological words are held like the sacred and burials (`Landmarks::publiclyKnown()`;
  Jason, 2026-11-14, decisions/0164 R7). "Closed by the nation" reopens only at its request. Never delete the data.
- **On a nation's land, what public sources already present is story only** (Jason, 2026-11-27: "If we find it
  online it is already public, we share its story, we do not show how to violate sovereignty"; 0066's note). It's named
  and told as those sources present it, with "visit only with the authorization of <the nation>" and its visitor rules.
  It never gets a pin, coordinates, directions or access, and an Editor confirms the public source first. A place the
  nation closed, or that no public source names, stays held.
- Anything about a user or owner is private unless they say otherwise.
- All landmark reads go through `Landmarks::visibleSql()` / `visible()`; pins on nation land (`Nations::pin()`) move to a
  public place.

## Migrations for Bluehost's MySQL: every build

Run by `DbUpdates::autoRun` (split on `;`): no `DELIMITER`, no stored procedures. MySQL rejects `ADD COLUMN/INDEX IF NOT
EXISTS` and `DROP … IF EXISTS` inside `ALTER TABLE`; `CREATE TABLE IF NOT EXISTS` is fine. Guard with `information_schema`:

```sql
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE t ADD COLUMN c INT DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't' AND COLUMN_NAME = 'c');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
```

(Indexes: `information_schema.STATISTICS`.) Tables may have moved to the admin database (decisions/0110,
`DataMove::GROUPS`, e.g. `products`): find the table first and name its database (see
`app/migrations/2026-12-23_promotions.sql`).

## cPanel through its API (`app/lib/CPanel.php`, Jason, 2026-10-28): every build

New cron jobs go in `Scheduler::JOBS` and `Scheduler::AUTO`; `Scheduler::sync()` adds them within the hour. Mailboxes via
`CPanelMail`. Never send Jason into cPanel, except for `.env` secrets (File Manager) or what the API can't do (say why).

## No income, no expense (Jason, 2026-11-03): every build

Nothing we build spends Traversence money (a per-use API, email, text, push, ID check, AI call) unless revenue covers
it: inside a paying plan's allowance, a paid top-up, or a fee charged first. Free tiers get only what costs us nothing,
except free members' Moments, Snaps and Keeps, paid from the community budget (3% of revenue, $25 floor; decisions/0154).
System emails the site sends itself (links, notices, work orders) cost nothing and aren't limited by this (Jason, 2026-11-09).
The one exception: Claude writing guides and journeys, inside the AI monthly cap (decisions/0131, 0147: on-demand
builds up to 50% of the daily allowance, the rest queued), and the research and development tools that feed them (research
briefs, topic research, Find it, live events, heritage, names, fixes; decisions/0160 §5). Every call's cost is counted,
failed calls included, so the cap holds. Also a place's description, written once when someone opens a place with no
Wikipedia summary, kept as research (decisions/0148, `place_about`).

## The live server's PHP version: every build

The real version is in `site-audit/results/server.json` (from the health report, daily; site audit P1); the code check (R04)
flags anything newer. Until it's recorded, write to PHP 8.0: no `readonly`, `enum`, first-class callables (`strlen(...)`),
`new` in parameter defaults, `array_is_list()`, `json_validate()`, typed class constants, or standalone `true`/`false`/`null`
types. A helper from `app/ui/app-shell.php` (`tv_icon`, `tv_count_item`…) is called only after `tv_page_start()`.
