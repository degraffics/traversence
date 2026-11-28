# Platform atlas: engage (merged)

*research/platform-atlas/engage.md. **Draft for human review.** This merges two independent passes, P1 and P2.*

**Citation key**

- **[both]**: both passes support the claim.
- **[P1 only] / [P2 only]**: only one pass says it.
- **Passes differ**: the passes disagree.
- Citations are given as each pass wrote them. P2 uses mirror paths: `sync-app/lib/` for `app/lib/`, and `sync-storage/qr/` for `website/qr/`. Below, these are shortened to the file name.

**Truncation of the passes themselves**

- P2 stops partway through (d) and has no (e).
- P1 stops partway through (e).
- So (d)'s "coming" list and all of (e) come from P1 only.

**Scope [both]**

The Engage bar covers views, likes, comments and replies, Share, QR and Report (P2: 0058 §20). Around it, the area also covers:

- comment notices in Pulse (P2: 0172 §2.3);
- GO Codes, QR codes and trvgo.us short links (P2: 0094);
- the Gallery's social side (P2: 0126);
- group growth (P2: 0136 §5).

[P1 only] Pulse saved views are explicitly in scope.

**Partly shown [both]**

- `QrIssue.php` is cut off at line 553.
- ADR 0058 is truncated. P1 places the cut after §24.
- ADR 0172 is truncated. P1 places the cut after §2.8.

**Not shown [both]**

- `/api/engage.php`, `app/ui/engage-ui.php`, `js/engage.js`;
- `/api/comment-act.php`;
- `LikeGuard`, `Guest`, `Notify`, `CommsBody`;
- `/user/api/qr.php`, `/api/qr.php`;
- the trvgo.us router;
- Gallery code and `GroupGrowth`.

Further items that one pass lists as not shown:

- [P1 only] `Reports`, `ShortCodes`, `QuickBooksPay`.
- [P2 only] `tv_engage_tools()` and `Notify::KINDS`.
- P2 also lists `ShortCodes.php` as not shown, but in its own (b) section.

---

## (a) What each person can do, page by page

### Resident or traveler, signed out

**Content pages [both]**

- Pages: `/listing/view.php?id=`, `/place/recreation.php?id=`, `/place/landmark.php?id=`, `/experiences/view.php?id=`, `/hub/…`, `/guide/…`, `/journeys/suggested.php`, `/guide/personal.php`.
  - P1 lists `/guide/how.php` and `/guide/resources.php` by name, and cites page paths at `Engage.php:241-242`.
  - P2 cites `pages.md` §1.
- Visitors see views, likes and comment counts, and can read comments.
- Views show even at 0 (0058 §20 note, 2026-11-19) [both].
- Share and QR are available [both].
  - [P2 only] Share uses the phone's share sheet or a copied trvgo.us link.
- [P2 only] The ⋯ menu shows "Add to a trip" greyed as "coming". P1 records this in (c) and (d).

**Guest likes [both]** (`Engage.php:127-154`)

- Once per item per visit.
- A per-connection daily cap per item. P1 cites `:143-147`.
- A prompt to join after a set number of likes, then a hard cap per visit. P1 cites `:141-142`.
- The `Guest::*` values are not shown [both].

**`/qr/`: make a GO Code with no account [both]**

- Citations: P1 `qr/index.php:65-74` (colours, round dots, logo); P2 `qr/index.php:28-88` (type, details, design, live preview).
- [P1 only] Eight types: Link, Phone, Email, Wi-Fi, Contact card, Event, Text, Text message (`Qr.php:20-21`). P2 gives "8 types" at the same lines in (b).
- [P1 only] Price is $4.95 each (`QrIssue.php:27`).
- The preview can't be scanned until the code is bought [both]: P1 cites `Qr.php:122-127` here; P2 gives the same lines in (b).

**`/qr/cart.php`: guest checkout with billing address [both]**

