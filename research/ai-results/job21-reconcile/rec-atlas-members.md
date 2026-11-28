# Platform atlas: members (merged from two passes)

*`research/platform-atlas/members.md`. **Draft for human review.** This entry reconciles two independent passes. Neither pass is complete: **pass1 stops partway through (d)**, and **pass2 stops partway through (c)**. Section (e) does not appear in either pass.*

**How to read the markers**
- **Unmarked:** both passes support the point.
- **[p1 only] / [p2 only]:** single-pass. Only one pass supports it.
- **passes differ:** the passes conflict or diverge.
- **Citations:** each pass's own citation is given as `p1:` / `p2:` wherever the line numbers differ.

---

**Coverage and visibility**

**Attached code.** The `/user/` pages, the `/user/api/*` endpoints and the `app/lib/` classes they call. The code is only partly shown.

**Pages not shown**
- Both passes: `user/dashboard.php` and `user/profile.php`.
- [p1 only]: `user/experiences/`, `user/market/`, `user/trips/trip.php` and `user/journeys/compose.php`.

**Libraries not shown**
- Both passes: `MemberPulse`, `PulseAct`, `Notify`, `Mailboxes`, `WebPush`, `MemberPosts`, `People`, `Links`, `Marketing`, `BizMessages`, `Roles`, `Trips`, `Journeys`, `AccountDeletion`, `Standing`.
- [p1 only]: `PulseMarks`, `Consent`, `ShortCodes`, `StaffLogin`, `Team`.
- Pass1 states that any description of these classes comes from comments and call sites only.

**Truncated decision records**
- Both passes: 0172, 0137 and 0171.
- [p1 only]: 0136 and 0133.

**Other attached documents** [p1 only]: the charter, brand, PRD and `site-audit/pages.md`. Pass2 also cites the PRD and `pages.md` in (c), but does not list them under scope.

**`PlaceProfile.php`.** Passes differ:
- Pass1 treats it as a library in this area (see (b)) and says its route to member pages is **not shown**.
- Pass2 classes it as "attached but outside this area" (Census figures for guides).

---

## (a) What each kind of person can do here

*Passes differ on grouping.*
- Pass1 uses four groups: Resident ("Get Local"), Traveler ("Let's Explore"), Member (any signed-in person) and Business.
- Pass2 uses three: a signed-out guest group (labelled "Resident and traveler"), Member (signed in) and Business. Pass2 places all the Resident and Traveler tools under Member.

The merged layout below keeps pass1's labels as sub-groups of Member and adds pass2's guest group.

### Guest (signed out)

- **Trips.** `/user/trips/` holds one trip on the device and offers an account.
  - Citations: p1 `trips/index.php:44-46`; p2 `user/trips/index.php:44-46`.
  - [p2 only]: an account keeps any number of trips, and the device trip moves into the account at sign-in.
- **Posts** [p2 only]. `/user/post.php?id=` shows the post if it is shared with the guest. Otherwise it offers "Sign in to react, comment" (`user/post.php:15,22`).
- **Linking** [p2 only]. A guest can check Link states, but linking needs sign-in (`user/api/links.php:3,34`).
- **Notice by link** [p2 only]. A member whose access was revoked can read the notice and ask for a review at `/user/notice.php?t=` without signing in (`user/notice.php:14-18`; `user/api/notice.php:2-3`).

### Member: Dashboard

**`/user/dashboard.php`** (not shown).
- Both passes treat it as the hub for the endpoints below.
- [p1 only]: per `pages.md` §3, it contains Pulse, Address Book, links, saved searches and roles.

### Member: Resident tools

*Pass1 labels this group "Resident"; pass2 lists the same tools under Member.*

**Saved searches** (`/user/api/saved-searches.php`)
- Up to 20 searches, with "new" counts.
  - p1: limit `:35-36`, counts `:50-52`.
  - p2: `saved-searches.php:3-6,36`.
- [p1 only]: each search is scoped to a cluster, geo-hub or hub (`:37`).

**Address Book** (`/user/api/people.php`)
- Add people, make connection requests, block, share card fields, use groups of people.
  - p1: `:40-47, :59-99, :81-91`.
  - p2: `people.php:3-11`.
- [p1 only]: private entries can be members, businesses, places or people not on Traversence.
- [p2 only]: respond, message, invite (`:3-11`) and person search by name (`:36`).
- Private contacts need the `address_book` consent.
  - p1: `Contacts.php:28-31`.
  - p2: `people.php:84-86`.

**Link button** (`/user/api/links.php`)
- Link, unlink and mute.
  - p1: `:41-52`.
  - p2: `:6`. [p2 only]: also unmute.
- Linked feeds: listings, discovery, community, marketplace.
  - p1: `:27-29`.
  - p2: `:5`.
- [p2 only]: Linking must be activated once, with consent (`links.php:43-46`). Pass1 cites `links.php:43` for the same rule under decision 0049.

**Businesses I've linked** (`/user/api/marketing.php`)
- Subscribe with a frequency, choose which contact fields to share, master "no marketing" switch.
  - p1: `:36-53`.
  - p2: `:5-9`.
- [p2 only]: unsubscribe with "ask again in…", and unlink.
- [p2 only]: the business must be linked first (`:46`).

### Member: Traveler tools

*Pass1 labels this group "Traveler".*

