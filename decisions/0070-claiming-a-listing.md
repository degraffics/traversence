# 0070. Claiming a listing: a quick way in, a text to finish, an admin when needed

Date: 2026-10-09
Status: Accepted (Jason, 2026-10-09: "approved")

**Supersedes:** decisions/0015 §1 (the claim page as a single step) and §5 (the dispute process).

**Amends:**
- 0015 §3 and §6 (the verification tiers);
- 0016 (the admin claims queue);
- 0017 (documents);
- 0018 (owner access is written to `listing_access`).

**Leaves standing:** 0038 (ownership transfer), 0054 (Nexus) and 0062 §4 (Verification consent).

## Context

All 4,430 listings are unclaimed, and no one can claim yet: `claim.php` is still a disabled form. What exists:
- The back end, `listing/api/claim.php`. It emails a code to the claimant's own account and makes them owner with
  status `claimed-unverified`.
- **Tier 1:** a second code to the same email marks the listing ✓ Verified.

Neither code proves any link to the business. Under 0015 a claim gave full edit rights at once, and disputes were
meant to catch wrong claims afterwards. Since 0062 ("evidence wins, unless claimed"), a claim also stops the crawler
from correcting a listing. The dispute process was never built.

Ownership is recorded in two places:
- `entities.owner_user_id`, used by claiming, verification and the listing page;
- `listing_access`, used by messaging and managers, and never written by claiming.

So a claimed listing couldn't receive messages.

**The aim:** a low gate, so owners get in within minutes, with the fields that could misdirect customers protected
until a second, quick confirmation or an admin's check. In rural and small-business settings the owner's own cell
phone is usually the business phone, so the confirmation has to allow for that.

What the data allows (live database, 2026-10-08):
- 3,819 listings (86%) have a phone number;
- 26 have a website;
- 7 have an email.

## Decision

### 1. Who can claim
A signed-in member who is 18 or older, with Verification switched on (0062 §4). The claim form can switch it on in
place.

### 2. Step 1: email code. "Managed by owner"
The claimant confirms a code sent to their account email (the existing `claim` start/confirm).

The listing becomes **Managed by owner**:
- **Status:** `claimed-unverified`.
- **Owner record:** written to `listing_access` as `owner` and kept in step in `entities.owner_user_id`.
  `listing_access` is the record from now on.
- **Badge:** "Managed by owner", no ✓.
- **What the owner can edit:** description, photos, hours, services, posts and offers.
- **What stays protected:** name, phone, address and website. Changes the owner makes there go to staff review, and
  the evidence from our sources can still update these fields (0062).
- **Also on:** listing messages, and activation (§6).
- **Staff notice:** admins are notified of each new claim (the Pulse and the claims queue).

### 3. Step 2: confirm by text. ✓ Verified
The last step of the claim page: "Text your code **TV-XXXX** to **(Traversence Verify number)** to confirm your
listing." The code is unique to the claim.

- **The text comes from the phone number on the listing:** ✓ Verified at once.
- **The text comes from another number** (usually the owner's own cell): the claim waits for an admin, who **contacts
  the claimant at that number within 48 hours** to confirm who they are and their role with the company. The
  admin's judgement decides (see below). The admin may also call the listed number or ask for a document. That's
  their choice, not a required step.
- **Page wording:** "Text from the phone number on your listing to finish right away. Texting from a different number?
  An admin will contact you within 48 hours to confirm your role with the company."
- **Admins decide case by case,** using the protocols already in place. The outcome is theirs, for example:
  - Verified;
  - left as Managed by owner;
  - access set up another way (a manager, a Nexus);
  - more information asked for;
  - declined.

  No fixed outcome is required.
- **Overdue:** past 48 hours with no admin action, the claim stays as it is (Managed by owner) and moves to the top of
  the queue, marked overdue. Nothing lapses on the claimant.

**The Verify number:**
- **In the pilot,** Jason's number, **602-708-3795**. It's shown only on the claim page, after step 1, to the
  signed-in claimant; it's never on public pages. The claims queue shows each claim's code, the listed phone and the
  claimant's phone, and the admin records "text received from listed number" or "from [number]".
- **Later,** a Twilio number (about $1/month, under a cent per text received). It reads the sender and the code,
  approves matches with the listed number by itself, and puts the rest in the queue.

On ✓ Verified, the name, phone, address and website become the owner's to edit, and evidence that differs is offered
to the owner, never applied (0062).

### 4. Other ways to confirm
For a claimant who can't text, or when the admin asks:
- **An email at the business's own domain:** a code sent there, offered only when the listing has a website on file.
  ✓ Verified at once.
- **A document:** a utility bill, business license, lease, or something similar. Stored in `app/` (never served),
  opened only from the admin queue, and deleted 72 hours after the claim is decided (0017).
- **A call or visit by appointment:** the claimant gives email, phone, address (the business address, prefilled and
  editable), a preferred date and time to meet an agent, and whether they'd like a call or an on-site visit.

**Later, once the pilot is running:** sending codes out by text or call, and postcards. **Future upgrades:**
referrals (Tier 2) and vouchers (Tier 3). Their code stays, but they aren't offered at launch.

### 5. One claim per listing
Only one claim on a listing goes ahead at a time. A second request isn't turned away; it's held:
- **The second person sees "Claim Request On Hold":** another claim is in progress, theirs is saved, and an admin
  will be in touch. They can add their contact details and any proof now. The request shows in their dashboard with
  its status.
- **Admins get an immediate Notice** (the Pulse and the claims queue), with both requests side by side. The admin
  decides how to proceed, and the person on hold is told the result either way.

### 6. Several locations (Nexus, 0054)
During a claim, we look for the claimant's other locations: listings with the same phone, website or name in our
data. When we find some, we offer to claim them together as a Nexus.
- **Each location stays its own listing:** its own page, address and place in search (Single Home Rule), and its
  own plan (0035).
- **One confirmation covers the group when it shows every location:**
  - the locations are listed on the business's own website (checked by staff, or by the crawler when the site is
    on file); or
  - an upload that shows them all: photos of the locations or the back office, or a brochure listing every location.
