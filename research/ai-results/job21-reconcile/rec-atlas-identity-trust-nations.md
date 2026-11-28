# Platform atlas: identity-trust-nations (merged from two passes)

*Draft for human review. In this entry P1 means pass 1 and P2 means pass 2. Unmarked items are supported by both passes. Where the two passes cite different line ranges for the same claim, both ranges are given. Items only one pass supports are marked **single-pass**.*

**Both source texts are truncated:**
- P1 stops partway through (b), at the IdentityJobs paragraph.
- P2 stops partway through (c), at the 0091 row.
- **Neither pass contains sections (d) or (e).** They cannot be merged without inventing content, so they are left empty below.

**Scope and sources**
- **Area covered:** accounts, sign-in, age, consent, business claims and verification, listing identities, duplicates, trust, and tribal nations' land. The scope sentence is single-pass (P1). P2 covers the same ground implicitly.
- **Code location:** passes differ.
  - P1 says `sync-app/lib/`.
  - P2 says `traverse-3way-sync/sync-app/lib/`.
- **Code files P1 lists:** `Auth`, `GoogleAuth`, `SocialAuth`, `WebAuthn`, `AgeGate`, `Consent`, `MemberVerification`, `StepUpAuth`, `Verification`, `VerifyRequests`, `TrustScore`, `IdentityJobs`, `WebIdentity`, `crawler/Verify`, `ListingIdentity`. P2 cites all of these too.
- **Docs P1 lists:** charter, `brand.md`, `prd.md`, `site-audit/pages.md`, and decisions 0015, 0058, 0062, 0133 and 0164.
- **Partly shown:**
  - `ListingIdentity.php` is cut at line 491.
  - 0164, 0133 and 0058 are truncated. Where each one stops is single-pass (P1): 0164 inside R17, 0133 inside its build list, 0058 inside §24.
- **Not shown, according to both passes:** `Claims.php`, `Identity.php`, `TwoStep.php`, `RegionGate.php`, the Nations, Landmarks and Jurisdictions code, all page templates, and decision 0070.
- **Not shown, single-pass (P1):** `AccountDeletion.php`, `SignInLinks.php`, `Acceptance.php`, and the endpoint files.
- **Not shown, single-pass (P2):** decision 0066 (nations).

---

## (a) What each person can do, page by page

### Resident or traveler (signed out)

**Account pages**
- `/register.php`, `/login.php`, `/forgot-password.php`, `/reset-password.php`, `/verify-email.php`, `/email-revert.php` and `/two-step.php`.
  - Source: pages.md. Passes differ on the section: P1 says §2, P2 says §1–2.

**Registration**
- Accounts are 18+ only.
  - P1 cites pages.md §2, which also says consent is required.
  - P2 cites `AgeGate.php:12-13`.
- Registration has server-side rules (`Auth.php:88-131`, `60-77`).
- The confirmation link lasts one day (`Auth.php:128`).

**Sign-in**
- Sign-in is refused until the email is confirmed (`Auth.php:236-239`).
- The refusal offers to send the confirmation again. *Single-pass (P1).*
- The reset token lasts 30 minutes (`Auth.php:417`). *Single-pass (P1).*
- `/two-step.php` is Live. Its logic is in `TwoStep.php`, which is not shown.

**Social sign-in (Google, Apple, Microsoft, Facebook)**
- It only appears when it is switched on.
  - P1 says this means keys plus a migration exist: `GoogleAuth.php:31-39`, `SocialAuth.php:42-49`.
  - P2 cites `GoogleAuth.php:31-39`, `SocialAuth.php:30-34`.
- Each provider returns to `/api/auth/<provider>.php`.
  - P1 cites `GoogleAuth.php:18-19` and `SocialAuth.php:23-25`.
  - P2 cites `SocialAuth.php:60`.
- These routes are not listed in the pages register's `/api/auth/*` row. *Single-pass (P2).*
- New social accounts finish on `/register.php?social=1` (`SocialAuth.php:167`). *Single-pass (P1).*

