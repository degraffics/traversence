# ADR 0030: Build Sequencing Across the Four Primary Constructs — Directory, Discovery, Connect, Market

**Status:** Accepted (2026-09-25)

## Context

`future-considerations.md`'s 2026-09-25 update confirmed Market as the fourth primary construct of Traversence's core architecture, alongside Directory (Resident/"Get Local"), Discovery (Traveler/"Let's Explore"), and Connect (Community) — reserved a permanent nav slot even though the underlying commerce model is still undecided. That resolved *where* Market sits in the platform's information architecture, but not *when* each of the four constructs actually gets built, or what "built" has to mean for each at a minimum. This ADR settles both, per direction.

The underlying goal, stated directly: give a client — personal or business — a small, affordable way to create content, become SEO/content-rich, connect with prospects and like-minded people, and do business with them. Each of the four constructs is one stage of that same path (presence and content → reach and discovery → relationship → transaction), not four unrelated features — which is what makes a strict build order meaningful rather than arbitrary. A construct downstream of another on this path is far less useful before its predecessor is real.

## Decision

Build order across the four constructs, even at a bare-bones/minimum-feature level for each, per direction:

1. **Directory — first.** A business or individual needs a real, claimable, findable presence — the "create content, be found" foundation — before anything else on this list is useful. Substantially live already (`prd.md` Tier 1) and largely completes in Tier 2.1–2.2.
2. **Discovery — second.** Once real listings and content exist in the Directory, Discovery is what makes that content SEO/content-rich and reachable by people who aren't already searching for a specific business by name — the editorial/narrative layer (`architecture.md` §25–§26, `prd.md` Tier 2.2–2.3) that drives organic reach.
3. **Connect — third.** Once a business or individual has findable presence and inbound reach, Connect is what turns that reach into an actual relationship — messaging, following, community — matching `prd.md`'s existing Tier 2.4–2.5 sequencing (safety controls before social features) and Tier 2.7 (Communities & Groups).
4. **Market — fourth, and explicitly bare-bones at first, per direction.** Once presence, reach, and relationship exist, Market is where a relationship converts into an actual transaction. The narrower, payment-free framing already recorded in `future-considerations.md` (reusing existing browse/search/claim patterns, no merchant-of-record compliance burden) is the natural fit for a first, minimal build — consistent with that entry's speed-to-market/rural-access rationale: a small or rural operator should be able to start doing business the moment this exists, not wait on a full commerce build.

"Bare bones" is explicit permission, not a caveat — each construct should ship a real, minimum version in this order rather than waiting for a fuller version of an earlier construct, or building a later construct out ahead of an earlier one actually being live.

## Consequences

This sits above `decisions/0020`'s Tier 2 dependency mapping, not in conflict with it — 0020 already sequences Directory and Discovery's core loop first (Tier 2.1–2.2) and Connect's safety-then-social features later (Tier 2.4–2.5) inside Tier 2. This ADR extends that same logic outward to include Market as the fourth and final stage, and names the reasoning — the presence → reach → relationship → transaction path — that the existing sequencing has implicitly been following all along. `prd.md` gains a new Tier 2.8 naming Market as a sequenced (if still undesigned) fourth stage, rather than leaving it recorded only in `future-considerations.md` as out of scope. Any future feature proposal touching one of the four constructs should be checked against this order — a Market feature proposed before Connect's core loop (2.4–2.5) is live, for instance, is a candidate for explicit deferral under this ADR, the same "Linear Milestone Enforcement" principle `phases.md` already applies to its own six macro-phases.
