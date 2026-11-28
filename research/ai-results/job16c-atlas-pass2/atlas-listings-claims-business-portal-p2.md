# Platform atlas: listings, claims and the business portal

*research/platform-atlas/listings-claims-business-portal.md. This is a draft for human review. Sources are the attached documents and the `sync-app/lib/*.php` files only. Code is cited as `File.php:line`.*

## Coverage

This entry only partly sees the area.

- **Not shown:** the page files (`claim.php`, `listing/view.php`, `listing/businessportal.php`, `business/*`, `admin/claims.php`) and several libraries the shown code calls: `Claims.php`, `Notify.php`, `Team.php`, `ListingMessages.php`, `MarketCheckout.php`, `Billing.php`, `Identity.php`, `ListingIdentity.php`.
- **Truncated:**
  - `MarketBiz.php` stops at line 526.
  - `decisions/0164` stops inside R17.
  - `decisions/0172` stops inside §2.8.
- **Source of statements:** every claim about a page's behaviour comes from `site-audit/pages.md` or the decisions, unless a library line is cited.

---

## (a) What each person can do, page by page

### Resident and traveler (signed out or signed in)

**`/listing/view.php?id=` — a business's page (pages.md §1, Live)**

- **Which actions appear.** They come from one function, `ListingActions::actions()` (`ListingActions.php:85-118`):
  - **Call** when a phone is present (`:103-104`).
  - **Email**, which opens the visitor's own mail app (`:105-106`).
  - **Directions**, except for confidential places or a P.O. box (`:107-111`).
  - **Website** and **Contact or book** (`:112-115`).
  - **Message**, when the listing is managed (`:99`).
  - **Ask a question** (guest), when the listing is managed and the visitor is signed out (`:101`).
  - **Leave a message**, when the listing is unclaimed (`:100`).
  - **Claim it**, when the listing is unclaimed and the viewer is not staff or team (`:116`).
- **How actions are counted.** Each tap counts once per session, listing, action and day. No user, device or address is stored (`ListingActions.php:2-5, 201-217`).
- **Confidence ring and "How we know".**
  - The ring shows the score and band (`ListingConfidence.php:27-32, 50-60`).
  - The evidence lines are built at `:164-171`.
  - "Is this right? Yes" counts once per detail per day, never who answered (`:241-248`).
- **"Still open?" answers** feed the closure logic (`ListingConfidence.php:250-280`).
- **Confidential places.** Only domestic-violence shelters and safe houses hide their address and pin. These listings show help lines instead (`ListingSafety.php:26-38`).

**`/listing/location.php?id=` — where a listing is (pages.md §1)**

- A visitor who drags the pin creates a suggestion that a person reviews. It does not move the pin (`ListingLocation.php:70-75`).
- A confidential listing has no pin (`:68`).

**`/claim.php?listing=…&mode=contribute`** lets a visitor suggest details (pages.md §1).

**`/api/suggestion.php`** lets a visitor report a missing place, a fix or a closure (pages.md §6). Open suggestions lower confidence (`ListingConfidence.php:139-142`).

### Member (signed in)

- **`/user/dashboard.php#addressbook:biz=`** is where Message leads for a managed listing (`ListingActions.php:99`).
- **Messaging a business:**
  - Members can send up to 50 business messages a day (`BizMessages.php:192-194`).
  - The business can reply while the member is linked, or within 30 days of the member's last message (`:270-277`).
  - Members can mute Sales, Support, Inquiry or Relationship messages per listing (`:29, 48-54`).
- **Linking and subscribing:**
  - Offers reach a member only if they are subscribed and have not muted Sales (`BizMessages.php:63-67, 319-335`).
  - A business may send one "ask" a week. An unanswered ask blocks re-asking for 3 months (`:390-410`).
- **`/claim.php?listing=` — claiming.** A claimant must be 18+ and have Verification on (0070 §1). The steps are:
  1. **Email code** → "Managed by owner" (0070 §2).
  2. **Text code** to the Verify number → "✓ Verified" (0070 §3).
  3. **Other routes:** a code to the business's own domain, a document, or a call or visit (0070 §4).
