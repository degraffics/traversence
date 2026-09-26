# ADR 0025: GDPR/CCPA Jurisdictional Scope and Cookie Consent Posture

**Status:** Accepted (2026-09-24)

## Context

`architecture.md` §24 flagged four open items in the platform's privacy/regulatory disclosure position: whether the platform intentionally targets EU/UK residents, whether any CCPA-style threshold is met, cookie-consent banner requirements, and formal DSAR handling. This ADR resolves the first three. DSAR handling is resolved separately in `decisions/0026-dsar-export-and-erasure-pipeline.md`; the registration-time mechanism that operationalizes the jurisdictional position below (rather than just documenting it) is `decisions/0027-international-registration-gate.md`.

## Decision

### 1. GDPR — out of scope, on the correct legal test: intent/targeting, not incidental reach

GDPR's extraterritorial reach (Article 3(2)) turns on whether the platform intentionally offers goods/services to, or monitors, people physically in the EU/UK — not on whether an EU/UK visitor could technically reach the site. Nothing in this platform's actual business model does that: `charter.md` and `commercial.md` describe a North America-only expansion plan (20 planned Continental Hubs), no Euro/GBP pricing exists anywhere in the pricing model, and there is no EU/UK-targeted marketing anywhere in the documented plan. This is a real, defensible "out of scope" position, not an assumption — and it should be stated as an affirmative disclosure in the privacy policy ("this service is not directed at, and does not knowingly target, residents of the EU or UK"), not left silent.

### 2. CCPA — out of scope today, on a threshold basis, not a targeting basis

Unlike GDPR, CCPA applies based on doing business in California and collecting California residents' personal information, once a business crosses one of three thresholds: annual gross revenue over the adjusted threshold (~$26.6M as of the 2025 inflation adjustment), processing 100,000+ consumers'/households' personal information annually, or deriving 50%+ of annual revenue from selling/sharing personal information. There is no "we don't target California" exemption the way GDPR has a targeting test — a nationwide U.S. platform is "doing business" in California regardless of intent, the moment it operates there. Two things keep this platform below CCPA's bar right now: current and near-term scale is nowhere near the volume/revenue thresholds, and the third threshold (selling personal information) is structurally, permanently inapplicable given the Content-to-Commerce Engine's zero-commission, no-ad-network model (`commercial.md` §9) — the platform never sells or shares personal information for advertising, at any scale. Unlike the GDPR position above, this should be revisited if/when a California-based regional hub launches and volume approaches the thresholds — it is not a permanent exemption.

### 3. Cookie consent — audited against the real code; likely no banner obligation currently exists

A direct review of the platform's authentication/session code (`login.php`, `register.php`, `session.php`, `Auth.php`, `Response.php`, and related files, reviewed 2026-09-24) confirms the only cookie mechanism in the system is the native PHP session cookie backing `$_SESSION` (`user_id`, `csrf_token`, `session_version`, `logged_in_at`), explicitly rotated on login (`session_regenerate_id`) and cleared on logout. The only other cookie anywhere in the platform is the already-documented `guest_session_id` correlation cookie (`decisions/0009`) — first-party, pseudonymous, purpose-limited, 30-day retention. Both fall under the "strictly necessary" exemption from consent-banner requirements under both the GDPR ePrivacy rule and CCPA. No analytics, advertising, or third-party tracking cookie exists anywhere in the reviewed code. This should be stated precisely in the privacy policy — name both cookies, state their purpose, note neither is used for tracking or advertising — rather than treated as an unresolved gap.

## Consequences

`architecture.md` §24's "Open items, not yet decided" list is resolved by items 1–3 above; its fourth item (DSAR handling) is resolved separately in `decisions/0026`. The registration-time mechanism enforcing the GDPR/CCPA jurisdictional posture is `decisions/0027`. This entire position remains a documentation-level determination, not legal advice — §24's existing disclaimer that it should be confirmed with counsel before launch stands unchanged.
