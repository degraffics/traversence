# The rural research test: Kayenta, St. Johns, Springerville (2026-11-14)

Decision 10 of `research/knowledge-engine-architecture-2026-11-14.md` (§1.5, §3.5). Research only: nothing on the live site
changed, no release, nothing under `workers/`, nothing paid, the Railway crawler left paused. Business names, addresses
and phones only; no people's names or emails (one health record named after a practitioner is counted, not named).

**In one line:** the open data finds most of these towns' businesses, but a business without a website almost always
shows up in it **once, as a Facebook page**, so open data alone establishes **none** of them; a second, independent
witness (Google confirming it open, a chamber or a directory found by grounded search) is what does it, and the
licence route couldn't be tested here (the network blocks it) and doesn't reach Kayenta.

**Run 2, in one line** (§8 below, same day, with the access set up): of 264 businesses in the six kinds, 130 have a
website (route 1) and **NPI alone establishes 34** of the 134 without one, all health care (§12 C3; about half of
Kayenta's are back offices or departments, not places to visit). **The collaborative score establishes none**: not one
no-website business is in two independent free sources, so the best score is 25. The Arizona licence sites can't be
used (Cloudflare challenges; TPT checks only a licence number, behind a CAPTCHA). Gemini's free key has **no grounding
allowance**. From memory it gets 55% of businesses right but only 30% of phones, so it **can't be a finder**. OSM's
mirrors were too busy to answer. **Our site lists nothing in Kayenta.**

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

---

## 8. Run 2: the full test with the access set up (2026-11-14, 20:55–22:30 UTC)

Jason (via the session manager): "set up the access and rerun the full test". Same rules as run 1: research only, nothing
on the live site changed, no release, nothing under `workers/`, nothing paid, the Railway crawler left paused. Same towns,
boxes and kinds (§2). Every request named Traversence and support@traversence.com, at small volume, with pauses. Business names,
addresses and phones only. Practitioners listed under their own names (NPI individuals, our "Doctors" and "Nurse
Practitioners" listings, a few Overture records) are **counted, not named, and set aside** from the business counts.

### 8.1 What could be reached this time

| Source | Reached? | What happened |
|---|---|---|
| **Overture Maps Places** (`2026-10-21.0`, still the newest release) | **Yes** | Same read as run 1 (651 records); DuckDB's `httpfs` now installs from `extensions.duckdb.org` |
| **Foursquare OS Places** (Hugging Face, `release/dt=2026-10-15`) | **No: gated** | The dataset is public but `gated: auto`: each file answers **401** until a signed-in Hugging Face account accepts Foursquare's terms and sends its token. The listing (100 files, ~124 MB each) and the licence (Apache-2.0) were read. A free account token, saved as a network secret for `huggingface.co`, opens it. That's Jason's call. Foursquare still reaches us through Overture (§8.2) |
| **OpenStreetMap**: Overpass mirrors `overpass.kumi.systems`, `overpass.private.coffee` | **No: the servers were busy** | kumi answered **504** "Dispatcher_Client::request_read_and_idx::timeout. The server is probably too busy" on all 4 tries. private.coffee completed the connection but sent nothing in 180 s, 5 tries. Both front pages answer, so it isn't our access. `overpass-api.de` not tried (it bans us) |
| **Geofabrik** regional extract | **No** | Allowed, but the proxy's tunnel closes mid-handshake every time (`ws_closed_mid_exchange`), 3 tries over an hour |
| **Arizona Corporation Commission** (`ecorp.azcc.gov`) | **No** | Unreachable at first (502 at the gateway). Later it answered with a 301 to **`arizonabusinesscenter.azcc.gov`**, which isn't on the allowlist. The registry has moved; the new host is what to allow |
| **Arizona TPT** (`aztaxes.gov` License Verification; `azdor.gov`) | Reached, **not usable** | Terms in §8.6: the lookup takes **only an 8-digit licence number** (no name or town search), behind **Cloudflare Turnstile**. Skipped |
| **Liquor (DLLC, `azliquor.gov`), Contractors (`roc.az.gov`), Apache County (`apachecountyaz.gov`)** | **No: refused by the site** | Every page, `robots.txt` included, is a Cloudflare managed challenge ("Just a moment…", `cf-mitigated: challenge`), retried after 30 s. An automated lookup can't pass one, and shouldn't try to. Skipped |
| **Navajo County** (`navajocountyaz.gov`, `www.`) | **No: refused by the site** | A Cloudflare "Attention Required!" block (403) on every page, `robots.txt` included. Skipped |
| **NPI** (`npiregistry.cms.hhs.gov/api`, v2.1) | **Yes** | 10 calls (each town spelling × individuals and organisations); 70 organisation records kept, individuals counted only |
| **Gemini** (key as a network secret) | **Partly** | Grounded search (`google_search`) answers **429 quota exceeded** on every current Flash model (3.8, 3.5, 3 preview, 3.1 lite, flash-latest), retried after 30 s. `gemini-2.5-flash`, which had a free grounding allowance, is **"no longer available to new users"** (404). The same models answer **without** grounding, so the key works but its free tier has no grounding allowance. In grounding's place I ran 15 ungrounded "from what you know" finder calls on `gemini-3.5-flash` (one per town and kind). The free tier's rate limit stopped the last three (Springerville/Eagar auto, health and outfitters). About 40 requests in all, failures included |
| **Our listings** (`traversence.com/api/search_listings.php`, the public directory API) | **Yes** | ~25 paced calls. St. Johns 184 listings (city "Saint Johns"), Springerville 158, Eagar 116; **Kayenta 0** (the universal search marks Kayenta "outside") |

### 8.2 Method changes from run 1

- **The kind is part of the join** (run 1's own note): Overture records join only within a kind, on the same phone or within 400 m
  with matching name words. Other sources join on the phone, or on the distinctive name words (generic words such as
  "auto parts", "RV park" and "restaurant" don't count). A phone used by more than two businesses (a hospital switchboard, a
  county office) is never a join key. Overture's six kinds give 172 businesses (run 1: 171).
- **Other sources' kinds:** NPI organisations are health care (school districts dropped). Our listings' categories map
  to the six kinds (ignition-interlock vendors and the like dropped). Gemini's leads take the kind they were asked for.
- **"Has a website"** as in run 1, from Overture's or our listing's website field; Gemini's websites are not trusted.
- **Independent origins:** Overture's upstreams count separately (Meta, Microsoft, Foursquare, BrightQuery, DAC), plus
  NPI. Our listings and Gemini are **not** counted as origins for establishing (§8.7).
- Scripts and raw pulls were kept in the session's scratchpad, not in the repo (research only; no data files committed).

### 8.3 Per town: what each source finds (businesses in the six kinds, open)

| Town | Businesses (all sources) | Overture | Meta | Microsoft | Foursquare | BrightQuery | DAC | NPI orgs | Ours | Gemini (memory) | OSM | Licences |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Kayenta | 51 | 33 | 27 | 7 | 1 | 0 | 2 | 11 | **0** | 23 (16 confirmed) | busy | — (Navajo Nation) |
| St. Johns | 79 | 45 | 35 | 6 | 4 | 0 | 1 | 14 | 24 | 25 (10 confirmed) | busy | refused |
| Springerville/Eagar | 134 | 94 | 73 | 10 | 11 | 6 | 4 | 22 | 47 | 32 (18 confirmed) | busy | refused |

Practitioners set aside (counted, not named): St. Johns 12, Springerville/Eagar 23 (our listings and NPI individuals);
NPI also lists 200+ individuals at Kayenta's Indian Health Service hospital, none counted.

### 8.4 By kind

Columns: businesses found by any source · in Overture · NPI · our listings · Gemini's leads · has a website · no website ·
in two or more independent origins.

| Town | Kind | All | Overture | NPI | Ours | Gemini | Website | No website | 2+ origins |
|---|---|---|---|---|---|---|---|---|---|
| Kayenta | Places to eat | 13 | 10 | 0 | 0 | 10 | 9 | 4 | 1 |
| | Auto repair and fuel | 9 | 7 | 0 | 0 | 6 | 4 | 5 | 1 |
| | Lodging | 5 | 5 | 0 | 0 | 3 | 3 | 2 | 2 |
| | Groceries | 2 | 2 | 0 | 0 | 1 | 2 | 0 | 0 |
| | Health care | 18 | 5 | 11 | 0 | 2 | 5 | 13 | 0 |
| | Outfitters and recreation | 4 | 4 | 0 | 0 | 1 | 2 | 2 | 0 |
| St. Johns | Places to eat | 21 | 14 | 0 | 5 | 6 | 7 | 14 | 0 |
| | Auto repair and fuel | 14 | 8 | 0 | 5 | 4 | 6 | 8 | 1 |
| | Lodging | 7 | 4 | 0 | 2 | 2 | 4 | 3 | 0 |
| | Groceries | 7 | 5 | 0 | 1 | 4 | 3 | 4 | 0 |
| | Health care | 24 | 9 | 14 | 11 | 7 | 9 | 15 | 4 |
| | Outfitters and recreation | 6 | 5 | 0 | 0 | 2 | 1 | 5 | 0 |
| Springerville/Eagar | Places to eat | 25 | 19 | 0 | 9 | 10 | 17 | 8 | 1 |
| | Auto repair and fuel | 25 | 22 | 0 | 7 | 1 | 14 | 11 | 2 |
| | Lodging | 27 | 22 | 1 | 8 | 9 | 18 | 9 | 2 |
| | Groceries | 11 | 6 | 0 | 1 | 8 | 5 | 6 | 0 |
| | Health care | 34 | 15 | 21 | 18 | 3 | 14 | 20 | 7 |
| | Outfitters and recreation | 12 | 10 | 0 | 4 | 0 | 7 | 5 | 0 |

"Gemini" counts every lead it gave, right or not (§8.8). Totals: Kayenta 51, St. Johns 79, Springerville/Eagar 134 = 264.

### 8.5 Overlaps and businesses only one source knows

**Two or more independent origins:** Kayenta 4 (NAPA Auto Parts: DAC + Foursquare; Sonic Drive-In, Monument Valley Tours,
Hampton Inn Kayenta: Meta + Microsoft), St. Johns 5 (Bride's Auto Center, Little Colorado Behavioral Center, Black Ridge
Physical Therapy, Outback Chiropractic, White Mountain Communities Special Health Care District), Springerville/Eagar 12
(among them Tow to the Rescue, Wildfire Espresso & Smoothie Bar, Safelite AutoGlass, Americas Best Value Inn, Round
Valley Smiles, Eagar Family Chiropractic, Western Drug and General Store, White Mountain Regional Medical Center, which
is in six). **Every one of the 21 has a website.** No business without a website is in two independent free sources.

**Known to one source only:**

| Town | Meta | Microsoft | Foursquare | BrightQuery | DAC | NPI | Ours | Gemini only |
|---|---|---|---|---|---|---|---|---|
| Kayenta | 14 | 3 | 0 | 0 | 0 | 11 | 0 | 7 |
| St. Johns | 22 | 2 | 2 | 0 | 0 | 7 | 9 | 15 |
| Springerville/Eagar | 43 | 3 | 6 | 3 | 2 | 8 | 12 | 14 |

Examples without a website, one source each:
- **Meta only:** Golden Rice Bowl (US-160, Kayenta, 928-697-3570); The Beets Gymnasium (Kayenta); Betty's Bakery (580
  W Cleveland St, St. Johns); Village Café (140 Commercial St, St. Johns); Gas-n-Go (1010 W Cleveland, St. Johns); Om
  Place RV Park (741 E Main St, Springerville); All American Paint and Body (632 E Main St, Springerville); Round Valley
  Tiny Village (510 N Main St, Eagar).
- **NPI only:** TRC Four Corners Dialysis Clinics (Hwy 163 #217, Kayenta, 928-697-8182); Kayenta Outpatient Treatment
  Center (ADOT Rd & AZ-163); Hinkson Assisted Living Center (725 N 13th W, St. Johns, 928-337-2499); La Casa Serena (324
  E 1st St, Eagar, 928-333-0151); EZ Counseling (910 S Burk St, Eagar).
- **Our listings only** (no open source has them): Rest Ur Rump (815 N 2nd Pl), El Camino (277 S White Mountain Dr), St
  Johns RV Resort (2135 W 7th S), New Performance (435 W Cleveland St) in St. Johns; El Corrales (211 S Mountain Ave), 26
  Bar Bed & Breakfast (26 Bar Rd), Arizona's 24/7 Fishing Trips (213 S Zuni St), HD Diesel (1024 Spanish Trl, Eagar),
  Coppedge's Auto Wrecking in Springerville/Eagar.

**What we list against the open data:** of Overture's businesses in the six kinds, we list **13 of 45** in St. Johns,
**29 of 94** in Springerville/Eagar and **0 of 33** in Kayenta. Of our 71 businesses in the six kinds in the two Apache County
towns, 21 are in no open source. Our listings are older bulk imports: their categories run to practitioners and vendors
(ignition interlock, propane exchange).

### 8.6 Terms, per source

| Source | Terms read | Verdict |
|---|---|---|
| Overture | Each record's `sources[].license` (run 1, unchanged) | Use with credit (CDLA-Permissive-2.0; Foursquare's records Apache-2.0; AllThePlaces CC0) |
| Foursquare OS Places | Hugging Face card: Apache-2.0, `NOTICE.txt`; **gated**: a person accepts the terms with an account | Usable once an account accepts; same credit and NOTICE rules as run 1 |
| OpenStreetMap | ODbL (decisions/0065) | Unchanged: its own layer, credited. Not reached this run |
| `aztaxes.gov` / `azdor.gov` | `robots.txt`: `User-agent: *` **Allow**, `Content-Signal: search=yes, ai-train=no, use=reference`; named AI crawlers (ClaudeBot, GPTBot, Google-Extended…) **disallowed**; privacy policy read | A paced lookup under our own agent is allowed by the file, but the License Verification form takes **only a licence number** and runs **Cloudflare Turnstile**. It can't find businesses, and a CAPTCHA means "not for automation". **Use it only to check a TPT number the owner gives us at claim**, by hand or in the owner's own session |
| `azliquor.gov`, `roc.az.gov`, `apachecountyaz.gov` | Unreadable: Cloudflare challenge on `robots.txt` itself | Skipped: a site that challenges every visitor isn't one to automate. Ask each agency for its public licence list (bulk file or public-records export) instead |
| `navajocountyaz.gov` | Unreadable: Cloudflare block | Skipped, as above |
| Arizona Corporation Commission | Not reached (moved to `arizonabusinesscenter.azcc.gov`) | Allow the new host and read its terms first |
| NPI (CMS NPPES) | Public federal registry with an open API, no key; data published by CMS under the NPPES public-disclosure rule | **Use**: store and show an organisation's facts with the source (§12 C3). Individuals stay counted only (R6) |
| Gemini | Google's API terms; grounded results show only as the cited links | Grounded search **not available on this free key**; ungrounded answers are never a source (R13) |
| Our site | No `robots.txt` (404); our own public API | Fine for our own audits. A `robots.txt` should exist; noted for the site audit |

### 8.7 What each route establishes (the 134 businesses without a website)

| Town | No website | Route 1: website (already) | **Route 2: NPI alone** | Route 3: score ≥ 60 (run-1 weights) | Best score | Only in ours or Gemini |
|---|---|---|---|---|---|---|
| Kayenta | 26 | 25 | **11** | **0** | 25 | 7 |
| St. Johns | 49 | 30 | **10** | **0** | 25 | 23 |
| Springerville/Eagar | 59 | 75 | **13** | **0** | 25 | 25 |
| **All** | **134** | **130** | **34** | **0** | | **55** |

- **A licence alone:** of the Arizona licences, none could be read (§8.1). **NPI**, a government registry that
  establishes alone (§12 C3), establishes 34, all health care, 26 of them in no other source. **The caution:** in
  Kayenta about half aren't places a visitor goes: an air-ambulance base at a mobile-home park, a VA registration with an
  out-of-state phone, the health center's radiology and counselling departments listed apart, residential treatment
  programmes, a home-care agency. NPI needs two guards before it creates a listing: fold departments into the parent (same
  address or building), and send a non-public kind (air transport, home care, residential treatment, VA registrations)
  to Review rather than straight to establishing.
- **The collaborative score:** with run 1's weights and threshold 60, **nothing** establishes. Every no-website business
  is one origin: a Facebook page (20), an NPI record (25, but that's route 2), BrightQuery or DAC (25), or Microsoft (20).
  Lowering the threshold doesn't help: at 40 it still establishes none, and at 20 it would establish every lone Facebook
  page, which run 1 showed includes wrong towns and non-businesses. **The free sources can't reach 60 for these
  businesses. Only witnesses we couldn't run here can: Google Places, grounded directory pages, a chamber.**
- **Gemini's role as a finder: none established.** Its leads score 0 by design, and ungrounded it has no page to cite.

### 8.8 Gemini's accuracy (ungrounded, from memory)

| Town | Leads | Businesses | Confirmed by another source | Phone right (of confirmed) |
|---|---|---|---|---|
| Kayenta | 28 | 23 | 16 (70%) | 6 |
| St. Johns | 25 | 25 | 10 (40%) | 3 |
| Springerville/Eagar | 33 | 32 | 18 (56%) | 4 |
| **All** | **86** | **80** | **44 (55%)** | **13 (30% of confirmed; 15% of all leads)** |

- **Confirmed** means Overture, NPI or our listings has the same business in that town. Most confirmed leads are chains
  and landmarks (McDonald's, Sonic, Safeway, Bashas', Wetherill Inn, Kayenta Health Center, Booga Red's, Reed's Lodge).
  The local independents it named, such as C & R Drive In, Dynamic Duo Pizza & Coffee, Java Blues, Rodeo Pizza, Go Mex
  Gourmet Taco Shop, Teel's Motel and K-Bar RV Park, are in no other source. They may exist, but nothing here can tell.
- **Its phones are made up:** it gave **928-337-2101** to both a Chevron and an RV park in St. Johns, **928-337-2300** to
  both El Ranchero and Family Dollar, and an RV park the number of Apache County's health office (928-333-2415, per NPI).
  Most phones it gave for confirmed chains don't match theirs.
- **It found no business that is both new to the other sources and checkable.** From memory, Gemini adds noise, not
  finds. R13 and §1.5 already say a lead counts only with a cited link; this run shows why. Without grounding it must not
  run as a finder at all.

### 8.9 Revised recommendation for §3.5 (for Jason's approval)

**Keep threshold 60 and run 1's weights, with three changes:**

| Change | Why (this run) |
|---|---|
| **Google Places: 40 when it agrees on the phone, 30 on the address only** (was 30) | No no-website business has a second free source, so the realistic proof is its Facebook page plus Google. Facebook (20) + Google on the phone (40) = 60 establishes; a looser address-only match (50) still waits for a third source |
| **NPI keeps "alone establishes" (§12 C3), with two guards:** departments fold into their parent, and non-public kinds (air ambulance, home care, residential treatment, VA registrations) go to Review | 34 established, but about half of Kayenta's aren't places to visit |
| **Gemini ungrounded: 0, and never run as a finder**; grounded leads stay 0 themselves (their cited page scores by its class) | 55% of businesses right, 15% of phones; repeated invented numbers |

**Unchanged:** Facebook 20; another open-dataset origin 20; business-managed or filings-built 25; directory found by
grounded search 15 (two at most); OSM 15; chamber 25; news 10; half weight under 0.5 confidence; closure −40.
**Our own listings: 0.** They're old bulk imports, the very thing being tested, but they are a **lead source**: 21
businesses in the Apache County towns are only there, worth Google's check.

**Order of work, revised:**
1. Overture as the base layer (unchanged).
2. **NPI by town** for health care, with the two guards (free, no key, already reachable).
3. **Google Places' check** for no-website businesses that have a phone (Facebook-only, ours-only): about 100 calls for
   these three towns (~$3 within the paced budget), which run 1 and this run show is the one witness that can complete
   their score.
4. **Grounded search** only once a grounding allowance exists. This key's free tier has none on any current model, and
   paying for it is a spending decision (R19, "no income, no expense") for Jason.
5. **Licences:** allow `arizonabusinesscenter.azcc.gov` and read its terms. Ask DLLC, ROC and Apache County for their
   public licence lists as files; their sites block automated lookups. TPT only at claim, from the owner's number.
   Kayenta: the Navajo Nation's own sources under 0066 (unchanged).
6. **OSM** by a Geofabrik extract when the proxy carries it, or an Overpass mirror at a quiet hour. Foursquare
   directly once a Hugging Face account accepts its terms (Jason's call). Both add coverage, but by this run's evidence
   rarely a second witness for a no-website business.
7. **Kayenta needs listings:** we have none. Overture's 33 plus NPI's 11 are a ready start under 0066's rules.

Data used: Overture Maps Foundation, release 2026-10-21.0 (CDLA-Permissive-2.0; Foursquare records Apache-2.0); NPPES NPI
Registry (CMS), 2026-11-14; Traversence's public directory API. No OpenStreetMap data was received in this run.
