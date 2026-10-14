# 0083. A website says which brand, its page says which place; merge several at once; rebuild an organization from its website

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0054 (the Nexus), 0074 and 0081 (merging the same place), 0076 (listings build themselves)

## Context

Jason's "Listing and nexus" notes, 2026-10-14:

- **Listings:** "Need to be able to click multiple listings and merge them into one or create nexus."
- **Holbrook:** three listings all named "Holbrook Unified School Dist 3" (1000 N 8th Ave, 453 N 7th St, Route 77): "the
  crawler failed to recognize the school district structure. How can I task the crawler to see the district website
  and rebuild these listings correctly without having duplicates or stray listings left over."
- **Franchises:** the brand and the store are different things. Each local owner manages their own store, never the
  brand.
- **Chains share one domain:** "this could be an issue for nexus". AlphaGraphics' stores are
  `alphagraphics.com/us-arizona-scottsdale-us037`; The UPS Store's are
  `locations.theupsstore.com/az/show-low/5551-s-white-mountain-rd`.

He also attached a Fortune 1000 list (2021) as a possible brand reference.

## Decision

### 1. A website has two levels (`app/lib/WebIdentity.php`)

- **The brand** is the main domain, with sub-domains folded: `locations.theupsstore.com` is `theupsstore.com`.
  Listings that share it belong to one organization. That is evidence for a Nexus, never for "the same place".
- **The page** is the brand plus the path: `alphagraphics.com/us-arizona-scottsdale-us037`. The query
  (`?usemylocation=true`), a trailing slash and `index.html` don't count.
- **Two listings are the same place by their websites** only when:
  - the pages match; or
  - both give only the home page of a site that isn't a chain's.
- **A chain's site** is one that listed places at two or more street addresses share, or that a Nexus of two or
  more has.
- **A shared parent is never one brand:**
  - school districts and local governments under a state's `.us` name (`holbrook.k12.az.us`,
    `ci.show-low.az.us`);
  - a state's agencies under its `.gov` (`des.az.gov`);
  - website builders' tenants (`joescafe.wixsite.com`, `sites.google.com/view/joes-cafe`).
- Social media and directories have no identity at all.
- **Used by:**
  - the merge rule (`AutoImport::relationTo`), where "same website" now means the same page;
  - the automatic Nexus;
  - the Nexus's "On its website, not in it yet".
- **No automatic Nexus for a person.** One provider at two offices ("Moore, Mathew D Dc") is not an organization,
  so no Nexus is made in their name.

### 2. Select several in Find & edit (`app/lib/ListingBulk.php`, `/admin/api/listings-bulk.php`)

Tick listings, across searches. A bar offers four actions:

- **Merge into one.** A pop-up shows each listing and you pick the one to keep and the name to use.
  - The kept listing's gaps are filled from the others, first picked first. What a person or the owner entered is
    never replaced.
  - The others are hidden, and their links go to the kept one. Waiting suggestions move to it, and so does a Nexus
    if it had none.
  - A listing its owner manages is never the one hidden.
- **Put in a Nexus:** an existing one (found by name or website) or a new one, with each listing's role (campus,
  clinic, office…).
- **Take out of its Nexus.**
- **Rebuild from its website** (§3).

Each action can be undone from the bar for a minute afterwards.

The pop-ups open over the whole page, even when Find & edit is shown inside the admin dashboard.

### 3. Rebuild from its website (`app/lib/Rebuild.php`, `/admin/rebuild.php`, `/admin/api/rebuild.php`)

It is opened from Find & edit (the listings ticked) and from a Nexus's page. It runs as a pop-up in three steps, shown
as breadcrumbs: **Its website › The plan › Done**.

