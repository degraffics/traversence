# Platform atlas: Engage (`research/platform-atlas/engage.md`)

*Draft for human review. Built only from the attached documents and code. Paths are given as the sync mirror shows them: `sync-app/lib/` for `app/lib/` and `sync-storage/qr/` for `website/qr/`.*

**Partly shown:**
- `QrIssue.php` is cut off at line 553.
- ADR 0058 and ADR 0172 are truncated.
- These are not shown at all: `/api/engage.php`, `app/ui/engage-ui.php`, `js/engage.js`, `LikeGuard.php`, `Guest.php`, `Notify.php`, `CommsBody.php`, `/api/comment-act.php`, `/user/api/qr.php`, `/api/qr.php`, the trvgo.us router, every Gallery file, and `GroupGrowth.php`.

**Scope.** "Engage" here means the content page's Engage bar and its parts: views, likes, comments, replies, Share/QR and Report (ADR 0058 §20). It also covers:
- how comments reach a business or a writer in Pulse (ADR 0172 §2.3);
- the GO Codes and short-link tools that Share and QR use (ADR 0094);
- the social layers built on the same tables: Gallery likes and comments (ADR 0126) and group growth (ADR 0136 §5).

---

## (a) What each person can do, page by page

### Resident or traveler, signed out
- **Any content page** (`/listing/view.php?id=`, `/place/recreation.php?id=`, `/place/landmark.php?id=`, `/experiences/view.php?id=`, `/hub/…`, `/guide/…`, `/journeys/suggested.php?id=`, `/guide/personal.php?id=`; `pages.md` §1):
  - See views, likes and comment counts. Views always show, 0 included (0058 §20, note of 2026-11-19).
  - Read comments.
  - Use Share (the phone's share sheet or a copied trvgo.us link) and the QR card.
  - Use the ⋯ menu. "Add to a trip" there is greyed as "coming".
- **Like as a guest.** Limits:
  - once per item per visit;
  - a cap per connection per item per day;
  - a prompt to join after a set number of likes;
  - a hard cap per visit (`Engage.php:127-154`).
  - The limit values (`Guest::*`) are not shown.
- **Make and buy GO Codes with no account:**
  - `/qr/` lets you pick a type, fill in details and design the look, with a live preview (`qr/index.php:28-88`);
  - `/qr/cart.php` checks out as a guest, with billing address and tax (`qr/cart.php:33-45`);
  - `/qr/manage.php?t=` opens codes and orders from the receipt link on any device (`qr/manage.php:20`).
- **Gallery** (ADR 0126):
  - view an unlisted album by its link;
  - add photos to an event album through `/gallery/drop.php`, no account, when the owner allows it.
- **Groups:** see a group's public page and pinned welcome post (0136 §5). Joining needs an account (0136 §5c).

### Member (signed in)
- **Comment** on a content page.
  - Must be 18+.
  - Up to 30 an hour, 2,000 characters, run through a content check (`Engage.php:250-270`).
  - **Reply one level deep.** A reply to a reply joins the top comment (`Engage.php:260-265`).
  - Remove your own comment (`Engage.php:313-321`).
  - Report any comment.
- **Like** on a member account. A daily cap is described as "100 a day" in a comment (`Engage.php:67`); `LikeGuard` itself is not shown.
- **In Pulse:**
  - Get a notice when someone replies to your comment (`comment.reply`).
  - Get a notice when someone comments on your experience (`content.comment`).
  - In that notice: Reply, see the thread "read live", and Report (`CommentNotices.php:27-82`).
  - Save up to 30 named Pulse filter views (`PulseViews.php:10,59-77`).
- **GO Codes:**
  - 5 free plain codes (link, phone, email, Wi-Fi), once the email is confirmed (`QrIssue.php:26,109-118`);
  - other codes are $4.95 each;
  - Dynamic GO Codes need the QR plan ($14.95 a month), shown as a free trial "while billing isn't open yet" (`qr/manage.php:192-197`);
  - codes made as a guest move into the account at sign-in (`QrIssue.php:540`; `manage.php:21`).
- **Gallery** (`/gallery/`, `/gallery/album.php`): albums, likes and comments on each photo, invites (ADR 0126 Phase 2).
- **Groups** (`/community/group.php?id=`): invite connections and use invite codes (0136 §5a, §5c, built).

### Business (owner, manager or team)
- **Comment notices.** A comment or reply on the listing arrives as a `biz.comment` notice in the business's Pulse. It goes to the team, except the person who wrote it (`Engage.php:286-295`).
  - Inside the notice, anyone on the team can Reply. The reply posts as the person, "named with" the business (`CommentNotices.php:76-78`; `Engage.php:194-199`).
  - **Hide and Show again** are for the Owner and Managers only. A comment our team hid after reports stays with our team (`Engage.php:365-373`).
- **Listings have no Like.** The Link button is how people keep a business (0058 §20). *The code does accept `listing` as a like target (`Engage.php:30,87`); whether the UI offers it is not shown.*
- **Dynamic GO Codes on Core and up.** They can be re-pointed, switched off and counted by day and device (`qr/manage.php:171-224`; ADR 0094).
- **Save contact** taps are counted in Business Insights (ADR 0094).
- **Business gallery** at `gallery/?b=<listing>`, with "Albums" shown on the listing page (0126 Phase 3).
- **Group growth counts:** joins by source and page views, counts only (0136, Built).

---

## (b) How it works underneath

**Engagement core: `sync-app/lib/Engage.php`**
- **Kinds** (`:13`): listing, rec, exp, place, lm, guide, how, sj, page, pg.
  - Extra kinds `photo` and `mp` are allowed only through Gallery and MemberPosts, which check who may see them (`:30-31`).
  - `rec` and `place` are checked by shape only (`:32-33`).
  - *The header (`:2-5`) still lists only four kinds and gives the old path `api/lib/`.*
- **Views** come from `PlatformCounts::total(…,'view',3650,key)`, which counts visits and never who (`:41-47`). The count keys are at `:15`.
- **Likes** are stored in `content_likes` (`:49-56`).
  - Test accounts are left out (`:52-53`).
  - Guest likes are added from `content_like_tally` (`:114-118`).
  - `exists()` refuses likes on items that are not there (`:77-94`).
  - `toggleLike` runs `LikeGuard` checks, but not for gallery photos (`:96-111`).
- **Guest likes** go through a bot check and a per-connection count against `like_events.ip_key` (`:132-147`).
  - Those connection records are purged after 30 days (`:157-161`, citing 0009).
- **Comments** are stored in `content_comments`.
  - `comment()` checks, in order: `Standing::guard`, `AgeGate::require`, `strip_tags` and a 2,000-character cap, `ListingSafety::contentProblem`, and 30 an hour (`:252-268`).
  - Then it notifies through `Notify::send` with the kinds `comment.reply`, `biz.comment` and `content.comment`. A block between the people stops the notice (`:277-308`).
  - A notification failure is logged and does not stop the comment.
- **Moderation:**
  - `removeComment` lets the writer or staff with the `journeys` permission delete (`:313-321`). *Content staff are tied to the journeys permission.*
  - `setCommentHidden` records `hidden_by` and `hidden_at` (`:324-328`).
  - `canHide` (`:365-373`) and `teamRole`/`teamIds` combine Team, `listing_access`, the owner and Roles (`:334-358`).

**Pulse bodies: `sync-app/lib/CommentNotices.php`**
- Draws the comment, its parent, the replies since, and its state read live (`:27-60`).
- Offers Hide/Show, Reply (with the 18+ box when needed) and Report through `Reports::REASONS` and `Reports::file` (`:62-93`, `:136-139`).
- Actions post to `/api/comment-act.php` (`:18`). That endpoint is not shown.

**Pulse saved views: `sync-app/lib/PulseViews.php`**
- Owner-only rows in `pulse_views`.
- Filters are cleaned to five keys (`:11,37-48`).
- A JSON cap of 8,000 characters (`:64`).
- Delete returns the view so it can be undone (`:87-93`).
- If the table is missing, it says "Saved views come with the next site update" (`:61`).

**QR drawing: `sync-app/lib/Qr.php`**
- Drawn on our own server with chillerlan/php-qrcode (`:2-9`).
- 8 types (`:20-21`); `BASIC` is link, phone, email and Wi-Fi (`:23`).
- Content builders for links and events route links through `ShortCodes::target` (`:31-87`).
- Colours must contrast at least 4:1, dark on light (`:105-110`).
- Previews have no corner squares, so they cannot be scanned (`:122-127`).
- The print SVG is one compound path with an inch size (`:160-199`).
- An uploaded logo is redrawn at 256 px, so nothing else in the file survives (`:248-264`).

**GO Codes: `sync-app/lib/QrIssue.php`**
- A guest is identified by the cookie `tv_go`, stored only as SHA-256 (`:57-68`).
- Every code except Wi-Fi routes through trvgo.us so scans are counted (`:13-16,31`).
- `create` issues a free code or puts it in the cart; the cart holds up to 25 (`:186-215`).
- A buyer can fix a code only while it is in the cart; after that, our team fixes it (`:263-270`).
- `checkout`:
  - creates a QuickBooks invoice, with tax worked out from the address (`:311-363`);
  - on failure, puts everything back in the cart and releases the promo (`:347-354`).
- `payCard` takes a card token, holds a DB lock and uses an idempotency key (`:371-414`).
- `markPaid` issues the short codes and emails the Manage link (`:502-534`).
- Suspended or limited accounts are refused (`:97-106`).
- **Pages:**
  - `qr/cart.php`: a card-page CSP with Intuit only (`:13`); plans and domains share the cart and the wallet card (`:54-109`);
  - `qr/manage.php`: `Referrer-Policy: no-referrer` (`:15`); "Try it" with `?b=1` is not counted as a scan (`:79`); scan charts by day and phone type, "never who" (`:212-221`).

**Not shown:** the `Engage` UI and `tv_engage_tools()`, `Notify::KINDS` and its delivery, the Gallery and GroupGrowth libraries, and `ShortCodes.php`.

---

## (c) Decisions

| # | What it rules here |
|---|---|
| **0058 §20** (+ note 2026-11-19) | The content page standard: header, Link, Engage bar (views never who, likes never who, comments, Share, Report), comments at the end; 18+, 30 an hour; listings have no Like; ⋯ tools; Report moves into ⋯ on some pages; "Add to a trip" greyed. §1: no star ratings; reviews live on the listing. §13: counts never who. |
| **0058 §4** | Accounts are 18+ and confirmed before commenting (`AgeGate`). |
| **0172** | Pulse is the inbox. §2.3: a listing comment becomes a notice in the business's Pulse, with Reply, Hide and Report inside it; replies tell the commenter. Levels: comments are "General". |
| **0094** | trvgo.us short links and QR codes; counts, never who; no ad pixels; 302 redirects; Share uses short links; Save contact counted. |
| **0095 / 0096 / 0098** | Cited in code for GO Codes pricing, the cart and checkout, and routing through TRVGO (`QrIssue.php:2,13`; `qr/index.php:2`). **The texts are not shown.** |
| **0126** | Gallery: likes and comments on each photo through `content_likes`/`content_comments` kind `photo`, only via `gallery/api.php`; guest uploads; QR for albums. |
| **0136 §5** | Groups grow by indirect tools only: no bought reach, no exporting lists; invites once per person per group; join counts only. |
| **0009 / 0079 / 0145 §3** | Cited in code: guest-like records purged at 30 days (`Engage.php:113,156`); limited accounts can't post (`:252`); test accounts' likes not counted (`:52`). Texts not shown. |

**Conflicts to resolve**
1. ADR 0094 says static codes are free for anyone and "nothing typed is kept". The code charges $4.95 for every GO Code, gives 5 free with an account, stores what each code holds, and routes all but Wi-Fi through trvgo.us (`QrIssue.php:7-16,200-203`; `qr/index.php:22-25`). 0094 appears to be superseded by 0095, 0096 and 0098, which are not shown.
2. `pages.md` lists `/api/comment-act.php` as "Coming (release comms-all)". The 0172 status note says steps 3–7 are live (#135), and `CommentNotices.php:18` depends on that endpoint.
3. ADR 0126 says `api/engage.php` refuses kind `photo`. `Engage::target` accepts it (`:30`). The refusal would have to be in the endpoint, which is not shown.

---

## (d) Live vs coming

**Live** (`pages.md`, or the code is present):
- `/api/engage.php`
- `/qr/`, `/qr/cart.php`, `/qr/manage.php`
- the Gallery pages
- `/community/`, `/community/group.php`
- `/user/post.php` (marked "Built")
- one level of comment replies, with the column checked at run time (`Engage.php:164-169`)
- `biz.comment`, `comment.reply` and `content.comment` notices