- **`/claim.php?…&dispute=1`** lets a member question a listing's owner (pages.md §1; 0070 §8).
- **`/claim.php?new=1`** adds a business. The account needs what claiming needs, per `Claims::missing` (not shown; `AddBusiness.php:39-42`). The steps are:
  1. The form is checked (`AddBusiness.php:61-98`).
  2. Possible duplicates are offered (`:105-149`).
  3. An email code is sent (`:155-166`).
  4. The listing is created and adopted as the member's claim (`:169-181`).
- **`/q.php?t=` — a customer's quote page.** No account is needed (`BizQuotes.php:7, 234-247`).
  - The customer can approve it by typing their name (`:250-267`) or decline it (`:288-294`).
  - They can add a discount code before approving (`:270-286`).
  - They can approve a proof or ask for changes (`BizFiles.php:140-148`).
  - **Gap:** `/q.php` is not in pages.md; only `/q-file.php` is listed.
- **`/market/item.php`** — Marketplace items. Buttons depend on the item kind and the business's plan and setup (`MarketBiz.php:120-152`).

### Business (owner, manager, team)

**`/listing/businessportal.php` — claims, plan, and the listing editor (`?id=`)** (pages.md §4, Live)

- **Editing rules.** Owners edit description, photos, hours, services, posts and offers. Name, phone, address and website go to review until the claim is verified (0070 §2, §4 build 4; `/listing/api/update-metadata`, pages.md §6).
- **Plan choice.** Free, Core, Strategic or Cornerstone; paid tiers start as a promotional trial until billing exists (0070 §7).
- **Secondary categories.** Up to 4, saved at once with no approval (`ListingCategories.php:13-17, 53-77`).

**`/listing/location.php`** — owner and staff set the pin or address immediately. Values they set are locked against the crawler (`ListingLocation.php:4-6, 85-118`).

**`/business/` — the business portal** (pages.md §4, 0109). Tools seen in the libraries:

| Tool | What it does | Code |
|---|---|---|
| Customers (CRM) | Customer records, tags, notes | `BusinessData.php:20-31` |
| Catalog | The business's products | `BusinessData.php:33-37` |
| Quotes → invoices → orders | One row from draft to paid order | `BizQuotes.php:12-17` |
| Manual payments | Cash, check, Zelle, Venmo, bank, own card link | `BizQuotes.php:16, 297-317` |
| Order stages | Ready and shipped email the customer | `BizQuotes.php:320-338` |
| Discount codes | No floor, no approval | `BizCodes.php:2-5` |
| Proofs and files | Count against plan storage | `BizFiles.php:50-62, 83-84` |
| Messages | Inbox, offers (one a day), asks (one a week) | `BizMessages.php:231-250, 373-410` |
| Marketplace | Publish, unpublish, 30-day "Still on offer?" | `MarketBiz.php:234-341` |

**`/business/pay-connect.php`** connects a business's payments (pages.md §4). The code is not shown.

**`/user/transfer.php`** hands over a listing (pages.md §3; 0038, not shown).

### Staff

**`/admin/claims.php`** (pages.md §5, Live) shows:
- claims to confirm, overdue first;
- claims on hold;
- pending owner changes;
- disputes;
- claim documents, which are stored outside the site and deleted once the claim is decided.

**Find & edit** can:
- merge listings, but never hides a managed one (`ListingBulk.php:50-104, 57`);
- group or ungroup a Nexus (`:110-143`);
- undo either action (`:146-186`).

`/admin/listing-edit.php`, `/admin/listing-add.php` and `/admin/crawler-review.php` are Live per pages.md; their code is not shown.

---

## (b) How it works underneath

**Who counts as an owner — three tests in the shown code**

1. `ListingActions::managed()`: any `listing_access` row, described as "the one test" (`ListingActions.php:37-43`).
2. `ListingLocation::role()`: `listing_access`, **or** `entities.owner_user_id` with status `verified`, **or** an org `role_grants` owner or manager (`ListingLocation.php:26-38`).
3. `ListingConfidence`: `owner_user_id > 0` and a claimed status (`ListingConfidence.php:147`).

0172 gap 5 ("Claimed and managed mean two things") is therefore only partly closed in the shown code.

**Confidence score (0–100)**

