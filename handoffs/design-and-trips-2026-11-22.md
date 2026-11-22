# Design and trips: hand-off at the 2026-11-22 hold (written by session_01CXLi5hFvhtEKj2JuNVmjWZ)

This session's context grew large (Jason, 2026-11-20: hand off "so we can reduce token use without logic loss"). It took
over from session_01CXLi5gxSyzVYGuXX3xuu2c (`design-and-trips-2026-11-20.md`, still the record for #230–#240). Read
CLAUDE.md, `builder-playbook.md`, then this. Newest sync at the hold: **ca2f7ab (Sync 12:25 UTC 2026-11-22)**.

## 1. Live from this session (each approved by Jason in a pop-up, reviewed by the session manager)
| # | Release | Hand-off | Checked on live |
|---|---|---|---|
| 241 | Pulse filters in one button, saved views with the account (`pulse_views`) | `pulse-filters.md` | FAQ and the API's sign-in answer as a guest; the admin Pulse is Jason's to see |
| 248 | Search: tabs count what's listed and use Search Directory's names (0058 §38), Peek on every result, Esc closes only the pop-up, the lacking note above, the guide offer under, Quick Search counts a category | `search-fixes.md` | the live API: "places to swim near St. Johns" gives Get Local 1 for 1 pool (was 2) |

## 2. In progress: `card-fixes.zip`, PR #1247 (branch `claude/card-fixes`), NOT clear to ask
Built on ca2f7ab; `card-fixes.md` has every behaviour with its source; build `card-fixes/build-cf.py`; test
`tools/design-tests/card-fixes/run.php` (10/10). Claims held for `card-fixes.zip` (discovery/index.php, explore-hero.php,
listing/view.php). The session manager's review (2026-11-22) asks for three fixes before Jason's pop-up:
1. **A4 at its cause:** hide a distance only when the place's point is a town-level geocode (equal to the town's point
   in `zip_coordinates`, or its precision says so); a real point under ½ mi shows "under ½ mi" (0060 "near me" with
   miles). Today's build hides everything under 0.5 mi (`dv_mi`): wrong. Test both.
2. **A5 readable without hover:** the visible credit on the photo carries what the licence needs: the author and the
   licence ("© Name · CC BY-SA") for Commons (0164: the licence decides the use); "Stock photo" for stock. The title may
   add more but is never the only place. No tap-to-open inside the card's link. Show a CC BY-SA card at 390. (Check what
   licence data `landmarks.image_credit` / `PlacePhotos` hold; if the licence isn't stored, that's a question first.)
3. **Proof on the real page:** render `discovery/index.php?view=parks` on the harness with ~30 seeded places (Commons,
   stock, none; two memorials, one with a real photo; distances unknown, town point, real under ½ mi, normal; long enough
   to page) at 1280 and 390, across paging: no stock photo twice in the list, no "0 mi", credits, no sideways scroll.
Fine as written: B3 "never twice in one list" (say it to Jason as the rule, and that it holds across pages: check that
paging is one list); A13 (say a memorial filed as a business keeps "Unclaimed" until the area data fix, which the session
manager holds). faq.md is clear to send. Then: summary to the session manager, then Jason's pop-up, merge, tell it.
**Progress (2026-11-22, session_01CXLi5kMXdmwb4aE7hWkGeU):** the three fixes built into `card-fixes.zip` on ca2f7ab (the licence
was stored, so no question was needed); zip check clean; run.php 16/16, parks.cjs 34/34 on the real page at 1280 and 390
(`card-fixes.md`). Next: the session manager's review, then Jason's pop-up.

## 3. Next, in order (approved in `screens-proposal-2026-11-22.md`; each its own PR and pop-up)
1. **The listing page (B5, Option 1):** photo capped lower; the actions in one row under the title, names on wide screens,
   icons on phones; the score ring in words or removed; no empty card; real categories before "Other"; "Fix the pin" only
   with a pin (not on a PO box); one Link. CLAUDE.md content-page header and card anatomy. Read `listing/view.php` first.
   **Progress (2026-11-22):** card-fixes live as #249; the listing page live as #250 (5237f07). Built as `listing-page.zip` on 114f938, with Jason's
   refinements in chat (the Link with ⋯ beside it, the name over the address, categories as quiet words); `listing-page.md`.
2. **3f sign-in** (**Progress, 2026-11-22:** surveyed; its full build brief is `signin-3f.md`, for a fresh session): A7 (tab state), A9 (social buttons' words), A10 (cart, bell, admin search off sign-in pages), A11 (code
   box "6 digits", expiry clock time, sign in at the 6th digit, paste with spaces), A12 ("Tap or click" in the email), B7
   (sentence case, one link style), **B8** the lost work email with the session manager's guard rails (the proposal's
   last section; decisions/0100's note of 2026-11-22), and the open question for its summary (setup link shown to the
   owner, never only emailed; a second owner as the safety net). Files: `login.php`, `admin/login.php`,
   `app/lib/StaffLogin.php`, the code email, Pulse case, faq.md.
   **Progress (2026-11-22, session_01CXLi5jNQCN3zFZ2Fh3qUNf):** built and sent as `signin-3f.zip` on 5237f07; see
   `signin-3f.md` "Built". Waiting for the session manager's review, then Jason's OK.
3. **Own quick searches counted** (Jason: "Count them", 2026-11-22): B2's follow-up in `search-panel.js` /
   `SearchPresets`; mind the cost (one count per phrase, cached like `have()`).
4. **The 5px gap above the hero** on Discovery and landmark pages (session manager, 2026-11-22; `site-audit/layout-probe.md`
   10:20 UTC): fix at its cause when it fits a release, never a one-off patch.
5. Then the old queue (`design-and-trips-2026-11-20.md` §2): the phone menu fixes (Jason hasn't picked; the three
   questions were posted in chat 2026-11-20), 3e (Marketplace, checkout, storefronts, domains), 3f's leftovers, the map
   release, 0174 R5.

## 4. Open with Jason
- The phone menu: which fixes. The Link icon in a place photo's corner. Whether the account bar shows for him on a
  listing (#240). (All posted in chat 2026-11-20; unanswered.)

## 5. Harness notes (`handoffs/design-harness/README.md` plus these)
- MariaDB stops when the worker restarts: `mysqld_safe --user=mysql &`, then `php -S 127.0.0.1:8790 -t root/website h/srv.php`.
- Search on the harness: `search_index` needs its full columns (see `SearchIndex::put`'s list); seed rows and
  `search_index_state` `built_listing`. Answers are cached briefly: add a dummy query param to see a change.
- notify-tests need the comms migrations, no longer in the mirror: from `comms-all.zip`, `comms-gaps.zip`, `notify-1.zip`
  in git history (`git show <commit>:releases/inbox/<zip>`); the repo needs a deeper fetch (`--depth=3000 --filter=blob:none`).
- A zip name used before is refused: `pulse-filters.zip` was (the release went as `pulse-filter-panel.zip`). Resending a
  rebuilt zip from the same branch: `git rm` its inbox zip and `deliveries/<name>.json` first.
- Claims lapse after 4 hours; re-claim before the last zip check. The zip check names JS functions too; keep a live
  function by repainting it in place rather than removing it (`paintFilters`).
- The stop hook flags the mirror's github-actions "Sync:" commits as unverified: never rewrite them.
