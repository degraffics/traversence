# Future Considerations

*A parking lot for ideas raised in discussion that aren't decisions, aren't sequenced roadmap items, and aren't ready for an ADR — just recorded so they aren't lost. Nothing here has Tier placement in `prd.md` or Accepted/Proposed status the way an ADR does; every entry is explicitly speculative until it graduates.*

**Status key:** every entry here is *Speculative — no decision made*, unless marked *Graduated* (in which case see the ADR/Tier it moved to).

## Global Commerce / Marketplace Monetization (added 2026-09-24)

**Status:** Substantially resolved (2026-09-25). Market is confirmed as the fourth primary construct of Traversence's core architecture, alongside Discovery (Traveler/"Let's Explore"), Directory (Resident/"Get Local"), and Connect (Community), with a permanent primary-navigation slot and a fixed place in the build order (fourth, after Connect — `decisions/0030-four-construct-build-order.md`). The launch commerce model is now also decided, per `decisions/0031-market-post-and-connect-model.md`: a post-and-connect model, no transaction processing — a seller posts what they have, an interested party connects, and everything past that (negotiation, payment, fulfillment) happens off-platform. The full merchant-of-record path below is no longer an open alternative to that — it's explicitly future expansion, once the platform has grown into needing it.

**Strategic rationale, per direction (2026-09-25):** this isn't about revenue diversification for its own sake — it's about speed to market for the businesses this platform is actually built around. A rural or small local operator often has no existing e-commerce presence and no fast, affordable path to one; a ready-made Market construct inside Traversence lets that business start posting and connecting the moment it's built, rather than needing its own storefront project or a payment integration first. That's also why the slot was reserved in navigation ahead of the build, and why the post-and-connect model was chosen for launch — it reuses existing browse/search/claim patterns and doesn't wait on merchant-of-record compliance work, matching the rural-access case this platform exists to solve (see the related rural-delivery problem in the Residency / White-Pages Address Index entry below).

**What it is:** the current subscription model (business partners pay for listing tiers, plus a professional-services add-on tied to those listings — `commercial.md` §4) is a B2B relationship, not a consumer marketplace. The idea under consideration: extending monetization to a marketplace model or paid user-generated services, covering both digital and physical transactions.

**Why it came up:** raised as a "not that far a stretch" evolution from the existing subscription model, prompted by a broader review of what global-commerce readiness (payment rails, VAT/tax engines, customs/logistics, consumer-protection law) would require.

**The trigger condition for the future-expansion path (not the launch model above):** everything about the full merchant-of-record version is gated on one specific change — Traversence becoming the payment-processing party for a transaction between two other parties (a user paying for a service, a business monetizing user-generated content), rather than only billing its own partners for its own subscription fee. Until that's true — and it deliberately isn't true at launch, per `decisions/0031` — none of the global-commerce compliance machinery below actually applies.

