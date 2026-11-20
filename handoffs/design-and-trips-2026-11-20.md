# Design and trips: hand-off at the 2026-11-20 hold (written by session_01CXLi5gxSyzVYGuXX3xuu2c)

Jason asked for fresh sessions when one grows large. This one handed off at a clean hold: nothing of mine is unmerged
except this hand-off and the Pulse answers note (branch `claude/pulse-filters`). Read CLAUDE.md, the builder playbook,
`handoffs/design.md` (the index, with its pitfalls), then this. The harness is in `handoffs/design-harness/`.

## 1. Live today, by release (all approved by Jason in pop-ups, reviewed by the session manager)
| # | Release | Hand-off | Checked on live |
|---|---|---|---|
| 230 | Hero tidy: ⋯ starts Link to, Claim, Add to a trip; journeys can be linked (0047 note); stock details see-through | `hero-tidy.md` | yes (no published journey yet to press Link on) |
| 232 | Admin kit: metadata text on `--tv-meta`; the way-back bar 390 wide | `admin-kit.md` | the bar, yes; admin colours need a staff sign-in |
| 233 | T5: the cost tab's tiers and the vault's list on the table standard; domains to 3e | `t5.md` | needs a staff sign-in (and the vault a passkey) |
| 235 | Vault row actions on §7: Copy password and ⋯ (Jason: "Follow the standard") | `vault-icons.md` | needs a passkey |
| 237 | 3d: /community/ to the mockup, adapted (Jason: "Mockup, adapted"); group page, member dashboard, pulse-panel, profile-header on the tokens | `r3d.md` | page yes; live has **no public groups**, so the cards are unseen |
| 240 | listing-signin: listing pages read the site's session folder (signed-in people looked like guests since 2026-10-10); Back to where you came from (0058 note) | `listing-signin.md` | the guest Back, yes; **the signed-in bar on a listing: Jason to confirm** |