**Reading the site:**
- The organization is the address on most pages (a footer), with its name.
- Each place is a link in the site's own list of its places: a heading or menu item named Schools, Locations,
  Clinics, Offices, Branches, Stores, Facilities and similar. The place takes the name the site gives it ("Holbrook
  High School"), not the site's name. That naming mistake is what made "Holbrook Unified School Dist 3" three times.
- Its address and phone come from its own page.
- A place whose page has no street number ("Junction of Route 77 & Route 15, Indian Wells") is still a place, with
  its town.
- Pages the crawl didn't reach are fetched by name (`SiteFetcher::fetchPages`), following robots.txt.

**The pool of listings it matches against:**
- the ones picked, and the Nexus's;
- the same brand website, or the same phone as a place;
- look-alike names in the towns.

**When a listing matches a place:**
- **By its own name:** the place's name, allowing spellings and the word "School" ("Holbrook Unified School Dist 3" is
  "Holbrook Unified School District #3"; "Park Elementary" is "Park Elementary School").
- **Or under the organization's name, with the place's details:** a registry's legal name on each campus, plus the
  place's address, page or phone.
- **Never by address alone under another name of its own.** A preschool in an elementary school's building is
  another place, and "Holbrook High School-Alternate" is not Holbrook High School.

**The plan, each part changeable before anything is done:**
- **The Nexus:** new, or the one the listings are already in. Its name and kind ("A system with branches" for a
  district, health system, county or tribe).
- **Each place:**
  - **Update**, when a listing becomes it: renamed to the site's name (never a listing its owner manages), with
    address, town, phone and page filled where empty;
  - **Merge in**, for other listings of the same place;
  - **New listing**, otherwise: unclaimed, with a category to pick, and the crawler fills in the rest.
  - Each place also has a role and can be unticked.
- **Left over:** listings that look related but aren't one of its places, each with why it's there. The choices are
  leave it (the default), put it in the Nexus as it is, merge it into a place, or hide it.

**Applying it:**
- It's done line by line. If anything fails, what was done is put back.
- **Undo all of it** puts everything back, newest first: renames, filled fields, merges, new listings, hidden ones,
  the Nexus.

**Tested against a copy of the Holbrook site** (the live site isn't reachable from the build sandbox). The plan it
made:
- #9116 becomes the district office, renamed "Holbrook Unified School District #3";
- the High School, Junior High, Hulet and Park listings are kept as they are;
- the "Dist 3" listing at 453 N 7th St merges into Park Elementary, which is at that address;
- Indian Wells Elementary is listed new;
- the Pre-School, the Alternate high school and the Route 77 "Dist 3" are left for a person.

### 4. Pop-ups that scroll like a page

`TvAsk.peek(url, title, {fit: true})`, or a link with `data-peek-fit`: the page inside grows to its full height, the
dimmed layer scrolls, and the pop-up's bar stays at the top. Every pop-up's page now has a margin (3px on a phone).

## The Fortune 1000 list

It's useful as a parent-company reference, not as a list of store brands:

- **It lists parent companies, not stores:** Yum Brands, not Taco Bell or KFC; Albertsons, not Safeway; Walgreens
  Boots Alliance, not Walgreens.
- **Its websites are corporate**, such as `corporate.mcdonalds.com` and `ir.yumchina.com`. They never match a store
  listing.
- **Chains common in our regions are missing:** Subway, Circle K, Maverik, Bashas', Wendy's, Jack in the Box, Ace
  Hardware and O'Reilly.
- **Names can mislead:** its "Sonic" is Sonic Automotive.
- **It's from 2021.**

## Next

- **A brand list:**
  - each brand's store name and other spellings, its own website, its parent company (from the Fortune list where
    one applies), whether it's franchised, and its category;
  - seeded from names our listings carry in three or more towns, and from Jason's fast-food list.
  - It feeds:
    - Sales: chain stores drop down "Best to call next";
    - the automatic Nexus: franchises as "Independent places under one name";
    - categories.
- **School directories as an official source:**
  - the state lists Jason gathered (AZ, NM, CO, UT, BIE), or one federal list (NCES) if its format suits;
  - every school would arrive under its district.
- **Store locators:** each brand's locator address saved on its Nexus, for Rebuild.
- **The crawler's own extractor** names every location found on a site after the site. Rebuild's rule (a place takes
  the name the site's own list gives it) belongs there too.
