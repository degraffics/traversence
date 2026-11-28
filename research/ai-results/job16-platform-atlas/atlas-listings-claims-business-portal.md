# Platform atlas: listings, claims and the business portal

*Draft for human review. Path: `research/platform-atlas/listings-claims-business-portal.md`.*

**Sources.** The charter, brand guide, PRD and `site-audit/pages.md`. Decision records 0015, 0070, 0133, 0164 and 0172. Code from `app/lib/` (synced copy): ListingActions, ListingSafety, ListingLocation, ListingCategories, ListingConfidence, ListingBulk, AddBusiness, BizMessages, BusinessData, BizQuotes, BizCodes, BizFiles and MarketBiz.

**Partly shown.**
- Each file was capped at 40,000 bytes. MarketBiz.php is cut off at line 526. Decisions 0164, 0172 and 0133 are truncated.
- These files are **not shown**: `claim.php`, `listing/view.php`, `listing/businessportal.php`, `app/lib/Claims.php`, `Notify.php`, `Team.php`, `Plan.php`, `Billing.php` and `MarketCheckout.php`. Neither is any page template or `commercial.md`.
- The decision records cited only from code comments (0044, 0047, 0052, 0054, 0058, 0062, 0071, 0073, 0083, 0110, 0118–0121, 0135, 0141, 0142) are **not shown** as text.
- Docs carry no line numbers, so they are cited by section.

---

## (a) What each person can do, page by page

Statuses below come from `site-audit/pages.md`.

### Resident and traveler (signed out or signed in)

**A business's page: `/listing/view.php?id=`** (Live)
- The page offers only the actions that work for that listing and that viewer (`ListingActions.php:85-118`):
  - **Call:** shown when there is a phone with 7 or more digits (`:103-104`).
  - **Email:** opens the visitor's own mail app (`:105-106`).
  - **Directions:** hidden for confidential listings and P.O. boxes (`:107-111`).
  - **Website** and **Contact or book** (`:112-115`).
  - **Ask a question:** for a guest when the listing is managed (`:101`).
  - **Leave a message:** when the listing is unclaimed (`:100`).
- Signed-in visitors can **Message** a managed listing (`:99`).
- Visitors see a confidence ring with a "How we know" list (`ListingConfidence.php:165-171`).
- Visitors can answer "Is this right? Yes" on each detail (`:241-248`).
- Visitors can answer "Still open? Yes / It's closed" (`:251-280`).

**Where a listing is: `/listing/location.php?id=`** (Live)
- A visitor who drags the pin sends a suggestion for a person to review. The pin does not move (`ListingLocation.php:70-74`).
- Confidential listings have no pin (`:68`).

**Other routes**
- Guest questions go through `/api/guest-inquiry.php`.
- Counts go through `/api/count.php` and `/api/out.php`.
- `/takedown.php` and `/api/suggestion.php` are also available.

### Member (signed in)

**Claim a listing: `/claim.php?listing=`** (Live)
- The member confirms an email code and the listing becomes "Managed by owner".
- The member then texts a code to the Verify number to reach ✓ Verified (0070 §2–3).
- Other ways to confirm: an email code to the business's own domain, a document, or a call or visit (0070 §4).
- Variants on the same page:
  - `&dispute=1` questions the current owner (0070 §8).
  - `?new=1` adds a business that isn't listed.
  - `&mode=contribute` suggests details (pages.md §1).
- Before adding or claiming, the account needs a confirmed email, a phone, age 18+ and Verification on (`AddBusiness.php:39-42`; the check itself, `Claims::missing`, is not shown).

**Messages to a business**
- The member writes from Pulse or the Address Book, linked at `/user/dashboard.php#addressbook:biz=` (`ListingActions.php:99`).
- The limit is 50 messages a day (`BizMessages.php:192-194`).
- The member can mute message types per business (`:48-54`).
- The member answers "link to us / subscribe again" asks (`:213-226`).