- Citations: P1 `cart.php:33-43`; P2 `qr/cart.php:33-45`, which adds tax.
- [P1 only] Promo code (`:27`).
- [P1 only] Card taken on the page via Intuit (`cart.php:4-5`).
- [P1 only] "Buying opens soon" shows while payments are closed (`:45`, `:128`; `QrIssue.php:315`).

**`/qr/manage.php?t=` [both]**

- Opens codes and orders from the receipt link on any device (`manage.php:20`). P1 adds `QrIssue::byToken` `:436-443`.
- [P1 only] Download SVG or PNG.
- [P1 only] An offer of an account, so the codes move into it (`manage.php:143-151`).
- "Try it" without counting a scan (`manage.php:79`) [both]. P2 records this in (b).

**Gallery**

- Guests can add photos to an event album with no account via `/gallery/drop.php` (0126 Phase 2) [both]. Code not shown.
- [P2 only] This works only when the owner allows it.
- [P2 only] Guests can view an unlisted album by its link.

**Groups**

- Joining needs an account (0136 §5c) [both].
- [P2 only] Guests can see a group's public page and pinned welcome post (0136 §5).

### Member, signed in

**Like [both]**

- Goes through `/api/engage.php` (not shown) and `LikeGuard` (not shown).
- A "100 a day" limit is described in a code comment. P1 cites `Engage.php:67-69`; P2 cites `:67`.
- [P1 only] Like or unlike is at `:96-111`.
- [P1 only] A 428 human check is described at `:67-69`.

**Comment or reply [both]**

- 18+, up to 2,000 characters, a content check, and 30 an hour.
  - P1 cites: `:255` (age), `:256` (length), `:258` (content check), `:266-268` (rate).
  - P2 cites `Engage.php:250-270`.
- Replies go one level deep. P1 cites `:263`; P2 cites `:260-265`.
  - [P2 only] A reply to a reply joins the top comment.
- **Passes differ on Standing.**
  - P1 says comments are "not limited by Standing" (`:252`).
  - P2 says `comment()` runs `Standing::guard` at `:252`, so "limited accounts can't post" (P2 (b) and (c)).
  - Same line, opposite readings. This needs checking against the code.
- Members can remove their own comment (`Engage.php:313-321`) [both].
- Members can report a comment [both].

**Pulse**

- `comment.reply` notice when someone replies to you [both].
  - [P1 only] Cites `Engage.php:277-285`.
- `content.comment` notice when someone comments on your experience [both].
  - [P1 only] Cites `:296-308`.
- Reply and Report are offered inside the notice [both].
  - Citations: P1 `CommentNotices.php:71-80`, `:85-93`; P2 `:27-82`.
  - [P2 only] The thread is "read live".
- P1 notes that the Pulse route isn't in pages.md. Per 0172, the member Pulse is on `/user/dashboard.php` and the profile [P1 only].
- Up to 30 saved, named, owner-only Pulse views (`PulseViews.php:10`, `:59-77`) [both].
  - Deleting a view returns it, so Undo can re-save it (`:87-93`) [both]. P2 records this in (b).

**GO Codes**

- 5 free plain or basic codes once the email is confirmed (`QrIssue.php:26`, `:109-118`) [both].
  - P1 also cites `:121-125`.
  - [P2 only] "Basic" means link, phone, email and Wi-Fi.
  - [P2 only] Other codes cost $4.95 each.
- Dynamic codes need the QR plan at $14.95 a month, shown as a trial while billing is closed (`manage.php:192-197`) [both].
  - [P1 only] The plan is also $119.40 a year.
  - [P1 only] Also cites `qr/index.php:95`.
- Repoint, switch off, and 30-day scan bars by day and device [both].
  - P1 places this under Member (`manage.php:201-222`).
  - P2 places it under Business (`manage.php:171-224`).
- Guest codes move into the account at sign-in [both].
  - P2 cites this in (a): `QrIssue.php:540`; `manage.php:21`.
  - P1 cites `claim()` at `:540+` in (b).

**Gallery** (`/gallery/`, `/gallery/album.php`) [both]

- Albums, invites, and likes and comments on photos (0126). Code not shown.
- [P1 only] Albums can be public, unlisted or private, with Portfolio and Brand modes.

