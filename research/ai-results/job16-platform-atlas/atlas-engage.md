# Platform atlas: engage

*research/platform-atlas/engage.md. **Draft for human review.***

**Scope.** "Engage" here means the shared engagement layer and what hangs off it:

- the Engage bar: views, likes, comments, Share, QR, Report;
- comment notices in Pulse, and Pulse saved views;
- GO Codes, QR codes and trvgo.us short links;
- the Gallery's social side;
- community-group growth.

**What was attached.** The code shown is `Engage.php`, `CommentNotices.php`, `PulseViews.php`, `Qr.php`, `QrIssue.php` (cut off after line 553), `/qr/index.php`, `/qr/cart.php` and `/qr/manage.php`.

**Partly shown.** The docs 0058 (after §24) and 0172 (after §2.8) are truncated.

**Not shown.** These files are referenced but were not attached:

- `/api/engage.php`, `app/ui/engage-ui.php`, `js/engage.js`;
- `/api/comment-act.php`;
- `LikeGuard`, `Guest`, `Notify`, `CommsBody`, `Reports`, `ShortCodes`, `Gallery`, `GroupGrowth`;
- `QuickBooksPay`, `/user/api/qr.php`, `/api/qr.php`;
- the trvgo.us router.

---

## (a) What each person can do, page by page

### Resident or traveler, signed out

| Page / route | What they can do |
|---|---|
| Content pages: `/listing/view.php?id=`, `/place/recreation.php?id=`, `/place/landmark.php?id=`, `/experiences/view.php?id=`, `/hub/?…`, `/guide/how.php`, `/guide/resources.php`, `/journeys/suggested.php`, `/guide/personal.php` | • See views, likes and comment count; read comments; Share; QR (0058 §20; kinds at `Engage.php:13`, page paths at `Engage.php:241-242`).<br>• Views show even at 0 (0058 §20 note, 2026-11-19).<br>• Like as a guest, with limits (`Engage.php:127-154`):<br>&nbsp;&nbsp;– once per item per visit;<br>&nbsp;&nbsp;– a per-connection daily cap per item (`:143-147`);<br>&nbsp;&nbsp;– an account prompt after `Guest::LIKES_PER_VISIT`, a hard stop at `Guest::MAX_PER_VISIT` (`:141-142`; the values are not shown). |
| `/qr/` | • Make a GO Code with no account: Link, Phone, Email, Wi-Fi, Contact card, Event, Text, Text message (`Qr.php:20-21`).<br>• Choose colours, round dots and a logo (`qr/index.php:65-74`).<br>• The preview is unscannable until bought (`Qr.php:122-127`).<br>• Price: $4.95 each (`QrIssue.php:27`). |
| `/qr/cart.php` | • Guest checkout with name, email and billing address (`cart.php:33-43`).<br>• Promo code (`:27`).<br>• Card is taken on the page via Intuit (`cart.php:4-5`).<br>• "Buying opens soon" shows while payments are closed (`:45`, `:128`; `QrIssue.php:315`). |
| `/qr/manage.php?t=` | • Open an order by its receipt link on any device (`manage.php:20`; `QrIssue::byToken` `:436-443`).<br>• Download SVG or PNG; "Try it" without counting a scan (`manage.php:79`).<br>• Offered an account so the codes move into it (`manage.php:143-151`). |
| `/gallery/drop.php?g=` | Add photos to an event album with no account (0126 Phase 2). Code not shown. |
| `/join.php?i=`, `/community/group.php?id=` | Joining needs an account (0136 §5c). Code not shown. |

### Member, signed in

| Page / route | What they can do |
|---|---|
| Content pages, via `/api/engage.php` (endpoint not shown) | • **Like or unlike** (`Engage.php:96-111`), behind `LikeGuard`. A 100-a-day limit and a 428 human check are described at `:67-69`.<br>• **Comment or reply**:<br>&nbsp;&nbsp;– 18+ (`AgeGate::require`, `:255`);<br>&nbsp;&nbsp;– not limited by Standing (`:252`);<br>&nbsp;&nbsp;– up to 2,000 characters (`:256`);<br>&nbsp;&nbsp;– content check (`:258`);<br>&nbsp;&nbsp;– 30 an hour (`:266-268`);<br>&nbsp;&nbsp;– one level of replies (`:263`).<br>• **Remove** their own comment (`:313-321`).<br>• **Report** a comment. |
| Pulse (route not shown in pages.md; the member Pulse is on `/user/dashboard.php` / profile per 0172) | • Get a `comment.reply` notice when someone replies to them (`Engage.php:277-285`).<br>• Get `content.comment` when someone comments on their experience (`:296-308`).<br>• In each notice: reply, or "Report it" (`CommentNotices.php:71-80`, `:85-93`).<br>• Saved views: up to 30, by name, owner-only (`PulseViews.php:10`, `:59-77`). Deleting a view returns it so Undo can re-save it (`:87-93`). |
| `/qr/`, `/qr/manage.php` | • 5 free plain basic codes; needs a confirmed email (`QrIssue.php:26`, `:109-118`, `:121-125`).<br>• Dynamic GO Codes on the QR plan, $14.95 a month or $119.40 a year, trial while billing is closed (`qr/index.php:95`; `manage.php:192-197`).<br>• Repoint, switch off, and see 30-day scan bars by day and device (`manage.php:201-222`). |
| `/gallery/`, `/gallery/album.php` | Albums (public, unlisted or private), invites, likes and comments on photos, Portfolio and Brand modes (0126). Code not shown. |
| `/community/` | Group tags, chapters, invites and invite codes (0136 "Built"). Code not shown. |

