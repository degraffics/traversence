# 0071. Adding a business: live in minutes, verified with one tap

Date: 2026-10-10
Status: Proposed (Jason's answers, 2026-10-10: "1. live 2. email enough 3. short list yes"; the verification request
and the account rule as he described them)

**Amends:**
- 0070 (claiming a listing): the owner's "text a code to 602-708-3795" step is replaced by the verification request
  below. A text still works as a fallback.
- 0053 (suggestions): a visitor's "something missing" gets a way to become a listing without a website.

**Leaves standing:**
- 0070's statuses (Managed by owner, ✓ Verified), one claim per listing, Notices, disputes, document handling;
- the noindex rule for listings not yet verified;
- 0068 (plain names for categories).

## Context

Today a business reaches the directory one way only. A crawlable website is read by the crawler, and staff approve
the result. The add-a-business form on `claim.php` only saves a suggestion. Staff can only turn it into a listing by
crawling a website (Listing Intake needs a URL; the listing editor only edits existing listings). The form promises
"we'll email you when it's on the site", and nothing sends that email.

The businesses this leaves out are the ones a rural directory most needs:
- the Facebook-only shop;
- the guide with a phone number and no web presence at all.

Facebook pages can't be read reliably (they block automated reading), so the crawler can't help with them either.

## Decision

### 1. You need an account, with a confirmed email and a phone number

- **Adding or claiming a business needs you signed in.**
  - Signed out, the form says so and offers **Create an account** or **Sign in**, then brings you back.
  - What you've typed so far is kept.
- **Your email must be confirmed** (the existing email link).
- **Your account needs a phone number.**
  - It's asked for once, in the same step, and saved to your account (new: `users.phone`).
  - It's private, shown only to staff.
  - There's no texting service, so it isn't checked with a code. An admin can call it.

### 2. The owner adds their own business: live once the email code is confirmed

A short form, built for a phone:
- **Required:**
  - the business name;
  - **what it is**, from a short plain list (§3);
  - the town;
  - the business phone.
- **Where:** a street address, or **Drop a pin / I'm here now**, for places with no usable address (the map point
  suggestions already use).
- **Optional:**
  - the Facebook page, website and other links;
  - hours;
  - what it offers;
  - up to 6 photos from the phone, re-encoded with hidden data removed, as everywhere else.

**Duplicate check before saving.**
- We look for an existing listing with the same phone, the same website, or a similar name close by.
- If there's a match: "Is this yours?" and straight into claiming it, so there's no second listing.

**Saving:**
1. We email a code (0070's first step).
2. With the code confirmed, the listing is created:
   - status `claimed-unverified`, shown as **Managed by owner**;
   - with you as its owner (`owner_user_id` and `listing_access`, as 0070);
   - it's public in the directory, on the map and in the site's search (search picks it up within 15 minutes);
   - it's kept out of search engines (noindex) until it's verified.
3. Staff get a **Notice** in the claims queue: "New business added by its owner".
4. Anyone can report it, and 0070's dispute process covers mistakes.

### 3. "What it is": a short plain list

The form shows about a dozen plain choices, each mapped to the directory's categories:
- Food & drink;
- Lodging & camping;
- Shops;
- Services;
- Outdoors & guides;
- Arts & culture;
- Health & care;
- Auto & repair;
- Farm & ranch;
- Events & venues;
- Community & churches;
- Something else.

Each choice opens its few sub-kinds (Café, Restaurant, Bar…) where the category tree has them. Staff can refine the
category later, as on any listing.

### 4. Verification: "Send verification request", and you confirm from the email

On the owner's listing, **Get verified** has one button: **Send verification request**.

The admins (you first) get an email with:
- the listing: name, kind, town, phone, address or map pin, links, photos;
- who sent it: name, account email, phone, and how long they've had the account;
- **Verify**:
  - opens a short page with the same details and one **Confirm** button, a deliberate tap, so a mail scanner
    opening the link can't verify anything;
  - the link is single-use, expires after 14 days, works only for that listing, and needs no sign-in;
- **Not yet**: a box for a note ("please call me", "a photo of the storefront"), sent to the owner.

When an admin confirms:
- the listing becomes **✓ Verified** and joins search engines;
- the owner gets an email saying so.

**The same request replaces 0070's text-a-code step for claims.** Texting the code still works if someone prefers it.
Every request also sits in the admin claims queue, so any admin can act on it there.

### 5. Someone else tells us about a business

It stays a suggestion (0053). It's sent signed in or out, as now.
- **In Review:** a new **Create the listing** button opens the listing form filled in with what they sent. Staff
  complete it and approve it, with the same checks as a crawled listing. No website is needed.
- **The suggestion is linked to the new listing.**
- **The person who sent it gets the promised email:** "It's on Traversence", with a **Claim it** link if they said
  it's theirs.

## Consequences

- **A business with only a phone number can be listed, and live, in a few minutes, by its owner.**
- **A wrong listing is live until someone reports it or an admin looks.** This is the trade-off Jason chose
  ("we have protocols in place").
  - Every add is a Notice in the queue.
  - Nothing is indexed by search engines until it's verified.
- **The text-to-Jason step goes away for most people.** You verify from your email.
- **New data:**
  - `users.phone`;
  - a verification-request table: request, single-use token, expiry, decision, note;
  - a link from a suggestion to the listing it became.
- **New pages and endpoints:**
  - the add-a-business flow on `claim.php`;
  - the confirm page;
  - Review's Create the listing.
- **FAQ:** adding a business, the account rule, getting verified.

## To confirm

- The list in §3 can change.
- Should the text option for claims stay as a fallback (as written), or go?
