# Release 3d: Community and the member dashboard (2026-11-20): sent to the branch, waiting for Jason's OK

Design and trips session. Built on **Sync 17:44 UTC (ac9cc04)** by `handoffs/r3d/build-3d.py`, with `recolor.py` (it maps
hex colours onto the tokens by what they colour: text, surface or line; each keeps its hex as the fallback; anything it
can't place stays and is listed). Sources: css-theme-brand §4 Release 3 (3d per admin-design.md); Jason, 2026-11-20,
3d's pop-up: **"Mockup, adapted"** (note in community-scope.md). Pulse's new look was struck from 3d, since T2 and the
Pulse releases did it.

## /community/ to the mockup, adapted
- **Start a group** at the top right: it opens the start form below (the same form, charter and all).
- One search bar on the section colour: the search box, **All topics** (the topics, in place of the chip row: css-theme-brand
  §1, CLAUDE.md's filters rule), the sort, and Find. Popular tags stay as words (links), not pills.
- **Sort:** with no place, **Most active** (the mockup's default: joins in the last 7 days, then members) or **Most members**
  (the order the list had before). With a place, **Nearest first** (the order it had before) or **Most active**. A
  non-default sort is kept in the address.
- **Trending & growing groups** (titled "Interest groups" under Most members) with its count, then **Local groups** with
  its note. In place mode, "Groups in <place>".
- **Cards** on the card colour (not white: §5, brand.md §4's levels): the topic's brand icon (`tv_icon`), **+N this week**
  (joins in the last 7 days from `community_group_members.joined_at`, a count only, never who, 0058 §13; shown only when
  above 0), the name as the link, where / topic / ask to join, part of, about, tags, then members and **Join group**
  (**Ask to join** for approval groups, **Open group** for yours). Join opens the group at `#charter`, "What you're
  agreeing to" (0137 §3: joining is consent to the charter). group.php already scrolls to `#charter`.
- The rail (Connect: Groups, Start a group; Yours: Your groups, Address Book, Your Pulse) was already the shell's
  (section-rails.php), as in the mockup.
- Kept: place mode, topics in the address, popular tags, Your groups, paging (`TvPager`), the start form with its
  charter, business and kind, the email-confirmation note, Sign in for guests.
- `Community::directory()` adds `week` to each group (one grouped COUNT over the listed ids).

## A group's page, the member dashboard and its parts: on the tokens, layouts kept
- `community/group.php` (49 values), `user/dashboard.php` (254), `app/ui/pulse-panel.php` (85) and
  `app/ui/profile-header.php` (36) onto the tokens. The last two are also drawn on public profiles and in the business
  portal, which change the same way.
- The group page's primary buttons on #181's standard (brand brown, page-colour text). Light words on the umber (the
  avatar's gold, the active tab, your own messages) on `--tv-on-umber(-meta)`.
- `admin-dashboard.php`: the counts (`.ad-cnt`, `.ad-sub i`, the icon colour at 4.38:1) in link brown, and "no change"
  (`.ad-m i.eq`, words at 4.36:1) in the meta colour. **A correction to the admin kit release**, which had read it as a
  bare mark.
- The "needs you" count badges: Grounded Amber (`--tv-hold`, css-theme-brand §2, waiting) with white at 5.6:1 (they were
  #D97706 at 3.19:1).
- Left as they were, unplaced (status greens and reds, a few edges), listed by `build-3d.py` when it runs.

## Tests (harness, rendered)
- `/community/`, 27 of 27 at 1280 and 390: headings; Most active first by joins this week; "+24 this week"; no "+0"; Most
  members gives the old order and title; the sort in the address; 12 topics in the dropdown; no pills; Ask to join and
  Join group to `#charter`; Start a group opens the form; no sideways scroll; cards 3px from the edges; no script errors.
- Contrast probe (every text element, 4.5:1, 3:1 for large): /community/ 46 texts and 0 under; a group's page signed
  in and out, 0 under (live: 4); the member dashboard as a member, 0 under (live: 12); the staff dashboard, 0 under
  (it was 14 before the admin-dashboard fix). No sideways scroll at 390.
- notify-tests member-pulse: 4 of 4. A public profile renders, no sideways scroll, no script errors.