### Business (team on a listing)

| Where | What they can do |
|---|---|
| Pulse, listing context | • A comment on the listing, and replies too, becomes a `biz.comment` notice to the team (`Engage.php:286-295`).<br>• In the notice: Reply, Hide or Show (Owner and Managers), Report (`CommentNotices.php:63-80`).<br>• A team member's comment is labelled with the business name (`Engage.php:194-199`).<br>• A comment hidden by staff after reports can't be un-hidden by the business (`Engage.php:370-371`). |
| `/listing/view.php?id=` | • Save contact is counted as `save` in Business Insights (0094).<br>• Albums under **Albums** (0126 Phase 3).<br>• Code for both not shown. |
| `/gallery/?b=` | Business gallery, counted toward plan storage (0126). Code not shown. |
| Dynamic codes | On Core and up (`qr/index.php:95`). If the plan ends, codes fall back to the listing page (0094). |
| Group | One group per business, with chapters and selling to members (0136 §2, "Selling in groups"). Bulk invites are not built (0136 "Not yet"). |

---

## (b) How it works underneath

### Engage core: `app/lib/Engage.php`

**Targets.**

- `KINDS` (`:13`) and `target()` (`:27-38`) validate what can be engaged with.
- `photo` and `mp` (member posts) are accepted only through Gallery and MemberPosts respectively (`:30-31`; 0126 says `/api/engage.php` refuses `photo`).

**Views.**

- `views()` reads `PlatformCounts::total(... 'view', 3650, COUNT_KEY…)['visits']` (`:41-47`).
- These are counts only, never who (0058 §13).

**Likes.**

- Stored in `content_likes`. Test accounts are excluded (`:53`), and guest likes are added from `content_like_tally` (`:114-118`).
- `exists()` refuses likes for items that are gone or hidden (`:77-94`).
- Guest connection records are purged after 30 days (`:157-161`).

**Comments.**

- Stored in `content_comments`, with `parent_id`, `hidden_by` and `hidden_at`. The code detects whether those columns exist yet (`:164-177`).
- A reply whose parent is hidden isn't listed (`:190-191`, `:206-207`).

**Who is told.**

- Notices go out through `Notify::send` with kinds `comment.reply`, `biz.comment` and `content.comment` (`:277-308`).
- Blocked pairs are skipped (`:277`, `:301`).

**Who may hide or remove.**

- `teamRole()` checks Team, then Roles (`:334-347`).
- `canHide()` allows staff, or a listing's Owner or Manager (`:365-373`).
- `canTidy()` means staff with `Journeys::staffCan(...,'journeys')` (`:376-381`).

### Comment messages in Pulse: `app/lib/CommentNotices.php`

- `pulseBody()` draws, live:
  - the comment;
  - the comment it answers;
  - "N replies since";
  - its state: hidden by whom, or shown with a reply count (`:27-82`).
- `act()` handles `reply`, `hide`, `show` and `report`, then redraws the body (`:117-146`).
- Posts go to `/api/comment-act.php` (`:18`).

### Pulse saved views: `app/lib/PulseViews.php`

- Table `pulse_views`. Filter keys are list, show, from, sev and group (`:11`).
- `clean()` drops anything unknown (`:37-48`).
- A view's filters are capped at 8,000 characters of JSON (`:64`).

### QR drawing: `app/lib/Qr.php`

- Drawn on our own server with chillerlan/php-qrcode (`:2-9`).
- `content()` builds each payload type, including iCal events (`:65-84`).
- `checkColors()` requires a dark-on-light code with contrast of at least 4 (`:105-110`).
- `svg()` merges squares into a compound path for print (`:160-199`; `outline()` `:205-245`).
- A logo is re-drawn so nothing else in the file survives (`:248-264`).

### GO Codes: `app/lib/QrIssue.php`

**Ownership.**

- A member owns codes by user id. A guest owns them by a SHA-256 of a 400-day `tv_go` cookie (`:57-68`).
- `claim()` moves a guest's codes and orders into an account (`:540+`, truncated).

**Making and fixing.**

- `create()` issues a code free if eligible; otherwise it goes to the cart (`:186-215`). The cart holds at most 25 (`:28`, `:198`).
- `fix()` works only while the code is in the cart (`:263-270`). After that, staff correct it (0098, not attached).

