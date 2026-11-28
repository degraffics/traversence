# Platform atlas: listings, claims and the business portal (merged from two passes)

*Draft for human review. Path: `research/platform-atlas/listings-claims-business-portal.md`.*

**How to read this entry**
- **Unmarked** lines are supported by both passes. Where both cite the same lines, one citation is given.
- **P1** and **P2** mark each pass's own citation where the two differ.
- **[single-pass: P1]** or **[single-pass: P2]** marks a finding only one pass makes.
- **Passes differ:** marks a disagreement between the passes.

**Gaps in the inputs**
- **P1 ends after section (c).** It has no (d) and no (e).
- **P2 is cut off partway through (d)**, inside its "Live" list. It has no (e).
- **Neither pass has a section (e).** This merge cannot reconstruct it, and it is marked below as missing.

## Coverage

**Sources**
- Both passes use the attached documents and the synced library code.
- Passes differ on the code path:
  - P1 gives `app/lib/` (synced copy).
  - P2 gives `sync-app/lib/`.
- P1 lists the charter, brand guide, PRD, `site-audit/pages.md` and decisions 0015, 0070, 0133, 0164 and 0172. [single-pass: P1, for the full list]
- P1 notes that docs have no line numbers and are cited by section. [single-pass: P1]
- P2 notes that page behaviour comes from `pages.md` or the decisions unless a library line is cited. [single-pass: P2]

**Truncated**
- `MarketBiz.php` stops at line 526.
- 0164 and 0172 are truncated.
  - P2 locates the cuts: 0164 inside R17, 0172 inside §2.8. [single-pass: P2]
- 0133 is also truncated, and every file was capped at 40,000 bytes. [single-pass: P1]

**Not shown in either pass**
- Pages: `claim.php`, `listing/view.php`, `listing/businessportal.php`.
- Libraries: `Claims.php`, `Notify.php`, `Billing.php`, `MarketCheckout.php`, `Team.php`.

**Not shown, per one pass only**
- P1 also lists `Plan.php`, all page templates and `commercial.md`. [single-pass: P1]
- P2 also lists `business/*`, `admin/claims.php`, `ListingMessages.php`, `Identity.php` and `ListingIdentity.php`. [single-pass: P2]

**Decisions named only in code comments (text not attached)**
- Both list: 0044, 0047, 0052, 0054, 0058, 0071, 0073, 0083, 0110, 0118, 0120, 0121, 0135, 0141, 0142.
- Passes differ: P1 also lists 0062 and 0119.
- P2 also cites 0038 and 0109 in section (a). Neither appears in either pass's list of attached decisions. [single-pass: P2]

---

## (a) What each person can do, page by page

### Resident and traveler

**`/listing/view.php?id=`: a business's page (Live, pages.md §1)**
- The actions shown depend on the listing and the viewer (`ListingActions.php:85-118`).
- **Call** (`:103-104`). Passes differ on the condition:
  - P1: a phone with 7 or more digits.
  - P2: a phone is present.
- **Email** opens the visitor's own mail app (`:105-106`).
- **Directions** are hidden for confidential listings and P.O. boxes (`:107-111`).
- **Website** and **Contact or book** (`:112-115`).
- **Message** appears when the listing is managed (`:99`). P1 adds that this is for signed-in visitors.
- **Ask a question** appears for a guest on a managed listing (`:101`).
- **Leave a message** appears on an unclaimed listing (`:100`).
- **Claim it** appears on an unclaimed listing when the viewer is not staff or team (`:116`). [single-pass: P2]
- **Tap counting.** Each tap counts once per session, listing, action and day. No user, device or address is stored (`ListingActions.php:2-5, 201-217`). [single-pass: P2]
- **Confidence ring with "How we know".**
  - P1 cites `ListingConfidence.php:165-171`.
  - P2 cites `:27-32, 50-60` for the ring and `:164-171` for the evidence lines.
- **"Is this right? Yes"** is offered on each detail (`ListingConfidence.php:241-248`).
  - It counts once per detail per day and never records who answered. [single-pass: P2]
- **"Still open? Yes / It's closed"** (P1 `:251-280`, P2 `:250-280`).
  - P2 adds that the answers feed the closure logic. [single-pass: P2]
- **Confidential places.** Only domestic-violence shelters and safe houses hide their address. They show help lines instead (P2 `ListingSafety.php:26-38`).
  - P1 states this in (b) (`:26-31`, `:34-38`).
  - Placing it on the view page is P2's.

**`/listing/location.php?id=`: where a listing is (pages.md §1)**
- A visitor who drags the pin sends a suggestion for a person to review. The pin does not move (P1 `ListingLocation.php:70-74`, P2 `:70-75`).
- A confidential listing has no pin (`:68`).
- P1 marks this page Live.

**Other routes**
- `/api/suggestion.php` is available to visitors.
  - P2 adds that it handles a missing place, a fix or a closure (pages.md §6). [single-pass: P2]
  - P2 adds that open suggestions lower confidence (`ListingConfidence.php:139-142`). [single-pass: P2]
- `/api/guest-inquiry.php`, `/api/count.php`, `/api/out.php` and `/takedown.php`. [single-pass: P1]
- `/claim.php?listing=…&mode=contribute` suggests details (pages.md §1).
  - Passes differ on who uses it: P1 files it under Member, P2 under Resident.

### Member (signed in)

**Claim a listing: `/claim.php?listing=` (Live)**
1. **Email code** → "Managed by owner" (0070 §2).
2. **Text code** to the Verify number → "✓ Verified" (0070 §3).
3. **Other routes:** a code to the business's own domain, a document, or a call or visit (0070 §4).

