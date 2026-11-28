# Platform atlas: identity-trust-nations

*Draft for human review. Written from the attached documents and code only. Citations use `file:line` for code (the paths are under `traverse-3way-sync/sync-app/lib/`) and `doc §` for decisions.*

**What is only partly shown:**
- `ListingIdentity.php` stops at line 491.
- Decisions 0164, 0133 and 0058 are truncated.
- These are **not shown**: `Claims.php`, `Identity.php`, `TwoStep.php`, `RegionGate.php`, `Nations`, `Jurisdictions`, `Landmarks`, every page template (`register.php`, `claim.php`, the dashboard and so on), decision 0066 (nations) and decision 0070 (claims).

---

## (a) What each person can do, page by page

### Resident or traveler (signed out)
- **Create an account and sign in.**
  - Pages: `/register.php`, `/login.php`, `/forgot-password.php`, `/reset-password.php`, `/verify-email.php`, `/email-revert.php` and `/two-step.php` (`site-audit/pages.md` §1–2).
  - Accounts are for people 18 or older (`AgeGate.php:12-13`).
  - Sign-in is refused until the email is confirmed (`Auth.php:236-239`).
- **Sign in through another provider, where it is switched on:**
  - "Continue with Google" (`GoogleAuth.php:31-39`).
  - Apple, Microsoft and Facebook (`SocialAuth.php:30-34`).
  - Each provider's endpoint is `/api/auth/<provider>.php` (`SocialAuth.php:60`). These routes are **not listed** in the pages register's `/api/auth/*` row.
- **Use passkeys** (`WebAuthn.php`). The page and endpoint routes are **not shown**.
- **See trust signals on content pages:**
  - a ✓ Verified mark on a member's public profile, `/user/profile.php?id=` (`MemberVerification.php:65-70`);
  - a claimed or verified state on a listing, `/listing/view.php?id=` (0164 R12).
- **Visit natural landmarks** at `/place/landmark.php?id=`.
  - A landmark on a nation's land shows "On <nation> land", the nation's note, and that nation's visitor rules (0058 §24).
- **Ask for something to be taken down** at `/takedown.php` (0133 item 11).
- **Suggest a pin fix** at `/listing/location.php?id=`. No sign-in is needed (0058 §15).

### Member (signed in)
- **Dashboard** at `/user/dashboard.php`.
  - Privacy Settings → Your tools lists each consent tool and its state (`Consent.php:171-183`).
  - Verification shows as a status with its checks (`MemberVerification.php:47-63`).
  - Other sign-ins can be connected from Privacy (`SocialAuth.php:153-158`).
- **Account pages:**
  - `/user/accounts.php`, `/user/delete-account.php`, `/user/restore-account.php`, `/user/transfer.php`, `/user/staff-link.php`;
  - `/user/notice.php`, the suspension notice (`Auth.php:240-242`).
- **Roles that need a confirmed email** (`MemberVerification.php:19-24, 40-45`):
  - become a contributor (`/user/journeys/`);
  - start a community (`/community/`);
  - claim a business;
  - join staff.
- **Commenting and contributing** also need the 18+ confirmation (`AgeGate.php:47-51`).

### Business
- **Claim a listing** at `/claim.php?listing=` (pages.md §1). Routes on this page:
  - an email code gives "Managed by owner";
  - a text code gives "✓ Verified";
  - `&dispute=1` questions a listing's current owner;
  - `?new=1` adds a new business;
  - `&mode=contribute` suggests details.
  - The page code is **not shown**.
- **Business portal** at `/listing/businessportal.php`. It shows:
  - each claim and where it stands;
  - "Send verification request" (`VerifyRequests.php:36-64`);
  - a 24-hour wait before the request can be resent (`VerifyRequests.php:45`).
- **"We found other information about your business."** On a claimed listing, evidence is only offered, with Use this or Dismiss (0062, "evidence wins, unless claimed"; `listing/api/evidence.php`).
- **Move the pin** and change the address. These fields are then locked against the crawler (0058 §15).

