# State tourism offices: APIs, feeds and data terms

Research date: 2026-11-12. Method: web search only. This session's network blocked direct page fetches (visitarizona.com,
simpleviewinc.com and developer.nps.gov all refused), so nothing below was read from the live page itself. Every item comes
from search-engine extracts of the cited pages. **Confirmed** means a search extract quoted the source's own wording.
**Unconfirmed** means it was inferred, came from memory or was not checked. Treat every licence point as something to
verify before the crawler uses the data.

## Summary

**No state tourism office publishes an open, documented, licensed public API for its listings or events.** Not one of the 51
has a developer page with published terms that allow commercial reuse by a third party. What exists falls into four groups:

1. **Public read endpoints, terms not stated:**
   - **Montana** (`data.visitmt.com`, the "Travel Montana Site Information Web Service") serves sites and events as
     HTML/XML/JSON per category and region, with search (confirmed). Bulk zip archives sit behind authorization. The state
     says listings are offered as web feeds to "tourism partners, regions and CVBs". No licence was found, so ask before
     reusing.
   - **Wisconsin** offers an embeddable events widget (`travelwisconsin.com/events/widgetcreate`). It's a display widget,
     not a data feed.
2. **Partner feeds or APIs on request** (credentials by email, intended for DMOs and regional partners):
   - **Oregon OTIS** (Oregon Tourism Information System): API docs and logins from `OTIS@traveloregon.com`, plus a WordPress
     import plugin (confirmed).
   - **Virginia**: a custom daily XML feed of virginia.org listings (events, lodging, dining, attractions), filtered by
     locality, free, 1–2 week turnaround. The 2017 sheet says "no service or api". A newer VTC page mentions "Data Sharing"
     (confirmed, but dated).
   - **Montana**: the authenticated flat-file archives above.
3. **Partner-only extranets with no outward feed** (businesses enter their own listings, and the data stays in the state's
   CMS/CRM): Arizona, New Mexico, Colorado, Nevada, Utah, Texas, Florida, Minnesota, North Carolina, Ohio, Kansas, Wisconsin
   and most others. On Simpleview, the CRM has a "Listings API" whose credentials the client DMO issues. A 2017-era Simpleview
   PDF calls it "methods for various queries of listings data for display on a remote web site". **A third party can only
   get it if the state office sponsors it.** Simpleview's public page says API access goes through the client's Account
   Executive or a request form.
4. **No statewide listings database found, or not checked.** California is the notable case: Visit California is a
   501(c)(6) nonprofit, and its site (Appnovation and Code and Theory builds) shows no partner listing database or feed.

**For our licence rule ("the licence decides the use"):**
- None of the state tourism listing sites state a licence that allows reuse.
- Business listings are written by the businesses, and AOT says "all information is provided and maintained by each business
  owner".
- **Crawling and copying visitarizona.com, newmexico.org and the others is not supported by any stated licence.** Use them
  only as discovery hints (that a business exists, a link to its own site), or get a written data-sharing agreement.

**Better free sources that already gather this data** (licences to verify):
- **NPS API** (developer.nps.gov): parks, places, things to do, events, campgrounds and alerts. Free key, 1,000 requests per
  hour. NPS-made material is public domain, with credit requested. Photos credited to anyone other than NPS are not public
  domain (confirmed from NPS disclaimer pages).
- **RIDB / Recreation.gov API**: federal recreation areas and facilities (USFS, BLM, USACE, NPS and others). Free key. The
  data.gov entry lists the licence as Creative Commons Attribution (confirmed from the catalog; the official ToS page wasn't
  read).
- **OpenTripMap**: ODbL, built from OSM, Wikidata and Wikipedia. Commercial use is allowed with attribution and share-alike.
  It also contains Russian ministry data with unclear terms, so for the US just use OSM and Wikidata directly (decision 0065
  already covers OSM).