Related variants and requirements:
- `&dispute=1` questions the current owner (0070 §8).
- `?new=1` adds a business that isn't listed.
- **Requirements.** Passes differ:
  - P1: a confirmed email, a phone, age 18+ and Verification on (`AddBusiness.php:39-42`; `Claims::missing` not shown).
  - P2: age 18+ and Verification on (0070 §1). P2 says `?new=1` needs "what claiming needs" per `Claims::missing` (not shown; `AddBusiness.php:39-42`).

**Add-a-business steps** (P2 lists them in (a); P1 covers them in (b))
- Form check (`AddBusiness.php:61-98`). [single-pass: P2]
- Possible duplicates (`:105-149`).
- Email code (`:155-166`).
- Create and adopt as the member's claim (P2 `:169-181`; P1 `:178`, `:208-211`).

**Messages to a business**
- Message leads to `/user/dashboard.php#addressbook:biz=` (`ListingActions.php:99`).
- P1 adds that members write from Pulse or the Address Book. [single-pass: P1]
- The limit is 50 messages a day (`BizMessages.php:192-194`).
- Members can mute message types per business (P1 `:48-54`, P2 `:29, 48-54`).
  - P2 names the types: Sales, Support, Inquiry, Relationship. [single-pass: P2]
- **Reply window.** A business can reply while the member is linked, or within 30 days of the member's last message (P1 `:271-277`, in (b); P2 `:270-277`).
- **Offers.** Passes differ on who receives them:
  - P1: offers skip people who switched marketing off or paused Linking (`:330-331`).
  - P2: offers reach members who are subscribed and have not muted Sales (`:63-67, 319-335`).
- **Asks.**
  - P1: members answer "link to us / subscribe again" asks (`:213-226`).
  - P2: a business may send one ask a week, and an unanswered ask blocks re-asking for 3 months (`:390-410`). [single-pass: P2, for the 3-month block]

**Orders and quotes**
- `/user/orders.php` (Live). [single-pass: P1]
- `/q.php?t=…` is the customer's quote page.
  - It is not in pages.md; only `/q-file.php` is listed (`BizQuotes.php:234-237`; P2 `:7, 234-247`).
  - No account is needed. The customer can:
    - approve by typing their name (`:250-267`);
    - decline (`:288-294`);
    - add a discount code before approving (`:270-286`);
    - approve a proof or ask for changes (`BizFiles.php:140-148`). [single-pass: P2]

**Other member pages**
- `/market/item.php`: buttons depend on the item kind and the business's plan and setup (`MarketBiz.php:120-152`). [single-pass: P2]
- `/listing/confirm-referral.php` is Live.
  - P1 notes referrals are "future upgrades, not offered" (pages.md §6). The page is live but the feature is not offered. [single-pass: P1]
  - P2 lists the page only in (d).

### Business (owner, manager, team or role)

**`/listing/businessportal.php` (Live, pages.md §4; code not shown)**
- Shows claims and their status, the plan, questions about the listing and the editor (`?id=`).
- **Editing.** Owners edit description, photos, hours, services, posts and offers directly. Name, phone, address and website go to review (0070 §2).
  - Passes differ on when review stops applying:
    - P1: review applies on a Managed listing.
    - P2: review applies "until the claim is verified" (0070 §2, §4 build 4; `/listing/api/update-metadata`, pages.md §6).
- **Plan choice.** Free, Core, Strategic or Cornerstone. Paid tiers start as a promotional trial until billing exists (0070 §7). [single-pass: P2; P1 mentions plan activation only in (c)]
- **Secondary categories.** Up to 4, saved at once (P1 `ListingCategories.php:13,17,53-77`; P2 `:13-17, 53-77`).
  - P2 adds "with no approval". [single-pass: P2]

**Pin and address**
- Owner (and, per P2, staff) changes apply at once and are locked against the crawler (P1 `ListingLocation.php:85-118`; P2 `:4-6, 85-118`).

**`/business/`: the portal (Live, pages.md §4)**
- P2 also cites 0109 for this page. [single-pass: P2]
- `router.php`, `api.php`, `files.php` and `pay-connect.php`. [single-pass: P1, except `pay-connect.php`, which P2 also lists with code not shown]

Tools in the portal:
- **Quotes → invoices → orders** (P1 `BizQuotes.php:14-17`; P2 `:12-17`).
- **Manual payments:** cash, check, Zelle, Venmo or the business's own pay link (`:16, :297-317`).
  - "Bank" is listed by P2 only. [single-pass: P2]
- **Order stages.** Ready and shipped email the customer (P1 `:328-336`; P2 `:320-338`).
- **Discount codes.** No floor, no approval (`BizCodes.php:2-5`).
- **Proofs and files** count against plan storage (P1 `BizFiles.php:83-84`; P2 `:50-62, 83-84`).
- **Messages.** Offers once a day, asks once a week (`BizMessages.php:373-410`).
  - P2 adds an inbox (`:231-250`). [single-pass: P2]
- **Marketplace publishing** (P1 `MarketBiz.php:234-313`; P2 `:234-341`).
- **CRM** (`BusinessData.php:20-31`) and **Catalog** (`:33-37`). [single-pass: P2]

**Plans and handover**
- `/plans.php`. A storage-full error links to `?listing=…#packs` (`BizFiles.php:84`). [single-pass: P1]
- `/user/transfer.php` hands over a listing (pages.md §3; 0038, not shown). [single-pass: P2]

**Coming (release comms-all)** [single-pass: P1; P2's coming list is cut off]
- `/listing/confirm.php?t=`: a guest confirms the email on a message to an unclaimed listing.
- `/listing/requests.php?t=`: an unclaimed listing's email address can stop requests or report spam.

### Staff

**`/admin/claims.php` (Live, pages.md §5)** shows:
- claims to confirm, overdue first;
- claims on hold;
- owner changes;
- disputes.

Claim documents are