**Trips** (`/user/trips/`)
- Stops in order; take a stop out with Undo; rename; Send to Maps; Delete with Put back.
  - p1: `:68-71, :89-99`.
  - p2: `trips/index.php:2-6,41,69`.
- **Passes differ on how long deleted trips are kept.**
  - Pass1: kept for `Trips::KEEP_DELETED_DAYS`, with no number given.
  - Pass2: kept 30 days.

**Trip page, share and Personal Guide** [p1 only]
- `/user/trips/trip.php?id=`, `/trip/?t=` and `/guide/personal.php`.
- Days, times, the map, a view-only share and a published Personal Guide (`pages.md` §3).
- The code is **not shown**.

**Journeys** (`/user/journeys/`)
- Become a contributor; then write, edit or delete journeys and accept co-author invites.
- **Passes differ on the requirements.**
  - Pass1: guidelines, 18+, a public profile, email confirmed (`:87-103`).
  - Pass2: a public profile, 18+, a confirmed email (`journeys/index.php:94-100`). Pass2 does not mention guidelines.
- Writing and co-author citations:
  - p1: `:29-36, :72-84`.
  - p2: `:72-83, 104-121`.

**AI Assist** (`/ai/`)
- Answers only from Traversence's own record ("only from what we have researched").
  - p1: `AiAssist.php:105-109`.
  - p2: `:2-8`.
- Questions the record can't answer are queued for research and followed up by email.
  - p1: `:136-159`.
  - p2: `:136-157`.
- Needs a paid plan.
  - p1: `:38-39`.
  - p2: `:39`.

### Member: account, profile and communications

**Profile**
- Display name, bio, private/public switch.
  - p1: `Profile.php:53-70`.
  - p2: `user/api/profile.php:3-4`.
- Per-field publish, photos, framing: `member-profile.php:3-10` (both passes). [p2 only]: avatar and cover photos are named specifically.

**Ways to sign in**
- **Passes differ on scope.**
  - Pass1: *disconnect* a linked Google, Apple, Microsoft or Facebook sign-in (`sign-ins.php:2`).
  - Pass2: *connect or disconnect* (`SignInLinks.php:16`; `sign-ins.php:2-3`).

**Tool consent and verification status**
- Citations: `consent.php:3-4, :17` (both passes).

**Messages with businesses** [p2 only]
- Inbox and threads, send, mute message types, report a mislabelled message (`business-messages.php:4-9`).

**Moments**
- Post, react, comment, report, Keep; posts last 3 or 7 days unless kept (`posts.php:41`).
  - p1: `:13-20`.
  - p2: `:3-20`.
- [p1 only]: edit, see viewers, set a default audience.
- [p2 only]: choose the audience and window per post, and preview as others see you.
- [p2 only]: media is served only to people who may see the post (`post-media.php:22-23`).

**Pulse (the inbox)**
- Row actions: reply, forward, report, block, mute, add to Address Book, Keep.
  - p1: `pulse-act.php:6-19`.
  - p2: `:8-19`.
- Own marks: follow-up, importance, tags, pin, folder, archive.
  - p1: `marks.php:2-8`.
  - p2: `:2-3`. [p2 only]: marks are "never shown to the sender".
- Saved views, up to 30: `PulseViews.php:10` (both passes).
- [p2 only]: messages open in place (`pulse-msg.php:6-7`).

**Bell**
- Counts across sources.
  - p1 lists the sources: messages, requests, business and guest items, dispatcher items, "needs you" (`bell.php:33-55`).
  - p2: `bell.php:4`.

**Communications**
- Each kind × channel (Pulse, email, push), quiet hours, weekly digest.
  - p1: `comms.php:7-9`.
  - p2: `:5-9`.
- Notice switches.
  - p1: `notices.php:4-5`.
  - p2: `:3-5`. [p2 only]: pass2 calls these "older" switches and includes the delivery choice.
- Push devices with a test send.
  - p1: `push.php:6-9`.
  - p2: `:4-9`.
- Connected mailboxes in Pulse: read, reply, make a case (`mailboxes.php:4-8`, both passes). [p2 only]: connect.

**Referrals** [p2 only]
- Referrals to confirm, confirmed and sent (`referrals.php:13-45`).
- Pass1 does not cover this in (a). It only lists `referrals` among live APIs in (d) and `entity_referrals` among tables.

**QR codes**
- Create, manage, see stats: `qr.php:3-8` (both passes).
- [p2 only]: also called "GO Codes"; switch on/off; free trial "until billing opens".

**Close or restore account**
- Close with 30 days to restore, or delete now.
  - p1: `delete-account.php:30-67`.
  - p2: `:30-41`.
- Restore, keep closed, or delete now: `restore-account.php:36-41` (both passes).
- [p2 only]: restore works from the email link.

**Account notice** (`/user/notice.php`)
- Warning → Limited → Revoked; confirm the steps or ask for a review.
  - p1: `:83-100`.
  - p2: `:84-99`.

**Orders** (`/user/orders.php`)
- Orders and released artwork, with "Ask to reorder".
- **Passes differ on how orders reach the member.**
  - Pass1: orders "Traversence prepared for you" (`:64-95`).
  - Pass2: orders "sent to your confirmed email" (`orders.php:2-4,23-37,66`).

**Domains** (`/user/domains/`)
- **Passes differ on how much is visible.**
  - Pass1: domains and wallet, rendered by `tv_domains_view` (`:22`). The view itself is **not shown**.
  - Pass2: l