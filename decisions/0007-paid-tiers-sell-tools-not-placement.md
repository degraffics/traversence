# ADR 0007: Paid Tiers Sell Tools and Support, Never Ranking Placement

**Status:** Accepted

## Context

The business documents defining Traversence's three paid tiers (Core $19/mo, Strategic $79/mo, Cornerstone $249/mo) originally described Core and Cornerstone using ranking language — "priority placement," "category leadership," "maximum visibility," "priority routing." That directly contradicted two things already established elsewhere: the Charter's "Sustainable Tools, Not Paywalls" and "Merit-Based Prominence" principles, and the Trust-Weighted Interaction Mechanics in `architecture.md` §7, which ties visibility entirely to earned community resonance, explicitly ruling out "artificial inflation or paid placement." Building ranking logic against the tiers as originally worded would have meant shipping a paid-placement system while every other governance document promises the opposite.

## Decision

Paid tiers grant greater access to platform tools, not greater visibility:

- Deeper access to the AI content-generation engine (Core: standard assistance; Strategic: expanded, including custom partner content; Cornerstone: full access across a partner's entire catalog).
- Better support through group-account management, for operators running more than one listing (introduced at Strategic, deepened at Cornerstone).
- White-Glove services (setup, video production, SEO consultation, creative assets) as previously scoped.

No tier — including Cornerstone — grants a ranking, category-leadership, or routing advantage over a free or lower-tier listing with a comparable trust score. The Trust-Weighted Interaction Mechanics in `architecture.md` §7 remains the sole determinant of visibility and prominence, for every partner regardless of subscription.

## Consequences

Marketing and sales language for Cornerstone needs to be rewritten wherever it still promises "category leadership" or "maximum visibility" (the original source business documents use this language throughout and should be corrected to match this decision, not treated as still-current). The revenue model and financial projections in `commercial.md` §4–6 are unaffected — this changes what partners are told they're buying, not the price or the target mix. Product work building search ranking, category ordering, or map-pin prominence must never accept tier or payment status as an input signal; trust score, verification status, and community resonance are the only legitimate inputs.
