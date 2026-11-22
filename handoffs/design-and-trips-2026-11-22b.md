# Design and trips: hand-off at the 2026-11-22 afternoon hold (written by session_01CXLi5jNQCN3zFZ2Fh3qUNf)

This session took over from session_01CXLi5kMXdmwb4aE7hWkGeU (`design-and-trips-2026-11-22.md`, still the record of the
queue and its Progress lines). Its context grew large, so it hands off at a clean hold (Jason, 2026-11-20). Read
CLAUDE.md, `builder-playbook.md`, `design-and-trips-2026-11-22.md`, `design-and-trips-2026-11-20.md` §2, then this.
**Newest sync at the hold: b735f56 (Sync 14:38 UTC 2026-11-22).** Nothing is held, sent or waiting on Jason.

## 1. Live from this session (each approved by Jason in a pop-up, reviewed by the session manager, verified on the mirror)
| # | Release | Record | Verified |
|---|---|---|---|
| 251 | 3f sign-in: tabs, social buttons' words, no cart/bell/search on sign-in pages, the code box, the lost work email (B8) and the authenticator notice | `signin-3f.md` "Built"; 0100's note "Built as" | 70d0cb1: 16 files byte for byte; live /login.php shows the worded buttons |
| 252 | Own quick searches counted ("Count them") | `own-quick-counts.md`; 0053's note | 0b58101: 4 files byte for byte |
| 253 | The phone menu: two defects fixed, Jason's option B; with the layout probe's false 5px hero gap (#1256, tool only) | `phone-menu.md`; 0055's note | b735f56: header.php byte for byte |

## 2. Next, in order
1. **3e** (`design-and-trips-2026-11-20.md` §2 item 4): Marketplace, checkout, storefronts and domains (table-standard's
   dated note). Business colours kept, the owner warned below 4.5:1 (`admin-design.md`). Read `marketplace-architecture.md`
   and `design/marketplace-landing-mockup.png` first. **The session manager asked (2026-11-22): bring it the "rebuild or
   re-skin" question first**, as 3d raised, before Jason.
2. 3f's leftovers (long text, standalone pages; public profiles and the business portal took 3d's colours already).
3. The map release (`js/map-view.js`, `--tv-route` lime over umber casing, brand.md §4 notes).
4. 0174 R5 (openrouteservice: read its terms first; the host may need allowing).

## 3. Open with Jason
- Whether the account bar shows for him on a listing, signed in (from 2026-11-20): his to look at, not a decision.
- A sole owner can be reset only from the server (3f's summary): recommendation, keep a second owner or admin. Answered
  only as part of 3f's Approve; no separate decision asked.
- Answered, don't ask again: the Link icon on a listing ("Use link icon in right top corner and ... tool kit just to the
  left of it", 2026-11-22, #250).

## 4. Harness notes (this session's, add to `design-harness/README.md`'s)
- `apt-get update` first, or `mariadb-server` isn't found.
- The harness `.env` sets `HARNESS_MAIL=<file>` with `h/harness-patch.sh` (in this session's container only, recreate):
  `Mailer::send` writes JSON lines there instead of sending, so tests read emailed codes; `HARNESS_CPANEL=<file>` makes
  `CPanelMail` ready and records `setPassword`. Never `STAGING=1` (the staging gate blocks every page).
- Social sign-in buttons show only with GOOGLE_/MICROSOFT_/FACEBOOK_ keys in `.env` and `users.google_sub`,
  `user_identities` present.
- Pulse on the harness: `pulse_marks` (item_key unique, case_no, assigned_to, done_at, done_by, esc_level, esc_at) and
  `pulse_notes` (item_key, user_id, kind, body, to_email) need their real columns; Security cases show to founders and
  executives only (`Workspaces::allowsArea`). Open a case by a full load of `/admin/dashboard/stream#case=<key>` (a hash
  change alone doesn't reload Pulse).
- Search on the harness: `search_index` with `SearchIndex::put`'s columns and a FULLTEXT key, `search_index_state`
  `built_listing`; `search_index_state.v_long` MEDIUMTEXT. A search's total is `results.total`.
- Live pages measured from here need Tailwind's CDN served from `@tailwindcss/browser@4` (the sandbox blocks the CDN; without it the header renders 273px tall).
- The phone menu opens from the compass (`#brand-link`) on phones, not ☰.
- `releases/inbox/<zip>` leaves `main` once the site takes it; check a delivered zip from the local branch copy.
- A README conflict with another session's merge: take theirs, re-apply only this build's lines (both tables).
