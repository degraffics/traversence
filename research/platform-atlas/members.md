# Platform atlas: members

*`research/platform-atlas/members.md`. This is a draft for human review. It was built only from the attached files, and anything not in them is marked **not shown**.*

**Coverage.** The attached code is part of the `/user/` tree (`sync-storage/user/…`) plus a handful of `app/lib/` classes.

Shown in part or in full:
- The decision records 0172, 0137, 0136, 0171 and 0133, which are truncated.
- The charter, brand, PRD and `site-audit/pages.md`.

Not shown, though the attached code calls them:
- Page files: `user/dashboard.php`, `user/profile.php`, `user/experiences/`, `user/market/`, `user/trips/trip.php`, `user/journeys/compose.php`.
- Libraries: `MemberPulse`, `PulseAct`, `PulseMarks`, `Notify`, `Mailboxes`, `WebPush`, `MemberPosts`, `People`, `Links`, `Marketing`, `BizMessages`, `Roles`, `Trips`, `Journeys`, `AccountDeletion`, `Standing`, `Consent`, `ShortCodes`, `StaffLogin`, `Team`.

Wherever this entry describes what those classes do, the description comes from comments and call sites only.

---

## (a) What each kind of person can do here

### Resident ("Get Local")

| Page / endpoint | What they can do |
|---|---|
| `/user/dashboard.php` (not shown; `pages.md` §3) | Open the Dashboard: Pulse, Address Book, links, saved searches and roles. |
| `/user/api/saved-searches.php` | Save up to 20 searches (lines 35-36), scoped to a cluster, geo-hub or hub (:37). Each search shows a "new since last opened" count (:50-52). |
| `/user/api/people.php` | Use the private Address Book: add members, businesses, places or people not on Traversence (:40-47, :81-91). Make connection requests, block, share card fields and use groups of people (:59-99). |
| `/user/api/links.php` | Link a business or place, mute it, or unlink it (:41-52). Read linked feeds: listings, discovery, community or marketplace (:27-29). |
| `/user/api/marketing.php` | For each linked business: subscribe and set a frequency, pick which contact fields to share, or use the master "no marketing" switch (:36-53). |

### Traveler ("Let's Explore")

| Page | What they can do |
|---|---|
| `/user/trips/` | See trips with their stops in order, and take a stop out with Undo (:89-99). Rename a trip, Send to Maps, or Delete it (kept `Trips::KEEP_DELETED_DAYS`, then Put back, :68-71). A guest gets one trip on the device and an offer of an account (:44-46). |
| `/user/trips/trip.php?id=`, `/trip/?t=`, `/guide/personal.php` | Days, times, the map, a view-only share and a published Personal Guide (`pages.md` §3). **Not shown.** |
| `/user/journeys/` | Become a contributor: guidelines, 18+, a public profile and email confirmed (:87-103). Then write, edit or delete journeys and accept co-author invites (:29-36, :72-84). |
| `/ai/` (not shown) via `AiAssist` | Ask questions that are answered only from Traversence's own record (`AiAssist.php:105-109`). Asks the record can't answer are queued for research and followed up by email (:136-159). Needs a paid plan (:38-39). |

### Member (any signed-in person)

