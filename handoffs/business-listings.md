# Business listings: the build session's record and hand-off (2026-11-20)

The business listings session (opened by Jason 2026-11-19, session_01CXLi5kipJLJoRvo3jqLnQQ) hands off to a fresh one
(Jason, 2026-11-20: every session hands off to save tokens). This is everything the next session needs; nothing else is
held in the old session.

## 1. Live: #191, site-audit case #22, owners open their hidden listing (listing-portal-hidden.zip)

Built on Sync 2026-11-19 15:56 UTC, reviewed by the session manager, approved by Jason in the pop-up, merged as PR #1063,
live 2026-11-19 16:03, verified at Sync 16:05 UTC (record `deliveries/releases/2026-11-19-191-…json`, PR #1065).

**The cause:** `website/listing/businessportal.php` looked the listing up with `Claims::listing()`, the public query
(`is_suppressed = 0`), so a hidden listing (the test listing 9035, or one held for review, a takedown or a dispute) had
no editor and no Hours box, and its name in "Your business" linked back to the same page.

**What's live:**
- `app/lib/Claims.php` (additions only; `listing()` unchanged for the public site):
  - `ownListing($pdo, $uid, $e, $mine = null)`: the same row plus `is_suppressed`, no suppression filter, only for listings
    `Team::listingsOf` gives that person (owners, managers, members, SupportAccess).
  - `hiddenReason($pdo, $row)`: null when public; otherwise `{text, link, ask, merged?}` in the owner's words. Said plainly:
    the test listing (`test_records`, no ask), a private client record (`client_listings.private`), merged into another
    listing (`same_as`, only when the kept one is public: link to it, `merged` => [kept id, name]), not public yet (status
    outside the public four). **Everything else is only "under review by our team"** with Ask our team: Data cleanup's
    Restricted and quarantine, low trust, and any nation or sensitive-place hold (decisions/0066, 0164 R7). Staff reasons
    are never shown to owners.
  - `askAboutHidden($pdo, $user, $e, $note)`: a system email to `ListingMessages::helpTo()` (subject "Why is my listing
    hidden? <name>", the listing, an admin link `/admin/listing-edit.php?id=N`, what the owner sees, their note), with
    Reply-To set to the account email. Only the listing's team can send it, 3 per session a day. Costs nothing (our inbox).
- `website/listing/businessportal.php`:
  - the editor opens for a hidden listing, with "Hidden from the public site" and the reason at the top;
  - Ask our team (form posts JSON `{action:'ask_hidden'}` to the page itself, `TvAsk.busy`/`TvAsk.say`);
  - **a merged listing is read only** (the session manager's review): fields and Save are disabled (`$noEdit = $frozen ||
    $merged`) with "Read only: This listing was joined with <kept>". The main button is "Edit <kept>" when they're on the
    kept listing's team; otherwise "See <kept>" plus "Ask our team to manage <kept>";
  - the list's names open `/listing/businessportal.php?id=N`, a request on hold opens `/claim.php?listing=N`, and a
    non-merchant id opens `/business/N`, never the page you're on. Hidden listings carry a "Hidden" pill;
  - disputes and frozen editing are unchanged.
  `listing/api/update-metadata.php` already allowed saving a hidden listing, and `SearchIndex` skips suppressed listings,
  so a save never puts a hidden listing back in search.
- `app/ui/faq.md`: "My business's listing is hidden. Can I still see it?" reworded in place (the anchor holds).
- Tests: `tools/listing-tests/portal-hidden.php`, 20 tests on MariaDB (`php tools/listing-tests/portal-hidden.php <root>`, a
  root holding `app/` with a `.env` four folders up from `app/lib/`). The page was rendered with the live code at 390px.
  Ask our team's real send was seen only as its error path (no mail server in the test); it sends on live.

## 2. Open: the "Edit my listing" path check, due Monday 2026-11-23

The path (`tools/page-audit/paths-staging.mjs`, runner `'edit-listing'`) runs on staging only on **Mondays and Thursdays**
(and in a full run, or a Run workflow of `site-checks.yml` naming it in PATHS_ONLY). The 2026-11-20 run skipped it, so
there's no result since #191. Its last result ("stuck: no hours box on Edit listing (/listing/businessportal.php?id=9035)"
and "dead end: tapping the test listing showed nothing") predates the fix.

**How to read it** after Monday's run (the run lands around 09:40 UTC, commit "Site checks: rendered pages and paths"):
`git fetch origin main`, then
- `git show origin/main:site-audit/paths.md | grep -n "Edit my listing"`: the row's status (goal / stuck / dead end), and
- `site-audit/results/paths.json`: the object with `"id": "edit-listing"`: `status`, `problems`, `notes`.
Passing looks like: Your business → Edit listing opens 9035's editor with the Hours box → Save says "Saved." → See it as
visitors do shows the stamped hours (view.php shows a hidden listing to its own team, with the hidden banner). Report the
result to the session manager. The session manager has said Monday is fine: #191's own tests proved the fix, so this
run confirms it rather than blocking anything. If it's still stuck, read the runner's step that failed and fix the cause
in place (a new release, built on the newest sync).

A reminder was scheduled in the old session for 2026-11-23 12:30 UTC (trigger trig_017BqiceMio5n3pFCeNQQR92). It fires
into that session, so the new session should do this check itself.

## 3. Pending, held by Jason for discussion: area data (may come to this area)

Ten micro-clusters named "Albuquerque", and Abilene, are filed under the Ancient Borderlands zone. Five code paths create
clusters, among them `api/auth/session.php`; the others found so far are the intake paths
(`CrawlerIngest::resolveMicroClusterId()` via `api/ingest.php`, `IntakeStager`, `IntakeClusters`, `AddBusiness`), which file
any ZIP outside an existing area under the default hub. The direction already decided is in
`handoffs/session-manager.md` (W15 Funnel, decisions/0170): no default hub; a find goes to its zone, active or planned,
by the zone's geography; only a find outside all zones is "no hub yet". Data cleanup proposes moves with preview and Undo.
**Don't build it until Jason releases it for discussion**; when he does, start from 0170 and that note, not from scratch.

## 4. Working notes for the next session

- Live code: `degraffics/traverse-3way-sync` (`sync-storage/` = website, `sync-app/` = app). Clone shallow and sparse;
  start every file from the newest sync.
- The site treats curl and HeadlessChrome as bots (no session; `app/bootstrap.php`'s `$tvMachine`). For a local render,
  give Playwright an ordinary browser user agent.
- Claims taken for #191 (`app/lib/Claims.php::ownListing,hiddenReason,askAboutHidden`, `website/listing/businessportal.php`)
  ended when it was verified live.