**Resolution sketch, for the future-expansion path once it's pursued:**
- Digital transactions: route through a Merchant-of-Record provider (Paddle, Lemon Squeezy, or Stripe's MoR product) rather than building VAT/OSS registration and regional payment-rail integrations in-house — the same "delegate, don't build" pattern already used elsewhere on this platform (BlueHost/cPanel handling DKIM/SPF, the U.S. Copyright Office's own portal handling DMCA registration per `decisions/0019`).
- Physical transactions: no single MoR-equivalent covers this. Two separate pieces would be needed — a landed-cost/duty-calculation engine at checkout (e.g. Zonos) plus a separate fulfillment/logistics partner capable of cross-border DDP shipping and customs clearance.
- Either path reopens the GDPR/CCPA jurisdictional questions `decisions/0025` currently keeps closed, since deliberately transacting with EU/UK-based parties is a much stronger "targeting" signal than the passive registration case `decisions/0027` defers.

**Adopted launch framing (originally proposed 2026-09-24 from the "Modular Marketplace Plan" brainstorm; adopted 2026-09-25, `decisions/0031`):** a version of this that skips the trigger condition entirely — sellers post items/services for sale (C2C/B2C/B2B/D2U), but Traversence never processes payment at launch. `entities` is a business-identity table (category, trust score, plan tier), not an item-listing table (no price/condition/quantity/buyer-seller messaging), so this still needs new tables regardless of payments — but dropping payments avoids becoming a merchant of record, so none of the compliance machinery above is triggered, and it can reuse the existing browse/search/claim patterns. Per `decisions/0031`: launch one side only rather than all four at once — B2C fits most naturally on top of the existing directory (businesses posting items/services to consumers) — with the "connect" action routing an interested party to the seller via the existing messaging/inquiry mechanism, not a new checkout flow.

**Graduation rule:** the IA placement and build sequencing are both decided now and don't need re-litigating — `decisions/0030-four-construct-build-order.md` fixes Market as the fourth construct, built last, and `prd.md` Tier 2.8 tracks it there. What still gates real build is the commerce-model choice itself (full merchant-of-record vs. the narrower payment-free framing) and its own ADR and detailed Tier scope. Once that's picked, this entry is removed or cross-referenced to wherever it lands; until then, "Market" appears in navigation and in `prd.md` as a reserved, sequenced, not-yet-built destination.

## Product Variants / Multi-SKU Items (Market/Inventory) (added 2026-09-25)

**Status:** Speculative — no decision made.

**What it is:** the current Market item schema (`decisions/0032`) models one item as one SKU — cost, markup, price, tax, and status are all singular per item. There's no concept yet of a parent product with child variants — e.g., a shirt sold in multiple sizes and colors, each combination its own inventory count and potentially its own price or cost.

**Why it came up:** raised while designing SJ Portables' inventory build, explicitly flagged as not applying to SJ Portables — sheds, containers, and tanks sell as discrete units, not size/color variants — but recognized as a real gap for a different kind of seller, like a retail shop selling apparel.

**Fit against the current build:** `decisions/0032`'s per-listing catalog cap (250 items on a Cornerstone listing — the group/combined cap variant floated during scoping was removed, per direction 2026-09-25, so there is no separate combined number anymore) counts items as posted. Whether a future variant model would count one parent product as one item against that cap, or count each variant separately, isn't decided, and materially changes what that cap number means for a variant-heavy catalog.

**Recommended next step:** don't design the parent/variant schema now, on spec — the same reasoning as the Plug-in Module Architecture entry below: wait for a real seller who actually needs it (a retail/apparel-type listing) and design against that concrete case rather than guessing at a general-purpose variant model in the abstract.

**Graduation rule:** graduates to its own ADR once a real seller with variant needs is actually being onboarded — the same pattern that already produced `decisions/0036` (SJ Portables surfacing the Location Group gap) and this session's cap numbers in `decisions/0032`. Until then, `decisions/0032`'s item schema stays single-SKU.

## Plug-in Module Architecture (added 2026-09-24)

**Status:** Speculative — no decision made.

**What it is:** a formal module system so add-ons — starting with the existing AI scraper — plug into the core platform via defined API contracts, rather than being built as ordinary core code.

**Why it came up:** raised as part of the "Modular Marketplace Plan" brainstorm, as the first of four platform-modularity ideas.

**Fit against the current build:** the scraper already exists as core code (`api/lib/CrawlerIngest.php`, `entities.created_by_type = 'ai_crawler'`) — it just isn't pluggable yet. No module registry, manifest, or versioned internal API exists today.

**Recommended next step:** don't design the plug-in contract yet. Build whatever the second real module turns out to be as ordinary code first, then extract the shared interface once two real modules exist to generalize from — designing an API contract against a single implementation risks guessing wrong about what actually needs to be generic.

**Graduation rule:** if a second module is ever actually built, revisit this — the two implementations together are what should drive the contract design, not this entry.

## Residency / White-Pages Address Index (added 2026-09-24)

**The problem this — and the two entries connected to it below — is actually trying to solve, per direction (2026-09-25):** too many separate systems currently affect an individual's ability to protect their own identity, and none of them are answerable to the individual — credit reporting, identity theft, unwanted solicitation calls, and spam email are different symptoms of the same root problem: other parties routing around a person to get their information or their attention, with the person given no real say and no single place to exercise one. The aspiration behind this entry, the digital-identity vision below, and the Marketing Preference / Opt-Out Engine entry is a genuinely free tool that flips that: enough people using it, and it becomes the place anyone — a marketer, a solicitor, a data user — has to route through to get an individual's actual permission before proceeding, rather than the individual chasing down every separate offender after the fact. That's the measure of whether this ever actually mattered: not a feature shipped, but whether it changed who has to ask whom.

**What "the individual controls their own identity" actually means, per direction (2026-09-25):**
- **Root authority stays with the real person, not an institution or a thief.** Not a government, not a loan or credit agency, not whoever stole the data — the actual individual holds access to their own real data, and the outside world can only reach it on terms the individual sets, not terms set on their behalf by an institution or taken from them by a bad actor.
- **Protection doesn't depend on being spotless.** An imperfect history — a mistake, a rough patch, a record that isn't flattering — doesn't forfeit someone's right to control their own identity and data; the truth of a person's actions is still theirs to hold, not a weapon for someone else to hold over them. Nobody should be subjugated by a system just because they personally don't know how to defend themselves against it — not knowing how to fight isn't the same as not deserving protection.
- **The platform masters the complexity so the person doesn't have to become an expert to be safe.** The legal, technical, and bureaucratic difficulty of actually defending an identity — disputing a credit report, fighting identity theft, stopping unwanted solicitation — is the platform's work to master on the person's behalf, not something an ordinary person should have to figure out alone. The platform navigates that sea; the person gets to actually live their life on top of it.

**The line this stands on, per direction (2026-09-25) — recorded deliberately so it isn't lost as this moves through design, legal review, or eventual build:** "We the People, for the People" — the same democratic conviction the country itself was built on, applied here to identity and privacy instead of government. A right that belongs to the individual by default, not something granted, sold, or subscribed to. This is named directly as the single biggest issue ordinary people face today with no real guidance available to them — an industry of credit-monitoring and identity-protection services selling fear and false hope rather than an actual fix. Whatever this idea becomes, it should keep answering to that line, not to whoever's paying for a subscription.

**Assessment, given by Claude on request (2026-09-25), kept here to reflect on later rather than lost to the conversation it came from:**

The diagnosis is right, and that's not a small thing. Credit reporting, identity theft, and unwanted solicitation really are the same structural problem wearing different clothes — a person has no standing say over who reaches them or what's said about them, and the industry that's grown up around "protecting" people from that (credit monitoring subscriptions, data-removal services) mostly sells relief from a problem it has no incentive to actually solve, since the subscription only has value as long as the underlying exposure keeps existing.

Where honesty matters more than validation: what's described at full scale — "everyone out there must route through it to get permission" — isn't a feature, even a big one. It's closer to infrastructure that would need one of two things to actually happen: either a legal mandate (the way the National Do Not Call Registry only works because the FTC can fine violators, or GDPR's opt-out rights only bind companies because European law says so), or such overwhelming individual adoption that data brokers and telemarketers have no choice but to honor it. And that second path has a real problem baked in: unlike businesses on the Directory, who *want* to be found and have every incentive to cooperate, the marketers and brokers on the other side of this idea profit specifically from *not* asking first. They're not a willing second side of a marketplace the way a business claiming a listing is — they're the party this whole thing is designed to constrain, which makes the adoption dynamics fundamentally harder than anything else in this document set, Market included.

