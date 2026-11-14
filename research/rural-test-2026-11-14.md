# The rural research test: Kayenta, St. Johns, Springerville (2026-11-14)

Decision 10 of `research/knowledge-engine-architecture-2026-11-14.md` (§1.5, §3.5). Research only: nothing on the live site
changed, no release, nothing under `workers/`, nothing paid, the Railway crawler left paused. Business names, addresses
and phones only; no people's names or emails (one health record named after a practitioner is counted, not named).

**In one line:** the open data finds most of these towns' businesses, but a business without a website almost always
shows up in it **once, as a Facebook page**, so open data alone establishes **none** of them; a second, independent
witness (Google confirming it open, a chamber or a directory found by grounded search) is what does it, and the
licence route couldn't be tested here (the network blocks it) and doesn't reach Kayenta.

---

## 1. What could be reached

This session's network allows only a few hosts. Each blocked one was recorded and skipped; no setting was changed.

| Source | Reached? | What happened |
|---|---|---|
| **Overture Maps Places** (release `2026-10-21.0`) | **Yes** | Read straight from its public bucket (`overturemaps-us-west-2`, HTTPS), DuckDB reading only the row groups in each town's box: 651 places in 3 seconds, no download of the full files |
| **Foursquare open places** | Partly | Its old public bucket now holds only `LICENSE.txt` and `NOTICE.txt` (read: Apache-2.0); the data has moved to Hugging Face (account and token), which is blocked here. Foursquare **does** reach us through Overture: 13 of these businesses carry a Foursquare record |
| **OpenStreetMap** (Overpass mirrors kumi.systems, private.coffee, mail.ru, osm.ch; Geofabrik; the OSM API) | No | Refused by the session's proxy. overpass-api.de was not tried (it bans us). The site's own `site-audit/towns.md` shows the same: St. Johns' town builds failing on Overpass timeouts and the 406 |
| **Arizona Corporation Commission, AZ TPT lookup (azdor.gov, aztaxes.gov), DLLC (azliquor.gov), Registrar of Contractors (roc.az.gov), Apache and Navajo county sites, NPI** | No | All refused by the proxy, so neither their terms nor their records could be read |
| **Gemini grounded search** | Skipped | No `GEMINI_API_KEY`. In its place, eight web searches by hand (§4) show what it would add |
| **Our listings** | Partly | The live database and the public site are unreachable here; the mirror holds code, not listings. Counts come from the health report in `site-audit/towns.md` (2026-11-14 10:19 UTC) |

To run the missing half, the environment needs those hosts allowed (Overpass mirror, the four Arizona agencies, the two
counties, `npiregistry.cms.hhs.gov`, `huggingface.co`). That's Jason's call; I didn't ask for it in this run.

## 2. The towns and the method

- **Boxes:** Kayenta 36.68–36.76 N, 110.22–110.32 W; St. Johns 34.47–34.54 N, 109.31–109.41 W; Springerville
  34.08–34.17 N, 109.24–109.33 W, which takes in **Eagar** (the two towns are one valley, Round Valley).
- **Kinds:** places to eat (`food_and_drink`); auto repair and fuel (vehicle service, fuel, parts); lodging; groceries
  (food stores, convenience stores, markets); health care; outfitters and recreation (sports and recreation other than
  parks, travel and tour services, outdoor stores).
- **One business, many records:** Overture keeps some of its upstreams' records apart, so the same business can appear
  twice (DaVita Kayenta Dialysis, Hampton Inn Kayenta, NAPA). Records were joined when they share a phone, or are within
  400 m with matching name words. 651 records became 535 places; in the six kinds, 228 records became 171 businesses
  (3 permanently closed set aside).
