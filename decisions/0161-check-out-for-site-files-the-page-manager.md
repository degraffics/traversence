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

### 2. Check out before editing

- **Claim:** before writing a file for a release, a session runs
  `tools/page-audit/checkout.py claim <path>… --why "<one line>" --session <session id>`. The claim is a commit to
  `main` in `releases/checkouts.json`: path, who, why, when, and an expiry. Git's own push conflict makes it first come,
  first served: a session whose push loses fetches and tries again.
- **Refused early:** a path another session holds can't be claimed. The tool names the holder, why, and since when. The
  session waits (a background loop, never idle-waiting on Jason), works on something else, or asks the holder by
  `send_message` to fold its change in.
- **Paths, not just pages:** a claim is on files (`website/directory/index.php`, `app/lib/Blocks.php`). The page tree maps
  a page to its files, so `claim --page /directory` claims the page's own files.
- **Several files, one claim:** a release's files are claimed together; it fails whole if any one is held.

### 3. Check in

- A claim is released by itself when its release is verified live (`deliveries.py verify` after the sync), or when the
  session releases it (`checkout.py release`), for instance when the work is dropped.
- **Expiry:** a claim lapses after 4 hours without a release sent, and is renewed each time the session sends or renews.
  A session that ended (archived, or its worker gone) never leaves a file locked for good. A lapsed claim is reported, not
  silently dropped.
- The session manager may release a claim whose session has ended, and says so to Jason.

### 4. Shared, add-only files never lock

`app/ui/faq.md` and other files that sessions only add to (named in `checkout.py`, `SHARED`) aren't claimed. The rule of
2026-11-13 stands: wait for the other release to go live, re-apply only your own entries on the live copy, send.

### 5. The zip check enforces it

`zipcheck.py` refuses a zip that carries a file claimed by another session, and warns on a file the sending session
didn't claim (a blocking failure once check-out has run for a week without trouble). `send.py` claims the zip's files
if they're free, so a session that forgot still can't collide.

### 6. The page builder and the crawler

- **The page builder** writes page content to the database (`Blocks`), not files. Those edits don't need check-out. The
  page tree marks which pages carry editor zones (`home.after-doors`, `page.<id>`), and a claim on such a page's files
  says so, so a code change is checked against what's been built there.
- **The crawler** writes data, not site files. Only its worker code (`workers/crawler/`) and its site endpoints
  (`website/api/crawl/`, `app/lib/crawler/`) are claimed like any other file.

### 7. Seen by Jason

- The site manager's Releases menu and Pulse's Site health list what's checked out: the file, who, why, since when, and
  claims near expiry.
- The session manager's hourly check reports clashes and stale claims.

## Consequences

- Collisions show at the start, not after a build; fewer rebuilds and fewer re-applied changes.
- One more step for every session (claim before editing), carried by `send.py` when forgotten.
- A dead session's claim holds its files for up to 4 hours unless the session manager releases it.
- CLAUDE.md gets one short rule under "Several sessions at once": claim before editing; the zip check enforces it.

## Build

One small fresh session, after Jason accepts this: `tools/page-audit/checkout.py` (claim, release, renew, list, expire),
`releases/checkouts.json`, the zip check and `send.py` changes, `deliveries.py verify` releasing claims, the page tree's
page-to-files map and editor-zone mark, the site manager and Site health views (a site release), and the CLAUDE.md rule.
Repository tools go to `main` by PR; the site views go as a release.