### Admin
These pages are context only:
- `/admin/claims.php`, `/admin/identities.php`, `/admin/helper.php`;
- `/admin/nations.php` (tribal nations and their visitor rules);
- `/admin/landmarks.php`;
- `/verify-listing.php?t=`, the admin's one-tap confirm link (`VerifyRequests.php:56`).

---

## (b) How it works underneath

### Accounts and sessions (`Auth.php`)
- **Registration** (`Auth.php:88-131`):
  - hashes the password with Argon2id where available, otherwise bcrypt (`:35-38`);
  - enforces a server-side password policy (`:60-77`);
  - uses a vague error for a duplicate email (`:101-104`);
  - records acceptance of the Terms (`:126`);
  - issues a confirmation link that lasts one day (`:128`).
- **The `role` column** is deliberately left at its default `business_owner`. The code marks this as an open product question (`:118-123`).
- **Email confirmation** sets `email_verified_at` and adds +10 to the user's trust score (`:194-198`).
  - The confirmation tokens are stored **in plain text** in `email_confirmations` (`:150-160`).
  - By contrast, `StepUpAuth` and `VerifyRequests` store only hashes (`StepUpAuth.php:38`, `VerifyRequests.php:55`).
- **Session invalidation** uses `session_version` (`:312-315`). The version is bumped on a password change or reset, and trusted devices are forgotten (`:381-385, 426-428`).
- **Admin access** requires `is_admin` and then passes the `TwoStep` gate (`:336-345`). `TwoStep` is not shown.

### Step-up codes (`StepUpAuth.php`)
- Six-digit codes, hashed, valid 10 minutes, single use, with a limit on attempts (`:25-26, 86-104`).
- Purposes: `claim`, `password_change`, `email_change`, `tier1_verify` (`:111`).

### Social sign-in (`GoogleAuth.php`, `SocialAuth.php`)
- **Google's ID token** is checked against Google's published keys, plus nonce, issuer, audience and expiry (`GoogleAuth.php:74-124`).
- **Protection against address takeover:** if an existing account's email was never confirmed, its password is cleared and its sessions are ended before it is linked (`GoogleAuth.php:170-176`; `SocialAuth.php:296-300`).
- **Microsoft work or school accounts** whose email is not verified never open an existing account (`SocialAuth.php:201-203, 289-292`).
- **New social sign-ups** must tick 18+ (`SocialAuth.php:114`) and pass a region gate (`:116-120`, decision 0027). Age confirmation is then saved (`:129`).
  - `GoogleAuth::create` (`:211-227`) does not call `AgeGate` itself. The Google endpoint that might do so is **not shown**.

### Passkeys (`WebAuthn.php`)
- Only ES256 and RS256 keys are accepted (`:102-116`).
- The device must report user verification (`:65`).
- A sign counter that goes backwards is rejected (`:97`).
- The challenge lifetime is 300 seconds (`:10`), but the file header says "two minutes" (`:5`).

### Age gate (`AgeGate.php`)
- An older 13+ confirmation is replaced by an 18+ one (`:42-44`).
- If the `age_confirmations` table is missing, the gate treats everyone as confirmed, so it **fails open** (`:32`).

### Consent (`Consent.php`)
- Each tool keeps its consent version (`:15-70`).
- Every switch on or off is written to `tool_consent_log` (`:152-153`).
- The exact wording of each version is kept in `consent_wordings` the first time anyone agrees to it (`:158-169`).
- The two cookie tools (analytics, video) are off by default (`:50-69, 101-108`).

### Member verification (`MemberVerification.php`)
- "Verified" means a confirmed email and an 18+ confirmation (`:65-70`).
- The role gate checks the email only (`:30-45`).
- The phone check is marked "coming" (`:57`).