**Your Marketplace orders and quotes**
- `/user/orders.php` (Live).
- A quote opens at the customer's page `/q.php?t=…` (`BizQuotes.php:234-237`). That route is **not listed** in pages.md. Only `/q-file.php` is listed.

**Confirm a referral: `/listing/confirm-referral.php`** (Live)
- Referrals are "future upgrades, not offered" (pages.md §6). The page is live but the feature is not offered.

### Business (an owner, manager or role on the listing)

**`/listing/businessportal.php`** (Live)
- Shows claims and where each stands, the plan, questions about the listing, and the listing editor (`?id=`) (pages.md §4).
- Owner edits to name, phone, address and website on a Managed listing wait for staff review. Description, photos, hours, services, posts and offers are edited directly (0070 §2).
- The page itself is not shown.

**`/business/`** (Live): the portal, scoped to one business.
- `router.php` draws its sections; `api.php` is its endpoint; `files.php` serves files; `pay-connect.php` connects payments.
- **Quotes, invoices and orders.** Flow: draft → sent → approved (now an invoice) → paid → stages (new, production, ready, shipped, done) (`BizQuotes.php:14-17`).
  - Payments are recorded by hand. Methods include cash, check, Zelle, Venmo, or the business's own pay link (`:16`, `:297-317`).
  - The customer is emailed when an order is ready or shipped (`:328-336`).
- **Discount codes** have no floor and need no approval (`BizCodes.php:2-5`).
- **Proofs and files** on quotes count against the plan's storage (`BizFiles.php:83-84`).
- **Marketplace publishing** (`MarketBiz.php:234-313`).
- **Messages to customers.** Offers go out once a day, link or subscribe asks once a week (`BizMessages.php:373-410`).
- **Pin and address editing.** Changes apply at once and are locked against the crawler (`ListingLocation.php:85-118`).
- **Up to 4 secondary categories**, saved at once (`ListingCategories.php:13,17,53-77`).

**Plan pages**
- `/plans.php`. A storage-full error links to `/plans.php?listing=…#packs` (`BizFiles.php:84`).

**Coming (release comms-all)**
- `/listing/confirm.php?t=`: a guest confirms the email on a message to an unclaimed listing.
- `/listing/requests.php?t=`: an unclaimed listing's email address can stop requests or report spam (pages.md §4).

### Staff

- **`/admin/claims.php`** (Live): claims to confirm (overdue first), requests on hold, owner changes, disputes. Documents are opened with `?doc=`, kept in `traversence-private/claims/`, and deleted once the claim is decided (pages.md §5).
- Also live: `/admin/listing-edit.php`, `/admin/listing-add.php`, `/admin/crawler-review.php` and `/admin/helper.php`.
- Merge and Nexus moves happen in Find & edit (`ListingBulk.php:50-186`). The route is **not shown** in pages.md by that name.

---

## (b) How it works underneath

### Two records of "managed" and "claimed"

- **Managed** means someone holds a `listing_access` row (`ListingActions.php:37-43`). Messages and actions use this test.
- **Claimed**, for confidence, is read from `entities.owner_user_id` plus status (`ListingConfidence.php:147`).
- **Owner**, for location, accepts any of these (`ListingLocation.php:27-38`):
  - a `listing_access` row;
  - the verified `owner_user_id`;
  - an org `role_grants` owner or manager.
- 0172 lists this split as gap 5 ("Claimed and managed mean two things"). 0070 §2 says `listing_access` "is the record from now on". `ListingConfidence.php:147` still reads `owner_user_id`.

### The claim flow (0070)

- `Claims.php` is **not shown**. `AddBusiness.php` shows how it plugs in:
  - The form is checked and duplicates are looked up by phone, website, name in the same town and the identity resolver (`:105-149`).
  - An email code is issued with `StepUpAuth` (`:155-166`).
  - The listing is created as `unclaimed` (`:208-211`), then opened as the member's claim through `Claims::adopt` (`:178`).
  - A business already listed is refused unless the member says it is another business (`:203-204`). That choice is recorded for good (`:231`).