## 2. Next, in order
1. **Pulse filters** (design/table-standard.md, Jason's note of 2026-11-20 and its **Answers**). Not started. Plan, cleared
   by the session manager:
   - One button "Inbox · All (n) ▾" whose panel holds Lists, Show (the views), **Senders** and **Levels** (ticks applied
     at once, no Show/Cancel), and **Group by**. Active filters show as × tags beside it. The separate Group ▾ folds in.
     Both header funnels go (From per note 1; the level funnel by §4 "no filter controls in column headers"). Sorting
     stays on the headers. TvAsk.choose's centred "Show these …" box goes.
   - Counts within the current view; 0 greyed (note 2). The panel opens under its button and closes with ×, Esc or a
     click outside (note 3).
   - Groups: **3 rows or fewer open by themselves** (Jason); open groups remembered on the device; Open all / Close all
     (note 4).
   - **Saved views with the account** (Jason: "With my account"). New add-only table `pulse_views` (id, user_id, name,
     filters JSON, created_at); its staging class in `app/lib/staging-tables.php` (user data, R14). An owner-only API to
     list, save and delete, with Undo on delete. One click each in the panel (note 5).
   - Words beside the bell, Your day, Quick actions and Settings on a wide screen; icons only on a phone (note 6).
   - FAQ: Pulse's entry updated in place (keep its anchor).
   - Kept/moved list: kept are lists, views, sorting, paging, + New, Filter this list, the four icons and their pop-ups,
     grouping; moved are Group ▾ and the From and level funnels (into the one button); dropped is only the "Show these"
     box (note 1).
   - Files: `app/ui/pulse-ui.php` (1,507 lines; current controls at #pb-fdd, #pb-gdd, `.ps-flt`, TvAsk.choose at
     ~l.1044; choices in localStorage pb_view, pb_group, pb_table, pb_per). A new migration, an API endpoint, faq.md.
     notify-tests (member-pulse, admin-stream, pulse-act) must pass, run on a copy.
2. **The screenshot proposal** (Jason's three screenshots, 2026-11-20). Send to the session manager, then to Jason as one
   pop-up. Design items, each needing its source or a question:
   - Stock photos repeat (the same desert road on every Parks & Landmarks card; the same shopfront street on M2
     ServCore and on the Navajo Code Talkers Memorial). 0143's order is own photo, then Commons, then stock, then
     pattern. Proposal: never the same stock photo on neighbouring cards; memorials and monuments get no stock photo
     (their own or Commons, else the pattern). Suspected cause, **to confirm**: places stored as business listings never
     reach the landmark Commons match (`dv_card_photos`).
   - "0 mi" on every card when no distance is known: show distance only when known (principle 4, words follow the state).
   - The stock credit line repeated on every card: keep it in the photo's ⓘ (Jason 2026-11-17, credit on the photo with
     an i).
   - The photo-corner icon is unreadable (white on sky): §3 contrast.
   - Claim on places nobody owns (a memorial shows a big "Claim it" and "Unclaimed"): 0070's claim-kinds note; ask
     whether monuments get no Claim or an agency's Claim.
   - The listing page: the photo takes about 40% of the screen; eight wordless round icons stacked far right, with an
     unexplained score ring and a large empty card; "Other" leads the categories; "Fix the pin" on a PO box; two Link
     controls. CLAUDE.md's card anatomy (actions in a row on the name's line). Propose options; don't build without his
     pick.
   - Data, passed to Operations by the session manager: memorials filed as business listings ("Entertainment &
     Recreation", "State Parks", a state office's phone); Phoenix under Ancient America.
3. **The phone menu fixes** (Jason's screenshot, my review in chat; he hasn't picked yet):
   - `header.php`'s `.tvpm-in` names both the menu's inner column and the Sign in button. The column takes Sign in's
     styles (an outline round the menu, display:block, Help Center overlapping "Ai Assist" by 7px, Sign in 76px wide).
     Rename Sign in's class (tested in the browser).
   - "Ai Assist" → "AI Assist" (every other surface says AI).
   - His call: menu words 1.9rem/800, Help Center the only uppercase button, a 150px logo.
4. **3e:** Marketplace, checkout, storefronts, and now domains (table-standard's dated note). Business colours kept, the
   owner warned below 4.5:1 (admin-design.md). Read `marketplace-architecture.md` and `design/marketplace-landing-mockup.png`
   first: the mockup may raise the same "rebuild or re-skin" question 3d did.
5. **3f:** sign-in, long text, standalone pages, leftovers (public profiles and the business portal took 3d's colour
   change through the shared parts).
6. **The map release** (`js/map-view.js`, `--tv-route` lime over umber casing, brand.md §4 notes).
7. **0174 R5** (openrouteservice: read its terms first; the host may need allowing).

## 3. Open questions with Jason (asked in chat, unanswered)
- The Link icon: does he want one in the **photo's** top-right corner (there's none; the page's Link is the card's
  icon, which hides while ⋯ is open and comes back on every close, re-tested on live)?
- The phone menu: which fixes.
- Confirm the account bar now shows on a listing for him, signed in.

## 4. Left for later
- `dshort()` in `js/tv-vault.js` has no caller. Delete it with the next vault release that asks for drops.
- The status greens and reds `recolor.py` left unplaced (dashboard, pulse-panel, profile-header), listed when
  `handoffs/r3d/build-3d.py` runs.

## 5. Rules learned today (in design.md's pitfalls too)
- **Shared files:** the zip check reads only the inbox. Before merging anything that carries faq.md or another shared
  file, ask the session manager whether one is Planned or On staging (#235 dropped #234's FAQ lines).
- Releases may stack while Jason is away (session manager, 2026-11-20): each its own PR and pop-up. Rebuild on the
  newest sync before merging, in the order the session manager sets.
- Test what you claim: a "bug" reported from a test that scrolled the page wasn't real. Re-test from a clean page before
  telling Jason.
- A question built on a wrong premise (⋯ on journeys) gets corrected in the next message, with the real gap named.
