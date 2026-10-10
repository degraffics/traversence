# 0076. Listings build themselves: one pipeline, a person only when it's exhausted

- **Status:** Accepted
- **Date:** 2026-10-10
- **Replaces:** the publishing guardrails of 0044 (two sources, confidence 85, a sure category, categories always reviewed
  by a person) and the category hold of 0050 for listings
- **Builds on:** 0048 (NPI file), 0054 (Nexus), 0072 (closed), 0074 (Review, duplicates), 0075 (search)

## Context

Jason: "I feel like there are fragments of a process in different areas all generating more work for me than
necessary. Create listing and/or point crawler. Gather info. Have it properly sorted, categorized, validated, and put into
listing entities so it can be claimed. This should not need me to validate or check one by one or manual override things,
because our process should generate good claimable listings."

Every rule the crawler wasn't sure of became a queue for a person: Review, Listing Intake, Identities, duplicate
compares, category guesses, Nexus building. 740 listings were waiting, most for the 0044 guardrails (a second source, a
confidence of 85, a sure category).

## Decision

1. **One pipeline, and it decides.** Add a business (name and town) or point the crawler at a website; the crawler does
   the rest. Each step has an automatic outcome; nothing waits on a person while the pipeline can still try something.
   - **Gather:** the business's own site, official registries, our sources, then a web search (Verify: up to three
     tries). Where they disagree on a location's phone, the business's own site wins over a registry's head-office number.
   - **Match:** the same business merges (filling gaps only); another location of it is its own listing, put in a Nexus
     with the others when they share a website (made automatically); a provider at a facility is its own listing; an
     uncertain match is matched again on the next pass.
   - **Categorize:** the best relevant guess: a category whose words appear in what the business says it is (its
     declared kind, name or offerings). Not one found from its description alone.
   - **Validate:** a listing is ready when it has its name; a phone or a website; a street address or a map point in an
     area we cover; one strong source (its own website, an official registry, or two independent sites; the NPI file
     alone still needs one more, since its addresses can be old, 0048); a relevant category; nothing that reads as spam
     or adult content; and no unresolved duplicate.
   - **Publish unclaimed,** ready to claim, each fact keeping its source.
2. **A person only when the pipeline is exhausted.** A listing reaches Review only when it isn't ready and every try is
   spent: Verify has looked (or there's nothing to look it up by). Review says why in plain words. Everything else is "the
   crawler is still working on N", a number, not a list.
3. **Public information isn't held back.** Listings come from public information, so no kind of business waits for a
   person to publish (0044's always-reviewed categories end). The one display rule that stays: domestic-violence shelters,
   safe houses and sober-living homes show their phone, not their street address or a map pin (no work for anyone; a
   map pin is not the same as an address in a registry). Owners can claim a listing and make details private, or ask for
   it to be removed.
4. **Admin can add, monitor, change, review and remove,** all in Manage listings (0074): add one (or crawl a site),
   the overview (what was published, merged, waiting, how complete), find and edit any listing, Review for what's
   exhausted, and **Remove from the site** with a reason (kept, restorable with Put back).
5. **The backlog** goes through the same rules: each time Review opens it publishes what's now ready (0074 §9), and
   Verify keeps working through the rest.

## How each match is decided (as built)

A possible match is decided by its phone, its street address and ZIP, and how much of the name it shares (short forms
read in full, "Svc" as Services, "Dist" as District):

| What they share | Outcome |
|---|---|
| Nearly the same name (3 in 4 words) at another street number | **Another location**: its own listing, in its business's Nexus when they share a website or main phone |
| Nearly the same name and the same phone, or the same street number, or no street number to compare in the same ZIP | **The same business**: merged, filling only what the listed one is missing |
| A person's name at the same phone or address ("Hirrlinger, Erinn") | **A provider at the facility**: its own listing |
| Another business name at the same phone and street address, or a half-similar name at the same address (a legal name, a billing company at a hospital: "Kelbert Anesthesia LLC", "German Dobson Cvs LLC") | **Covered** by the listed one: not listed again, nothing copied in |
| A different name, sharing only the phone or only the address, or only some words and nothing else | **Another business** |
| Anything else (a part-similar name and one shared detail) | **Maybe**: matched again next pass, then a person |

Categories: the NPI Registry's own label for a provider ("Family medicine", "Physical therapy") maps straight to a
category and is sure. Otherwise the best category whose distinctive words are all in what the business says it is
(general words like "family", "care", "consultants" don't count): "Family Medicine Associates" is no longer Family
Entertainment Centers, nor "Peak Physical Therapy" Aquatic Therapy. A name that's really a page label ("Physical
Address") isn't a business.

## What it did to the waiting listings (local copy, 2026-10-10)

737 were waiting, none ready under 0044. One pipeline pass over all of them: **105 published**, **71 merged** into the
listing they already were, **119 covered** (closed, nothing copied), **218 still being worked on** by the crawler, and
**224 for a person** in three groups: 190 known only to the NPI file (one answer: Publish all, or Leave them out), 20
possible duplicates to compare, 14 missing a way to reach them or a place.

## Consequences

- `AutoImport::evaluate` checks readiness; `relationTo` decides each match; `advance` is the pass (Review runs 150 a
  load, oldest-checked first); `exhausted` and `reason` decide what Review shows and how it's grouped; `autoNexus` links
  other locations. `IntakeStager::NPI_CATEGORY` maps registry labels; `Verify` prefers the business's own site's phone.
- Review lists only exhausted listings; "Check again" stays for when a person knows something changed.
- Still to build: reading a business's Locations page into one listing per location, and not publishing an address that
  isn't on the business's own list (an old NPI address); owners' removal requests from the claim page.

## Added 2026-10-10: shelters' addresses, and the deeper look

Jason: "What if someone needs these resources?" and "How can we use OpenStreetMap or the crawler to check listing
verification when it fails?"

- **Only domestic-violence shelters and safe houses keep their address private** (`ListingSafety::CONFIDENTIAL_NAME`,
  narrowed). They don't take walk-ins: you call, they screen, they arrange where to meet, and a published address puts
  the people inside at risk. Every walk-in place shows its address: homeless and family shelters, crisis centers,
  transitional and sober living, and the elder-care, group and residential care homes (whose whole categories were
  hidden before). Those are placed on the map again (`Geocoder`). A private listing shows **Need help now?**: its own
  line, 911, the National Domestic Violence Hotline and its text line (`ListingSafety::HELP_LINES`), and an office
  address the shelter publishes itself, which staff can add in the listing editor (`office_address`).
- **The deeper look** (`app/lib/crawler/DeepLook.php`), run by Verify before a listing can be exhausted, a few a call:
  1. **OpenStreetMap as a second reference:** an entry with nearly the same name or the same phone at the same street
     number confirms the business (an independent source, kind open_data). The same name and phone at another street
     number marks the address **probably moved**. OSM data stays OSM's (0065, ODbL): only the yes or no and a link to
     the entry are kept; its phone, hours and address are never copied. Its website tag is only a pointer. OSM asks
     share OpenPlaces' hourly budget.
  2. **The business's own website** (from the listing, or OSM's pointer, never a directory or social page): read like
     "Crawl a site". A location there at the same street number confirms it from its own site, with that location's
     phone (which replaces a registry's head-office number) and hours. A site that lists its locations without this
     street number marks it **probably moved** (Changepoint's 1801 W Deuce of Clubs).
  - A probably-moved listing goes to Review as its own group ("Probably moved or closed"), never published at the old
    address.
- **Listed twice already:** when the directory has the same business twice, a found copy merges into the claimed one,
  else the oldest, instead of waiting for a person.
