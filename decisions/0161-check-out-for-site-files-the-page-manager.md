# ADR 0161: Check-out for site files: the page manager

**Status:** Proposed (Jason, 2026-11-13: "I think we need a check in check out system for file management, so we have
sessions, the site builder, and the crawler all doing things now. Would you say the audit system, which is responsible
for the page tree, be the page manager as well. That way we have one tool that allows access to files for
development."). Drafted by the session-manager session for Jason's review. It extends `decisions/0145` (the audit, the
zip check and the delivery log) and `decisions/0159` §5 (every build is a release through git), and keeps every rule in
them.

## Context

Several things now change the live site at once: Claude sessions (often three at a time), the page builder (decisions
0159) and the crawler (decisions 0160). Since 2026-11-13 only releases change the site's files (`SYNC_HOME=git`).

What guards against two sessions overwriting each other today:
- the zip check (`tools/page-audit/zipcheck.py`) blocks a zip when another session's zip that isn't live yet carries one
  of its files (Jason, 2026-11-11);
- each session starts every file from the live mirror and re-applies its own change when a file moved underneath it
  (CLAUDE.md, "Several sessions at once").

Both act at the end, when the work is done. On 2026-11-13 alone: the audit's release was blocked twice by the crawler's
`faq.md`; the crawler found `listing/view.php` changed on live since it copied it; the builder held step 4a until the
crawler's W2+W3 went live; the audit's FAQ follow-up was orphaned when its session closed. Nothing says, before work
starts, that a file is someone else's for now.

## Decision

### 1. The audit tooling is the page manager

The tools that already own the page tree (`site-audit/pages.md`), the zip check and the delivery log
(`tools/page-audit/deliveries.py`, `deliveries/`) also own check-out. No separate system: one place says what pages
exist, who is changing which file, and what reached the site.

### 2. Lock as little as possible, as briefly as possible

Check-out must not become a bottleneck (Jason, 2026-11-13: "I don't want to create a bottleneck in development by having
files and functions locked down so much that we can't build until they are returned or released."). So:

- **Two kinds of claim.**
  - **Add:** a new function, a new entry in a list, a new section, a new file. Shared: any number of sessions can add to
    the same file at once. When one's release goes live, the others re-apply only their additions on the new live copy
    (as `faq.md` works since 2026-11-13). Nothing waits.
  - **Change:** editing or removing code that already exists and that something else uses. Exclusive, and on the
    **functions** (or named blocks: a constant, a manifest section, a route) being changed, not the whole file. Another
    session can still add to that file, or change a different function in it.
- **Claimed at build time, for one release.** A session claims when it starts writing its release's files, not when it
  starts looking into the work, and the claim covers that one release (usually under an hour). Reading a file is never
  claimed.
- **Claim:** `tools/page-audit/checkout.py claim <path>[::<function>]… --add|--change --why "<one line>" --session <id>`.
  The claim is a commit to `main` in `releases/checkouts.json`: path, functions, kind, who, why, when, expiry. Git's own
  push conflict makes it first come, first served: a session whose push loses fetches and tries again.
- **Refused early:** a change claim on a function another session is changing is refused, naming the holder, why and
  since when. The session builds new beside old (§3) instead, works on something else, waits (a background loop, never
  idle-waiting on Jason), or asks the holder by `send_message` to fold its change in. Two sessions that really need the
  same function at once go to the session manager, who asks Jason which goes first.
- **Pages:** the page tree maps a page to its files, so `claim --page /directory` claims the page's own files (as add,
  unless `--change` names functions).
- **Several files, one claim:** a release's claims are taken together; they fail whole if any one is refused.

### 3. Modify by building new beside old

The standard way to change what existing code does, so that a modification rarely needs to wait on anyone:
1. **Add the new version beside the old:** a new function (`matchWords()` beside `match()`), or an optional parameter
   at the end whose default keeps the old behaviour. Existing callers keep working untouched. This is an add claim.
2. **Move callers over,** each in its own small release (each one a change claim on that caller only).
3. **Remove the old version** in a later small release, once nothing calls it (checked against the live mirror). The zip
   check already requires Jason's OK to drop a live function (`--drops`).