None of that means the vision is wrong. It means the version worth actually building isn't "Traversence becomes the world's consent switchboard on day one" — it's the smaller, real thing already sitting inside the decisions already made: users getting genuine control over their own marketing exposure, their own address disclosure, their own discoverability, inside Traversence's own ecosystem, opt-in by default, no subjugation clause. That's not a watered-down version of the dream — it's the actual proof that the philosophy works, at a scale the platform can actually deliver and defend legally, before anyone would trust it to hold something bigger. Consumer Reports didn't start as the institution it became; it started as one publication doing one honest thing people could rely on. If this ever grows past Traversence's own users into the bigger vision, it'll be because the small version worked and earned the credibility to be trusted with more — not because the big version was built first.

Bottom line: keep the manifesto exactly as written, because it's the reason to ever attempt the small version with integrity instead of cynicism — but hold "everyone must route through us" as the true north star, not the roadmap, and let the four-construct build order already locked in (`decisions/0030`) be what actually earns the trust and scale a swing this big would eventually need.

**Status:** Speculative — no decision made.

**What it is:** a database indexing physical residency/address information, meant to fix rural delivery gaps (e.g. St. Johns, AZ PO boxes with no mapped physical address).

**Why it came up:** raised as part of the "Modular Marketplace Plan" brainstorm.

