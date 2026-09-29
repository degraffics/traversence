# ADR 0045: Our Own Reference Index and Source-First Crawling

**Status:** Accepted (2026-09-29). Builds on `decisions/0044` (confidence-gated auto-import) and its
"Second look" section. Step 1 (the source list) is built; steps 2–4 are the plan.

## Context

`decisions/0044`'s second look confirms single-source listings by looking them up by name, address and city:
in the NPI Registry (free) and, for everything else, through a paid web search API (Tavily's free plan is
1,000 searches a month; Brave has no free plan; Google's search API is closed to new customers). The first
live runs worked: 7 listings published themselves in an afternoon, confirmed by the NPI Registry or by 3–4
independent websites each.

But every one of those confirmations was rented. The worker read a page, used it once for one listing, and
threw it away, and the next listing named on the same page cost another search. Several of the websites it
found are sources that list many places at once: nmfinder.org (a New Mexico community resource directory),
gallupcommunityhealth.org, gowhitemountains.com (regional tourism), chamber member directories. Read
directly, each could confirm dozens or hundreds of places with no search at all.

Direction (Jason, 2026-09-29): needing an API to find things on the web is exactly why the crawler must
collect sources, so Traversence has its own data. The free way on the internet's data highway is to read the
sources themselves.

## Decision

**Traversence builds its own reference index, and the crawler becomes source-first: it reads good sources
regularly and follows their links, and searches only for what no source covers.**

1. **A source list** (`reference_sources`, Admin → Sources). Every website that confirms a place (showing its
   name with its street address or phone) is recorded, with how many listings it has confirmed. A person
   decides what each is for:
   - **Read regularly**: community and government directories, chambers, tourism sites, organizations'
     resource pages. The worker reads them and follows their links.
   - **Cite only**: counts as a reference when found, never read in bulk. Commercial directories (Yelp,
     MapQuest, BBB, Yellow Pages, Healthgrades…) start here because their terms forbid automated copying;
     social media too; and the NPI Registry until its bulk file is loaded (step 3).
   - **Ignore**: does not count as a reference at all; the second look drops it.
   New websites arrive "waiting for you" with a guessed kind. A place's own website confirms only that place,
   so it is not listed as a source. People can add sources by hand (a county services page, a chamber
   directory, a tribal government's public services page).
2. **Keep what the crawler reads.** From every page the worker reads, keep the facts it can extract: names,
   street addresses and phones, with the page, the website and the date. Facts only, never whole pages.
3. **Load official data in bulk.** The NPI Registry, HRSA health centers and the IRS exempt-organization list
   publish free monthly downloads; load the pilot states (AZ, NM) into our own tables instead of calling their
   websites per listing.
4. **Check our own index first.** The second look looks a listing up in our index, then in official data,
   and only then spends a web search. Search becomes a last resort, then optional.

Rules that don't change: robots.txt and a polite pace on every site; only public sources; the pilot region's
boundaries; tribal lands presented as sovereign nations, their public services only, sacred or restricted
sites never pinpointed (`decisions/0043`); and the four `decisions/0044` guardrails decide what publishes.

## Consequences

- The reference index is Traversence's own asset: it grows with every run, and every reference carries its
  citation, which Discovery guides and the Chameleon engine can use too (`decisions/0043`).
- Worker time shifts from per-listing searches to steadily reading sources; the monthly search allowance
  covers only the gaps.
- A person curates the source list. That's deliberate: "read regularly" is a decision about what the
  platform copies facts from, and the terms of commercial sites rule some out.
- Storage stays small (facts, not pages), which fits the current MySQL database; `decisions/0004`'s
  Supabase move is still not triggered.
- Open: how often to re-read a source (start monthly); how far to follow links off a source (start: same
  website, plus one step to pages it links to inside the pilot region); the extraction rules for directory
  platforms (ChamberMaster/GrowthZone first, since several pilot chambers use it).

## Progress (2026-09-29)

Step 1 built: `api/migrations/2026-09-29_reference_sources.sql`, `api/lib/crawler/Sources.php`,
`admin/sources.php` (Admin → Sources: filters, kind, Read regularly / Cite only / Ignore, add by hand, and
"Recount from listings" to collect the websites found before the list existed). The second look
(`api/lib/crawler/Verify.php`) records each accepted reference and skips websites marked Ignore.

- **2026-09-30, steps 2 and 4 (first version):** the worker reads one "Read regularly" source per run
  (up to 25 pages on that website, directory-like links first; re-read every 30 days) and keeps each place's
  name, street address and phone (`source_facts`; from schema.org data where a site publishes it, otherwise
  a name line followed by an address or phone). Before a listing goes to the worker for its second look,
  the site checks it against these facts (`SourceIndex::matches`: name, plus house number or phone). A match
  is a free reference; if that confirms the listing, it publishes with no NPI call or paid search. Otherwise
  the NPI Registry and then web search run as before. Migration `2026-09-30_source_facts.sql`;
  `api/crawl/sources.php`; worker `SOURCE_TIME` (60 s) and `SOURCE_PAGES` (25).

