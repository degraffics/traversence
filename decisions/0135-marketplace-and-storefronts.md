# ADR 0135: Marketplace and Storefronts, Jason's Answers to the Outline

**Status:** Proposed (2026-11-01). Jason's answers to `marketplace-architecture.md` §7. Design only; nothing is built.
Choices marked **(Jason)** are proposals waiting for him to confirm. Builds on 0031, 0032, 0058, 0098, 0103 and 0109,
and on `commercial.md` §1.1.

## What's live (checked against the `traverse-3way-sync` mirror, 2026-11-01)

- `/market/`: the placeholder from 2026-09-30 (decisions/0055). Sample cards marked "Sample", and "Post something" is
  greyed out.
- Admin workspace 5 (`market`, `app/lib/Workspaces.php`, label "Market 360°"):
  - **Built:** GO Codes & short links; Storefront & pages and Form entries (our own site pages, `SitePages`).
  - **Placeholders:** Post-and-Connect, Storefront Studio, Custom domains.
- Not built: items, offers, ratings, the Shop tab, checkout, gateways, storefronts at `trvgo.us/<shop>`.

## Decision

### 1. Two display models, one admin workspace (Jason)

The Marketplace and Storefronts are **two different display models**:
- **Marketplace:** one shared grid where everyone browses posts near a place.
- **Storefronts:** one business's own shop (its listing's Shop tab, `trvgo.us/<shop>`, its own domain).

Admin workspace 5 keeps the name **Market 360°** (`/admin/market/`). Inside it, **Marketplace** and **Storefronts** are
two sub-sections, **managed independently**: each has its own sections, rules, metrics and settings. Neither depends on
the other being set up. GO Codes & short links and our own pages stay in the workspace beside them.

What they share is the record underneath: a business's catalog item is one record, so an edit shows in both.

### 2. Free businesses can sell (already decided)

`commercial.md` §1.1 already gives the Free tier selling with manual payments, marked paid by the business. Card checkout
through the business's own gateway starts at Core.

### 3. Catalog caps live in `commercial.md` (Jason)

The per-listing item caps are a tier axis, so they sit in `commercial.md` §1.1 beside the tier table. Cornerstone's 250
per listing is decided (0032). Free, Basic, Core and Strategic are proposed there **(Jason)**. A member's personal posts
have no catalog and no cap (0032); limits on how many personal posts one person runs at once go in Marketplace rules.

### 4. Ratings are comments (0058 replaces 0032 here)

decisions/0058 dropped star ratings: on a listing, comments are how people review a business. So the outline's "Rating"
record becomes a comment, anchored to a completed, dual-confirmed offer or a paid order where there is one (0032's
"verified interaction" stays). Businesses reply the way anyone replies to a comment.

### 5. Custom domains: in the Storefronts build (Jason)

Connecting a business's own domain belongs to the Storefronts build (outline §6 step 5). How it's done on Bluehost
(addon domain and SSL by hand, or automated) is decided when that build starts.

### 6. How long a post stays up (Jason's direction; details proposed)

Jason: the system checks posts and asks the seller to confirm they're still on offer; no confirmation takes the post
down. But a personal item and a business's product or service are offered for very different lengths of time, so the
seller sets how long.

**Proposed (Jason):**
- **"Offer this for"** on every post, with a default by kind:

  | Kind | Default | Choices |
  |---|---|---|
  | Personal item | 30 days | 7, 14, 30, 60 or 90 days; until a date |
  | Personal service (lawn mowing, lessons) | 90 days | the same, plus Ongoing |
  | Business product | Ongoing | the same, plus Ongoing |
  | Business service | Ongoing | the same, plus Ongoing |

- **The check.**
  - **Dated posts:** when the time is up, the seller gets "Still available?" in their Pulse and by email. One tap renews
    it for the same length again.
  - **Ongoing posts:** checked every 30 days, but they keep showing while the business answers.
    - A product with stock tracked that has **sold out** comes down by itself.
    - One **Pulse case** "Still on offer?" carries a step per post (the Pulse rule: one case per kind of chore). Confirm
      all, or untick the ones that are gone.
- **No confirmation:** after 7 days with no answer, the post moves to a new Item stage, **Expired**. It's off the
  Marketplace and the Storefront, kept (never deleted), and the seller can relist it in one tap. Expired posts follow
  0103's inactivity path (Hidden → Archived) after that.
- The lengths, the reminder and the grace period are settings in Marketplace rules, not code.

### 7. Gateways: Square and Stripe first (Jason)

Checkout is built for **Square** and **Stripe** together (the options compared are in `marketplace-architecture.md`
§4). Square fits the counter shops we sign; Stripe covers the rest, with low-cost bank payment (ACH). PayPal / Venmo
come next if buyers ask; QuickBooks Payments and others when a business asks. Fixed already: the business
connects **its own** account; checkout is the gateway's hosted page (no card data on our server); we take no cut
(`commercial.md`); the order is recorded on our ledger (0098).

### 8. The public address stays `/market/` (Jason)

The Marketplace keeps the live address `/market/`; the name shown everywhere stays "Marketplace" (0055). The admin
stays `/admin/market/`.

### Still open

- **Catalog caps below Cornerstone.** No ADR sets them: 0032 sets only Cornerstone (250 per listing) and says Core and
  Strategic "still need their own numbers"; Free and Basic aren't mentioned. Some docs read "the 250-item cap" as if it
  were every tier (AOS §5, `admin-architecture.md`); `workspace-map.md` asks the same question.
- The post-length details in section 6.

## Consequences

- `marketplace-architecture.md`: §0 and §3A follow section 1; the Item workflow gains **Expired**; Rating becomes a
  comment; §4 lists the gateway options; §7 points here.
- `commercial.md` §1.1 gains the catalog-cap row.
- When built: `app/admin/manifests.php` splits workspace 5's sections under Marketplace and Storefronts, and the
  existing placeholders (Post-and-Connect, Storefront Studio, Custom domains) move under them.