### Listing safety (`ListingSafety.php`)

- Only domestic-violence shelters and safe houses keep their address private (`:26-31`). Help lines are shown instead (`:34-38`).
- Nine categories need a person's review before going live (`:41`).
- Spam and unsafe words are checked (`:43`, `:57-64`). Added businesses run through this check (`AddBusiness.php:97`).

### Confidence (`ListingConfidence.php`)

- Each independent source is weighted (`:24`), ages over three years (`:88`), and counts a little less after the first (`:116-118`).
- Machine evidence alone stops at 75 (`:33,144`).
- A claim lifts the score to 80–89. A verified claim lifts it to 90–99, and keeps that band even with evidence against it (`:150-161`).
- Visitor "Yes" answers add up to 24 points (`:120-123`).
- The daily "Still open?" pass puts a listing on the Call list, or raises an engine case if the listing has no phone (`:287-318`).
- No step closes a listing by itself (`:263`).

### Merge and Nexus (`ListingBulk.php`)

- A managed listing is never the one hidden in a merge (`:57`).
- Staff values are locked (`:189-194`).
- Every move can be undone. An undone merge is recorded as "different" so the two are never merged again (`:146-186`, `:159`).

### Business walls (`BusinessData.php`)

- The business realm signs in as the listings login only (`:2-8`).
- Each connection is set to one tenant (`:79-87`).
- Each table is reached through a `b_<table>` view, and triggers refuse writes to another business's rows (`:119-134`).
- Quotes, codes and files live behind these walls (`:19-68`).

### Marketplace publishing (`MarketBiz.php`)

- Publishing copies only the public parts out of the walls. Cost, markup and supplier stay inside (`:4-9`, `:165-175`).
- Each publish is checked against:
  - the rulebook (prohibited, restricted, review) and recalls (`:243-270`);
  - the Seller Agreement (`:250-253`);
  - the plan's item cap (`:29`, `:273-276`).
- Every 30 days the business is asked whether each item is still on offer (`:20`, `:337-341`).

### Messaging (`BizMessages.php`)

- A conversation is 1:1 between a person and a business. The business sees a person's name only after that person writes (`:9-24`).
- A business can reply while the person is linked, or within 30 days of their last message (`:271-277`).
- Offers skip people who switched marketing off or paused Linking (`:330-331`).
- A new message notifies the team through `Notify::send` (`:199-209`). `Notify.php` is not shown.

---

## (c) Decisions and what each rules

| Decision | Rules (as shown) |
|---|---|
| **0015** (Accepted 2026-09-23) | Four verification tiers and a dispute process with a 48-hour and 7-day clock and auto-abandon. **Superseded in part by 0070**: §1 (claim page) and §5 (disputes); §3 and §6 amended. |
| **0070** (Accepted 2026-10-09) | Claiming. Email code → Managed by owner; text from the listed phone → ✓ Verified; any other number → admin contact within 48 hours. Protected fields: name, phone, address, website. One claim at a time; others are held. Nexus groups; plan activation with promotional trials and `noindex`; disputes (7-day answer for a ✓ owner, a first default). Tier 1 retired. Notes of 2026-11-19 and 2026-11-27/28 add claim kinds (public, community, business, nation, care). |
| **0133** (Superseded by 0164, 2026-11-14) | Its 2026-11-19 note still applies: Get Local's Best match orders by words, then confidence, then distance; "once claimed that pushes them to the top". Closed listings come last. |
| **0164** R4, R9, R12 (Accepted 2026-11-14) | R4: never overwrite what a person entered; a claimed listing is only offered changes ("Check these"). R12: confidence, not restriction; a claim is a claim; three card states (Open, Caution, Closed); state registries only add. |
| **0172** (accepted 2026-11-18) | Communications. Pulse is the inbox. §1.4–1.6: who a business reaches; §2.1: listing and claim events. "Managed" means a `listing_access` row (§3.1, cited at `ListingActions.php:7-10`). Messages to unclaimed listings are held (§3.3). |
