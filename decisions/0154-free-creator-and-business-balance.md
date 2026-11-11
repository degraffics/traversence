# ADR 0154: Free, Creator and Business: Who Pays for What, and How Upgrades Are Offered

**Status:** Accepted (Jason, 2026-11-11, in conversation), earned storage included (§7, confirmed the same day). Built 2026-11-11 (see Built, below), except the video worker and the coming items it lists. Builds on
`decisions/0007` (plans sell tools, never placement), `0098` (the order system and products), `0108` (plans),
`0153` (Moments, Snaps and Keep), `commercial.md` §1.2 and master-aos §5. Updates `commercial.md` §1.2.

## Context

Jason, 2026-11-11: "We need a free account with robust enough support to grow engagement. We need influencers and
content creators to choose our platform for growing their audiences and revenue streams. We need a way for storage
and tools to be revenue generators without gating or capping the UI and UX. Businesses will drive much of the revenue
through subscriptions, storage and additional tools, so where we can offset user costs against business revenue we
can prioritize." "No income, no expense" (CLAUDE.md) holds: a cost is fine when revenue covers it, business revenue
included.

## Decision

### 1. Free: the whole social experience

Posting Moments and Snaps (0153), following, the feeds, all reactions, comments, viewer lists, Keep into the Gallery
and Channel, basic insights (views, followers, reactions) and a share link and QR code for each post are free, with no
visible cap. The only limits are the fair-use ones in 0153 §10, set where normal use never reaches them. Free kept
videos are the compressed 720p copy. Storage: 2 GB (master-aos §5A), plus **earned storage** (§7).

### 2. Creators: the Personal Pro plan, sold as tools

The existing personal plans carry it; no new tier:

| Plan | Price | Adds |
|---|---|---|
| Base | $0 | §1; video at 720p |
| Personal Plus | $4.95/mo | 25 GB, the AI tools, video at **1080p** |
| **Personal Pro (Creator)** | $9.95/mo | 50 GB, the AI tools, video at **4K** (originals kept, a 720p copy for feeds), recordings up to 10 minutes, uploads to 1 GB, detailed insights (where viewers came from, what kept them watching), scheduled posts, Channel layout and pinned playlists, a link-in-bio page, and the income tools below |

**Income tools (Pro):** fan memberships (members-only posts and Snaps, paid monthly to the creator), tips on posts and
videos, paid guides and journeys sold through the marketplace, and business collaborations and licensing (§3).
**Creators keep 100%** (Jason, 2026-11-11): no transaction or commission fee from Traversence on memberships, tips,
collaborations or licensing; only the card processor's own fee applies. We earn from the plan, matching the "zero
extraction" promise to businesses (commercial.md §0). Nothing in any plan buys reach (0007): feeds run newest
first for everyone.

### 3. Businesses: most of the revenue, and members' posts are their audience

Two business **add-ons** (not included in a plan):
- **Creator collaborations:** a business finds local creators, asks for a post or video, and pays them through our
  checkout. The post carries **"Paid partnership"** (FTC). The creator keeps the fee.
- **Licensing:** a business asks to use a member's kept photo or video on its listing (0058 §7's "Others may share it,
  with credit"); the member says yes, with or without a fee.

Business Moments count against the business's plan. "Featured in" on a listing comes from members tagging it (0132),
never from payment.

### 4. The community budget: business revenue covers free members

Free members' costs (Moments, Snaps, free Keeps, the video worker for their uploads) come from a **community budget of
3% of all revenue** (business and personal) the month before, **never less than $25 a month**, set in Admin → System
Configuration → Settings. The site counts its own spending from the uploads it records (0153 §10). At 80%, a Pulse case;
at 100%, free video Moments and Keeps wait until the 1st, photo and text carry on, and paid plans are unaffected.

Example: 500,000 members and 2,000 businesses averaging $60 a month is about $120,000 a month in business revenue, a
community budget of about $3,600, against Moments at about $375 in a busy month and about $1,500 if free members keep
200 MB each.

### 5. How upgrades are offered: one upgrade sheet

