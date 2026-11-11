# ADR 0154: Free, Creator and Business: Who Pays for What, and How Upgrades Are Offered

**Status:** Accepted (Jason, 2026-11-11, in conversation), earned storage included (§7, confirmed the same day). Not built. Builds on
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