### Listing verification, the legacy tiers (`Verification.php`, `TrustScore.php`)
- Four routes each set `verified` independently (`Verification.php:36-151`).
- `trust_score` records how a listing was verified, never whether (`:11-14`).
- Bonus sizes are in `TrustScore.php:56-86`.
- Suppression only ever switches on, never off by itself (`TrustScore.php:27-38, 187-190`).
- `/listing/api/verify/tier1` now answers 410, retired by decision 0070 (pages.md §6). The current claim model is in `Claims.php`, which is **not shown**.

### Admin verify-by-email (`VerifyRequests.php`)
- Each admin gets their own link, 14 days, single use, stored as a hash (`:17, 52-62`).
- Confirming takes a deliberate POST (`:9-13`).
- Confirming calls `Claims::verify` and closes the other admins' links (`:88-90`).

### Listing identity and evidence
- **IdentityJobs** queues listings with no phone or website (`IdentityJobs.php:45-59`). It looks in our own copies first, NPI and Recreation.gov (`:143-171`).
- **Evidence-only hosts** (Google, Yelp, Facebook, Instagram) never supply a value (`:32, 229-230`).
- **A confidential address** is never sent to the worker (`:191`).
- **A value goes onto the listing** only when the listing has none and the source is trusted (`:253-260`).
- **Verify.php** is the crawler's second look. A confidential service is checked in our own records only (`crawler/Verify.php:100-104`).
- **Source trust weighting** follows decision 0157 §5 (`crawler/Verify.php:208`).
- **WebIdentity** separates brand from page (`WebIdentity.php:4-17`). Social and directory hosts carry no identity (`:23-25`).
- **ListingIdentity** handles duplicates (`ListingIdentity.php`):
  - one shared match function with a ZIP lock (`:237-276`);
  - a claimed listing is never the one hidden in a merge (`:386`);
  - the engine never merges claimed listings or anything a person entered (`:387`);
  - a person's "different businesses" verdict is permanent (`:318, 323-326, 388`);
  - an exact Undo exists (`:453-482`);
  - the background recheck shipped switched off (`:19-20`).

### Nations
- **Rules:**
  - Landmarks on a nation's land wait until an Editor ticks "The nation welcomes visitors here" (0058 §24).
  - A landmark held on tribal land gets no identity card and nothing is stored (0062 step 6).
  - The 0164 R7 tribal-land rules and R8 layered jurisdiction register apply.
- **Code:** `Nations`, `Jurisdictions`, `Landmarks::decide` and `/admin/nations.php` are **not shown**.

---

## (c) Decisions

| ADR | What it rules |
|---|---|
| 0005 | No automated consequence without a person's sign-off. Cited in 0015; text not shown. |
| 0014 | Email must be confirmed before sign-in (`Auth.php:236`). |
| 0015 | Claim and the four verification tiers, plus disputes with 48-hour and 7-day clocks and an editing freeze. §1, §5 and the tiers are superseded or amended by 0070. |
| 0016 / 0017 / 0018 | Admin claims queue with a manual phone check, document upload, and managers through `listing_access`. Referenced in 0015; texts not shown. |
| 0027 | International registration gate (`SocialAuth.php:115-120`). |
| 0049 / 0051 / 0052 | Tool activation and consent, including AI & Learning (`Consent.php:6`). |
| 0058 | Public profile (§1); adult accounts 18+ (§4, though code cites "§7"); who may move a pin (§15); landmarks and nation land (§24). |
| 0062 | One identity per thing, every fact with its sources; people verified only with consent; "evidence wins, unless claimed". Amended 2026-11-05: verified = email + 18+. |
| 0066 | Tribal nations and visitor rules. Not shown. |
| 0070 | Claim page and tiers rewritten; tier1 retired. Not shown. |
| 0071 §4 | "Send verification request" (`VerifyRequests.php:8`). |
| 0079 | Suspension notice (`Auth.php:241`). |
| 0083 / 0086 | Website brand vs page; a social page is not a website. |
| 0085 / 0090 | Google, then Apple, Microsoft and Facebook sign-in. |
| 0091 | Closing-account hold (`