Every upgrade is offered **where someone reaches for more**, beside a free way to carry on, through one sheet
(`TvUpgrade.offer(feature, {anchor})`), never banners, pop-ups on page load or greyed-out rows:
1. **A small plan label on the control:** the quality switch reads "720p · 1080p Plus · 4K Pro"; past 2:00 the ring says
   "Pro records up to 10 minutes", with **Try Pro free for 7 days** and **Keep my 2 minutes**.
2. **At a real limit, a choice of fixes:** "Your Gallery is full. **Add 25 GB for $2.95** · **Upgrade to Plus** ·
   **Choose what to clear**."
3. **One-off purchases** where a plan is too much: keep one video in 4K ($0.99), a single month of Pro, a storage pack.

At most once a visit for the same thing; after paying you land back where you were, draft and recording kept;
payment through the existing checkout (QuickBooks Pay); a paid account never sees an offer for what it has (T06).

### 6. The Traversence Shop: one place for what we sell

The cart icon now opens the GO Codes cart (`/qr/cart.php`). It becomes **one cart for everything Traversence sells**,
and beside it a **Shop** page (`/shop/`) lists what's on offer, shown for who's looking (T06):
- **A member:** Plus, Pro, storage packs, one-off purchases.
- **A business:** its plan and upgrades, storage, email, text and push packs, the add-ons (§3), White Glove, design,
  video, print and signs, GO Codes (commercial.md §2).
- **A guest:** GO Codes, and what an account would add.

Items come from the order system's products (0098), so a new tool or service is a product, not a page. The upgrade
sheet (§5) adds to the same cart or pays in place. Named **Shop**, not Market, so it isn't confused with the members'
Marketplace or Market 360°.

### 7. Decided (Jason, 2026-11-11: "use the recommended earned storage, creators keep 100%")

- **Earned storage.** Jason proposed +100 GB per 500 followers and per friend who joins, up to 1 TB. At R2's price that
  is about $1.50 a month per 500 followers and $15 a month per free account at the limit (5,000 such accounts: about
  $75,000 a month), more than a Pro plan earns and twice Pro's storage. Recommended instead: **+1 GB per 500 followers
  and per friend who joins, up to 25 GB** (at most about $0.38 a month an account). **Decided: the recommended +1 GB,
  up to 25 GB.**
- **Earned storage, how it's counted (decided, as recommended):** no cap on followers; the storage earned stops at 25 GB
  (12,500 qualifying followers, fewer with friends who joined). A follower or friend counts only once they're a real,
  settled member: email confirmed and 18+ (Verified), the account at least 30 days old, active on at least 3 separate
  days, not blocked by the earner, not removed or suspended, and passed the sign-up check (Cloudflare Turnstile). A
  friend counts only through the earner's own invite link and only once. At most 5 GB earned a month. An account later
  removed as fake takes its earned space back: nothing is deleted, but new Keeps wait until the owner is under their
  space again. Earned space stays when followers leave.

## Consequences

- `commercial.md` §1.2 updated with the video quality and Pro's creator tools, and §2 with the two add-ons.
- `future-considerations.md`: creator earnings beyond §2's tools (revenue sharing on views, sponsorship marketplaces).
- The Shop and the one cart are a build of their own, after 0153's first zip.

**Note (2026-11-11, decisions/0152):** outside services' settings now live on their company's card in Outside services,
and caps and gates in System Configuration → Thresholds & gates. So the community budget's 3% and $25 floor (§4) are set in
Thresholds & gates (beside the AI free asks), and its spending shows on Cloudflare's card (and Railway's, for the video
worker), not in Settings.

## Built (`channel-shop-earned_20261111.zip`, built on the 20:09 UTC sync, 2026-11-11)

- **Member storage now counts** (§1): `Gallery::roomFor` checks a member's Gallery against their plan (2 / 25 / 50 GB),
  its packs and what they've earned (`Gallery::memberCap`), and throws `MemberStorageFull`, which the upload shows as the
  upgrade sheet. Nothing already stored is removed. Members' kept posts now count too (`MemberPosts::keepMedia`).