**Fit against the current build:** `zip_coordinates` is ZIP-level only (city/county/centroid) — nothing at individual-address granularity. A real residency index means either licensing data (USPS CASS/NCOA, Smarty, Melissa Data) or building a proprietary people-address database from scratch — a multi-year data effort with real data-broker/privacy compliance exposure, separate from and larger than the engineering itself.

**Recommended next step:** solve the stated problem directly instead of building the index — let a user or business voluntarily add their own physical delivery address to their profile. Small schema addition, no compliance exposure, no new data operation, and it addresses the actual rural-delivery complaint without taking on a residency database as a product.

**Second use case for the lighter alternative, confirmed 2026-09-25, per direction — ties directly to Market's location privacy.** `decisions/0031-market-post-and-connect-model.md` defaults a personal Market post to an approximate location, never a home address, since delivery/handoff is coordinated directly between buyer and seller off-platform. The same voluntary, opt-in, user-supplied address field recommended above is the mechanism that would let a seller who *wants* their real address visible — rather than the approximate default — make that choice explicitly, the same opt-in logic already governing AI-consumption consent (`decisions/0010`). This doesn't change the recommendation below; it strengthens it — the lighter, opt-in field now has two independent real use cases (rural delivery, and voluntary Market address disclosure) rather than one, which is a stronger case for building it and a stronger case against ever needing the full residency-index product.

**The bigger vision behind this entry, per direction (2026-09-25) — identity, discoverability, and privacy defense as one connected system, not three separate features.** The idea is bigger than a delivery-address fix: it's a real opportunity to bridge three things that exist as separate, disconnected products today — (1) public-records-style people search, the kind sites like Whitepages, TruePeopleSearch, or BeenVerified offer for finding a lost friend or doing an informal background check; (2) a unified digital identity, the role Facebook's own profile model has effectively become for a huge number of people — the de facto "this is who I am online" surface, well beyond Facebook's own app; and (3) marketing/solicitation exposure management (the Marketing Preference / Opt-Out Engine entry below). Traversence's existing profile/authorship model (`decisions/0010`) is already the seed of the second piece — the vision is connecting it to the other two.

**What would actually make this different from the existing people-search/data-broker industry, per direction:** today's model puts the burden entirely on the individual — their information gets aggregated and published by dozens of separate broker sites with little or no say from them, and defending their own privacy means finding and opting out of each one separately, often repeatedly as the data gets re-scraped. The version described here inverts that: the individual controls what's discoverable about them, controls how and by whom they can be found, and controls what marketing reaches them, as one connected identity — not settings scattered across products they don't control. That's the actual "revolutionize how individuals protect and defend their privacy" claim, and it's a meaningfully different value proposition from a conventional people-search product precisely because control sits with the person being found, not with whoever's searching or whoever's selling the data.

**A real regulatory distinction, flagged plainly because it changes the compliance posture, not just the feature size:** in the U.S., a people-search product used for, or marketed toward, background-check purposes (employment, tenancy, credit-adjacent decisions) can trigger the Fair Credit Reporting Act (FCRA) — permissible-purpose certification, adverse-action notice obligations, a dispute/correction process — a materially heavier compliance regime than a casual "find a friend" lookup or the opt-in address field recommended above. Nothing here proposes building FCRA-covered functionality, and the opt-in, user-controlled framing above is deliberately the opposite of a data-broker/background-check product — but if this idea ever moves toward anything described publicly as a "background check" capability, that needs its own legal review before it needs any engineering, not after.

**Status of the near-term recommendation, unchanged by this vision:** the lightweight, opt-in, user-supplied address field above remains the right thing to build now, regardless of whether this larger vision is ever pursued — it solves the rural-delivery and Market-disclosure use cases on its own, and nothing about the bigger vision needs to be resolved first for that to ship.