**Passkeys**
- Passkeys are handled in `WebAuthn.php`. The page and route are not shown.
- Passes differ on the audience: P1 lists passkeys under Member, P2 under signed-out visitors.

**Landmarks** (`/place/landmark.php?id=`, 0058 §24)
- A landmark on a nation's land shows "On <nation> land" and the nation's note.
- Passes differ on what else the page shows:
  - P1 says "Leave it as you found it".
  - P2 says that nation's visitor rules.
- A landmark on a nation's land appears only after Editor approval. P2 gives the Editor's tick-box wording as "The nation welcomes visitors here".

**Takedown** (`/takedown.php`, 0133 item 11)
- Open to guests, with a captcha and a limit of 5 a day. *Single-pass (P1).*
- A protected reason hides the item at once. *Single-pass (P1).*

**Pin fix** (`/listing/location.php?id=`)
- No sign-in is needed (0058 §15).
- A person reviews the suggestion. *Single-pass (P1).*

**Listing signals under 0164 R12.** Passes differ:
- P1 says R12 covers "Still open?" and "Is this right?", with build state not shown.
- P2 says R12 is a claimed or verified state shown on `/listing/view.php?id=`.

**Profile mark**
- A ✓ Verified mark appears on `/user/profile.php?id=` when the email is confirmed and 18+ is ticked.
  - P1 cites `MemberVerification.php:66-70`.
  - P2 cites `MemberVerification.php:65-70`.
- P1 files this under Member, P2 under public trust signals.

### Member (signed in)

**Dashboard** (`/user/dashboard.php`, Privacy Settings → Your tools)
- It lists each consent tool and its state. Passes differ on the citation:
  - P1 cites `Consent.php:15-70` and names the tools: Linking, Address Book, AI Assist & Learning, analytics, video.
  - P2 cites `Consent.php:171-183`.
- Verified status and its checks.
  - P1 cites `MemberVerification.php:48-63`.
  - P2 cites `MemberVerification.php:47-63`.
- Members can connect another sign-in provider (`SocialAuth.php:153-158`).
  - This returns to `#security`. *Single-pass (P1).*

**Account pages**
- `/user/delete-account.php` and `/user/restore-account.php`.
- `/user/notice.php`, the suspension notice with the review link (`Auth.php:240-242`).
- `/data-deletion.php`. *Single-pass (P1).*
- `/user/accounts.php`, `/user/transfer.php`, `/user/staff-link.php`. *Single-pass (P2).*
- A closing account is held at the restore page instead of signing in (`Auth.php:244-249`, `GoogleAuth.php:193-200`). *Single-pass (P1).*

**Roles that need a confirmed email** (`MemberVerification.php:19-24`)
- Passes differ on how the roles are named:
  - P1 says contributor, owner, steward, staff.
  - P2 says become a contributor (`/user/journeys/`), start a community (`/community/`), claim a business, join staff. P2 also cites `:40-45`.

**Commenting and contributing** need the 18+ confirmation.
- P1 cites `AgeGate.php:48-51`.
- P2 cites `AgeGate.php:47-51`.

### Business

**Claiming** (`/claim.php?listing=`, from pages.md)
- An email code gives "Managed by owner".
- A text code to the Verify number gives "✓ Verified".
- `&dispute=1` questions a listing's current owner.
- `?new=1` adds a new business.
- `&mode=contribute` suggests details.
- The page code is not shown.

**Business portal** (`/listing/businessportal.php`)
- It shows each claim and where it stands.
- "Send verification request" emails every admin a single-use link.
  - P1 cites `VerifyRequests.php:37-64`.
  - P2 cites `VerifyRequests.php:36-64`.
- A request can only be resent after 24 hours (`:45`). *Single-pass (P2).*
- The portal also shows the plan and the listing editor. *Single-pass (P1).*

**Evidence offers**
- When the evidence differs from a claimed listing, the owner sees "We found other information about your business", with Use this or Dismiss.
- This follows 0062 ("evidence wins, unless claimed") and uses `listing/api/evidence.php`.

**