- **Wikidata**: CC0 (well known; not re-checked here).
- **State GIS open data for parks and trails:**
  - Utah UGRC SGID (Trails and Pathways, Trailheads, Boat Ramps): open download and API.
  - New Mexico RGIS (state park boundaries): free.
  - Arizona AZGeo hub, and a statewide trails FeatureServer that includes AZ State Parks data.
  - Texas TPWD GIS Open Data (State Parks Boundaries, Public Areas): use as-is.
  - Colorado CPW Maps & GIS (shapefiles and services). COTREX has no open licence found.
  - **California State Parks GIS** (boundaries, routes, day-use, camping, entry points): open download, but **commercial use
    needs advance approval from geodata@parks.ca.gov** (confirmed).
- **Tourism Exchange USA** (Brand USA, built on the Tourism Exchange Australia tech): a B2B marketplace for bookable products
  (tours, activities) with live rates. Distributors connect through an account manager (Ventrata/OCTO and others). It's a
  bookings channel, not a listings source, but worth a look if Traversence later sells experiences. Utah and Colorado have
  joined. API docs are not public.

### Recommended next steps

1. **Don't crawl the state tourism sites for content.** Keep them as discovery links only, unless a state agrees in writing.
   Record this in the crawler's source register under decision 0133.
2. **Send a short request to each pilot-state office.** Ask for:
   - (a) a read-only export or API of their **public** listings and events (Simpleview CRM Listings API credentials, a
     Miles/extranet export, or a scheduled XML/JSON/CSV/iCal feed);
   - (b) the **licence**: may a third-party platform show it commercially with attribution and a link back;
   - (c) update frequency, and whether they can include their category codes, lat/long and the listing's last-modified date;
   - (d) whether businesses opted in to syndication (some extranets carry a sharing flag);
   - (e) the right contact for data.

   Contacts found:
   - **AZ**: AOT extranet support (extranet.visitarizona.com/support), via AOT industry relations.
   - **NM**: Industry.Relations@state.nm.us.
   - **CO**: partner.inquiries@colorado.com.
   - **NV**: industry@travelnevada.com.
   - **UT**: info@visitutah.com, 801-538-1900.
   - **TX**: TxDOT Travel Publications, for the Texas Highways events database that feeds traveltexas.com (they accept bulk
     uploads in table format, so they may export one too).
   - **CA**: geodata@parks.ca.gov for parks commercial-use permission.
   - **OR**: OTIS@traveloregon.com (outside the pilot, but a ready-made API).
   - **VA**: the datafeed contact on vatc.org.
3. **Ask the regional DMOs as well as the states.** City and county CVBs (Visit Phoenix, Visit Tucson, Visit Albuquerque,
   Visit Salt Lake, Visit Denver and others) are mostly Simpleview clients. Many already send events to the state and can
   issue Listings API credentials. An agreement with one DMO covers a dense area.
4. **Build on the open federal and state GIS layers now:** NPS API, RIDB, the state parks GIS above, OSM and Wikidata. They
   cover parks, landmarks and recreation without licence risk. California parks data is the exception: ask first for
   commercial use.
5. **Events:** no state publishes an open events feed. For events, the business's own site (schema.org Event, iCal) or
   agreements with DMOs are the clean routes.

## Platforms seen