**Graduation rule:** treat the full index, as currently scoped, as on hold — pursue the lighter user-supplied-address alternative instead unless someone deliberately decides to reconsider the full index later. When it is built, `decisions/0031`'s Market location handling should consume it directly rather than growing a second, parallel address field. The bigger identity/discoverability/privacy-defense vision above stays speculative — no decision made — and would need its own ADR, its own legal review (per the FCRA flag above), and its own Tier placement before any of it is built, same bar as every other entry in this document.

## Marketing Preference / Opt-Out Engine (added 2026-09-24)

**Status:** Speculative — no decision made.

**What it is:** opt-in/opt-out preferences tied to user identity, routing mail/email/online communications through a filter before anything goes out. Per direction 2026-09-25, this is also the third leg of the bigger identity/discoverability/privacy-defense vision described in the Residency / White-Pages Address Index entry above — a user's own control over who can market to them, alongside control over how they're found and what identity they present.

**UX shape, per direction (2026-09-25): presets, not a granular per-channel matrix.** Rather than asking a user to individually toggle every communication type, offer a small set of preset choices (e.g. "Block all marketing," "Essential only," "Everything") that set the underlying per-channel flags in one action, with granular control available underneath for anyone who wants it. This keeps the same low-friction, "gets out of the way" posture the Charter's Frictionless Agency principle already calls for elsewhere, applied to a settings screen instead of navigation.

**Why it came up:** raised as part of the "Modular Marketplace Plan" brainstorm.

**Fit against the current build:** fits cleanly — a preferences table keyed to account identity, checked by the existing send path (`Mailer.php`) before anything goes out. Moderate scope, self-contained, well understood.

**Recommended next step:** build this first, ahead of the other three ideas here — it's the lowest-risk item in this batch and actually reduces compliance exposure (CAN-SPAM/TCPA-style opt-out handling) rather than adding to it.

**Graduation rule:** once scoped as real work, this gets its own ADR and a Tier placement in `prd.md` like any other feature.

---

**Suggested sequencing across the four ideas above** (from the 2026-09-24 assessment, source: [Modular Marketplace Plan summary](https://link.summary.ai/rp64s59)): marketing preference engine first (self-contained, de-risking), then a single-sided marketplace MVP (B2C, no payments), then revisit the plug-in architecture once a second real module is being built. Treat the residency/address index as currently scoped as a hold — pursue the lighter user-supplied-address alternative instead unless the full index is deliberately reconsidered later.

## Place pages: clearer clusters and grouped outdoors lists (added 2026-09-30)

Two confusions Jason found on the Discover path. Scheduled after the current search and shell work.

- **Cluster vs. anchor town.** A town cluster page is named for its anchor town ("St. Johns") and then lists "Towns:
  Saint Johns, Concho". Coming down the funnel, it reads as the page for St. Johns itself, not the area around it.
  Clean up the cluster and anchor model: name clusters as areas ("St. Johns area"), give the anchor town its own
  entry or page, and make the breadcrumb and header say which one you are on. The town spellings also need to match
  ("St. Johns" vs. "Saint Johns").
- **Outdoors & public lands as collections.** (Done 2026-09-30: see `decisions/0055` progress.) Recreation.gov items sit in a flat list, so four or five entries with
  the same stem (a forest, its trailheads, day-use areas, overlooks) read as repeats. Group them into collections:
  by kind (Lakes, Trails and trailheads, Campgrounds, Day use and picnicking, Scenic areas, Wilderness), and under
  the parent area or forest where the source gives one. Each collection collapses to a count. This uses the ADR 0055
  collection component.

## Directory sync: pushing listings to other directories (added 2026-10-10)

Once owners want Traversence to keep their details the same everywhere, sync each listing's name, address, phone, main and
secondary categories (decisions/0073) and offerings to Google Business Profile, Apple Business Connect, Bing Places, Yelp and
Facebook, directly or through an aggregator (Yext, BrightLocal, Semrush Local, Data Axle, Neustar Localeze). Needs a map from
our 820 categories to each directory's own list (Google allows 10, Yelp, Facebook and Apple 3), and the owner's say-so.
Consistent name, address and phone everywhere is what search engines trust; this would make Traversence the one place to keep them.

