# ADR 0026: Data Subject Access Request (DSAR) Export & Erasure Pipeline

**Status:** Accepted (2026-09-24)

## Context

`architecture.md` §24 flagged formal data subject access/export/deletion request handling as undesigned — only account deletion (§14) existed, with no export mechanism and no verified-intake path for a request submitted outside an active logged-in session. This is worth building regardless of `decisions/0025`'s GDPR/CCPA jurisdictional-scope conclusions: a growing number of U.S. state privacy laws, beyond CCPA, already expect equivalent access/export/deletion rights, so this pipeline isn't contingent on the international-scope question at all.

## Decision

### 1. Intake & verification — reuses the existing generic token mechanism, not a new one

`Auth.php`'s `email_confirmations` table (already used for `registration`, `password_reset`, `ownership_change`, `claim`) gains two new `action_type` values: `dsar_export` and `dsar_deletion`. A logged-in user's request is verified by their existing session. An anonymous request (a public form or a `privacy@` mail route) is verified via the same lookup-by-email, don't-reveal-existence pattern already used in `Auth::requestPasswordReset()` — a token is issued and emailed regardless of whether the account exists, and nothing executes until that token is confirmed. This is a two-value addition to an existing enum, not a new verification mechanism.

### 2. Data scope — the real table inventory, enumerated against the actual schema

Checked directly against `Auth.php`'s own documented column list and the other reviewed code — not the generic "two buckets" framing this ADR started with. Note first what does **not** exist: `users` has no generic `status` column (that enum lives on `entities`, a different table), and there is no `user_sessions` table at all — this platform uses native PHP sessions (`session_start()`/`$_SESSION`), not a database-backed session store, so there is nothing there for this pipeline to touch. `Auth::logout()` already tears the session down, and `Auth::currentUser()` already self-heals if a session outlives its user row. There is also no `claims` table — ownership lives on `entities.owner_user_id` directly — and no `user_roles`/`memberships` table, since Communities & Groups has no schema yet at all (`architecture.md` §27, `prd.md` Tier 2.7).

The real inventory:

- **Hard delete outright** (transient security tokens, not content — no legal-retention reason to anonymize-and-keep either one): `email_confirmations`, `user_otp_challenges`.
- **Anonymize to the existing `DELETED_USER_UUID` placeholder** (never `NULL` — that risks breaking a NOT NULL foreign-key constraint — and never an ad hoc string like `[Deleted User]`, which would be a second, competing convention): `users` itself (respecting `is_suspended`/legal-hold per item 5 below before anonymizing), `entities.owner_user_id` (the entity/listing itself is retained permanently per `architecture.md` §14 — only the owner reference anonymizes), `verification_attempts.reviewed_by_user_id`, `disputes.filed_by_user_id`, `listing_access` (owner/manager grants), `admin_access`.
- **Retain with no user reference at all:** `dsar_logs` — see item 6 below; this table is deliberately designed so it can never itself become personal data requiring a future erasure request.
- **Not yet applicable:** any Communities & Groups membership table — there is no schema to enumerate against yet, and this list should be revisited once `architecture.md` §27's design pass actually happens.

### 3. Export pipeline

A script queries by `user_id` across the identified tables, formats the result as JSON or CSV, and delivers a secure, time-limited download link to the verified email. This is the genuinely new piece — no export mechanism existed anywhere in the platform before this ADR.

### 4. Erasure pipeline — synchronous, not cron-first; reuses existing mechanisms; two triggers into one destination

On confirmation of a `dsar_deletion` token, erasure executes synchronously inside the same transaction that marks the token confirmed — mirroring `Auth::confirmEmailToken()`'s existing pattern of firing side effects (`TrustScore::adjustUserTrust()`) inside the same transaction, not deferred to a periodic cron. A cron may still run as a backstop catching anything verified-but-somehow-not-executed, but it is not the primary trigger — a nightly batch job would itself reintroduce the delay risk this design exists to avoid.

Erasure reuses the existing `DELETED_USER_UUID` anonymization convention (`architecture.md` §14) rather than inventing a new placeholder. This creates two distinct triggers into the same underlying mechanism: a user's own self-service "delete my account" action through the UI keeps its existing 30-day undo grace period (a product kindness, not a legal requirement); a verified DSAR/Article 17 erasure request skips that grace period entirely and executes on verification. GDPR Article 17(1) requires erasure "without undue delay," and there is no statutory grace period for an explicit erasure demand — a request sitting in an unmonitored backlog past the response window is non-compliant regardless of when it's eventually actioned, even if verification happened promptly.

### 5. Legal-hold exception — reuses `is_suspended`; DMCA retention is permanent, not dispute-conditional

Where GDPR Article 17(3) applies (e.g. defense of legal claims, an active dispute, a pending claim verification), the record moves to legal hold instead of erasure: access is revoked by setting the existing `users.is_suspended` flag (already enforced in `Auth::attemptLogin()`) rather than inventing a new lock state, with an added reason/type distinction so a DMCA-strike suspension and a legal-hold suspension aren't recorded identically. For a record specifically tied to a `dmca_notice`, the hold is **permanent** per `decisions/0019`'s already-established retention (grounded in the 3-year copyright statute of limitations, 17 U.S.C. § 507(b)) — not conditional on whether a specific dispute is still active, since `decisions/0019`'s retention rule was never tied to dispute status in the first place. An ordinary pending claim or dispute, by contrast, can legitimately clear its hold once resolved.

### 6. Operational tracking — deliberately decoupled from identity, so the log can't become its own compliance problem

A new `dsar_logs` table records `request_id` (a random identifier, not the user's own ID), `type` (export/deletion), `status` (received/verified/executed), `requested_at`, `verified_at`, and `completed_at` — both a start and an end timestamp are needed to actually prove the GDPR 30-day / CCPA 45-day response windows were met, not just the completion time alone. Deliberately no reversible link back to the requesting user is stored once a request executes: if this table carried a plain `user_id`, the compliance log itself would be personal data, which creates a real recursive problem — an audit trail that could itself become the target of a future DSAR request. Enough is kept to prove "a request was received on this date and executed on that date" for an audit, and nothing more.

## Consequences

`architecture.md` §24's DSAR open item is resolved by this ADR. §14's account-deletion flow gains the two-trigger distinction described in item 4. `decisions/0019`'s DMCA permanent-retention rule is explicitly cross-referenced as the legal-hold exception's grounding, rather than this pipeline re-deriving its own "legal retention" logic that could drift out of sync with it. The table inventory in item 2 is now enumerated against the actual schema, correcting an earlier draft that named tables which don't exist in this codebase (`user_sessions`, `claims`, `user_roles`/`memberships`) and missed real ones that do (`email_confirmations`, `user_otp_challenges`, `listing_access`, `admin_access`).