| Page / endpoint | What they can do |
|---|---|
| `/user/api/profile.php`, `/user/api/member-profile.php` | Edit display name, bio and private flag (`Profile.php:53-70`). Manage per-field publish, photos and framing (`member-profile.php:3-10`). |
| `/user/post.php?id=`, `/user/api/posts.php` | Moments: post, keep, edit, react, comment, see viewers, report, set a default audience (:13-20). Posts end after 3 or 7 days unless kept (:41). |
| `/user/api/pulse.php`, `pulse-act.php`, `pulse-msg.php`, `marks.php`, `pulse-views.php` | Use Pulse, the inbox. On each row: reply, forward, report, block, mute, add to the Address Book, keep (`pulse-act.php:6-19`). Your own marks: follow-up, importance, tags, pin, folder, archive (`marks.php:2-8`). Saved views, up to 30 (`PulseViews.php:10`). |
| `/user/api/bell.php` | See the bell's count of messages, requests, business and guest items, dispatcher items and "needs you" (:33-55). |
| `/user/api/comms.php`, `notices.php`, `push.php` | Choose each kind × channel (Pulse, email, push), set quiet hours and the weekly digest (`comms.php:7-9`). Turn notice kinds on or off (`notices.php:4-5`). Manage push devices and send a test (`push.php:6-9`). |
| `/user/api/mailboxes.php` | Read and answer connected mailboxes in Pulse, and make a case from one (:4-8). |
| `/user/api/consent.php` | Tool consents ("Your tools") plus verification status (:3-4, :17). |
| `/user/api/sign-ins.php` | Disconnect a linked Google, Apple, Microsoft or Facebook sign-in (:2). |
| `/user/delete-account.php`, `/user/restore-account.php` | Close the account with 30 days to restore, or delete it now (`delete-account.php:30-67`). Restore, keep closed, or delete now (`restore-account.php:36-41`). |
| `/user/notice.php` | Read an account notice (warning → limited → revoked). Confirm the steps or ask for a review (:83-100). |
| `/user/orders.php` | See orders Traversence prepared for you, released artwork, and Ask to reorder (:64-95). |
| `/user/domains/` | Domains and wallet, rendered by `tv_domains_view` (:22). The view itself is not shown. |
| `/user/api/qr.php` | Create and manage your own QR codes and see stats (:3-8). |
| `/user/accounts.php`, `/user/staff-link.php` | Switch context (:126-132). Confirm a staff-login link (:38-43). |

### Business (owner or team, acting in a listing's context)

| Page / endpoint | What they can do |
|---|---|
| `/user/accounts.php` | See listings you manage, grouped by Nexus, with search once there are more than 6 (:92-103). |
| `/user/api/context.php` | Switch into a listing (:61). Grant or revoke team roles: Owners grant any role but owner, Managers only General (:33, :62-68). |
| `/user/api/business-messages.php` | Reply to people and to guest questions; mark guests spam or unreachable. Send offers to subscribers and ask lapsed people again (:10-17, :54-64). |
| `/user/api/marketing.php?insights=` | Counts of linked people, available to listing staff or admins only (:24-28). |
| `/user/transfer.php` | Accept or decline taking over a business (:68-71). |
| `CommentNotices` (Pulse bodies) | Reply to a comment on your listing, or Hide / Show it again (`CommentNotices.php:63-79`). Hiding is allowed for Owners, Managers and staff per the comment at :8-9. |

---

## (b) How it works underneath

**Endpoint pattern.** Every `/user/api/*` file follows the same shape:

1. Load `app/bootstrap.php`.
2. Require a signed-in user, returning 401 otherwise (e.g. `profile.php:12-13`).
3. Require CSRF on POST (`Response::requireCsrf()`, e.g. `links.php:35`).
4. Delegate the work to one library.
5. Return 422 for `InvalidArgumentException` with a plain message (e.g. `people.php:103-104`).

Most endpoints also refuse with 503 and name the migration when tables are missing (e.g. `consent.php:12`, `people.php:21`).

**Libraries shown:**
- `Profile.php`. Name rules (:16-27):
  - 2–40 characters;
  - no 6+ digit runs;
  - "admin/staff/support/traversence" and "Member #N" are reserved;
  - `ListingSafety::contentProblem`.
  
  Email is never part of the profile (:3).
- `MemberVerification.php`. "Verified" means email confirmed plus 18+ (:66-70). It is required only for contributor, owner, steward and staff (:19-24). Phone shows as "coming" (:57).
- `Contacts.php`. Private contacts, max 2000 (:21). "The other side is never told… nothing here feeds links, feeds, marketing or learning" (:12-13). Saving needs the `address_book` consent (:28-31).
- `SignInLinks.php`. One account per provider. A provider account linked to someone else is never moved (:11, :37-53).
- `Notices.php`:
  - kinds are listed at :14-25;
  - sending goes through `Notify::send` as `notice.<kind>` (:40-47);
  - email goes via SES from `notices@send.traversence.com` (:88);
  - the weekly digest is at :100-124;
  - opening Pulse marks all notices seen (:144-148).
- `PulseViews.php`. Saved filter mixes, owner-only, whitelisted keys (:11, :37-48).
- `CommentNotices.php`. The live comment body: a thread excerpt, its state, Hide/Show, a Reply box with an 18+ check, and Report (:27-82). Actions run through `Engage` and `Reports` (:117-146).
- `AiAssist.php`:
  - knowledge comes from the Topic index, vetted heritage facts (public/story tiers only, :80) and the place's published guide (:60-99);
  - each ask is logged in `ai_asks` with its cost and who paid (:50-52);
  - follow-ups are emailed via `Mailer::send` (:167).