- **Personal Pro** (§2): videos up to 10 minutes and uploads to 1 GB (`MemberPosts::secsMax`, `fileMax`; businesses post at
  Pro's). 1080p and 4K wait for the video worker (0153 §8).
- **The upgrade sheet** (§5): `js/tv-upgrade.js` (`TvUpgrade.offer(feature, {anchor, listing})`), choices from
  `app/lib/Upgrades.php` through `/api/upgrade.php`. Shown when the Gallery is full (packs only on a paid plan, then
  Plus, Pro, and "Choose what to clear") and when a recording reaches 2:00 (Pro, beside "Keep my 2 minutes"). Once a
  visit per feature; never for what the account has. Choices open the Plans page in a new tab (with `?pick=` choosing
  the plan), so the draft and recording stay. Not yet: "Try Pro free for 7 days" (trials are staff or promo codes only
  today), the quality label on the control (with the video worker).
- **The Shop** (§6): `/shop/`, for a member, the business being acted as, or a guest, from `Billing::PLANS`, `ADDONS` and
  GO Codes; linked from the cart. Plans and packs pay in place on the Plans page; GO Codes stay in the cart, so the cart
  is not yet one cart for subscriptions. Listed as coming: the two business add-ons, email/text/push packs, a single
  video in 4K and a single month of Pro. Design, video, print and signs: a quote by email for now.
- **Earned storage** (§7): `app/lib/EarnedStorage.php`, migration `2027-02-28_earned_storage.sql`
  (`member_active_days`, `member_storage_earned`), the daily job `earned_storage_tick`, and the day each signed-in
  person uses the site (`app/ui/header.php`). Shown on the Plans page (`#earned`), counted again when it opens. Active
  days start counting from this upload, so the first followers and friends qualify 3 days in. Not yet: taking back space
  earned from an account later removed as fake (suspended accounts already don't count), and checking the sign-up
  check per person (every sign-up passes it when it's on).
- **Fixed the same day (`shop-cart-tabs` zip):** the Shop said "2 items" with an empty cart (it counted the cart record's
  two fields); it now counts the items, as the header's cart does. And rows that fold into **More ▾** (`js/tv-ask.js`)
  leave children marked `data-keep` in place: the profile tab row's + and layout switch never fold (Jason).

## Built: one cart, the free week of Pro, and video quality by plan (`video-cart-trial_20261111.zip`, 2026-11-11)

- **One cart (§6):** plans and storage packs go in the GO Codes cart (`cart_plans`, `app/lib/CartPlans.php`, actions
  `plan_add` / `plan_remove` / `plans_pay` in `/api/go-codes.php`), from the Shop's **Add to cart**, for the member or a
  business they handle billing for. One card entry pays everything: the card form makes one token per need
  (`js/go-codes.js` `tokens`), a plan that starts takes one (`Billing::start`, the card kept on file), the GO Codes
  another (`QrIssue::payCard`, as before). A change of plan or packs on a paid plan go on the card on file
  (`changePlan`, `addAddon`). Plans are paid before GO Codes; a failure stops there and says what was done. The header's
  cart counts plans and packs too. Packs on a trial or on Free wait for a plan (said in the cart).
- **Try Pro free for 7 days (§5):** `Billing::selfTrial` (`SELF_TRIAL_DAYS = 7`), the Plans page's **Start my free week**
  (`#try`, action `try`), offered by the upgrade sheet and the Shop. Once an account (never a Pro plan or a trial
  before), Verified members only, no card; ends on Free unless kept. No AI credits during it (`subscriptions.self_trial`):
  AI calls cost us (No income, no expense).
- **Video quality by plan (§2, 0153 §8):** the video worker (`workers/video`, Railway) makes 720p copies of posted videos
  bigger than that, and 1080p for Plus, Pro and businesses (`app/lib/VideoJobs.php`, `/api/video/*`, Bearer
  `VIDEO_WORKER_TOKEN`). Feeds play the 720p copy; Pro's and businesses' originals play with **Watch in 4K** (or HD).
  Keep copies the plan's size into the Gallery: 720p Personal, 1080p Plus, the original on Pro (`MemberPosts::bestPart`).
  Videos already in Galleries are untouched.
- **Not yet:** keeping a single video in 4K ($0.99) needs one-off lines on the GO Codes order and its invoice; its own
  build, so the money path changes on its own (the `keep_hd` column is ready). A single month of Pro likewise.