| Platform | Evidence | Third-party data access |
|---|---|---|
| **Simpleview** (CRM, CMS, DAM) | Asset URLs under `assets.simpleviewinc.com/.../clients/<state>`. Seen for New Mexico, Kansas, Wisconsin. A 2019 announcement lists Texas, Rhode Island, New Mexico, Utah, New York and Kansas state tourism as clients. | CRM Listings API exists; the client DMO arranges credentials (Account Executive or Simpleview's request form). Its terms are set by each DMO. |
| **Miles Partnership** (extranet, data portal) | Colorado's business rules are hosted on `miles-extranet-dev` Cloudinary; events go through `data.colorado.com`. | No public API found. Ask CTO or Miles. |
| **iDSS** (CRM) | AOT's 2024 PDF pointed partners to `arizona.idss.com/extranet`. The current portal is `extranet.visitarizona.com` (vendor unconfirmed). | No public API found. |
| **OTIS** (Oregon, custom with Locl) | industry.traveloregon.com | API docs on request. |
| **VTC custom** (Virginia) | vatc.org datafeed sheet | Custom XML feed on request. |
| **Montana custom** (`data.visitmt.com`, `business.visitmt.com`) | Public service pages | Public HTML/XML/JSON endpoints; bulk downloads by authorization. |

## Table

Legend: **C** = confirmed in a source extract; **U** = unconfirmed or not checked (domain from general knowledge).

| State | Tourism site | Platform | Public API/feed? | Terms | Notes / links |
|---|---|---|---|---|---|
| **Arizona** | visitarizona.com (AOT; tourism.az.gov for industry) | Extranet at extranet.visitarizona.com; earlier arizona.idss.com (iDSS CRM). Site relaunched 2025; CMS vendor unknown (U) | **No** public API (C: no docs found). Free partner listings, events and deals entered by businesses | Listings "provided and maintained by each business"; no reuse licence stated. Partner-only entry (AZ-located tourism businesses, one listing each) | Alternatives: AZGeo Data Hub; statewide trails FeatureServer (includes AZ State Parks data); BLM AZ recreation sites on data.gov; NPS, RIDB. Ask AOT for an export or feed and a licence |
| **New Mexico** | newmexico.org (NM Tourism Dept) | **Simpleview** (C: asset URLs; 2019 client list) | **No** public API (C). Partners Portal for listings, special offers, calendar of events | Partner-only; no reuse terms published. Contact Industry.Relations@state.nm.us | Simpleview CRM Listings API could be issued by NMTD. Alternatives: RGIS state park boundaries (free, approximate); NPS, RIDB. Mind tribal land rules (decision 0066) |
| **Utah** | visitutah.com; industry site travel.utah.gov | **Simpleview** per 2019 client list (U, possibly changed) | **No** public listings API found. travel.utah.gov has an industry events calendar (WordPress) | No terms found. Contact info@visitutah.com | Joined Tourism Exchange USA. Best alternative: **UGRC SGID** recreation layers (trails, trailheads, boat ramps), open download and ArcGIS API (C); Now Playing Utah (arts events, Utah Division of Arts & Museums) |
| **Colorado** | colorado.com (CTO, part of OEDIT) | **Miles Partnership** extranet (C: miles-extranet Cloudinary; data.colorado.com intake) | **No** public API (C: none found). Industry Partner account for free listings, offers and events | Partner-only; business rules PDF. Contact partner.inquiries@colorado.com | Joined Tourism Exchange USA. Alternatives: CPW Maps & GIS (shapefiles and web services, C); COTREX trails (no open licence found); data.colorado.gov; NPS, RIDB |
| **Nevada** | travelnevada.com; industry travelnevada.biz | Unknown (U). Partner Portal for business and event pages | **No** public API found | Partner-only; contact industry@travelnevada.com | Alternatives: NPS, RIDB, BLM; NV State Parks GIS (not checked) |
| **California** | visitcalifornia.com (Visit California, 501(c)(6) nonprofit) | Custom; built by Appnovation (2020), CMS replatformed by Code and Theory; Mindtrip AI planner (C) | **No** statewide listings database or feed found | n/a | Local DMOs run their own Simpleview-type extranets. **CA State Parks GIS open data (boundaries, routes, day-use, camping, entry points); commercial use needs advance approval from geodata@parks.ca.gov** (C). data.ca.gov / CNRA hub |
| **Texas** | traveltexas.com (Office of the Governor, Travel Texas) | **Simpleview** per 2019 client list (U). Events come from TxDOT's **Texas Highways Events Calendar** | **No** public API (C: none found). Events submitted via texashighways.com feed both sites; bulk upload accepted for 10+ events | No reuse terms found. Contact TxDOT Travel Publications | Alternatives: TPWD GIS Open Data (State Parks Boundaries, Public Areas FeatureServers, use as-is, C); city portals (Austin ACCD event listings); NPS, RIDB |
| Alabama | alabama.travel | U | Not found | U | |
| Alaska | travelalaska.com | U | Not found | U | NPS, RIDB cover most federal lands |
| Arkansas | arkansas.com | U | Not found | U | |
| Connecticut | ctvisit.com | U (new-partner application) | Not found | Partner-only | |
| Delaware | visitdelaware.com | U | Not found | U | |
| District of Columbia | washington.org (Destination DC) | U | Not found | U | |
| Florida | visitflorida.com (VISIT FLORIDA; industry visitflorida.org) | U | **No** (C: none found). Login-only Partner Portal and Partner Tools | Partner-only (marketing partners); partner@visitflorida.org | |
| Georgia | exploregeorgia.org | U | Not found | U | |
| Hawaii | gohawaii.com | U | Not found | U | |
| Idaho | visitidaho.org | U | Not found | U | |
| Illinois | enjoyillinois.com | U | Not found | U | |
| Indiana | visitindiana.com | Simpleview user per a third-party estimate (U) | Not found | U | |
| Iowa | traveliowa.com | U | Not found | U | |
| Kansas | travelks.com | **Simpleview** (C: assets, Map Publisher case study) | Not found | Partner-only | Simpleview Listings API possible via the state |
| Kentucky | kentuckytourism.com | U | Not found (C: none in search) | U | |
| Louisiana | louisianatravel.com | U | Not found | U | |
| Maine | visitmaine.com | U | Not found | U | |
| Maryland | visitmaryland.org | U | Not found | U | opendata.maryland.gov: county, municipal and state welcome/visitor centre datasets (C) |
| Massachusetts | visitma.com | U | Not found | U | |
| Michigan | michigan.org (Pure Michigan) | U | Not found (C: events calendar only) | U | |
| Minnesota | exploreminnesota.com | Extranet (C) | **No** (C) | Partner-only | |
| Mississippi | visitmississippi.org | U | Not found | U | |
| Missouri | visitmo.com | U | Not found | U | |
| **Montana** | visitmt.com | Custom (business.visitmt.com, **data.visitmt.com**) | **Yes, partly public** (C): HTML/XML/JSON endpoints for sites, events, regions, activities, amenities; search; bulk zips by authorization. Listings offered as web feeds to partners, regions and CVBs | **No licence found.** Ask Montana Office of Tourism (Dept of Commerce) before use | Strongest state lead outside the pilot |
| Nebraska | visitnebraska.com | U | Not found | U | |
| New Hampshire | visitnh.gov | U | Not found | U | |
| New Jersey | visitnj.org | U | Not found | U | |
| New York | iloveny.com | **Simpleview** per 2019 client list (U) | Not found | U | data.ny.gov: Tourism Information Centers, State Park attendance, Essex County attractions (stale, 2019) (C) |
| North Carolina | visitnc.com (partners.visitnc.com) | U | **No** (C: partner database, listings approved by Visit NC) | Partner-only | |
| North Dakota | ndtourism.com | U | Not found | U | |
| Ohio | ohio.org (TourismOhio) | U | Not found. Listing and events database audited twice a year (C) | Partner-only | |
| Oklahoma | travelok.com | U | Not found | U | |
| **Oregon** | traveloregon.com | **OTIS** (custom, with Locl, ORLA, Oregon Wine Board) | **Yes, on request** (C): OTIS API docs and logins from OTIS@traveloregon.com; WordPress import plugin | Aimed at DMOs and partners; terms come with access | Shared listings database across Oregon sites |
| Pennsylvania | visitpa.com | U | Not found | U | |
| Rhode Island | visitrhodeisland.com | **Simpleview** per 2019 client list (U) | Not found | U | |
| South Carolina | discoversouthcarolina.com | U | Not found | U | |
| South Dakota | travelsouthdakota.com | U | Not found | U | |
| Tennessee | tnvacation.com | U | **No** (C: Zartico research dashboard for partners, not a feed) | Partner-only | |
| Vermont | vermontvacation.com | U | Not found | U | |
| **Virginia** | virginia.org (VTC; industry vatc.org) | Custom | **Yes, on request** (C, 2017): free custom daily XML feed of listings (events, lodging, dining, attractions) by locality; "no service or api" | Built for industry partners; terms on request | Ask whether a newer "Data Sharing" offer includes an API |
| Washington | stateofwatourism.com | U | Not found | U | |
| West Virginia | wvtourism.com | U | Not found | U | |
| Wisconsin | travelwisconsin.com (industry.travelwisconsin.com) | **Simpleview** (C: assets, database manual) | Embeddable **events widget** (C); no data API | Partner-only listings | |
| Wyoming | travelwyoming.com | U | Not found | U | |

## National and aggregator sources

| Source | What | Terms | Status |
|---|---|---|---|
| NPS API (developer.nps.gov) | Parks, places, things to do, events, campgrounds, alerts, multimedia | Free key (api.data.gov), 1,000 req/hr. NPS material public domain, credit requested. Third-party-credited media is not public domain | Confirmed from NPS guide and disclaimer pages |
| RIDB / Recreation.gov | Federal recreation areas, facilities, campsites, tours, permits | Free key. data.gov lists Creative Commons Attribution | Licence confirmed from catalog only; official ToS not read |
| Tourism Exchange USA (Brand USA) | B2B bookable products with live rates | Distributor access via account manager; no public docs | Unconfirmed details |
| Brand USA / US Travel Association | No public listings or data API found | — | Not found |
| OpenTripMap | POIs from OSM, Wikidata, Wikipedia (+ Russian ministry data) | ODbL (attribution, share-alike) | Confirmed licence statement |
| Wikidata | Entities with coordinates, heritage IDs | CC0 | Well known; not re-checked |
| OpenStreetMap | Everything | ODbL; keep in its own layer (decision 0065) | Confirmed (OSMF FAQ) |
| Simpleview Listings API | Per-DMO listings API | Credentials only through the client DMO | Docs exist (Lake Charles PDF); access confirmed as client-arranged |

## Sources

- https://visitarizona.com/arizona-business-listings-home
- https://extranet.visitarizona.com/support
- https://tourism.az.gov/wp-content/uploads/2024/09/How-to-Work-with-AOT_2024.pdf
- https://www.visitarizona.com/arizona-business-listings/?page=2
- https://tourism.az.gov/fulfillment-2
- https://idss.com
- https://www.simpleviewinc.com/company/contact-us/request-api-documentation/
- https://assets.simpleviewinc.com/simpleview/image/upload/v1/clients/lakecharles/Listings_API_Documentation_2__f77b869c-0b8f-42c3-bbe5-59d42d5ef5b0.pdf
- https://www.wispolitics.com/2019/travel-wisconsin-announces-new-agencies-of-record/?pdf=72860
- https://data.landbase.com/technology/simpleview/
- https://www.newmexico.org/industry/work-together/partners-portal/
- https://res-3.cloudinary.com/simpleview/image/upload/v1/clients/newmexico/step_by_step_instructions_for_creating_a_listing_ec3eb2cd-84f4-485b-801c-df4d59aef0bf.pdf
- https://travel.utah.gov/partner-directory/
- https://www.visitutah.com/utah-media/press-releases/uot-partners-with-tourism-exchange-usa
- https://www.visitutah.com/contact
- https://gis.utah.gov/data/recreation/
- https://gis.utah.gov/products/sgid/recreation/trails-pathways
- https://www.colorado.com/partner-registration
- https://oedit.colorado.gov/cto-partner-resource-guide
- https://res.cloudinary.com/miles-extranet-dev/image/upload/v1633701208/Colorado/Colorado_CTO_Business_Rules-2020-01-14.pdf
- https://meetingsmags.com/colorado/colorado-tourism-office-partners-with-tourism-exchange-usa/
- https://cpw.state.co.us/maps-and-gis
- https://cpw.state.co.us/news/05192023/cotrex-app-verified-source-trail-info-colorado-public-lands
- https://travelnevada.biz/list-your-business-partner-portal/
- https://travelnevada.biz/wp-content/uploads/TNT-2026-Kickoff.pdf
- https://www.appnovation.com/news/appnovation-launches-new-website-for-a-leading-tourism-board
- https://www.codeandtheory.com/work/visit-california
- https://resources.mindtrip.ai/business/partner-stories/visit-california-drives-record-engagement-with-ai-powered-trip-planning
- https://parks.ca.gov/?page_id=29682
- https://catalog.data.gov/dataset/park-boundaries
- https://texashighways.com/events/submit-event/event-submission-guidelines
- https://www.txdot.gov/business/partnerships/travel-industry-partners/marketing-partnerships/events-calendar-submissions.html
- https://tpwd.texas.gov/gis/
- https://services1.arcgis.com/1mtXwieMId59thmg/ArcGIS/rest/services/Texas_State_Parks_Boundaries/FeatureServer
- https://gstore.unm.edu/apps/rgis/datasets/ee7fb504-5276-4faf-979a-0aa008e9cbf8/metadata/FGDC-STD-001-1998.html
- https://atlas.co/data-portals/az-geo-data-hub/
- https://services1.arcgis.com/UpxtrwRYNaXVpkGe/ArcGIS/rest/services/AZSPTrails/FeatureServer
- https://catalog.data.gov/dataset/blm-az-recreation-sites-polygon-fe19d
- https://data.visitmt.com
- https://www.VisitMT.com/about-us/business-listings
- https://partners.visitmt.com/Partner_Tool_Instructions_Desktop.pdf
- https://industry.traveloregon.com/marketing/list-your-business/oregon-tourism-information-system
- https://industry.traveloregon.com/?p=36127
- https://www.vatc.org/wp-content/uploads/2017/05/Datafeed1Sheet.pdf
- https://vatc.org/marketing/digital-marketing/webmarketing/
- https://travelwisconsin.com/events/widgetcreate
- https://industry.travelwisconsin.com/travel-promotion/event-business-listings
- https://www.senecaregionalchamber.com/blog/2019/07/25/ohioorg-listing-and-events-database
- https://www.exploreminnesota.com/industry/opportunities/business-event-listings
- https://partners.visitnc.com/visitnc-com-database
- https://industry.tnvacation.com/node/7426
- https://www.visitflorida.org/resources/research/partner-tools/
- https://www.casestudies.com/company/simpleview/case-study/kansas-tourism-customer-case-study
- https://catalog.data.gov/dataset/tourism-information-centers
- https://catalog.data.gov/dataset/county-welcome-and-visitor-centers
- https://catalog.data.gov/dataset/essex-county-tourist-attractions
- https://www.thebrandusa.com/tourism-exchange-usa
- https://support.ventrata.com/en/article/tourism-exchange-usa-reseller-connection-112uaeu/
- https://www.nps.gov/subjects/developer/guides.htm
- https://www.nps.gov/aboutus/disclaimer.htm
- https://www.nps.gov/articles/000/copyright-restrictions.htm
- https://catalog.data.gov/dataset/recreation-information-database-ridb-58364
- https://dev.opentripmap.com
- https://osmfoundation.org/wiki/Licence_and_Legal_FAQ