- **Otherwise,** each location is confirmed on its own, through any route in §3–§4.
- Staff approve the whole group at once, or each location separately.
- The owner manages the Nexus, and shared details are edited once (0054 §5).

### 7. Activation: choosing a plan
Once a claim is Managed by owner, the owner activates the listing by choosing a plan:
- Free;
- Core ($19/mo);
- Strategic ($79/mo);
- Cornerstone ($249/mo) (`commercial.md` §1).

Free starts at once. **Until billing exists (0035),** a paid choice starts as a **promotional trial** of that plan
(0034), recorded as its own per-listing Subscription (source: promotional). When billing goes live, each trial owner
is asked to confirm and pay, or move to Free. **0034's `noindex` rule holds for these trials** (trial-tier content
is kept out of search engines) until the claim system has proven itself and Jason is comfortable with live claimed
listings. Lifting it is his call.

### 8. Disputes
Anyone who believes a listing's owner is wrong can file a dispute.
- **Who can file:** a signed-in, 18+ member with Verification on.
- **What they give:** a reason plus their own proof (§3–§4).
- **What happens:** the case goes to the claims queue, and the owner is notified.
  - A **Managed by owner** listing: the admin decides directly, as with any claim.
  - A **✓ Verified** owner: they have 7 days to answer with their own proof *(first default)*.
- **During the case,** the listing stays visible. Owner edits are frozen only once an admin accepts the case as worth
  looking at, not on filing.
- **Admins decide case by case,** for example: keep the owner, transfer the listing to the challenger, or return it
  to unclaimed.
- **Trust penalty:** only when an admin finds a claim was fraudulent.

Not disputes:
- a business that was sold, or an owner handing over: ownership transfer (0038);
- an employee or partner who needs access: a manager invite (0018).

**Dropped from 0015:** the automatic `disputed` status and reduced display on filing, the 48-hour response clock and
auto-abandon, the filer standing check, and the domain-match fast path for filers.

### 9. Adding a business that isn't listed
"Add your business" (`claim.php?new=1`) runs the same flow. The new listing goes to Review first (0044 guardrails),
then the claim proceeds from step 1.

## Build
1. **The claim page** (`claim.php`):
   - confirm the business;
   - Verification;
   - the email code (Managed by owner);
   - the text step with its code and the Verify number;
   - the other routes;
   - progress in the business dashboard.

   Phone layout and content standards apply.
2. **A claims table**, holding:
   - status (waiting, on hold, managed, verified, declined, withdrawn);
   - the code;
   - the listed phone and the claimant's phone;
   - the route and its details;
   - the 48-hour due time;
   - the Nexus group, if any.
3. **The admin claims queue** (`admin/claims.php`), scoped by area:
   - overdue claims first;
   - texts to match;
   - calls to make;
   - appointment requests;
   - documents;
   - requests on hold (with the Notice);
   - Nexus groups;
   - disputes.

   Actions: verify, keep as Managed by owner, set up a manager or Nexus, request more information, decline, and
   record the contact with a note. The admin picks whatever fits the case.
4. **Protected fields:** owner edits to name, phone, address or website on a Managed listing go to Review, and
   evidence can still update them.
5. **The owner editor** in the business dashboard (description, hours, services, photos), within `ListingSafety` and
   the existing field locks.
6. **Activation:** the plan step, with the promotional trial.
7. **Retire Tier 1** (`listing/api/verify/tier1.php`). The email code is now step 1 of the claim.
8. **The FAQ:** claiming, the text step, the admin call, Nexus, plans and disputes.

## Consequences
- Owners get in within minutes. ✓ Verified means the listed phone texted us, or an admin confirmed the person.
- The fields that could misdirect customers can't be changed by an unconfirmed claim alone.
- Admin load in the pilot is mostly the 48-hour calls. The queue makes them the first thing staff see.
- There's one record of ownership (`listing_access`) going forward. No claims exist yet, so there's nothing to
  migrate.

## Open
- The 7-day dispute answer is a first default.