**Paying.**

- `checkout()` creates the order, its lines and a QuickBooks invoice. If the invoice fails, everything rolls back (`:311-363`).
- `payCard()` uses a `GET_LOCK` and an idempotency key, and checks whether the invoice was already paid on QuickBooks' own page (`:371-414`).
- `markPaid()` issues trvgo.us short codes, records the ledger, and sends the receipt email (`:502-534`).

### Pages

- `/qr/manage.php` sends `Referrer-Policy: no-referrer` (`:15`) so the order link isn't leaked.
- The cart also carries plans, storage packs and domains (`cart.php:54-109`).

### Not shown

Tables or migrations for `content_comments`, `content_likes`, `qr_issued` and `short_codes`. They are named only in docs: 0058 §20 migration `2026-10-14_engage.sql`; 0094 `2026-11-27_short_codes.sql`.

---

## (c) Decisions

| ADR | What it rules (as attached) |
|---|---|
| **0058** (§9, §13, §20–§22) | • Content-page standard: header, Link, Engage bar, comments at the end.<br>• No Like on listings ("the Link button is how you keep a business").<br>• No star ratings; comments are the reviews.<br>• Counts only, never who.<br>• The ⋯ tools menu, and "Add to a trip" greyed as "coming".<br>• Accounts are 18+ (§4). |
| **0172** (§2.3, Pulse) | • Pulse is the inbox; one message per thing.<br>• A comment on a listing is a notice to the business, with reply, hide and report inside it.<br>• Six levels; comments are General. |
| **0094** | • trvgo.us short links and on-server QR.<br>• Scans counted by day and device only, with no session or cookie.<br>• Dynamic codes for the QR plan or Core+.<br>• Codes fall back, never break, when a plan ends. |
| **0126** | Gallery: viewer standard; public, unlisted or private albums; photo likes and comments only through `gallery/api.php`; guest uploads; Portfolio and Brand modes; business galleries. |
| **0136** | Group tags (5), chapters, places a group is about, search by topic and place, indirect growth tools, selling in groups, invites and codes. |
| Cited in code or docs, not attached | • 0009 (guest likes, 30-day purge)<br>• 0079 (Standing)<br>• 0095, 0096, 0098 (GO Codes pricing, cart, fixes)<br>• 0124 (owner folders)<br>• 0145 §3 (test accounts)<br>• 0153 (member posts)<br>• 0174 (Personal Guides)<br>• 0137 (group amendments)<br>• 0007 (no pay-for-rank) |

---

## (d) Live vs coming

### Live in the shown code

- Views, likes and guest likes, comments, one-level replies, hide and show.
- `biz.comment`, `comment.reply` and `content.comment` notices.
- Pulse saved views (they need the `pulse_views` table: `PulseViews.php:61`).
- GO Codes make, cart, guest checkout, card pay, and manage.
- Dynamic codes on a trial plan.

pages.md lists `/api/engage.php`, `/qr/*` and `/gallery/*` as Live.

### Live per docs only

- Gallery Phases 1–3 (0126).
- Group tags, chapters, places, search and growth (0136 "Built … Live").

### Coming, or not built

- No notice to the maker for comments on guides, journeys or places. Only experiences notify (`Engage.php:296` says guides and pages "have no maker to tell"). 0172 §2.3 marks guide and journey creators as G.
- Add to a trip (0058 §20 note).
- `#` tag pages (0058 §3).
- Activity stream and follower counts on profiles: decided in 0058 §1; build state not shown.
- Group flyers and badges, bulk business invites, chapter endings, "Groups near you" (0136 "Not yet").
- Designed $4.95 static code with logo upload, clients' own domains, device routing (0094 "Not now"). The current `/qr/` already offers logo upload, so the GO Codes ADRs presumably superseded this; not attached.

### Inconsistencies to resolve

1. **Comment action endpoint.** pages.md marks `/api/comment-act.php` "Coming (release comms-all)". But `CommentNotices.php:18` posts to it, and 0172's status says steps 3–7 are live as #135.
2. **Static codes.** 0094 says static codes are free for anyone. `QrIssue.php:7-9` and `qr/index.php:22-23` charge $4.95, with 5 free for an account.
3. **Free allowance wording.** `Qr.php:22` says "free within the month's allowance", while `QrIssue.php:7` says "FREE_TOTAL in all".
4. **Likes on listings.** 0058 §20 says listings have no Like. `Engage::target`/`exists` accept `listing` (`:30`, `:87`). Whether the UI hides it is not shown.

---

## (e) Charter promises, economics, gaps

The charter's guarantees are in "Why This Matters" (charter, *Why This Matters: The Counter-Reaction to Algorithmic Bloat*).

### Immersion and connection

*Charter, "The Mission & Purpose": "immersion and connection engine … connecting users to the heartbeat of every community"; "Connect Communities".*

How it's delivered:

- Comments sit on every content kind (`Engage.php:13`).
- Replies tell the writer (