- Each independent source counts once, by kind:
  - our team or the owner: 30;
  - a registry: 22;
  - the business's own site: 18;
  - and lower for others (`ListingConfidence.php:24-25`).
- Evidence ages over 3 years, down to a floor of 0.4 (`:88`).
- Google is the only "evidence" link that counts, at weight 10 (`:101-103`).
- Other sources follow 0164's collaborative weights (`:180-195`).
- Machine evidence alone is capped at 75 (`:33, 144`).
- A claim lifts the score to 80–89; a verified claim to 90–99. Signals against a claimed listing lower it only within its band (`:150-161`).

**Closure handling**

- A closure signal lowers the score and shows a caution line (`:130-133`). It never closes a listing on its own.
- `stillOpenTick` puts due listings on the Call list or raises an engine case (`:287-318`).

**Add a business**

- The listing is created as `unclaimed` with `submission_source 'user_submitted'` (`AddBusiness.php:208-211`).
- Its metadata is locked at trust 90 (`:223-225`).
- An identity lock and resolve step refuses known duplicates unless the person chooses "Add anyway" (`:196-206, 231`).
- It gets a micro-cluster from its ZIP (`:189-192`).
- **Not shown in code:** the "noindex until verified" mentioned at `:13`.

**Walled business data**

- The portal connects through the listings-database login and sets `@tv_tenant`.
- Tools read `b_<table>` views.
- Triggers refuse cross-tenant writes (`BusinessData.php:2-8, 79-136`).

**Marketplace publish**

- Only public parts are copied to the main database. Cost, markup and supplier stay in the business's walled data (`MarketBiz.php:4-9, 165-175`).
- Each publish passes these checks:
  - the rulebook: prohibited, restricted, review or notice (`:248-265`);
  - a CPSC recall hold (`:266-270`);
  - the Seller Agreement (`:251-253`);
  - the plan's item cap (`:273-276`);
  - the listing must have a town (`:155-160`).
- Every version is kept (`:296-297`).

**Messages**

- A member's message notifies the business team through `Notify::send` (`BizMessages.php:199-209`; `Notify.php` not shown).
- This appears to close 0172 gap 1, which said "staffUnread is never called".

**Safety**

- Some categories always need a person's review: cannabis, tobacco, guns, pawn, bail bonds, massage (`ListingSafety.php:40-41`).
- Spam and unsafe wording are rejected (`:43, 57-64`; used at `AddBusiness.php:97`).

---

## (c) Decisions

| Decision | What it rules | State in docs |
|---|---|---|
| **0015** | Claim OTP, four verification tiers, `listing_access` written on claim, dispute clocks (48 h and 7 days) | §1 and §5 superseded by 0070; §3 and §6 amended |
| **0070** | Claim = email code (Managed by owner), then text code (✓ Verified) or admin; other routes; one claim at a time (others on hold); Nexus claims; plan activation and promotional trial; disputes without automatic status changes; add a business; claim kinds (public, community, business, nation, care) | Accepted 2026-10-09; amended by 0164 R12 |
| **0164** | R4: a person's fields are never overwritten; a claimed listing is only offered changes. R9: licence decides use. R12: confidence, not restriction; "a claim is a claim"; Closed only by owner, team, public or own site. R14: social pages are links only | Accepted 2026-11-14; supersedes 0133 |
| **0133** | Earlier crawler rule book; note that confidence orders "Best match" | Superseded by 0164 |
| **0172** | §3.1 one test of "managed" and per-card actions; §3.3 messages left on unclaimed listings and Sales counts; gaps 1, 3 and 5 | Accepted; partly built |
| **Named only in code comments** (texts not attached) | 0044 safety; 0047/0052 messages; 0054 Nexus; 0058 §15 location; 0071 add business; 0073 categories; 0083 bulk; 0110 walls; 0118 quotes; 0120 files; 0121 codes; 0135, 0141, 0142 Marketplace | Content not shown |

---

## (d) Live vs coming

**Live (pages.md):**
- `/claim.php` with dispute, new and contribute modes;
- `/listing/view.php`, `/listing/location.php`, `/listing/businessportal.php`, `/listing/confirm-referral.php`;
- `/business/*`;
- `/admin/claims.php`;
- `