# Platform atlas: identity-trust-nations

*File: `research/platform-atlas/identity-trust-nations.md`. Draft for human review.*

**Scope and sources.** This area covers who people are (accounts, sign-in, age, consent), who businesses are (claims, verification, listing identities, duplicates, trust), and how tribal nations' land is handled.

Sources used:
- **Code:** `Auth.php`, `GoogleAuth.php`, `SocialAuth.php`, `WebAuthn.php`, `AgeGate.php`, `Consent.php`, `MemberVerification.php`, `StepUpAuth.php`, `Verification.php`, `VerifyRequests.php`, `TrustScore.php`, `IdentityJobs.php`, `WebIdentity.php`, `crawler/Verify.php` and `ListingIdentity.php`. All are under `sync-app/lib/`.
- **Docs:** the charter, `brand.md`, `prd.md`, `site-audit/pages.md`, and decisions 0015, 0058, 0062, 0133 and 0164.

**Partly shown:**
- `ListingIdentity.php` is cut at line 491.
- 0164 is cut inside R17. 0133 is cut inside its build list. 0058 is cut inside §24.

**Not shown:** `Identity.php`, `Claims.php`, `TwoStep.php`, `RegionGate.php`, `AccountDeletion.php`, `SignInLinks.php`, `Acceptance.php`, and any `Nations`, `Landmarks` or `Jurisdictions` code. No page templates are shown, and no endpoint files.

---

## (a) What each person can do, page by page

Routes and statuses come from `site-audit/pages.md` unless a file is cited.

### Resident or traveler (guest)

- **`/register.php`** creates an account. The person must be 18 or older and give consent (pages.md §2).
  - Server rules: a valid email and a strong password (`Auth.php:88-131`, `60-77`).
  - The confirmation link lasts a day (`Auth.php:128`).
- **`/login.php`** signs in. An unconfirmed email is refused with a "send it again" offer (`Auth.php:236-239`).
- **Password and email recovery:**
  - `/forgot-password.php` and `/reset-password.php`. The reset token lasts 30 minutes (`Auth.php:417`).
  - `/verify-email.php` confirms an email.
  - `/email-revert.php` undoes an email change.
- **Social sign-in:** "Continue with Google / Apple / Microsoft / Facebook" returns to `/api/auth/<provider>.php` (`GoogleAuth.php:18-19`, `SocialAuth.php:23-25`).
  - The buttons show only once keys and a migration exist (`GoogleAuth.php:31-39`, `SocialAuth.php:42-49`).
  - New accounts finish on `/register.php?social=1` (`SocialAuth.php:167`).
- **`/two-step.php`** is two-step sign-in (Live). Its logic is in `TwoStep.php`, which is not shown.
- **`/place/landmark.php?id=`** shows a natural landmark (0058 §24):
  - "On \<nation\> land", the nation's note, and "Leave it as you found it".
  - A landmark on a nation's land shows only after an Editor approves it.
- **`/takedown.php`** asks Traversence to take something down (0133 item 11).
  - Open to guests, with a captcha and a limit of 5 a day.
  - A protected reason hides the item at once.
- **`/listing/location.php?id=`** lets a visitor suggest a pin fix without signing in. A person reviews it (0058 §15).
- **"Still open?" and "Is this right?"** on listings are decided in 0164 R12. Their build state is not shown.

### Member

- **`/user/dashboard.php`** (Privacy Settings → Your tools):
  - Consent tools: Linking, Address Book, AI Assist & Learning, analytics, video (`Consent.php:15-70`).
  - The Verified status and its checks (`MemberVerification.php:48-63`).
  - Connect a sign-in provider (`SocialAuth.php:153-158`, which returns to `#security`).
- **`/user/profile.php?id=`** shows the ✓ Verified mark when the email is confirmed and the 18+ tick is done (`MemberVerification.php:66-70`).
- **Account close and restore:**
  - `/user/delete-account.php` and `/data-deletion.php`.
  - `/user/restore-account.php`: a closing account is held there instead of signing in (`Auth.php:244-249`, `GoogleAuth.php:193-200`).
- **`/user/notice.php`** is where a suspended member gets the review link (`Auth.php:240-242`).
- **Passkeys** are registered and checked in `WebAuthn.php`. The page and route are not shown.

### Business

- **`/claim.php?listing=`** claims a listing:
  - email code (Managed by owner);
  - text code to the Verify number (✓ Verified);
  - `&dispute=1` questions a listing's current owner;
  - `?new=1` adds a business;
  - `&mode=contribute` suggests details.
- **`/listing/businessportal.php`** shows claims and their state, the plan, and the listing editor.
  - "Send verification request" emails every admin a single-use link (`VerifyRequests.php:37-64`).
- **`/verify-listing.php`** is where an admin confirms that request, or replies "Not yet" with a note (`VerifyRequests.php:82-99`).
- **Evidence offers.** When the evidence differs from a claimed listing, the owner sees "We found other information about your business", with Use this or Dismiss. This uses `listing/api/evidence.php` (0062, "evidence wins, unless claimed").
- **`/listing/api/*` endpoints:** claim, evidence, update-metadata, my-listings, vouch, and referral (Tiers 2–3 are "not offered"). `verify/tier1` answers 410.

