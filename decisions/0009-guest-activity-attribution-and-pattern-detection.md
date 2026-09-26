# ADR 0009: Guest Activity Attribution & Cross-Session Pattern Detection

**Status:** Accepted

## Context

Two statements already in `architecture.md` describe unregistered guests in ways that don't fully reconcile: §7 says "anonymous visitors get zero-ad, read-only access... all writes require authentication," while §11 says guest "inputs [are] subject to immediate steward curation and purge" — which only makes sense if guest input already exists to curate. Guests do have limited engagement and content-creation activity (contribute-mode submissions such as suggested corrections, comments), and that activity needs to be captured — both because it has real value (source attribution, conflict resolution when two people edit the same thing) and because a small share of it is abusive, and abuse from an unregistered visitor currently leaves no trail to act on.

Separately, ADR 0002 and ADR 0008 establish `actor_user_id` as a required, always-populated field carrying real identity and trust weight through the dual-accounting and telemetry systems. Guests must never be able to populate that field or borrow its trust semantics — whatever solution captures guest activity has to stay structurally separate from the authenticated-identity system, not a lightweight version of it.

## Decision

**A third identity lane, distinct from `actor_user_id` and `acting_as_listing_id`.** Every unauthenticated visitor is issued a `guest_session_id` — a random, non-identifying token stored in a session cookie — on arrival. It is never accepted anywhere `actor_user_id` is required, and it carries zero trust weight by definition (per §11's "zero credibility").

**Guest writes still never reach the live graph.** This is what actually resolves the §7/§11 tension, rather than loosening §7: a guest submission (a correction, a comment, a contribute-mode entry) is captured and tagged with `guest_session_id`, then routed into the same Tier 4 quarantine/HITL pipeline `decisions/0005-hitl-ai-guardrails.md` already established for autonomous and AI-ingested content. A platform admin reviews and approves before anything from a guest touches live data — this is the technical content-review function, not the community Steward role defined in `architecture.md` §11. "All writes require authentication" remains true of the live graph; it was never true of the staging queue guests submit into.

**Purpose is limited, by design, to three things:** (1) identifying the source of a submission, (2) resolving conflicts when multiple submissions disagree, and (3) detecting abuse patterns across repeat sessions. This data is never used for marketing, behavioral profiling, or personalization — that boundary is deliberate and should be treated as a hard constraint on any future feature built on top of it.

**Cross-session pattern detection** correlates guest activity across visits to catch repeat bad actors who never register:

- **Primary signal:** the persistent `guest_session_id` cookie.
- **Fallback signal:** IP-based clustering, for the case where the cookie is cleared or blocked.
- These feed a **guest risk score** — a separate, much simpler mechanism than `TrustScore.php` (which ADR 0008 reserves for authenticated personal and business-proxy activity). The guest risk score never contributes to `TrustScore.php` and never grants credibility; it only flags a session cluster for review.
- Any action a risk flag might justify (holding future submissions from that cluster, rate-limiting, blocking) is a recommendation to a platform admin through the same Tier 4/HITL gate — never an automatic, silent block.

**Amendment (2026-09-23) — soft vs. hard automated response, a default subject to override.** The Communities & Groups design (`architecture.md` §27) raised a case this ADR didn't originally address: a report against guest misbehavior inside or around a community feeding this same risk score, with an automatic throttle/CAPTCHA/session-invalidation response proposed once a threshold is crossed. That's only partly compatible with the "never an automatic, silent block" line above, so the following default is adopted to resolve the tension rather than leave it unresolved: **soft actions** — rate-limiting and CAPTCHA challenges — may fire automatically when a guest's risk score crosses threshold, since they're reversible, non-destructive, and preserve rather than erase evidence of what happened. **Hard actions** — full session invalidation — still route through the same admin-reviewed Tier 4/HITL gate rather than firing automatically, preserving the original guarantee for anything irreversible. This is a documented default, not a closed decision — it should be explicitly revisited rather than assumed permanent.

**A single 30-day window governs both lookback and retention.** Guest session and activity data used for pattern detection looks back 30 days, and raw guest session/activity data is purged on a rolling 30-day basis. One number for both keeps the design simple and matches the stated purpose: the data is kept exactly as long as it's useful for source-attribution, conflict-resolution, and abuse-detection, then it's gone.

**Guest-to-user promotion (already described in `architecture.md` §14) extends to this data.** When a guest registers, any pending or approved contributions tied to their `guest_session_id` are re-attributed to the new `actor_user_id`, using the same re-evaluation mechanism §14 already describes for telemetry.

## Consequences

`architecture.md` §7's guest-boundaries bullet needs rewording so it no longer reads as a contradiction of §11 — guests can submit into staging, they still cannot write to the live graph. §11's guest-visitor actor description gains a pointer to this ADR. §14's guest-to-user promotion bullet gains the contribution-reattribution detail. A new compliance section (`architecture.md` §23) documents the GDPR/CCPA disclosure position this decision creates: guest correlation data is a form of pseudonymous tracking, even though it's purpose-limited and short-retention, and the privacy policy needs to name this purpose explicitly rather than relying on a generic analytics disclosure.

This decision does not authorize using guest correlation data for anything beyond the three stated purposes. A future feature that reuses `guest_session_id` or IP-clustering data for personalization or marketing would need its own decision record, not an extension of this one.

`architecture.md` §27 (Communities & Groups) now points here for the guest-abuse-in-communities case the amendment above resolves.