A change in place (§2's change claim) is for fixes where the old behaviour is simply wrong, and stays one release long.

- **Ship dark behind a switch.** New behaviour that visitors would notice goes live switched off (`Switches::DEFS`, the
  release's `switch`), and is turned on once it's checked. The code is live early, so everything after builds on it.
- **One file, one job.** A new feature goes in its own files (a new class, page or endpoint), not into a shared file
  that's growing. Shared files are for what's truly shared.

### 4. Files that call a checked-out function

- A change claim lists the functions changing and, from the live mirror, the files that call them
  (`checkout.py` finds the callers).
- A session claiming, or building on, a file that calls one of those functions is warned: "`LocalWords::match()` is being
  changed by <session>, for <why>". It builds against the current live version and is told when that release goes live,
  then re-checks its call on the new live copy; or, if it needs the new behaviour, it waits for that release.
- The zip check checks the callers too: a zip that changes what a function takes or returns, while files on the live
  site still call it the old way, is refused (the same rule as dropping a live function).

### 5. The hotspot files

`app/admin/manifests.php`, `app/admin/tools.php`, `app/lib/Pulse.php`, the router, `app/ui/section-rails.php`,
`portal.php` and `app/ui/faq.md` are where sessions collide most, and they're mostly "add an entry" files.
- **Now:** adding an entry to them is an add claim (shared). Changing an existing entry is a change claim on that entry.
- **Over time:** each is split into one file per feature or module that the central file loads, so sessions rarely touch
  the same file. Each split is its own small release, when that file next needs work, never one big move.

### 6. Check in

- A claim is released by itself when its release is verified live (`deliveries.py verify` after the sync), or when the
  session releases it (`checkout.py release`), for instance when the work is dropped.
- **Expiry:** a claim lapses after 4 hours without a release sent, and is renewed each time the session sends or renews.
  A session that ended (archived, or its worker gone) never leaves a file locked for good. A lapsed claim is reported, not
  silently dropped.
- The session manager may release a claim whose session has ended, and says so to Jason.

### 7. Shared, add-only files never lock

`app/ui/faq.md` and other files that sessions only add to (named in `checkout.py`, `SHARED`) aren't claimed. The rule of
2026-11-13 stands: wait for the other release to go live, re-apply only your own entries on the live copy, send.

### 8. The zip check enforces it

`zipcheck.py` refuses a zip that changes a function another session holds a change claim on, and one that breaks a
live caller (§4). A file another session is only adding to doesn't block: the zip must be built on the live copy, and
the later release re-applies its additions (§2). It warns on a change the sending session didn't claim (a blocking
failure once check-out has run for a week without trouble). `send.py` takes the zip's claims if they're free, so a
session that forgot still can't collide.

### 9. The page builder and the crawler

- **The page builder** writes page content to the database (`Blocks`), not files. Those edits don't need check-out. The
  page tree marks which pages carry editor zones (`home.after-doors`, `page.<id>`), and a claim on such a page's files
  says so, so a code change is checked against what's been built there.
- **The crawler** writes data, not site files. Only its worker code (`workers/crawler/`) and its site endpoints
  (`website/api/crawl/`, `app/lib/crawler/`) are claimed like any other file.

### 10. Seen by Jason

- The site manager's Releases menu and Pulse's Site health list what's checked out: the file, who, why, since when, and
  claims near expiry.
- The session manager's hourly check reports clashes and stale claims.

## Consequences

- Collisions show at the start, not after a build; fewer rebuilds and fewer re-applied changes.
- Little waiting: adding never locks, change claims are on functions and last one release, and a modification is
  normally built new beside old (§3).
- One more step for every session (claim before writing a release), carried by `send.py` when forgotten.
- Old versions of functions live a while beside the new ones until their callers move; the zip check's `--drops` rule
  removes them in a later release.
- A dead session's claim holds its files for up to 4 hours unless the session manager releases it.
- CLAUDE.md gets one short rule under "Several sessions at once": claim when writing a release (add is shared, change
  is per function); modify by building new beside old; the zip check enforces it.

## Build

One small fresh session, after Jason accepts this: `tools/page-audit/checkout.py` (claim as add or change, per file or function; finding a function's callers;
release, renew, list, expire),
`releases/checkouts.json`, the zip check and `send.py` changes, `deliveries.py verify` releasing claims, the page tree's
page-to-files map and editor-zone mark, the site manager and Site health views (a site release), and the CLAUDE.md rule.
Repository tools go to `main` by PR; the site views go as a release.
