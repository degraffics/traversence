# ADR 0029: Admin Auth Uses Shared Session, Not a Separate Login System

**Status:** Accepted (2026-09-24)

## Context

`routes.md` has carried `admin/login.php` as "Scoped — open decision: shared session auth vs. separate login" since the route table was first built. A direct read of the real `api/lib/Auth.php` and `admin/adminportal.php` (found in the live export) shows the shared-session answer was already the implicit design before this ADR — `adminportal.php`'s own placeholder comment says to protect the real page with `Auth::requireAdmin()`, and its client-side check reads `is_admin` off the same `/api/auth/session.php` response every ordinary user session uses. This ADR makes that explicit and resolves the routes.md open item, rather than introducing a new mechanism.

## Decision

### 1. Shared session auth, not a separate login system — confirmed against the real code, not just architectural preference

There is one `users` table, one session mechanism (`Auth::attemptLogin()`, native PHP sessions), and one login endpoint (`login.php` → `POST /api/auth/login.php`). Admin status is not a separate account type or a separate credential store — it's `is_admin` (a stored column on `users`, deliberately decoupled from `role` per `Auth.php`'s own class doc-comment) plus, for the newer hub-scoped tiering, `admin_access` (`super_admin`/`regional_admin`, `architecture.md` §11) — both grants layered on the same identity a regular user already has. An admin who is already logged into their account already carries everything `Auth::requireAdmin()` / `Auth::requireHubAdmin()` / `Auth::requireSuperAdmin()` need to check, within the same session — there is no separate admin session, no separate admin cookie, and no second credential to manage. Running a parallel login system would fragment that single source of truth and add a second cookie/session lifecycle to keep in sync with the first, for no real security gain — admin-ness here is an attribute of the account, not a different kind of account.

### 2. What `admin/login.php` actually does, concretely — three cases, not one generic "auth entry"

- **Not authenticated:** renders a standard login form that posts to the exact same `/api/auth/login.php` → `Auth::attemptLogin()` endpoint every other login already uses — not a second credential-verification code path. On success, the now-authenticated session either has `is_admin`/`admin_access` (proceeds into the admin area) or doesn't (falls into the case below).
- **Already authenticated, not an admin:** shows the same "you're signed in, but this account is not an admin" message `adminportal.php`'s own placeholder already displays — no login form, since there's no separate admin credential to enter, and no hidden elevation path for a non-admin account.
- **Already authenticated as an admin:** `admin/login.php` is a no-op redirect straight into `admin/adminportal.php`. The active session already satisfies `Auth::requireAdmin()`; there is no second "log in again, as admin" step layered on top of an already-valid session.

This resolves the routes.md placeholder's own literal wording ("either elevates the active session or authenticates against the master users table directly") into the three concrete cases above.

### 3. Route protection — this reuses `decisions/0022`'s existing rule, not a new one

Every admin route, including bare stub placeholders, already sits behind `Auth::requireHubAdmin()` / `Auth::requireSuperAdmin()` / `Auth::requireAdmin()` from its first commit (`decisions/0022`). `admin/login.php` is the one admin-area route that's intentionally reachable while unauthenticated — it has to be, to let an admin who isn't logged in yet actually log in — and everything past it stays behind the standing per-route guard `decisions/0022` already mandates. No new route-protection mechanism is introduced by this ADR.

### 4. Correcting the "self-healing" claim precisely — and a real gap this surfaced

`Auth::currentUser()`'s self-healing is real (`decisions/0026` already cites it: "already self-heals if a session outlives its user row"), but it's narrower than "if a user row is suspended or modified mid-session, the session drops gracefully" suggests. **A direct read of `currentUser()` in the real `Auth.php` shows it never selects `is_suspended` at all.** Its only two self-heal checks are: the row being entirely missing (an actually-deleted user — `$user === false`), and a `session_version` mismatch (used for password-change/email-revert invalidation). The function's own inline comment — `// User was deleted/suspended mid-session — treat as logged out.` — is aspirational, not accurate: a user whose row still exists with `is_suspended = 1` fails neither check and keeps a fully working, unaffected session, admin or not, until they log out on their own or `session_version` happens to get bumped for an unrelated reason.

This matters directly for the scenario an admin-login design exists to guard against: an admin account suspended mid-session — a compromised or rogue admin caught and suspended by a super_admin, or a `decisions/0019` DMCA 3-strike suspension, or a `decisions/0026` legal-hold suspension — keeps fully working admin access under the code as it stands today, rather than dropping gracefully as the comment claims.

**Fix, closing the gap rather than just describing it:** `currentUser()`'s SELECT adds `is_suspended`, and the function returns `null` (triggering `self::logout()`, the same pattern already used one branch below it for the `session_version` check) when `is_suspended = 1`. This is a small, mechanical change in the same function, using the same self-heal pattern already established there — it's what actually makes the Unified State / Self-Healing argument for shared session auth true, for every session on the platform, not just admin ones, rather than leaving a real user-facing security gap standing on an inaccurate code comment.

### 5. Explicitly not decided here: a step-up OTP on admin login

The platform already has a generic step-up mechanism (`StepUpAuth.php`, used for claim confirmation, password-change re-verification, and email-change proof-of-destination). Layering that same mechanism onto `admin/login.php` — extra friction proportional to what admin access can do, even within an already-valid session — is a real, reasonable option, and would reuse existing infrastructure rather than build new. It's flagged here as worth a look, not decided: the fix in item 4 removes the specific gap that would most motivate it (a suspended admin's session no longer silently persists), so this becomes a defense-in-depth call rather than a gap-closing one, and is left open rather than bundled into this ADR's shared-vs-separate resolution.

## Consequences

`routes.md`'s `admin/login.php` row is updated to record this resolution and the three-case behavior in item 2, closing the "open decision" it previously carried. `architecture.md` §11 gets the `is_suspended` gap in `currentUser()` recorded as a real, live fix to make, not a hypothetical. `decisions/0019` and `decisions/0026` are unaffected in their own logic (suspension is still set the same way, via the same `is_suspended` flag) — this ADR changes when that flag actually takes effect against an already-open session, not how or why it gets set.