- `PlaceProfile.php`. Census ACS profiles for clusters, hubs and counties, always with a citation and margins of error (:7-19, :24-26). It feeds Discovery Guides; how it reaches member pages is **not shown**.

**Pulse and the bell.**
- `pulse.php:18-23` reads the current context (`Roles::current`). It returns `MemberPulse::sources/needs/stream`, with each row decorated by `PulseAct::decorate`.
- `pulse-msg.php:23` builds the bell list from `comms_messages` where `lvl IN ('critical','urgent','alert')` and the item is unread.
- `bell.php:23-32` sends a staff login to the admin Pulse count. For everyone else it sums People, `BizMessages::staffUnread`, guest questions, `Notify::unreadForBell` and `MemberPulse::needs` (:35-55).

**Account safety.**
- Deletion requires typing "DELETE" (`delete-account.php:15`) and a sign-in within the last 15 minutes (:16).
- Restore works by a 40-hex emailed token or a 15-minute session (`restore-account.php:16-17`). The token is protected by `Referrer-Policy: no-referrer` (`restore-account.php:10`).
- Transfer is checked with CSRF via `hash_equals` (`transfer.php:22`).

**Tables seen in shown code:**
- `saved_searches`
- `address_contacts`
- `user_identities` / `users.google_sub`
- `notices_sent` / `notice_prefs` / `notice_delivery`
- `pulse_views`
- `comms_messages`
- `entity_referrals`
- `ai_asks`
- `place_profile`
- `staff_accounts`

---

## (c) Decisions this area rests on

| Decision | What it rules here (as cited in code/docs) |
|---|---|
| 0047 | The Link button, Address Book, business messages and marketing preferences (`links.php:2`, `people.php:2`, `marketing.php:2`). |
| 0049 | Tool activation and consent, including Linking before the first link (`consent.php:2`, `links.php:43`). |
| 0052 | Private contacts (§2) and guest questions (§5) (`Contacts.php:10`, `business-messages.php:16`). |
| 0053 §6 | Saved searches (`saved-searches.php:2`). |
| 0055 §4, §5, §8 | Acting-as contexts, Pulse and team (`context.php:2`, `pulse.php:2`). |
| 0056, 0058 | Journeys and contributors (`journeys/index.php:2`). |
| 0062 §4 | Member verification, amended 2026-11-05 so no separate yes is needed (`MemberVerification.php:9, 27`). |
| 0079 | Notice of action: warning → limited → revoked, with review (`notice.php:2`). |
| 0090 | Ways to sign in (`SignInLinks.php:2`). |
| 0091 | Account deletion with a 30-day grace period (`delete-account.php:2`). |
| 0094 | QR codes (`qr.php:2`). |
| 0100 | Staff login linked to a member account; the link never grants admin (`staff-link.php:2-5`). |
| 0101 | Client orders and artwork (`orders.php:2`). |
| 0103 §2, 0120 | Business transfer by hand (`transfer.php:2`). |
| 0136, 0137 | Groups, charters, notices sent once (§6), email on SES (§8) (`Notices.php:2-8`). |
| 0153 | Moments, Keep and post media (`posts.php:2`). |
| 0166 §6a, 0169 §1a | Domains and wallet (`domains/index.php:2-4`). |
| 0172 | Communications: Pulse is the inbox, six levels, row icons, Communications setting, mailboxes in Pulse, push (`comms.php:2`, `pulse-act.php:3`, `pulse-msg.php:2`, `mailboxes.php:2`). |
| 0174 | Trips (`trips/index.php:2`). |
| 0133 → 0164 | AI Assist answering from our own record. 0133 is closed and superseded by 0164; 0164 itself is **not shown**. |

---

## (d) Live vs coming

**Live** (per `pages.md` §3 and §6):
- dashboard, profile, journeys, experiences, accounts, delete and restore;
- market posts, notice, orders, staff-link, transfer;
- trips, released in stages: list (release 2), trip page (release 3), shared view and Personal Guide (release 4);
- `/user/api/*` for profile, people, links, consent, context, pulse, marketing, business-messages, community, referrals, saved-searches, bell and comms.

**Built, awaiting release