**Groups** [both]

- Invites and invite codes. P1 cites 0136 "Built"; P2 cites §5a and §5c.
- [P1 only] Group tags and chapters.

### Business (team on a listing)

**Comment notices**

- A comment or reply on the listing becomes a `biz.comment` notice to the team (`Engage.php:286-295`) [both].
  - [P2 only] The notice goes to everyone except the writer.
- Reply, Hide or Show, and Report are offered in the notice [both].
  - Citations: P1 `CommentNotices.php:63-80`; P2 `:76-78`.
- Hide and Show are for the Owner and Managers only [both].
  - [P2 only] Anyone on the team can Reply.
- A team member's comment or reply is labelled with the business name (`Engage.php:194-199`) [both].
- A comment hidden by staff after reports can't be un-hidden by the business [both].
  - Citations: P1 `:370-371`; P2 `:365-373`.

**Likes**

- Listings have no Like (0058 §20) [both].
- The code still accepts `listing` as a target (`Engage.php:30`, `:87`). Whether the UI hides it is not shown [both].

**Dynamic codes**

- Available on Core and up [both]. Citations: P1 `qr/index.php:95`; P2 0094.
- [P1 only] When the plan ends, codes fall back to the listing page (0094).

**Listing page and gallery**

- Save contact taps count in Business Insights (0094) [both].
- The listing page shows **Albums** (0126 Phase 3) [both].
- There is a business gallery at `/gallery/?b=` [both].
  - [P1 only] It counts toward plan storage.

**Groups**

- [P1 only] One group per business, with chapters and selling to members (0136 §2, "Selling in groups").
- [P1 only] Bulk invites are not built.
- [P2 only] Group growth counts: joins by source and page views, counts only (0136, Built).

---

## (b) How it works underneath

### `Engage.php`

**Kinds** (`:13`) [both]

- `photo` and `mp` are accepted only through Gallery and MemberPosts (`:30-31`) [both].
- [P2 only] The kind list is: listing, rec, exp, place, lm, guide, how, sj, page, pg.
- [P2 only] `rec` and `place` are checked by shape only (`:32-33`).
- [P2 only] The file header (`:2-5`) is stale: it lists four kinds and gives the old path `api/lib/`.

**Views**

- `PlatformCounts::total(…'view',3650,…)` (`:41-47`) [both].
- Counts only, never who [both]. P1 cites 0058 §13.
- [P2 only] Count keys are at `:15`.

**Likes**

- Stored in `content_likes`; test accounts are excluded [both].
  - Citations: P1 `:53`; P2 `:52-53`.
- Guest likes are added from `content_like_tally` (`:114-118`) [both].
- `exists()` refuses likes on missing items (`:77-94`) [both].
  - [P1 only] It also refuses hidden items.
- [P2 only] `toggleLike` runs the `LikeGuard` checks, except for gallery photos (`:96-111`).
- [P2 only] Guest likes go through a bot check and a count against `like_events.ip_key` (`:132-147`).
- Guest connection records are purged after 30 days (`:157-161`) [both].

**Comments**

- Stored in `content_comments`. The code checks at run time whether the newer columns exist [both].
  - Citations: P1 `:164-177`; P2 `:164-169`, in (d).
- [P1 only] A reply whose parent is hidden isn't listed (`:190-191`, `:206-207`).

**Notices**

- Sent via `Notify::send` with kinds `comment.reply`, `biz.comment` and `content.comment`; blocked pairs are skipped (`:277-308`) [both].
- [P2 only] A failed notice is logged and does not stop the comment.

**Moderation**

- Staff with the `journeys` permission can moderate content [both].
  - P1: `canTidy` (`:376-381`).
  - P2: `removeComment` (`:313-321`).
- `canHide` allows staff, or a listing's Owner or Manager (`:365-373`) [both].
- **Passes differ on `teamRole` scope.**
  - P1: Team, then Roles (`:334-347`).
  - P2: Team, `listing_access`, the owner, and Ro