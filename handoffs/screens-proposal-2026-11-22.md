# Screenshot proposal: places and listings, search, sign-in (Design and trips, 2026-11-22)

Jason's screenshots of 2026-11-20 (cards and the listing page), 2026-11-21 (Quick Search, "places to swim near me" at
St. Johns) and 2026-11-22 (staff sign-in, the code step, the code email), folded into one proposal at his word ("yes,
fold them into one proposal", 2026-11-22). Each item names its source, or it is a question. Read against the live
mirror at Sync e229587 (2026-11-21 22:55 UTC). Reviewed by the session manager 2026-11-22 (B4 moved to A13: 0070
decides it; A8 moved to B7). Nothing is built until he answers. Every release meets CLAUDE.md's end-to-end standard (#1241).

## A. Fixes the rules already call for (one release each area; asked once as a batch)

| # | What's wrong | Fix | Source |
|---|---|---|---|
| A1 | Search: "Results (5)" counts the rows; the tabs also count the categories matched ("Public Pools"), so Get Local says 5 for 4 rows. Live API, same search: `local 2` = one pool + the category | Tabs count only what's listed under them | principle 4 (words follow the state); the code's own rule "the categories found … not counted here" (`search-panel.js` ~l.929) |
| A2 | Search: a Let's Explore row has no Peek (only listings get one), and rows are tagged "Directory"/"Discovery" while the tabs say Get Local/Let's Explore | Peek on every row that has a page; the tag uses the tab's name | CLAUDE.md "Peek at a page without leaving"; card anatomy (actions the same on every card) |
| A3 | Search: "Our results … near St. Johns are lacking" is drawn under the list, so it's missed and the 130–160 mi rows look unexplained | The note goes above the rows when it shows | decisions/0060 ("near me": nearest first, no cap, with miles; fewer than 3 within 5 mi says results are lacking) |
| A4 | Cards: "0 mi" when no distance is known | Distance only when known | principle 4 |
| A5 | Cards: the stock credit repeated under every card | The credit in the photo's ⓘ | Jason 2026-11-17 (credit on the photo with an i); decisions/0143 |
| A6 | Cards: the photo-corner icon white on sky | Its contrast to 4.5:1 (a soft dark disc behind it) | css-theme-brand §3 contrast |
| A7 | Sign-in: the active tab is pale, the other solid orange, so Create account looks chosen | The chosen tab marked (brown, underline), the other quiet | css-theme-brand (one look for state); brand.md |
| A9 | Sign-in: Google, Microsoft, Facebook buttons with no words | "Continue with Google" … (words beside, or labels at least) | CLAUDE.md icon buttons carry `title`/`aria-label` |
| A10 | Sign-in pages (named in the pop-up): the cart and bell look switched off with no reason; the admin search shows before you're signed in | Left off the sign-in pages (nothing to show yet) | CLAUDE.md "greyed with the reason, never silently"; "never offer what their account can't use" |
| A11 | Code step: placeholder "0 0 0 0 0 0" reads as a code typed; "works for 10 minutes" | Placeholder "6 digits"; "until 4:42 AM MST"; signs in at the 6th digit; a pasted code with spaces accepted | CLAUDE.md "Times where they happened"; principle 1 (the system does it) |
| A12 | Code email: "Click the code once" | "Tap or click the code" | phones first (0058 §11) |
| A13 | Cards and pages: a memorial shows "Claim it" and "Unclaimed" like a business | A public place shows who runs it from its Nexus and the agency's Claim ("Staff? Claim it", free); never "Unclaimed". Where it's filed as a business listing, it follows once the data is fixed (C) | decisions/0070's claim-kinds note (lines 223–226), live as #215 |

## B. Choices for Jason (no decision sets them)

- **B1. The guide card above the results** ("Build the guide to St. Johns"): move under the results, or one slim line
  above them. Recommend: under the results.
- **B2. Quick Search's blank counts** (Open now, Restaurants show none while others do): every row counted, or no
  counts on the quick links. Recommend: no counts on links whose answer changes by the minute (Open now), counts on the
  rest.
- **B3. Stock photos repeat** (the same desert road on every Parks & Landmarks card; the same shopfront on M2 ServCore
  and the Navajo Code Talkers Memorial). 0143's order (own photo, Commons, stock, pattern) stands; two new rules to choose:
  never the same stock photo on neighbouring cards, and no stock photo on memorials and monuments (their own, Commons,
  else the pattern). Suspected cause, to confirm before building: places stored as business listings never reach the
  landmark Commons match (`dv_card_photos`).
- **B5. The listing page** (photo about 40% of the screen; eight wordless round icons stacked far right; an unexplained
  score ring; a large empty card; "Other" first among the categories; "Fix the pin" on a PO box; two Link controls).
  CLAUDE.md's content-page header (kind, title, place, Link top right; engage bar under it) and card anatomy (actions in
  a row on the name's line, with names) are the rules. Options:
  - **Option 1 (recommended):** the photo capped lower; the actions in one row under the title with their names on a
    wide screen (as Pulse's header now does), icons on a phone; the score ring said in words or taken off; the empty
    card not drawn; real categories before "Other"; "Fix the pin" only where there's a pin to fix; one Link.
  - **Option 2:** the same fixes, keeping the actions down the right side, each with its name.
- **B6. Sign-in security (new behaviour):** "This wasn't me" in the code email (cancels the code, opens Change
  password), and the email saying where the sign-in came from (browser, about where). Each is new; yes or no each.

- **B7. Sign-in's capitals and link font** ("SIGN IN", "CREATE ACCOUNT"; "Forgot password?" in bold serif): sentence case
  and the site's one link style, or keep as is. No decision sets case for buttons and tabs (table-standard §4 is for
  table headers). Recommend: sentence case.

## C. Data, passed to Operations by the session manager

Memorials filed as business listings ("Entertainment & Recreation", "State Parks", a state office's phone); Phoenix
under Ancient America. Not design work.

## Releases if approved

1. **Search** (A1–A3, B1, B2): `js/search-panel.js`, `api/search.php` counts.
2. **Cards and photos** (A4–A6, A13, B3).
3. **The listing page** (B5).
4. **3f: sign-in** (A7, A9–A12, B7, B8), as part of Release 3f.
Each its own PR and pop-up; the order set by the session manager.

## Jason's answers (2026-11-22, pop-up reviewed by the session manager)
- **A (all 13 fixes):** Approve.
- **B1** guide card under the results; **B2** no counts on by-the-minute links, counts on the rest; **B3** no stock photo
  repeated on neighbouring cards and none on memorials; **B7** sentence case and the one link style: all yes.
- **B5:** Option 1.
- **B6:** dropped. Jason, 2026-11-22: "we send an email with a token to from the system so no one can get email unless
  its the email of records. The bigger issue over wasnt me is that if someone no longer has access to email of record."
  In its place (his pop-up answer "Yes, all of it", asked after the session manager's review of the proposal, not of
  this follow-up): **B8, a lost work email**, built into 3f. "Can't get the email?" on the code step opens a short form
  (name, a phone or other email to reach them) that becomes an owners' Pulse case; the case's buttons are Change work
  email (`StaffLogin::setEmail`), Reset the mailbox password (`CPanelMail`, our own domain) and New setup link to hand
  over (shown to the owner, not only emailed); after sign-in, staff with no authenticator app get one dismissable notice
  to add it. Recorded in decisions/0100 as a dated note.
Each answer goes into its decision as a dated note when its release is built (0060 for B1–B2, 0143 for B3, 0058 for B5).