### Admin and staff

| Route | What it does |
|---|---|
| `/admin/claims.php` | Claim queue and disputes. Documents are kept outside the webroot and deleted once decided. |
| `/admin/identities.php` | Facts by the 5 W's, with their sources |
| `/admin/helper.php` | Ask → Decide → Act, one item at a time |
| `/admin/landmarks.php` | Waiting, Shown, Approved, Hidden |
| `/admin/nations.php` | "Tribal nations and their visitor rules" |
| `/admin/crawler-review.php` | Crawler review |

All are Live. Admin pages need `is_admin` plus two-step (`Auth.php:336-345`).

---

## (b) How it works underneath

### Accounts and sessions (`Auth.php`)

- Passwords are hashed with Argon2id, or bcrypt where Argon2id isn't available (`35-43`).
- Login does not reveal which emails exist (`224-228`). Session ids are regenerated at sign-in (`258`).
- **Forced sign-out:** `session_version` is checked on every request (`312-315`). Password change and reset bump it and forget trusted devices (`382-385`, `426-428`).
- **Email confirmation** adds +10 user trust (`194-198`; `TrustScore.php:48`).
- **Placeholder role:** a new user's `role` falls through to `'business_owner'`, flagged in the code as unresolved (`118-123`).

### Social sign-in

**Google** (`GoogleAuth.php`):
- Asks only for `openid email profile` (`63-66`).
- The ID token comes back through the page. Its signature is checked against Google's keys, along with nonce, issuer, audience, expiry and a confirmed email (`74-92`, `111-124`).
- An existing account whose email was never confirmed loses its password and sessions before being linked (`170-176`).

**Apple, Microsoft and Facebook** (`SocialAuth.php`):
- Each provider's answer is checked (`174-208`).
- Microsoft work or school emails count as unconfirmed unless the organisation owns the domain (`200-203`).
- An unconfirmed provider email never opens an existing account (`289-292`).
- New accounts:
  - pass 18+ (`114`) and the region gate (`116-120`, `RegionGate` not shown);
  - record `AgeGate::confirm` (`129`) and consent choices (`131-135`);
  - get the usual confirmation email when the provider's email isn't confirmed (`356-364`).

### Passkeys (`WebAuthn.php`)

- The relying-party id is the site's own host (`13-17`).
- The person must be verified on the device (`64-65`).
- ES256 and RS256 keys are accepted (`102-117`).
- A sign counter going backwards is rejected as a possible copy (`97`).

### Age and member verification

- **`AgeGate.php`:** 18+ is stored in `age_confirmations` and required before commenting or contributing (`48-51`).
- **`MemberVerification.php`:**
  - "Consented" now means only that the email is confirmed (`30-34`), per Jason's 2026-11-05 amendment in 0062.
  - Roles that need it: contributor, owner, steward, staff (`19-24`).
  - The phone check is "coming" (`57`).

### Consent (`Consent.php`)

- One record per person per tool (`138-156`), plus a log row (`152-153`).
- The exact wording of each version is kept in `consent_wordings` (`160-169`).
- Cookie tools are off until someone says yes (`101-108`).

### Claims and business verification

**`StepUpAuth.php`:**
- 6-digit one-time codes, stored as hashes, lasting 10 minutes, used once, with an attempt cap (`25-52`, `86-94`).
- Purposes: `claim`, `password_change`, `email_change`, `tier1_verify` (`111`).

**`Verification.php`** has four independent tiers, each setting `status='verified'`:

| Tier | Route | Lines |
|---|---|---|
| 1 | Self-confirm OTP | `36-46` |
| 2 | Three referrals; also auto-flags for admin review | `57-92` |
| 3 | Three vouchers | `99-130` |
| 4 | Admin | `137-151` |

The trust score records *how* a listing was verified, never *whether* (`13-14`).

**`VerifyRequests.php`** (0071 §4):
- Each admin gets their own link: hashed, valid 14 days, used once (`53-60`).
- Confirming takes a deliberate POST, so an email scanner can't verify anything (`12-13`).
- Confirming closes the other admins' links (`90`).

**`TrustScore.php`:**
- The point economy for users and listings.
- A listing is suppressed below 0, and suppression never lifts automatically (`27-38`, `180-204`).
- Several point values are marked "NOT YET WIRED" (`49-50`, `72-74`, `89`).

**Claims and disputes logic** lives in 0015. Parts of it are superseded by 0070, which is not shown. `Claims.php` is not shown.

### Listing identities: facts with their sources

**`IdentityJobs.php`:**
- Queues listings with no phone or website (`45-59`), and listings whose only "website" is a social page (`67-93`).
- Checks our own copies first: the NPI Registry and Recreation.gov (`143-171`).
- Google, Yelp, Facebook and Instagram are evidence only. They can confirm a value, never supply one (`32`, `229-233`).
- A confidential listing's address is never sent out (`173-193`).
- A value goes onto the listing only when it is trusted and the listing's field is empty or unlocked (