- **Overture's upstreams** are named on every record (`sources[].dataset`), so one Overture extract is really several
  sources: **Meta** (Facebook pages), **Microsoft** (Bing), **Foursquare**, **BrightQuery** (built from business
  filings), **DAC** and **Uberall** (listings the business manages), **AllThePlaces** (scraped from chains' store pages).

## 3. Per town

### Businesses found by each source (the six kinds, open records)

| Town | Businesses | Meta (Facebook) | Microsoft | Foursquare | BrightQuery | DAC | AllThePlaces | OSM | Licences |
|---|---|---|---|---|---|---|---|---|---|
| Kayenta | 31 | 27 | 8 | 2 | 0 | 2 | 1 | blocked | blocked |
| St. Johns | 43 | 35 | 6 | 4 | 0 | 1 | 0 | blocked | blocked |
| Springerville/Eagar | 97 | 77 | 11 | 7 | 7 | 4 | 1 | blocked | blocked |

### By kind

| Town | Kind | Businesses | Has a website | No website | In 2+ sources |
|---|---|---|---|---|---|
| Kayenta | Places to eat | 10 | 9 | 1 | 3 |
| | Auto repair and fuel | 6 | 4 | 2 | 2 |
| | Lodging | 5 | 2 | 3 | 1 |
| | Groceries | 0* | 0 | 0 | 0 |
| | Health care | 5 | 5 | 0 | 0 |
| | Outfitters and recreation | 5 | 3 | 2 | 1 |
| St. Johns | Places to eat | 13 | 6 | 7 | 0 |
| | Auto repair and fuel | 8 | 6 | 2 | 1 |
| | Lodging | 3 | 3 | 0 | 0 |
| | Groceries | 5 | 4 | 1 | 1 |
| | Health care | 7 | 7 | 0 | 1 |
| | Outfitters and recreation | 7 | 1 | 6 | 0 |
| Springerville/Eagar | Places to eat | 21 | 19 | 2 | 1 |
| | Auto repair and fuel | 24 | 18 | 6 | 1 |
| | Lodging | 19 | 13 | 6 | 0 |
| | Groceries | 9 | 6 | 3 | 3 |
| | Health care | 14 | 11 | 3 | 4 |
| | Outfitters and recreation | 10 | 6 | 4 | 0 |

\* Kayenta does have a supermarket: Bashas' landed under places to eat (one of its two records is its deli), and the
Giant store joined Speedway's records at the same corner. The 400 m join also folds a hotel's restaurant and tours into
the hotel (Kayenta Monument Valley Inn, 6 records). A real build needs the category on the join, not only the place.
"Has a website" means a site other than Facebook, Airbnb, MapQuest, Wikipedia or a directory; for chains it's often a
store-locator page naming the location, which counts as "its own website naming it" (§3.5 route 1).

### Overlaps

Few businesses are in more than one upstream: **7 of 31** in Kayenta, **3 of 43** in St. Johns, **9 of 97** in
Springerville/Eagar. Nearly all overlaps are chains or institutions (Speedway in four, Safeway and Bashas' in three; Sonic,
Burger King, Hampton Inn, Champs Chicken, North Country HealthCare, White Mountain Regional Medical Center in two). Of the
local independents, only Tow to the Rescue, Wildfire Espresso & Smoothie Bar, Bride's Auto Center, White Mountain Eye
Institute and Round Valley Smiles appear twice.

### Businesses only one source knows

| Town | Only Meta | Only Microsoft | Only Foursquare | Only BrightQuery | Only DAC |
|---|---|---|---|---|---|
| Kayenta | 21 | 3 | 0 | 0 | 0 |
| St. Johns | 32 | 5 | 2 | 0 | 1 |
| Springerville/Eagar | 69 | 6 | 5 | 5 | 3 |

**Every business without a website** is in that single-source group: Kayenta 8 of 8, St. Johns 16 of 16 and
Springerville/Eagar 21 of 24 are known **only from a Facebook page** (the other three only from BrightQuery). Examples,
all with a phone and an address in the record: Golden Rice Bowl (US-160, Kayenta); Ditty's Pizza (25 E Commercial St),
Betty's Bakery (580 W Cleveland St), Jones Tire & Auto Services (1350 White Mountain Dr) in St. Johns; Doyle's Auto Body
(51 W Central Ave), All American Paint and Body (632 E Main St), Om Place RV Park (741 E Main St), Round Valley Tiny
Village (510 N Main St) in Springerville/Eagar.

Some single-source records are noise: low confidence (Overture under 0.5: "Car Wash", "Details", "5D Fitness"), wrong
town (Draper Glass is in Tuba City, not Kayenta; a Downtown Iron phone appears in both St. Johns and Springerville), or
not a business of that kind (a mobile home park, a dance studio under recreation, the county jail). Overture's
`confidence` separated most of these: of 48 no-website records, the 9 under 0.5 hold most of the noise.

### What we list today

- **St. Johns:** 190 listings (186 unclaimed, 2 unclaimed hidden, 1 claimed-unverified hidden, 1 verified), 5 outdoor
  places (`site-audit/towns.md`). Our listing count is **above** Overture's 140 places in the box (all kinds); our
  listings came from earlier bulk sources and a wider area, and the town's own on-demand builds for "do", "essentials",
  "services", "civic" are stuck on Overpass errors. Eat and stay built (3 and 4 found).
- **Springerville/Eagar and Kayenta:** no town build yet and no row in the health report's town table, so the count
  couldn't be read here. The search probe counts "3,985 businesses near St. Johns" at its default radius, which takes
  in Springerville and Eagar, so both are covered by older listings; how many and which wasn't readable.
- **Next time:** a read-only count by town and category from the database (a Pulse report or the health endpoint)
  would let this table compare names, not just totals.

## 4. Witnesses: what grounded search would add

Eight no-website, Facebook-only businesses were looked up by hand, as Gemini grounded search would:

| Business | Second source found | Agrees on |
|---|---|---|
| Golden Rice Bowl, Kayenta | Kayenta Chapter page, TripAdvisor, Trip.com | phone (928-697-3570) |
| Ditty's Pizza, St. Johns | MenuPix, Restaurantji | phone and address |
| Jones Tire & Auto, St. Johns | Superpages | address |
| Doyle's Auto Body, Eagar | BBB (accredited), Birdeye | phone and address |
| Betty's Bakery, St. Johns | Fox 10 news story | name and town only |
| Round Valley Tiny Village, Eagar | a vacation-rental listing | name and town only |
| Little Pink House, Springerville | nothing | |
| Draper Glass, Kayenta | the business is in Tuba City | wrong town |

So **4 of 8** get an independent second source agreeing on the phone or the address, 2 more a mention, and 2 fail (one a
wrong town the score should reject). The second sources are directories, whose terms don't let us copy them: they're
**witnesses** (a cited link and a weight on the score), never what we show. Grounded search would find these same pages
at no cost within the free allowance (Decision 7).

## 5. What each mix would establish

The businesses without a website in the six kinds: **48** (Kayenta 8, St. Johns 16, Springerville/Eagar 24). Those with
a website (Kayenta 23, St. Johns 27, Springerville/Eagar 73) already qualify by route 1.

| Mix | Establishes (no-website businesses) | Notes |
|---|---|---|
| **A licence alone** (route 2) | not tested | The licence sites are blocked here. On the expected yield: liquor (bars, some groceries and restaurants), contractors (auto glass and body shops rarely; they aren't contractors), and food-service health permits would cover most places to eat and groceries in **St. Johns and Springerville** (Apache County). **Kayenta is on the Navajo Nation**: Arizona TPT and county health permits generally don't reach businesses there (the Navajo Tax Commission, the Nation's environmental health program and Kayenta Township's business site leases are the authorities), so the licence route needs the Nation's own sources, under decisions/0066, before it helps Kayenta |
| **B open data alone** (Overture with Foursquare) | **0 of 48** | Each is one Facebook page; the highest no-website score is 30 (BrightQuery), whatever the weights, below any threshold that asks for agreement between two sources |
| **C open data + grounded search** | about **4 of 8** reach two agreeing sources (≈ 24 of 48) | at 60, still short without Google (Facebook 20 + directory 15 = 35) |
| **D open data + grounded search + Google Places open** | about **4 of 8** (≈ 24 of 48) at 60 | 20 + 15 + 30 = 65; Google's 3¢ check only for candidates that already have two agreeing sources |
| **E all of it + a licence** | most of the rest in Apache County | the licence establishes alone |

## 6. Licences and terms

| Source | Licence | Store and show with credit? | Limits |
|---|---|---|---|
| **Overture Maps Places** | **CDLA-Permissive-2.0** on Meta, Microsoft, BrightQuery, DAC, Uberall and Overture's own fields; **Apache-2.0** on Foursquare's records; **CC0** on AllThePlaces (read from each record's `sources[].license`) | **Yes**, with credit: "Overture Maps Foundation" plus the upstream; keep each record's source and licence with it | Monthly releases; IDs (GERS) are stable across releases, use them as the key. Overture also carries an `emails` field: **don't import it** (Decision 2's rules apply only to emails found on a business's own page) |
| **Foursquare OS Places** | **Apache-2.0** (NOTICE.txt read) | **Yes**: keep the licence and NOTICE.txt with any data we distribute, mark changes, credit Foursquare | Now distributed through Hugging Face with an account; already in Overture, so the direct copy adds coverage but **counts as the same source** where Overture carries its record |
| **OpenStreetMap** | ODbL (unchanged; decisions/0065) | Its own layer, credited "© OpenStreetMap contributors", never copied into our records | Overpass blocked here and banned on overpass-api.de; a regional extract (Geofabrik) read once a week would avoid Overpass for towns |
| **Arizona state and county records** | not read (blocked) | Public records; §1.5's rule stands (store and show the facts with the source) | Each site's terms are to be read before any automated lookup; that's the first step when the hosts are allowed |
| **Directories found by search** (BBB, Superpages, MenuPix, TripAdvisor) | proprietary | **No**: witness only, a cited link | |
| **Gemini grounded search** | Google's grounding terms | the cited links only; never Google's text | free daily allowance (Decision 7) |

## 7. Recommendation for §3.5

**Count sources by origin, not by channel.** Overture's Meta record **is** the business's Facebook page, and its
Foursquare record **is** Foursquare's open data: each counts once, however many channels bring it. Directories copy one
another and the data sellers, so at most two count.

| Source (agreeing on the name plus the phone or the address) | Weight |
|---|---|
| Google Places confirms it open (place ID kept) | 30 |
| Chamber of commerce or tourism office listing | 25 |
| Business-managed listing (Uberall, DAC) or filings-built (BrightQuery) | 25 |
| An open-dataset record from another origin (Microsoft, Foursquare) | 20 |
| Its Facebook page (or Overture's Meta record, once) | 20 |
| A directory page found by grounded search (at most two count) | 15 |
| OpenStreetMap | 15 |
| A news story naming it in that town (name and town only) | 10 |
| A Gemini lead itself | 0 (the page it cites scores by its own class) |
| A record under 0.5 Overture confidence | half its weight |
| A closure signal (Overture "permanently closed", Google closed, a licence lapsed) | −40, and two send it to Review (W2) |

**Threshold: 60.** It needs at least three independent sources, or two strong ones (Google + a chamber, Google + a
business-managed listing). From this test: a Facebook page and one directory (35) don't establish; add Google (65) and
they do. Facebook + two directories (50) doesn't, which is right where directories may copy each other. Lowering it to
50 would establish Facebook + Google alone; worth trying only after the first months show how often Google and Facebook
agree on a business that turns out to be gone.

**Order of work that follows:** (1) load Overture by town box as the base layer (free, monthly, licence clear); (2) for its
no-website businesses, grounded search for witnesses (free allowance); (3) Google's open check only for those already at
35 or more, inside its paced budget; (4) licences for Apache County first; the Nation's own sources for Kayenta under
0066; (5) rerun this test with the licence sites, OSM and our listings by name, once the environment allows those hosts.

## Method, for rerunning

Overture, without credentials (the environment's AWS placeholder must not be used; list the files over HTTPS instead):

```sql
-- DuckDB 2.0; httpfs loaded from the PyPI wheel duckdb-extension-httpfs (extensions.duckdb.org is blocked)
SELECT id, names.primary, taxonomy, basic_category, confidence, websites, socials, phones,
       addresses, sources, operating_status, bbox
FROM read_parquet([<the 16 https://overturemaps-us-west-2.s3.amazonaws.com/release/2026-10-21.0/theme=places/type=place/*.parquet files>])
WHERE bbox.ymin BETWEEN 36.68 AND 36.76 AND bbox.xmin BETWEEN -110.32 AND -110.22;  -- one box per town
```

Joining records: same phone, or within 400 m with matching name words. Kinds from `taxonomy.hierarchy`. Data used:
Overture Maps Foundation, release 2026-10-21.0 (CDLA-Permissive-2.0; Foursquare records Apache-2.0; AllThePlaces CC0).
