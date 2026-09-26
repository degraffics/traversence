# ADR 0028: Refined 24-Group Level-1 Master Taxonomy — Adopted as the Real Classifier Target List

**Status:** Accepted (2026-09-24)

## Context

`architecture.md` §18 has said since it was first written that the platform's category system has "23 Level-1 browse groups," but never once enumerated what they actually are — the group names came from whatever keyword rules produced the original 4,376-business migration, and that ruleset itself was never captured in this document set. `decisions/0023` (Personal Care & Beauty, the "Other" bucket) and `decisions/0024` (the keyword classifier) both independently hit the same wall from opposite directions: `decisions/0023` §2a needed a real target list to validate the 121 "Other" records' reclassification against, and this document set flagged plainly, more than once, that it did not have one to check against — inference from category-name oddity was explicitly rejected as "presenting speculation as if it were the real mapping."

Per direction, that gap is closed here — not by recovering the original, never-documented raw 23-group list, but by adopting a **deliberately redesigned** 24-group structure as the platform's real, forward-looking Level-1 taxonomy: broad macro groups that hold specific leaf categories underneath them, rather than forcing every niche industry into its own top-level group. This is stated plainly as a redesign, not a rediscovery, because the two are a different kind of claim: nothing in this document set can confirm the new list matches the original raw group names bucket-for-bucket, and this ADR doesn't claim that it does.

## Decision

### 1. The Refined 24-Group Level-1 Master Taxonomy

1. Automotive, Transport & Logistics — vehicle sales, repairs, towing, trucking, delivery services
2. Business & Professional Services — corporate consulting, marketing, legal, accounting, staffing, administrative support
3. Construction, Architecture & Engineering — general contractors, heavy construction, architectural design, structural trades
4. Education, Childcare & Training — schools, universities, tutoring, driving academies, vocational training
5. Entertainment, Media & Production — event venues, performing arts, film/media production, broadcasting, publishing
6. Finance, Insurance & Real Estate (FIRE) — banking, lending, financial planning, insurance agencies, real estate brokers, property management
7. Food, Beverage & Hospitality — restaurants, bars, cafes, bakeries, catering, lodging/hotels
8. Health, Wellness & Medical — clinics, hospitals, physicians, dental, chiropractic, mental health, specialized wellness
9. Home, Garden & Maintenance — landscaping, interior design, cleaning services, pest control, pool service, nurseries
10. Manufacturing, Industrial & Wholesale — factories, heavy equipment, industrial supply, B2B manufacturing/distribution
11. Personal Care & Beauty — barbers, hair salons, day spas, skincare, nail salons, tattoo/piercing studios
12. Pets, Animals & Veterinary — veterinarians, pet grooming, boarding, training, supply shops
13. Public Administration, Civic & Religious — government offices, utilities, churches, non-profits, civic associations
14. Retail, Shopping & Specialty Goods — apparel, electronics, general merchandise, bookstores, local specialty boutiques
15. Sports, Fitness & Recreation — gyms, fitness centers, sports leagues, martial arts, outdoor recreation outfitters
16. Technology, Software & IT Services — software development, IT support, computer repair, web hosting, data infrastructure
17. Trades & Home Improvement — electricians, plumbers, HVAC, roofing, carpentry, locksmiths
18. Travel, Tourism & Attractions — travel agencies, tour operators, amusement attractions, local tourism boards
19. Agriculture, Mining & Natural Resources — farming, ranching, forestry, mining, agricultural supply
20. Security, Legal Enforcement & Investigations — private security, alarm systems, investigators, legal support services
21. Printing, Signage & Marketing Production — commercial printing, sign makers, promotional products, graphic production
22. Cleaning, Waste & Environmental Services — waste management, junk removal, environmental cleanup, commercial sanitation
23. Events, Weddings & Party Services — event planners, DJs, photographers, party rentals, bridal services
24. Local & Community Services — the strict operational fallback for unique, localized community operations that span multiple or niche boundaries

### 2. Group 11 cross-checked against `decisions/0023`'s already-validated real data — matches

`decisions/0023` §1 independently confirmed Personal Care & Beauty's real membership against the live radius export: `Barbers`, `Beauty Salons`, `Health Spas`, `Massage`, `Massage Therapists`, `Spas-Beauty & Day`, `Tanning Salons`, `Tattooing`, `Cosmetics & Perfumes-Retail` — 9 leaf categories, 43 businesses. That set is consistent with group 11's description here (barbers, hair salons, day spas, skincare, nail salons, tattoo/piercing studios). No conflict; `decisions/0023` §1 stands as already accepted, now with a matching Level-1 group in the master list.

### 3. Group 24 is a redefinition of the old "Other" catch-all, not a same-named continuation

`architecture.md` §18 previously described "Other" as an unqualified catch-all — 121 businesses landed there simply because a keyword rule didn't match anything else, with no stated criteria for what belongs there. **"Local & Community Services" is a materially narrower concept**, scoped explicitly to businesses that genuinely span multiple category boundaries or serve a uniquely local/niche function that doesn't reduce to one of groups 1–23. This changes what `decisions/0023` §2a's reclassification pass is actually doing: it is not renaming "Other" to a nicer label and moving all 121 records into it. It is scoring all 121 against the real leaf-level content of groups 1–23 first, exactly as `decisions/0024`'s classifier already does, and only a record that genuinely fails to fit any of those 23 — not merely one the keyword dictionaries haven't scored high enough yet — is a correct fit for group 24 under its stricter definition. A low-confidence result under `decisions/0023` §2a's 60% bulk-remediation threshold is a "needs a human to place it," not automatically "belongs in Local & Community Services."

### 4. This is the target list both `decisions/0023` §2a and `decisions/0024` were waiting on

`decisions/0023` §2a's validation rule ("every `assigned_category` the classifier writes... must match a string in the real, enumerated 24-group taxonomy tree exactly") can now actually run — this is that list. `decisions/0024` §1's per-category weighted keyword dictionaries are built against these 24 groups (and their eventual Level-2 leaves) as the fixed target set, rather than against whatever the original, undocumented raw groups were.

## Consequences

`architecture.md` §18 is updated to carry this list directly (superseding its earlier "23 groups, never enumerated" framing) and to rename its "Other" references to "Local & Community Services" going forward, with the narrower scope from item 3 above. `decisions/0023`'s "What's Still Needed" section is resolved by this ADR for the taxonomy-list dependency specifically. `decisions/0024` is updated to reference this ADR as its concrete target list.

**One real migration consequence, flagged rather than glossed over:** the 4,376 already-migrated businesses currently carry `category_id` values assigned under the original, never-documented raw 23-group structure. Adopting this refined 24-group list as canonical means those existing assignments need to be re-mapped onto this structure during rollout — a real data-migration pass, not just a reference-list swap, and not yet scoped or scheduled by this ADR. The 121 "Other" records are the known subset already scoped for reclassification (`decisions/0023` §2a); the remaining ~4,255 records currently sitting under whichever of the original 23 groups matched their keyword rule may also shift Level-1 group under this refined structure (e.g., if the original raw taxonomy split trades and construction differently than groups 3 and 17 do here) and haven't been audited against it yet.
