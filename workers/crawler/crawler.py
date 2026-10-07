#!/usr/bin/env python3
"""
Traversence guide-crawl worker (decisions/0044) — runs off Bluehost, on Railway, on a schedule.

Each run is one small, budgeted increment:
  1. Ask the site for the next few jobs (GET /api/crawl/jobs.php). A job is one coverage gap, e.g.
     "Gallup x Health care", with the cluster's towns, ZIPs and ZIP centre points.
  2. Ask OpenStreetMap (Overpass API) for that kind of place around the cluster: one query per job.
  3. For each place with a website, fetch its homepage once (robots.txt respected) and count it as a
     second, independent source only if the page names the place.
  4. Send the findings back (POST /api/crawl/results.php). The site stages them and auto-imports those
     that pass every ADR 0044 guardrail; the rest wait in Listing Intake.
Before the jobs, a "second look" (GET/POST /api/crawl/verify.php) takes a few listings waiting for review
that have only one source and looks each up by name, address and city:
  - NPI Registry (federal list of health-care providers; free, no key). A match on name AND street
    address or phone counts as a trusted registry.
  - With TAVILY_API_KEY (or BRAVE_API_KEY): a web search for the place and, if that found no chamber
    listing, one aimed at chamber of commerce listings. Skipped when the NPI Registry already confirmed it. A
    result counts only if it names the place AND shows its street address or phone. Each is labelled:
    chamber of commerce, well-known directory (BBB, Yelp, Yellow Pages, Healthgrades...), government,
    the place's own website, or another website. Each website counts once.
The site decides what the references are worth and re-runs the guardrails.
Search that learns (decisions/0061): GET /api/crawl/learn.php runs the site's learning pass when due and hands
out a few Tier 2 web searches for situation gaps open data couldn't fill; pages on .gov, .edu, state and local .us
sites or sources we read go back with POST /api/crawl/learn.php. Situation gaps open data covers arrive as ordinary
jobs ("sit:..."), bringing their own OpenStreetMap filters and Wikidata classes (Tier 1).
The run stops at whichever budget comes first: JOBS_PER_RUN jobs, MAX_FETCHES website fetches, or
TIME_BUDGET seconds. Unfinished jobs simply go back to the queue when their lease expires.

Settings (environment variables):
  TRAVERSENCE_URL      e.g. https://traversence.com                        (required)
  CRAWLER_API_TOKEN    same value as CRAWLER_API_TOKEN in the site's .env    (required)
  JOBS_PER_RUN         default 5 (the site hands out at most 10)
  MAX_FETCHES          website fetches per run, default 60
  TIME_BUDGET          seconds per run, default 240
  OVERPASS_URL         optional first Overpass server; the public server and two mirrors follow
  VERIFY_PER_RUN       listings given a second look per run, default 10 (0 turns it off)
  VERIFY_TIME          seconds of each run for the second look, default 100
  TAVILY_API_KEY       Tavily search key (free plan: 1,000 searches a month); without a search key the
                       second look uses the NPI Registry only
  BRAVE_API_KEY        Brave Search API key, used instead when there is no Tavily key (paid)
  MAX_SEARCHES         web searches per run, default 10
  LEARN_PER_RUN        Tier 2 web searches for situation gaps per run, default 3 (0 turns it off)
  LEARN_TIME           seconds of each run for them, default 45
  SEARCH_MONTHLY       the search plan's monthly allowance (default 1000), reported to the site for the admin dashboard
  NPI_BULK             "off" stops the monthly NPI Registry load (default on). When the site says a load is due
                       (every 30 days), that run downloads CMS's full NPI file (about 1 GB), keeps health-care
                       organizations in the site's states, sends them in batches, and skips the rest of the run.
  NPI_TIME             seconds allowed for that load, default 1500
  IRS_BULK             "off" stops the monthly IRS exempt-organization load (default on): one small file per state
                       (eo_az.csv, eo_nm.csv), used by the site only to confirm listings
  RIDB_API_KEY         Recreation.gov RIDB key. With it, once a month the worker loads public campgrounds,
                       recreation areas, trailheads and visitor centers in the site's states for place pages
  LANDMARKS            "off" stops the monthly natural landmarks load (default on): USGS Geographic Names for the site's
                       states, tribal nations' boundaries (Census), Wikidata, Wikipedia summaries and Commons photos
Test hooks (not for production): OVERPASS_FIXTURE=file.json, WEB_FIXTURE=file.json ({url: html}),
NPI_FIXTURE=file.json (an NPI API reply), NPI_FILE_FIXTURE=file.zip (a small NPI file), IRS_FIXTURE_DIR=dir (eo_az.csv…), RIDB_FIXTURE=file.json ({"facilities|AZ|0": reply}), SEARCH_FIXTURE=file.json ({query: {"results": [...]}}),
GNIS_FIXTURE_DIR=dir (DomesticNames_AZ_Text.zip…), AIANNH_FIXTURE=file.zip, WIKI_FIXTURE=file.json ({sparql: [bindings], images: {file: {url, page, artist, license}}, extracts: {title: text}}).
Standard library only.
"""
import csv
import html
import io
import json
import math
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
import zipfile

SITE = os.environ.get("TRAVERSENCE_URL", "").rstrip("/")
TOKEN = os.environ.get("CRAWLER_API_TOKEN", "")
JOBS_PER_RUN = int(os.environ.get("JOBS_PER_RUN", "5"))
MAX_FETCHES = int(os.environ.get("MAX_FETCHES", "60"))
TIME_BUDGET = int(os.environ.get("TIME_BUDGET", "240"))
# Public Overpass servers, tried in order: the main one sometimes refuses (406/429/504), so fall back to mirrors.
OVERPASS_URLS = [u for u in [os.environ.get("OVERPASS_URL"),
                             "https://overpass-api.de/api/interpreter",
                             "https://overpass.private.coffee/api/interpreter",
                             "https://overpass.kumi.systems/api/interpreter"] if u]
VERIFY_PER_RUN = int(os.environ.get("VERIFY_PER_RUN", "10"))
VERIFY_TIME = int(os.environ.get("VERIFY_TIME", "100"))
IDENTITY_PER_RUN = int(os.environ.get("IDENTITY_PER_RUN", "5"))   # listings looked up per run for a missing phone or website (decisions/0062); 0 turns it off
IDENTITY_TIME = int(os.environ.get("IDENTITY_TIME", "60"))
TAVILY_API_KEY = os.environ.get("TAVILY_API_KEY", "")
BRAVE_API_KEY = os.environ.get("BRAVE_API_KEY", "")
SEARCH_ON = bool(TAVILY_API_KEY or BRAVE_API_KEY or os.environ.get("SEARCH_FIXTURE"))
MAX_SEARCHES = int(os.environ.get("MAX_SEARCHES", "10"))
SEARCH_MONTHLY = int(os.environ.get("SEARCH_MONTHLY", "1000"))  # the search plan's monthly allowance, shown on the admin dashboard
LEARN_PER_RUN = int(os.environ.get("LEARN_PER_RUN", "3"))         # Tier 2 web searches for situation gaps per run (decisions/0061); 0 turns it off
LEARN_TIME = int(os.environ.get("LEARN_TIME", "45"))
GUIDES_PER_RUN = int(os.environ.get("GUIDES_PER_RUN", "2"))      # how-to guides looked for per run (decisions/0063 §8); 0 turns it off
GUIDES_TIME = int(os.environ.get("GUIDES_TIME", "45"))
TARGETS_PER_RUN = int(os.environ.get("TARGETS_PER_RUN", "2"))    # search targets (a place and a kind of business, decisions/0066) per run; 0 turns it off
TARGETS_TIME = int(os.environ.get("TARGETS_TIME", "90"))
GEOCODE_PER_RUN = int(os.environ.get("GEOCODE_PER_RUN", "2"))    # batches of up to 500 street addresses placed on the map per run (the site asks the Census); 0 turns it off
GEOCODE_TIME = int(os.environ.get("GEOCODE_TIME", "420"))
WIKIDATA_SPARQL = "https://query.wikidata.org/sparql"
SOURCE_TIME = int(os.environ.get("SOURCE_TIME", "60"))            # seconds per run for reading one of our sources
SOURCE_PAGES = int(os.environ.get("SOURCE_PAGES", "25"))          # pages read per source
REFRESH_PER_RUN = int(os.environ.get("REFRESH_PER_RUN", "3"))     # live listings refreshed from their own website per run
REFRESH_TIME = int(os.environ.get("REFRESH_TIME", "40"))
NPI_BULK = os.environ.get("NPI_BULK", "on").lower() != "off"      # monthly NPI Registry file (decisions/0048)
NPI_TIME = int(os.environ.get("NPI_TIME", "1500"))                # seconds allowed for one monthly load
NPI_FILES_PAGE = "https://download.cms.gov/nppes/NPI_Files.html"
IRS_BULK = os.environ.get("IRS_BULK", "on").lower() != "off"      # monthly IRS exempt-organization list (decisions/0048)
IRS_FILE = "https://www.irs.gov/pub/irs-soi/eo_{state}.csv"
RIDB_API_KEY = os.environ.get("RIDB_API_KEY", "")                 # Recreation.gov RIDB key (decisions/0048)
RIDB_API = "https://ridb.recreation.gov/api/v1"
NPI_API = "https://npiregistry.cms.hhs.gov/api/"
UA = "TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources)"
ZIP_RADIUS_M = 19000          # ~12 miles around each ZIP centre of the cluster
MAX_PER_JOB = 25
FETCH_DELAY = 1.5             # seconds between website fetches

# What to look for in OpenStreetMap for each Support & wellness group (api/lib/SupportGroups.php).
GROUP_QUERIES = {
    "Health care": ['nwr["amenity"~"^(hospital|clinic|doctors|pharmacy)$"]', 'nwr["healthcare"~"^(hospital|clinic|centre|pharmacy)$"]'],
    "Mental health & recovery": ['nwr["healthcare"~"^(psychotherapist|counselling|rehabilitation)$"]',
                                 'nwr["social_facility:for"~"mental_health|drug_addicted"]'],
    "Food & basic needs": ['nwr["amenity"="food_bank"]', 'nwr["social_facility"~"^(food_bank|soup_kitchen)$"]'],
    "Family & youth": ['nwr["amenity"~"^(childcare|kindergarten)$"]', 'nwr["social_facility:for"~"child|juvenile|family"]'],
    "Seniors & veterans": ['nwr["social_facility:for"~"senior"]', 'nwr["club"="veterans"]'],
    "Community & wellness": ['nwr["amenity"~"^(community_centre|library)$"]'],
}
# Plain words for each OSM kind, so the site's category matcher can place the listing.
KIND_WORDS = {
    "hospital": "Hospital", "clinic": "Clinic", "centre": "Medical center", "doctors": "Medical clinic",
    "pharmacy": "Pharmacy", "psychotherapist": "Mental health counselor", "counselling": "Counseling",
    "rehabilitation": "Substance abuse treatment", "food_bank": "Food bank", "soup_kitchen": "Soup kitchen, food bank",
    "childcare": "Child care", "kindergarten": "Preschool, child care", "community_centre": "Community center",
    "library": "Library", "veterans": "Veterans organization",
}

# Well-known directories: a listing there is labelled "directory". NPI copy sites are left out because
# they repeat the NPI Registry rather than confirm it independently.
DIRECTORY_HOSTS = {
    "bbb.org", "yelp.com", "yellowpages.com", "superpages.com", "mapquest.com", "manta.com", "healthgrades.com",
    "zocdoc.com", "vitals.com", "webmd.com", "findhelp.org", "tripadvisor.com", "foursquare.com", "nextdoor.com",
    "hotfrog.com", "chamberofcommerce.com", "yellowbook.com", "cylex.us.com", "brownbook.net", "local.com",
    "caring.com", "psychologytoday.com", "careacross.com", "sharecare.com", "usnews.com", "medicare.gov",
    "findatreatment.gov", "hrsa.gov", "211.org", "unitedway.org", "feedingamerica.org", "foodpantries.org",
}
SKIP_HOSTS = {"openstreetmap.org", "npino.com", "npiprofile.com", "npidb.org", "hipaaspace.com", "opennpi.com",
              "npi.report", "npiregistry.cms.hhs.gov", "google.com", "bing.com", "duckduckgo.com", "search.brave.com"}

started = time.monotonic()
searches = 0
last_search = 0.0
fetches = 0
last_fetch = 0.0
robots_cache = {}


def log(*a):
    print(time.strftime("%H:%M:%S"), *a, flush=True)


def out_of_time():
    return time.monotonic() - started > TIME_BUDGET


def site_call(path, payload=None, timeout=60):
    """GET or POST JSON to the Traversence site with the worker token."""
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(SITE + path, data=data, method="POST" if data else "GET", headers={
        "X-Crawler-Token": TOKEN, "Authorization": "Bearer " + TOKEN,
        "Content-Type": "application/json", "User-Agent": UA, "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        # Say which call failed and what the site said (Bluehost's firewall answers 406 with an HTML page).
        raise RuntimeError(f"site {path} said {e.code}: {e.read()[:200]!r}") from None


def overpass(job):
    if os.environ.get("OVERPASS_FIXTURE"):
        with open(os.environ["OVERPASS_FIXTURE"]) as f:
            return json.load(f).get("elements", [])
    pts = [p for p in job["place"].get("zip_points", []) if p.get("lat") is not None] or \
          [{"lat": job["place"]["center"]["lat"], "lon": job["place"]["center"]["lon"]}]
    parts = []
    # a situation gap's job brings its own filters (decisions/0061); the Support & wellness groups use ours
    for q in job["looking_for"].get("osm") or GROUP_QUERIES.get(job["looking_for"]["group"], []):
        for p in pts:
            parts.append(f'{q}["name"](around:{ZIP_RADIUS_M},{p["lat"]},{p["lon"]});')
    query = "[out:json][timeout:60];(" + "".join(parts) + ");out center tags 80;"
    body = urllib.parse.urlencode({"data": query}).encode()
    tried = []
    for url in OVERPASS_URLS:
        req = urllib.request.Request(url, data=body, headers={
            "User-Agent": UA, "Accept": "*/*",   # a strict "application/json" can earn a 406 Not Acceptable
            "Content-Type": "application/x-www-form-urlencoded"})
        try:
            with urllib.request.urlopen(req, timeout=90) as r:
                return json.loads(r.read().decode("utf-8")).get("elements", [])
        except urllib.error.HTTPError as e:
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e.code} {e.read()[:120]!r}")
        except (urllib.error.URLError, TimeoutError, ValueError) as e:
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e!r}"[:160])
    raise RuntimeError("OpenStreetMap lookup failed: " + " | ".join(tried))


def wikidata(job):
    """Tier 1 open data (decisions/0061): Wikidata items of the job's classes (a hospital, a post office...) within
    40 km of the area, as OpenStreetMap-like elements so candidate() reads them the same way."""
    classes = job["looking_for"].get("wikidata") or []
    if not classes:
        return []
    if os.environ.get("WIKIDATA_FIXTURE"):
        with open(os.environ["WIKIDATA_FIXTURE"]) as f:
            rows = json.load(f).get("results", {}).get("bindings", [])
    else:
        c = job["place"]["center"]
        if c.get("lat") is None:
            return []
        values = " ".join("wd:" + q for q in classes if re.fullmatch(r"Q\d+", q))
        query = f"""SELECT ?item ?itemLabel ?coord ?website ?phone WHERE {{
  VALUES ?cls {{ {values} }}
  SERVICE wikibase:around {{ ?item wdt:P625 ?coord . bd:serviceParam wikibase:center "Point({c['lon']} {c['lat']})"^^geo:wktLiteral .
                             bd:serviceParam wikibase:radius "40" . }}
  ?item wdt:P31/wdt:P279* ?cls .
  OPTIONAL {{ ?item wdt:P856 ?website . }}
  OPTIONAL {{ ?item wdt:P1329 ?phone . }}
  SERVICE wikibase:label {{ bd:serviceParam wikibase:language "en" . }}
}} LIMIT 60"""
        req = urllib.request.Request(WIKIDATA_SPARQL + "?" + urllib.parse.urlencode({"query": query, "format": "json"}),
                                     headers={"User-Agent": UA, "Accept": "application/sparql-results+json"})
        try:
            with urllib.request.urlopen(req, timeout=60) as r:
                rows = json.loads(r.read().decode("utf-8")).get("results", {}).get("bindings", [])
        except Exception as e:                     # Wikidata busy: OpenStreetMap's answer still counts
            log(f"  wikidata: {e!r}"[:200])
            return []
    out, seen = [], set()
    for b in rows:
        item = b.get("item", {}).get("value", "")
        name = b.get("itemLabel", {}).get("value", "")
        m = re.match(r"Point\(([-\d.]+) ([-\d.]+)\)", b.get("coord", {}).get("value", ""))
        if not m or not name or re.fullmatch(r"Q\d+", name) or item in seen:
            continue                                # no English name: skip
        seen.add(item)
        qid = item.rsplit("/", 1)[-1]
        out.append({"type": "wikidata", "id": qid, "lat": float(m.group(2)), "lon": float(m.group(1)),
                    "url": "https://www.wikidata.org/wiki/" + qid,
                    "tags": {"name": name, "website": b.get("website", {}).get("value", ""), "phone": b.get("phone", {}).get("value", "")}})
    return out


def miles(a, b, c, d):
    r = math.radians
    x = math.sin(r(c - a) / 2) ** 2 + math.cos(r(a)) * math.cos(r(c)) * math.sin(r(d - b) / 2) ** 2
    return 2 * 3959 * math.asin(min(1, math.sqrt(x)))


def allowed(url):
    host = urllib.parse.urlsplit(url)
    base = f"{host.scheme}://{host.netloc}"
    if base not in robots_cache:
        rp = urllib.robotparser.RobotFileParser(base + "/robots.txt")
        try:
            rp.read()
        except Exception:
            rp = None                      # no robots.txt reachable: treat as allowed
        robots_cache[base] = rp
    rp = robots_cache[base]
    return rp is None or rp.can_fetch(UA, url)


def fetch_page(url):
    """One polite GET of a homepage: robots.txt, a delay, a size and time cap. None on any failure."""
    global fetches, last_fetch
    if os.environ.get("WEB_FIXTURE"):
        with open(os.environ["WEB_FIXTURE"]) as f:
            return json.load(f).get(url)
    if fetches >= MAX_FETCHES or out_of_time() or not allowed(url):
        return None
    wait = FETCH_DELAY - (time.monotonic() - last_fetch)
    if wait > 0:
        time.sleep(wait)
    fetches += 1
    last_fetch = time.monotonic()
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "text/html"})
        with urllib.request.urlopen(req, timeout=12) as r:
            if "html" not in (r.headers.get("Content-Type") or ""):
                return None
            return r.read(400_000).decode("utf-8", "replace")
    except Exception:
        return None


def words(s):
    """Meaningful words, singular ("Walgreens" and "WALGREEN CO" share "walgreen")."""
    return {w[:-1] if len(w) > 4 and w.endswith("s") and not w.endswith("ss") else w
            for w in re.findall(r"[a-z0-9]+", (s or "").lower())
            if len(w) >= 3 and w not in {"the", "and", "inc", "llc", "center", "centre"}}


def names_place(page, name):
    """The page mentions the place: most of the name's meaningful words appear in its text."""
    text = html.unescape(re.sub(r"<[^>]+>", " ", re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", page))).lower()
    need = words(name)
    return bool(need) and sum(1 for w in need if w in text) / len(need) >= 0.75


def meta_description(page):
    m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']{20,400})', page, re.I) or \
        re.search(r'<meta[^>]+content=["\']([^"\']{20,400})["\'][^>]+name=["\']description["\']', page, re.I)
    return html.unescape(m.group(1)).strip() if m else ""


def phones_on(page):
    """The 10-digit phone numbers a page shows: its tel: links first, else numbers in its text."""
    tel = {digits(m) for m in re.findall(r'href=["\']tel:([^"\']+)', page, re.I)}
    tel = {d for d in tel if len(d) == 10}
    if tel:
        return tel
    text = re.sub(r"<[^>]+>", " ", re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", page))
    return {a + b + c for a, b, c in re.findall(r"\(?\b(\d{3})\)?[\s.-]{0,2}(\d{3})[\s.-](\d{4})\b", text)}


def fmt_phone(d):
    return f"({d[:3]}) {d[3:6]}-{d[6:]}" if len(d) == 10 else d


def address_on(page, street):
    """The page shows this street address: its house number and the street's distinctive words."""
    num, parts = street_parts(street)
    if not num:
        return False
    text = html.unescape(re.sub(r"<[^>]+>", " ", page)).lower()
    return re.search(r"\b" + re.escape(num) + r"\b", text) is not None and all(p in text for p in parts)


def candidate(el, job):
    t = el.get("tags", {})
    name = (t.get("name") or "").strip()
    lat = el.get("lat") or (el.get("center") or {}).get("lat")
    lon = el.get("lon") or (el.get("center") or {}).get("lon")
    if not name or lat is None:
        return None
    zp = (t.get("addr:postcode") or "")[:5]
    if not re.fullmatch(r"\d{5}", zp) or zp not in job["place"]["zips"]:
        near = [(miles(lat, lon, p["lat"], p["lon"]), p["zip"]) for p in job["place"].get("zip_points", [])]
        near = [n for n in near if n[0] <= 15]
        if not near:
            return None                     # not inside this cluster
        zp = min(near)[1]
    towns = job["place"]["towns"]
    osm_url = el.get("url") or f"https://www.openstreetmap.org/{el['type']}/{el['id']}"   # Wikidata items bring their own page
    kind = next((t.get(k) for k in ("amenity", "healthcare", "social_facility", "club") if t.get(k) in KIND_WORDS), None)
    street = " ".join(x for x in (t.get("addr:housenumber"), t.get("addr:street")) if x)
    website = t.get("website") or t.get("contact:website") or t.get("url") or ""
    phone = t.get("phone") or t.get("contact:phone") or ""
    c = {
        "name": name,
        "source": {"start_url": osm_url, "site": host_of(osm_url) or "openstreetmap.org", "crawled_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())},
        "where": {"address": street, "city": t.get("addr:city") or (towns[0]["town"] if towns else ""),
                  "state": t.get("addr:state") or (towns[0]["state"] if towns else ""), "zip": zp},
        "what": {"offerings": [KIND_WORDS[kind]] if kind else list(job["looking_for"].get("offerings") or [])[:3], "description": ""},
        "when": {"hours": [t["opening_hours"]] if t.get("opening_hours") else []},
        "actions": {"phone": phone, "website": website},
        "sources": {"name": [osm_url], "address": [osm_url]},
        "corroboration": [],
    }
    score = 15 + (25 if street else 0) + 5 + (15 if phone else 0) + (10 if c["when"]["hours"] else 0)
    if website and website.startswith("http"):
        page = fetch_page(website)
        if page and names_place(page, name):
            c["corroboration"] = [website]
            c["what"]["description"] = meta_description(page)
            score += 20 + (10 if c["what"]["description"] else 0)
            # facts from the business's own website; OpenStreetMap was the lead (decisions/0066). A value the site shows
            # is sourced to the site; one it doesn't stays the lead's, for a person to check.
            site_phones = phones_on(page)
            if phone and digits(phone) in site_phones:
                c["sources"]["phone"] = [website]
            elif len(site_phones) == 1:
                c["actions"]["phone"] = fmt_phone(next(iter(site_phones)))
                c["sources"]["phone"] = [website]
            if street and address_on(page, street):
                c["sources"]["address"] = [website]
            c["sources"]["name"] = [website]
            hrs = hours_from(page)
            if hrs:
                c["when"]["hours"] = hrs
                c["sources"]["hours"] = [website]
            c["lead"] = osm_url
    c["confidence"] = min(100, score)
    return c


# ---------------------------------------------------------------------------------------------------
# Second look: more references for listings waiting for review
# ---------------------------------------------------------------------------------------------------

def digits(s):
    return re.sub(r"\D", "", s or "")[-10:]


STREET_WORDS = {"n": "north", "s": "south", "e": "east", "w": "west", "st": "street", "ave": "avenue", "rd": "road",
                "dr": "drive", "blvd": "boulevard", "hwy": "highway", "ln": "lane", "ct": "court", "pkwy": "parkway"}


def street_parts(address):
    """House number and the street's distinctive words, e.g. '1662 South 2nd Street' -> ('1662', {'2nd'})."""
    m = re.match(r"\s*(\d+[a-z]?)\s+(.*)", (address or "").lower())
    if not m:
        return None, set()
    ws = {STREET_WORDS.get(w, w) for w in re.findall(r"[a-z0-9]+", m.group(2))}
    return m.group(1), {w for w in ws if w not in set(STREET_WORDS.values()) and w not in {"historic", "old", "us", "route"}} or ws


def shows_address(text, address):
    num, street = street_parts(address)
    if not num:
        return False
    t = " ".join(STREET_WORDS.get(w, w) for w in re.findall(r"[a-z0-9]+", text.lower()))
    for m in re.finditer(r"\b" + re.escape(num) + r"\b", t):
        near = set(t[m.end():m.end() + 60].split())
        if street and street & near:
            return True
    return False


def shows_phone(text, phone):
    want = digits(phone)
    if len(want) != 10:
        return False
    return any(digits(m) == want for m in re.findall(r"\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}", text))


def evidence(text, lst):
    a = shows_address(text, lst["address"])
    p = shows_phone(text, lst["phone"])
    return "both" if a and p else "address" if a else "phone" if p else None


def page_text(page):
    return html.unescape(re.sub(r"<[^>]+>", " ", re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", page)))


npi_note = [""]      # what the last NPI lookup found, for the log


def npi_lookup(lst):
    """Organisations in the NPI Registry matching the listing's name and its street address or phone."""
    if os.environ.get("NPI_FIXTURE"):
        with open(os.environ["NPI_FIXTURE"]) as f:
            replies = [json.load(f)]
    else:
        first = next((w for w in re.findall(r"[A-Za-z0-9]+", lst["name"]) if len(w) >= 2 and w.lower() not in {"the"}), "")
        if not first:
            return []
        replies = []
        for where in ({"postal_code": lst["zip"]}, {"city": lst["city"], "state": lst["state"]}):
            if not all(where.values()):
                continue
            q = urllib.parse.urlencode({"version": "2.1", "enumeration_type": "NPI-2", "organization_name": first + "*",
                                        "limit": 200, **where})
            try:
                req = urllib.request.Request(NPI_API + "?" + q, headers={"User-Agent": UA, "Accept": "application/json"})
                with urllib.request.urlopen(req, timeout=30) as r:
                    reply = json.loads(r.read().decode("utf-8"))
            except Exception as e:
                npi_note[0] = f"NPI failed: {e!r}"[:120]
                return []
            replies.append(reply)
            if reply.get("result_count"):
                break
    want = words(lst["name"])
    found = []
    total = sum(len(r.get("results", [])) for r in replies)
    named = 0
    errors = [str(e.get("description", e))[:80] for r in replies for e in (r.get("Errors") or [])]
    npi_note[0] = f"NPI error: {errors[0]}" if errors else f"NPI {total} result(s), none matching name" if total else "NPI no results"
    for reply in replies:
        for rec in reply.get("results", []):
            names = [(rec.get("basic") or {}).get("organization_name", "")] + [o.get("organization_name", "") for o in rec.get("other_names", [])]
            if not want or not any(len(want & words(n)) / len(want) >= 0.6 for n in names if n):
                continue
            named += 1
            npi_note[0] = f"NPI {named} name match(es), address/phone differ"
            # The main practice address, then any other practice locations the provider registered.
            for a in rec.get("addresses", []) + rec.get("practiceLocations", []):
                if a.get("address_purpose", "LOCATION") != "LOCATION":
                    continue
                text = f"{a.get('address_1', '')} {a.get('address_2', '')} {a.get('telephone_number', '')}"
                ev = evidence(text, lst)
                if ev:
                    tax = next((t.get("desc") for t in rec.get("taxonomies", []) if t.get("primary")), "")
                    found.append({"url": f"https://npiregistry.cms.hhs.gov/provider-view/{rec.get('number')}",
                                  "kind": "registry", "registry": "npi", "evidence": ev,
                                  "label": f"NPI {rec.get('number')}: {names[0]}"[:120],
                                  "phone": a.get("telephone_number", ""), "taxonomy": tax or ""})
                    return found[:1]
    return found


def search(query):
    """One web search (Tavily, else Brave); a list of {url, title, snippet}."""
    global searches, last_search
    if os.environ.get("SEARCH_FIXTURE"):
        with open(os.environ["SEARCH_FIXTURE"]) as f:
            data = json.load(f)
        items = (data.get(query) or data.get("*", {})).get("results", [])
        searches += 1                                 # counted the same as a real search
    else:
        if not (TAVILY_API_KEY or BRAVE_API_KEY) or searches >= MAX_SEARCHES:
            return []
        wait = 1.1 - (time.monotonic() - last_search)        # free plans allow about one query a second
        if wait > 0:
            time.sleep(wait)
        searches += 1
        last_search = time.monotonic()
        try:
            if TAVILY_API_KEY:
                req = urllib.request.Request("https://api.tavily.com/search", method="POST",
                                             data=json.dumps({"query": query, "max_results": 10, "search_depth": "basic"}).encode(),
                                             headers={"Authorization": "Bearer " + TAVILY_API_KEY, "Content-Type": "application/json",
                                                      "Accept": "application/json", "User-Agent": UA})
                with urllib.request.urlopen(req, timeout=30) as r:
                    items = [{"url": x.get("url", ""), "title": x.get("title", ""), "description": x.get("content", "")}
                             for x in json.loads(r.read().decode("utf-8")).get("results", [])]
            else:
                q = urllib.parse.urlencode({"q": query, "count": 20, "country": "us"})
                req = urllib.request.Request("https://api.search.brave.com/res/v1/web/search?" + q, headers={
                    "Accept": "application/json", "X-Subscription-Token": BRAVE_API_KEY, "User-Agent": UA})
                with urllib.request.urlopen(req, timeout=20) as r:
                    items = (json.loads(r.read().decode("utf-8")).get("web") or {}).get("results", [])
        except Exception as e:
            log(f"  search failed: {e!r}"[:200])
            return []
    out = []
    for r in items:
        snippet = " ".join([r.get("title", ""), r.get("description", "")] + list(r.get("extra_snippets") or []))
        out.append({"url": r.get("url", ""), "title": r.get("title", ""), "snippet": html.unescape(re.sub(r"<[^>]+>", " ", snippet))})
    return out


def host_of(url):
    return re.sub(r"^www\.", "", urllib.parse.urlsplit(url).netloc.lower())


def base_domain(host):
    parts = host.split(".")
    return ".".join(parts[-3:]) if host.endswith(".us.com") else ".".join(parts[-2:])


def kind_of(url, title, lst):
    host = host_of(url)
    dom = base_domain(host)
    if dom in DIRECTORY_HOSTS or host in DIRECTORY_HOSTS:
        return "directory"
    if "chamber" in host or "chamber of commerce" in (title or "").lower():
        return "chamber"
    if host.endswith(".gov") or host.endswith(".us") or ".gov." in host:
        return "government"
    label = dom.split(".")[0]
    if lst["website"] and host_of(lst["website"]) == host:
        return "own_site"
    name_words = [w for w in re.findall(r"[a-z0-9]+", lst["name"].lower()) if len(w) >= 3]
    initials = "".join(w[0] for w in re.findall(r"[a-z0-9]+", lst["name"].lower()))
    if sum(1 for w in name_words if w in label) >= 2 or (len(initials) >= 3 and label.startswith(initials)):
        return "own_site"
    return "website"


def web_references(lst, have_hosts):
    """Pages that name the place and show its street address or phone: chamber, directories, others."""
    found = []
    city = f"{lst['city']} {lst['state']}".strip()
    queries = [f'"{lst["name"]}" {city}', f'"{lst["name"]}" {city} chamber of commerce']
    looked = 0
    for n, q in enumerate(queries):
        if n == 1 and (len(found) >= 3 or any(f["kind"] == "chamber" for f in found)):
            break                                        # enough already; save the monthly search allowance
        for r in search(q):
            if len(found) >= 4 or out_of_time():
                return found
            url, host = r["url"], host_of(r["url"])
            if not url.startswith("http") or not host:
                continue
            dom = base_domain(host)
            if host in have_hosts or dom in have_hosts or dom in SKIP_HOSTS or host in SKIP_HOSTS:
                continue
            ev = evidence(r["snippet"], lst) if names_place(r["snippet"], lst["name"]) else None
            if not ev and looked < 6:                    # the snippet wasn't enough: read the page itself
                looked += 1
                page = fetch_page(url)
                if page and names_place(page, lst["name"]):
                    ev = evidence(page_text(page), lst)
            if ev:
                have_hosts.add(host)
                have_hosts.add(dom)
                found.append({"url": url, "kind": kind_of(url, r["title"], lst), "evidence": ev,
                              "label": r["title"][:120]})
    return found


# ---------------------------------------------------------------------------------------------------
# Reading our own sources (decisions/0045): one "Read regularly" website per run, facts only
# ---------------------------------------------------------------------------------------------------

PHONE_RE = re.compile(r"\(?\b\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}\b")
ADDR_RE = re.compile(r"^\s*\d{1,6}[a-z]?\s+[A-Za-z0-9 .'#-]{3,60}$", re.I)
CITY_ZIP_RE = re.compile(r"[A-Za-z .'-]+,\s*[A-Z]{2}\s+\d{5}")
LINK_HINTS = ("list", "member", "director", "clinic", "location", "service", "resource", "business", "provider", "partner", "program")


def page_lines(page):
    """The page's text, one block per line (headings, list items, paragraphs, cells)."""
    t = re.sub(r"(?is)<(script|style|noscript)[^>]*>.*?</\1>", " ", page)
    t = re.sub(r"(?i)<br\s*/?>|</(p|div|li|h[1-6]|tr|td|th|address|section|article|dt|dd)>", "\n", t)
    t = html.unescape(re.sub(r"<[^>]+>", " ", t))
    return [re.sub(r"\s+", " ", l).strip() for l in t.split("\n") if l.strip()]


def jsonld_facts(page, url):
    out = []
    for block in re.findall(r'(?is)<script[^>]+application/ld\+json[^>]*>(.*?)</script>', page):
        try:
            data = json.loads(block.strip())
        except Exception:
            continue
        stack = data if isinstance(data, list) else [data]
        while stack:
            d = stack.pop()
            if isinstance(d, list):
                stack.extend(d); continue
            if not isinstance(d, dict):
                continue
            stack.extend(v for v in d.values() if isinstance(v, (dict, list)))
            name, addr, phone = d.get("name"), d.get("address"), d.get("telephone")
            if not isinstance(name, str) or not (addr or phone):
                continue
            street, zp = "", ""
            if isinstance(addr, dict):
                street = " ".join(str(addr.get(k, "")) for k in ("streetAddress", "addressLocality", "addressRegion", "postalCode")).strip()
                zp = str(addr.get("postalCode", ""))[:5]
            elif isinstance(addr, str):
                street = addr
            out.append({"url": url, "name": name[:255], "address": street[:255], "phone": str(phone or ""), "zip": zp})
    return out


def text_facts(page, url):
    """A name line followed closely by a street address and/or phone number."""
    lines, out = page_lines(page), []
    for i, line in enumerate(lines):
        phone = PHONE_RE.search(line)
        is_addr = bool(ADDR_RE.match(line))
        if not phone and not is_addr:
            continue
        name, jname = "", i
        for j in range(i - 1, max(-1, i - 4), -1):              # the nearest short, word-like line above
            cand = lines[j]
            if 3 <= len(cand) <= 80 and re.search(r"[A-Za-z]{3}", cand) and not PHONE_RE.search(cand) and not ADDR_RE.match(cand) \
                    and not CITY_ZIP_RE.search(cand):
                name, jname = cand, j; break
        if not name:
            continue
        near = lines[jname + 1:i + 3]                           # this entry only: from its name down
        addr = next((l for l in near if ADDR_RE.match(l)), "")
        cz = next((CITY_ZIP_RE.search(l).group(0) for l in near if CITY_ZIP_RE.search(l)), "")
        ph = phone.group(0) if phone else next((PHONE_RE.search(l).group(0) for l in near if PHONE_RE.search(l)), "")
        out.append({"url": url, "name": name, "address": (addr + (", " + cz if cz else "")).strip(", "), "phone": ph,
                    "zip": (re.search(r"\d{5}", cz) or [""])[0] if cz else ""})
    return out


def read_source(deadline):
    d = site_call("/api/crawl/sources.php")
    src = d.get("source")
    if not src:
        return
    start, host = src["home_url"], host_of(src["home_url"])
    queue, seen, facts, pages = [start], set(), [], 0
    while queue and pages < SOURCE_PAGES and time.monotonic() < deadline and not out_of_time():
        url = queue.pop(0)
        if url in seen:
            continue
        seen.add(url)
        page = fetch_page(url)
        if not page:
            continue
        pages += 1
        facts += jsonld_facts(page, url) + text_facts(page, url)
        links = []
        for href in re.findall(r'(?i)href=["\']([^"\'#]+)', page):
            full = urllib.parse.urljoin(url, href)
            if host_of(full) == host and full.startswith("http") and not re.search(r"\.(pdf|jpe?g|png|gif|zip|docx?)$", full, re.I):
                links.append(full)
        links.sort(key=lambda u: 0 if any(h in u.lower() for h in LINK_HINTS) else 1)   # directory-like pages first
        queue += [l for l in links if l not in seen][:60]
    uniq = {}
    for f in facts:
        uniq.setdefault((f["name"].lower(), re.sub(r"\D", "", f["phone"])[-10:], f["address"][:20].lower()), f)
    res = site_call("/api/crawl/sources.php", {"host": host, "pages": pages, "facts": list(uniq.values())})
    log(f"read source {host}: {pages} page(s), {len(uniq)} place(s) found, {res.get('kept', 0)} kept")


# ---------------------------------------------------------------------------------------------------
# Search that learns, Tier 2 (decisions/0061): a web search for a situation gap Tier 1 couldn't fill, or for
# outside sources naming what people open after some words. Only .gov, .edu, state and local .us sites, and
# sources a person marked "Read regularly" count; each useful page becomes a source on the site.
# ---------------------------------------------------------------------------------------------------

ALLOWED_RE = re.compile(r"\.(gov|edu)$|\.gov\.|\.[a-z]{2}\.us$")
LIST_PHONE_RE = re.compile(r"\(?\b\d{3}\)?[-. ]\d{3}[-. ]\d{4}\b")   # phone numbers a page lists


def allowed_host(host, read):
    host = host.lower().removeprefix("www.")
    return bool(ALLOWED_RE.search(host)) or any(host == r or host.endswith("." + r) for r in read)


def learn(deadline):
    d = site_call(f"/api/crawl/learn.php?limit={LEARN_PER_RUN}")
    if d.get("paused"):
        return
    if d.get("learned"):
        log(f"learning: {d['learned'].get('scored', 0)} scored, {d['learned'].get('changed', 0)} changed")
    read = d.get("domains") or []
    for t in d.get("tasks", []):
        if time.monotonic() > deadline or out_of_time() or not SEARCH_ON:
            break
        words_needed = [w.lower() for w in t.get("need_words") or []]
        towns = []
        for x in t.get("towns") or []:                # "Saint Johns" is often written "St. Johns", and the other way round
            x = x.lower()
            towns += [x] + ([re.sub(r"^saint ", v, x) for v in ("st. ", "st ")] if x.startswith("saint ") else []) \
                + (["saint " + x[len(m.group(0)):]] if (m := re.match(r"^st\.? ", x)) else [])
        pages = []
        for r in search(t["query"]):
            host = host_of(r["url"])
            if not host or not allowed_host(host, read):
                continue
            text = (r.get("title", "") + " " + r.get("snippet", "")).lower()
            # the page is about this: the need's words, and for a gap one of the area's towns
            if words_needed and not any(w in text for w in words_needed):
                continue
            if towns and not any(tw in text for tw in towns):
                page = fetch_page(r["url"])
                if not page or not any(tw in page_text(page).lower() for tw in towns):
                    continue
            else:
                page = fetch_page(r["url"])
            places = len({re.sub(r"\D", "", p) for p in LIST_PHONE_RE.findall(page_text(page))}) if page else 0
            pages.append({"url": r["url"], "title": r.get("title", "")[:190], "places": places})
        res = site_call("/api/crawl/learn.php", {"task": t["task"], "id": t["id"], "pages": pages[:10]})
        log(f"learning: {t['task']} {t['id']} \"{t['query']}\": {len(pages)} official page(s), {res.get('kept', 0)} kept")


# ---------------------------------------------------------------------------------------------------
# How-to guides (decisions/0063 §8): the steps from official how-to pages only (.gov, .edu, a state's .us), each
# with its source. The site keeps them as a draft; a person puts them in our words and publishes.
# ---------------------------------------------------------------------------------------------------

GUIDE_HOST_RE = re.compile(r"\.(gov|edu|mil)$|\.gov\.[a-z]{2}$|\.[a-z]{2}\.us$")
STEP_HEAD_RE = re.compile(r"(?is)<h[2-5][^>]*>\s*(?:step\s*)?(\d{1,2})[.):\s-]+(.*?)</h[2-5]>")


def official_host(url):
    host = host_of(url)
    return bool(host) and bool(GUIDE_HOST_RE.search(host.lower().removeprefix("www.")))


def clean_text(fragment):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", fragment))).strip()


def steps_from(page):
    """The page's steps: its best ordered list (3 to 20 items that read as sentences), else "Step 1 …" headings."""
    body = re.sub(r"(?is)<(script|style|nav|header|footer|aside)[^>]*>.*?</\1>", " ", page)
    best = []
    for ol in re.findall(r"(?is)<ol[^>]*>(.*?)</ol>", body):
        items = [clean_text(li) for li in re.findall(r"(?is)<li[^>]*>(.*?)</li>", ol)]
        items = [t for t in items if len(t) >= 15]
        if 3 <= len(items) <= 20 and sum(len(t) for t in items) / len(items) >= 25 and len(items) > len(best):
            best = items
    if not best:
        heads = STEP_HEAD_RE.findall(body)
        if len(heads) >= 3:
            best = [clean_text(t) for _, t in heads if clean_text(t)]
    return [t[:300] for t in best[:15]]


def targets(deadline):
    """Search targets (decisions/0066): a place people search and the kind of business they look for there, queued by an
    admin or regional operator. OpenStreetMap finds the businesses (the lead); each is checked on its own website, and
    what's found goes to Listing Intake, held for a person (the place may be outside our areas)."""
    d = site_call(f"/api/crawl/targets.php?limit={TARGETS_PER_RUN}")
    for job in d.get("targets", []):
        if time.monotonic() > deadline or out_of_time():
            log("targets: out of time, the rest go back when their lease ends")
            break
        tid = job["target_id"]
        try:
            elements = overpass(job)
            seen, cands = set(), []
            for el in elements:
                if len(cands) >= MAX_PER_JOB or out_of_time():
                    break
                c = candidate(el, job)
                if c and (c["name"].lower(), c["where"]["zip"]) not in seen:
                    seen.add((c["name"].lower(), c["where"]["zip"]))
                    cands.append(c)
            res = site_call("/api/crawl/targets.php", {"target_id": tid, "candidates": cands, "pages": fetches,
                                                       "log": [f"osm elements: {len(elements)}", f"candidates: {len(cands)}"]})
            log(f"target {tid} {job['looking_for']['group']} in {job['place']['name']}: {len(elements)} open-data, {len(cands)} sent -> {res.get('staged', 0)} to review")
        except Exception as e:
            log(f"target {tid}: {e!r}"[:300])
            try:
                site_call("/api/crawl/targets.php", {"target_id": tid, "candidates": [], "error": repr(e)[:300]})
            except Exception:
                pass


def geocode(deadline):
    """Map points: the site sends a batch of street addresses to the U.S. Census geocoder and places what it finds
    (api/crawl/geocode.php, api/lib/Geocoder.php), so listings don't stack on their ZIP's middle waiting for someone to
    press Find points. Only missing or ZIP-middle points change; confidential listings are never geocoded."""
    for _ in range(GEOCODE_PER_RUN):
        if time.monotonic() > deadline or out_of_time():
            break
        r = site_call("/api/crawl/geocode.php", {}, timeout=250)    # a batch of 500 can take a few minutes at the Census
        if r.get("paused"):
            log("map points: queue paused on the site")
            break
        log(f"map points: sent {r.get('sent', 0)}, placed {r.get('found', 0)}, not found {r.get('not_found', 0)}, "
            f"placed another way {r.get('skipped', 0)}, left {r.get('left', 0)}")
        if not r.get("left") or (r.get("sent", 0) + r.get("skipped", 0) + r.get("private", 0)) == 0:
            break


def guides(deadline):
    d = site_call(f"/api/crawl/guides.php?limit={GUIDES_PER_RUN}")
    for g in d.get("guides", []):
        if time.monotonic() > deadline or out_of_time():
            log("guides: out of time, the rest go back when their lease ends")
            break
        pages, seen, error = [], set(), ""
        if not SEARCH_ON:
            error = "No search key: guides need a web search to find official pages."
        else:
            topic = set(words(g["words"]))
            for query in (g["query"] + " extension", g["query"] + " site:.gov OR site:.edu"):
                for r in search(query):
                    url = r["url"]
                    if url in seen or not url.startswith("http") or not official_host(url):
                        continue                       # official pages only: extension services, agencies
                    seen.add(url)
                    seen_words = words(r["title"] + " " + r["snippet"])
                    if topic and not any(w == x or (len(w) >= 4 and x.startswith(w)) for w in topic for x in seen_words):
                        continue                       # not about this ("compost" also matches "composting")
                    page = fetch_page(url)
                    if not page:
                        continue
                    steps = steps_from(page)
                    pages.append({"url": url, "title": r.get("title", "")[:160], "steps": steps})
                    if len(pages) >= 4 or time.monotonic() > deadline:
                        break
                if len([p for p in pages if len(p["steps"]) >= 3]) >= 2 or len(pages) >= 4:
                    break
        res = site_call("/api/crawl/guides.php", {"id": g["id"], "pages": pages, "error": error})
        log(f"guides: \"{g['title']}\": {len(pages)} official page(s), best {max([len(p['steps']) for p in pages] or [0])} steps -> {res.get('status')}")


# ---------------------------------------------------------------------------------------------------
# Refreshing live listings from their own websites: fill what's empty, find their other locations
# ---------------------------------------------------------------------------------------------------

DAY_RE = re.compile(r"\b(mon|tue|wed|thu|fri|sat|sun)[a-z]*\b.*\d{1,2}(:\d{2})?\s*(am|pm|a\.m\.|p\.m\.)", re.I)
REFRESH_HINTS = ("about", "contact", "hour", "location", "service", "clinic", "visit", "find-us", "offices")


def hours_from(page):
    for block in re.findall(r'(?is)<script[^>]+application/ld\+json[^>]*>(.*?)</script>', page):
        m = re.search(r'"openingHours"\s*:\s*(\[[^\]]*\]|"[^"]*")', block)
        if m:
            try:
                v = json.loads(m.group(1))
                return [x for x in (v if isinstance(v, list) else [v]) if isinstance(x, str)][:7]
            except Exception:
                pass
    lines = [l for l in page_lines(page) if DAY_RE.search(l) and len(l) <= 60]
    return list(dict.fromkeys(lines))[:7]


def split_city(address):
    m = re.search(r"^(.*?),?\s*([A-Za-z .'-]+),\s*([A-Z]{2})\s+(\d{5})", address)
    return (m.group(1).strip(" ,"), m.group(2).strip(), m.group(3), m.group(4)) if m else (address, "", "", "")


def refresh_listings(deadline):
    d = site_call(f"/api/crawl/refresh.php?limit={REFRESH_PER_RUN}")
    for lst in d.get("listings", []):
        if time.monotonic() > deadline or out_of_time():
            break
        site, host = lst["website"], host_of(lst["website"])
        home = fetch_page(site)
        if not home:
            log(f"refresh {lst['name']}: website didn't load")
            continue
        pages = {site: home}
        links = [urllib.parse.urljoin(site, h) for h in re.findall(r'(?i)href=["\']([^"\'#]+)', home)]
        links = [u for u in dict.fromkeys(links) if host_of(u) == host and any(k in u.lower() for k in REFRESH_HINTS)][:4]
        for u in links:
            if time.monotonic() > deadline:
                break
            pg = fetch_page(u)
            if pg:
                pages[u] = pg
        found = {}
        if "description" in lst["missing"]:
            found["description"] = meta_description(home)
        if "hours" in lst["missing"]:
            for pg in pages.values():
                h = hours_from(pg)
                if h:
                    found["hours"] = h; break
        if "phone" in lst["missing"]:
            m = PHONE_RE.search(" ".join(page_lines(home)))
            if m:
                found["phone"] = m.group(0)
        # Other places this organization lists, each with a full address: staged as new finds.
        own_num = (re.match(r"\s*(\d+)", lst.get("address") or "") or [None, None])[1]
        locations, seen = [], set()
        for url, pg in pages.items():
            for f in jsonld_facts(pg, url) + text_facts(pg, url):
                street, city, state, zp = split_city(f["address"])
                num = (re.match(r"\s*(\d+)", street) or [None, None])[1]
                if not zp or not num or num == own_num or (f["name"].lower(), num) in seen:
                    continue
                seen.add((f["name"].lower(), num))
                locations.append({"name": f["name"], "source": {"start_url": site, "site": host},
                                  "where": {"address": street, "city": city, "state": state, "zip": zp, "location_page": url},
                                  "what": {"offerings": [], "description": ""}, "actions": {"phone": f["phone"], "website": site},
                                  "sources": {"name": [url], "address": [url]}, "corroboration": [],
                                  "confidence": 15 + 25 + 5 + (15 if f["phone"] else 0)})
        res = site_call("/api/crawl/refresh.php", {"entity_id": lst["entity_id"], "website": site, "found": found, "locations": locations[:25]})
        log(f"refresh {lst['name']}: filled {', '.join(res.get('filled', [])) or 'nothing new'}; "
            f"{len(locations)} other location(s) -> {res.get('auto_imported', 0)} published, {res.get('staged', 0)} to review")


def second_look(deadline):
    d = site_call(f"/api/crawl/verify.php?limit={VERIFY_PER_RUN}")
    if d.get("paused"):
        return
    listings = d.get("listings", [])
    if listings:
        log(f"second look: {len(listings)} listing(s)" + ("" if SEARCH_ON else " (NPI Registry only; no search key)"))
    for lst in listings:
        if time.monotonic() > deadline or out_of_time():
            log("second look: out of time, the rest go back when their lease ends")
            break
        have = set(lst.get("known_hosts") or [])
        from_npi = bool(lst.get("from_npi"))           # came from the NPI file: it needs a different reference
        found, checked = [], [] if from_npi else ["npi"]
        try:
            npi_note[0] = ""
            if not from_npi:
                found += npi_lookup(lst)
            if SEARCH_ON and not found:                  # a registry match is enough on its own
                checked.append("search")
                found += web_references(lst, have | {h for f in found for h in [host_of(f["url"])]})
            res = site_call("/api/crawl/verify.php", {"row_id": lst["row_id"], "found": found, "checked": checked})
            kinds = ", ".join(f"{f['kind']} {host_of(f['url'])}" for f in found) or "nothing more"
            if not any(f["kind"] == "registry" for f in found) and npi_note[0]:
                kinds += f" [{npi_note[0]}]"
            log(f"second look {lst['name']} ({lst['city']}): {kinds} -> score {res.get('score')}"
                + (", published" if res.get("auto_imported") else ""))
        except Exception as e:
            log(f"second look {lst.get('name')}: {e!r}"[:300])


# ---------------------------------------------------------------------------------------------------
# Building a listing's identity (decisions/0062, step 4): its missing phone, website and email
# ---------------------------------------------------------------------------------------------------

# Google, Yelp and Facebook: their terms don't allow copying, so their pages only confirm a value another source gave,
# and are kept as links. They're never fetched; the search snippet is all that's read.
def evidence_only(host):
    return bool(re.search(r"(^|\.)(yelp\.com|facebook\.com|instagram\.com|g\.page)$|(^|\.)google\.[a-z.]+$", host))


SOCIAL_RE = re.compile(r"""href=["'](https?://(?:www\.|m\.)?(?:facebook\.com|instagram\.com|yelp\.com|x\.com|twitter\.com|linkedin\.com|youtube\.com|tiktok\.com)/[^"'\s?#]+)""", re.I)
TEL_RE = re.compile(r"""href=["']tel:([^"']+)""", re.I)
MAILTO_RE = re.compile(r"""href=["']mailto:([^"'?]+)""", re.I)
CONTACT_HINTS = ("contact", "about", "location", "hours", "visit", "find-us", "directions")


def good_phone(p, rejected):
    d = digits(p)
    return d if len(d) == 10 and d[0] >= "2" and f"phone:{d}" not in rejected else ""


def phone_near(text, name, rejected):
    """The phone printed closest after the place's name: a page that lists many places shows each one's phone by it."""
    low = text.lower()
    keys = [name.lower(), " ".join([w for w in re.findall(r"[a-z0-9]+", name.lower()) if len(w) >= 3][:2])]
    for key in keys:
        if not key:
            continue
        for m in re.finditer(re.escape(key), low):
            for pm in PHONE_RE.finditer(text[m.end():m.end() + 400]):
                d = good_phone(pm.group(0), rejected)
                if d:
                    return d
    return ""


def own_site_facts(site, lst, rejected, deadline):
    """Its own website: the phone (a tel: link, else the number printed most), email, social pages and hours."""
    home = fetch_page(site)
    if not home:
        return []
    host = host_of(site)
    pages = {site: home}
    links = [urllib.parse.urljoin(site, h) for h in re.findall(r"(?i)href=[\"']([^\"'#]+)", home)]
    for u in [u for u in dict.fromkeys(links) if host_of(u) == host and any(k in u.lower() for k in CONTACT_HINTS)][:3]:
        if time.monotonic() > deadline:
            break
        pg = fetch_page(u)
        if pg:
            pages[u] = pg
    found = []
    need = set(lst["need"])
    if "phone" in need:
        counts = {}
        for url, pg in pages.items():
            for p in TEL_RE.findall(pg):
                d = good_phone(p, rejected)
                if d:
                    counts[(d, url)] = counts.get((d, url), 0) + 5        # a tel: link is the site saying "call us here"
            for p in PHONE_RE.findall(" ".join(page_lines(pg))):
                d = good_phone(p, rejected)
                if d:
                    counts[(d, url)] = counts.get((d, url), 0) + 1
        if counts:
            (d, url), _ = max(counts.items(), key=lambda kv: kv[1])
            found.append({"fact": "phone", "value": d, "url": url, "kind": "own_site", "evidence": "own_site", "label": "Its own website"})
    if "email" in need:
        for url, pg in pages.items():
            mails = [m.strip() for m in MAILTO_RE.findall(pg) if "@" in m]
            mails.sort(key=lambda m: 0 if host.split(".")[-2] in m.lower() else 1)       # its own domain first
            if mails:
                found.append({"fact": "email", "value": mails[0], "url": url, "kind": "own_site", "evidence": "own_site", "label": "Its own website"})
                break
    socials = []
    for pg in pages.values():
        socials += [u for u in SOCIAL_RE.findall(pg) if not re.search(r"/(sharer|share|intent|plugins|dialog)\b", u)]
    for u in list(dict.fromkeys(socials))[:4]:
        found.append({"fact": "social", "value": u, "url": site, "kind": "own_site", "evidence": "own_site", "label": "Linked from its own website"})
    for url, pg in pages.items():
        h = hours_from(pg)
        if h:
            found.append({"fact": "hours", "value": "; ".join(h), "url": url, "kind": "own_site", "evidence": "own_site", "label": "Its own website"})
            break
    return found


def identity_jobs(deadline):
    d = site_call(f"/api/crawl/identity.php?limit={IDENTITY_PER_RUN}")
    listings = d.get("listings", [])
    if listings:
        log(f"identities: {len(listings)} listing(s) to fill in" + ("" if SEARCH_ON else " (own websites and the NPI Registry only; no search key)"))
    for lst in listings:
        if time.monotonic() > deadline or out_of_time():
            log("identities: out of time, the rest go back when their lease ends")
            break
        try:
            rejected = set(lst.get("rejected") or [])
            need = set(lst.get("need") or [])
            found, checked = [], []
            have = lambda fact: any(f["fact"] == fact for f in found)
            # 1. its own website
            if lst.get("website"):
                checked.append("own_site")
                found += own_site_facts(lst["website"], lst, rejected, deadline)
            # 2. the NPI Registry (health care): it gives the phone at the address we hold
            if "phone" in need and not have("phone") and lst.get("address"):
                checked.append("npi")
                for f in npi_lookup(lst):
                    p = good_phone(f.get("phone", ""), rejected)
                    if p:
                        found.append({"fact": "phone", "value": p, "url": f["url"], "kind": "registry", "evidence": "registry", "label": f["label"]})
            # 3. web search, for what's still missing
            if SEARCH_ON and (("phone" in need and not have("phone")) or ("website" in need and not lst.get("website") and not have("website"))):
                checked.append("search")
                city = f"{lst['city']} {lst['state']}".strip() or lst["zip"]
                results = search(f'"{lst["name"]}" {city}')
                looked = 0
                later = []                               # Google, Yelp, Facebook: read after the others, to confirm
                for r in results:
                    url, host = r["url"], host_of(r["url"])
                    if not url.startswith("http") or not host or base_domain(host) in SKIP_HOSTS or host in SKIP_HOSTS:
                        continue
                    if evidence_only(host):
                        later.append(r)
                        continue
                    kind = kind_of(url, r["title"], lst)
                    text = r["snippet"]
                    if not names_place(text, lst["name"]) and looked < 4 and time.monotonic() < deadline:
                        looked += 1
                        page = fetch_page(url)
                        text = page_text(page) if page and names_place(page, lst["name"]) else ""
                    if not text:
                        continue
                    shows_addr = bool(lst.get("address")) and shows_address(text, lst["address"])
                    town = bool(lst.get("city")) and lst["city"].lower() in text.lower()
                    if kind == "own_site" and "website" in need and not lst.get("website") and not have("website") and (shows_addr or town):
                        root = f"{urllib.parse.urlsplit(url).scheme}://{urllib.parse.urlsplit(url).netloc}/"
                        if f"website:{host}" not in rejected:
                            found.append({"fact": "website", "value": root, "url": url, "kind": "own_site", "evidence": "address" if shows_addr else "own_site",
                                          "label": r["title"][:120] or host})
                            if time.monotonic() < deadline:
                                more = own_site_facts(root, {**lst, "need": [n for n in need if not have(n)]}, rejected, deadline)
                                found += [f for f in more if not have(f["fact"]) or f["fact"] == "social"]
                    elif "phone" in need and not have("phone") and shows_addr:
                        p = phone_near(text, lst["name"], rejected)
                        if p:
                            found.append({"fact": "phone", "value": p, "url": url, "kind": kind, "evidence": "address", "label": r["title"][:120] or host})
                # their pages confirm a phone found above (or our address), and are kept as links
                phones = {f["value"] for f in found if f["fact"] == "phone"} | ({digits(lst["phone"])} if lst.get("phone") else set())
                for r in later[:3]:
                    if not names_place(r["snippet"], lst["name"]):
                        continue
                    shown = next((p for p in phones if p and shows_phone(r["snippet"], p)), "")
                    addr = bool(lst.get("address")) and shows_address(r["snippet"], lst["address"])
                    if shown or addr:
                        ev = "both" if shown and addr else ("phone" if shown else "address")
                        found.append({"fact": "social", "value": r["url"], "url": r["url"], "kind": "evidence", "evidence": ev, "label": r["title"][:120]})
                        if shown:
                            found.append({"fact": "phone", "value": shown, "url": r["url"], "kind": "evidence", "evidence": ev, "label": r["title"][:120]})
            res = site_call("/api/crawl/identity.php", {"job_id": lst["job_id"], "found": found, "checked": checked})
            log(f"identity {lst['name']} ({lst['city']}): " + (", ".join(f"{f['fact']} from {host_of(f['url'])}" for f in found) or "nothing found")
                + (f" -> on the listing: {', '.join(res.get('applied', []))}" if res.get("applied") else "")
                + (f"; {res.get('waiting')} for a person" if res.get("waiting") else ""))
        except Exception as e:
            log(f"identity {lst.get('name')}: {e!r}"[:300])
            try:
                site_call("/api/crawl/identity.php", {"job_id": lst["job_id"], "error": repr(e)[:240]})
            except Exception:
                pass


# ---------------------------------------------------------------------------------------------------
# Monthly NPI Registry file (decisions/0048)
# ---------------------------------------------------------------------------------------------------

def npi_file_url():
    """The newest full monthly file on CMS's download page (not the weekly or deactivation files)."""
    req = urllib.request.Request(NPI_FILES_PAGE, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=60) as r:
        page = r.read().decode("utf-8", "replace")
    names = re.findall(r'href=["\']?([^"\'\s>]*NPPES_Data_Dissemination_[A-Za-z]+_\d{4}(?:_V\.?2)?\.zip)', page, re.I)
    names = [n for n in names if "weekly" not in n.lower() and "deactiv" not in n.lower()]
    if not names:
        raise RuntimeError("no monthly file found on the NPI download page")
    names.sort(key=lambda n: bool(re.search(r"_V\.?2", n, re.I)), reverse=True)     # the current format first, when both are offered
    return urllib.parse.urljoin(NPI_FILES_PAGE, names[0])


def npi_bulk():
    """Loads the month's NPI organizations when the site says it's due. Returns True if this run did a load."""
    st = site_call("/api/crawl/npi.php")
    if not st.get("due"):
        return False
    deadline = time.monotonic() + NPI_TIME
    if os.environ.get("NPI_FILE_FIXTURE"):
        path, file = os.environ["NPI_FILE_FIXTURE"], os.path.basename(os.environ["NPI_FILE_FIXTURE"])
    else:
        url = npi_file_url()
        file = os.path.basename(urllib.parse.urlparse(url).path)
        if file == st.get("last_file"):          # CMS hasn't published a newer month yet: check again in 30 days
            lid = site_call("/api/crawl/npi.php", {"start": file})["load_id"]
            site_call("/api/crawl/npi.php", {"load_id": lid, "file": file, "done": True, "seen": st.get("records", 0), "staged": 0})
            log(f"NPI: {file} is still the newest file")
            return False
        path = "/tmp/npi.zip"
        log(f"NPI: downloading {file}")
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=120) as r, open(path, "wb") as f:
            while True:
                chunk = r.read(1 << 20)
                if not chunk:
                    break
                f.write(chunk)
                if time.monotonic() > deadline:
                    raise RuntimeError("NPI download ran out of time")
    lid = site_call("/api/crawl/npi.php", {"start": file})["load_id"]
    states = [s.upper() for s in st.get("states", ["AZ", "NM"])]
    tokens = [f'"{s}"' for s in states]
    seen = staged = lines = 0
    batch = []

    def send():
        nonlocal staged, batch
        if batch:
            res = site_call("/api/crawl/npi.php", {"load_id": lid, "file": file, "rows": batch})
            staged += int(res.get("staged", 0))
            batch = []

    with zipfile.ZipFile(path) as zf:
        member = next(n for n in zf.namelist() if re.match(r"npidata_pfile_.*\.csv$", os.path.basename(n)) and "fileheader" not in n.lower())
        with zf.open(member) as raw:
            text = io.TextIOWrapper(raw, encoding="utf-8", errors="replace", newline="")
            head = next(csv.reader([text.readline()]))
            col = {h.strip(): i for i, h in enumerate(head)}

            def c(name):
                return col[name]
            i_type, i_npi = c("Entity Type Code"), c("NPI")
            i_name, i_other = c("Provider Organization Name (Legal Business Name)"), c("Provider Other Organization Name")
            i_other_type = col.get("Provider Other Organization Name Type Code")
            i_a1 = c("Provider First Line Business Practice Location Address")
            i_city, i_state = c("Provider Business Practice Location Address City Name"), c("Provider Business Practice Location Address State Name")
            i_zip, i_phone = c("Provider Business Practice Location Address Postal Code"), c("Provider Business Practice Location Address Telephone Number")
            i_deact, i_react = col.get("NPI Deactivation Date"), col.get("NPI Reactivation Date")
            tax = [(col.get(f"Healthcare Provider Taxonomy Code_{n}"), col.get(f"Healthcare Provider Primary Taxonomy Switch_{n}")) for n in range(1, 16)]
            tax = [t for t in tax if t[0] is not None]
            for line in text:
                lines += 1
                if lines % 1000000 == 0:
                    log(f"NPI: {lines:,} lines read, {seen:,} kept")
                    if time.monotonic() > deadline:
                        raise RuntimeError("NPI load ran out of time")
                if not any(t in line for t in tokens):
                    continue
                try:
                    row = next(csv.reader([line]))
                except Exception:
                    continue
                if len(row) != len(head) or row[i_type] != "2" or row[i_state].strip().upper() not in states:
                    continue
                if i_deact is not None and row[i_deact].strip() and not (i_react is not None and row[i_react].strip()):
                    continue                       # deactivated
                code = next((row[a] for a, b in tax if b is not None and row[b] == "Y"), "") or (row[tax[0][0]] if tax else "")
                other = row[i_other] if (i_other_type is None or row[i_other_type].strip() in ("", "3")) else ""
                batch.append({"npi": row[i_npi], "name": row[i_name], "other_name": other, "address": row[i_a1],
                              "city": row[i_city], "state": row[i_state], "zip": row[i_zip][:5], "phone": row[i_phone], "taxonomy": code})
                seen += 1
                if len(batch) >= 500:
                    send()
            send()
    res = site_call("/api/crawl/npi.php", {"load_id": lid, "file": file, "done": True, "seen": seen, "staged": staged})
    log(f"NPI: {file} done: {seen:,} organizations in {', '.join(states)}, {staged} new places staged, {res.get('removed', 0)} old records removed")
    if not os.environ.get("NPI_FILE_FIXTURE"):
        try:
            os.remove(path)
        except OSError:
            pass
    return True


def irs_bulk():
    """Loads the IRS exempt-organization list for the site's states when it's due. Returns True if this run did a load."""
    st = site_call("/api/crawl/irs.php")
    if not st.get("due"):
        return False
    file = "eo-" + time.strftime("%Y-%m")
    lid = site_call("/api/crawl/irs.php", {"start": file})["load_id"]
    seen = 0
    for state in [s.lower() for s in st.get("states", ["AZ", "NM"])]:
        if os.environ.get("IRS_FIXTURE_DIR"):
            stream = open(os.path.join(os.environ["IRS_FIXTURE_DIR"], f"eo_{state}.csv"), "rb")
        else:
            stream = urllib.request.urlopen(urllib.request.Request(IRS_FILE.format(state=state), headers={"User-Agent": UA}), timeout=120)
        batch = []
        with stream:
            for r in csv.DictReader(io.TextIOWrapper(stream, encoding="utf-8", errors="replace", newline="")):
                batch.append({"ein": r.get("EIN", ""), "name": r.get("NAME", ""), "street": r.get("STREET", ""), "city": r.get("CITY", ""),
                              "state": r.get("STATE", ""), "zip": (r.get("ZIP") or "")[:5], "ntee": r.get("NTEE_CD", "")})
                if len(batch) >= 1000:
                    seen += site_call("/api/crawl/irs.php", {"load_id": lid, "file": file, "rows": batch}).get("saved", 0)
                    batch = []
        if batch:
            seen += site_call("/api/crawl/irs.php", {"load_id": lid, "file": file, "rows": batch}).get("saved", 0)
    if seen == 0:
        raise RuntimeError("the IRS files had no rows the site accepted")   # never finish (and remove the old copy) on an empty load
    res = site_call("/api/crawl/irs.php", {"load_id": lid, "file": file, "done": True, "seen": seen})
    log(f"IRS: {file} done: {seen:,} exempt organizations, {res.get('removed', 0)} old records removed")
    return True


def ridb_get(kind, state, offset):
    if os.environ.get("RIDB_FIXTURE"):
        with open(os.environ["RIDB_FIXTURE"]) as f:
            return json.load(f).get(f"{kind}|{state}|{offset}", {"RECDATA": [], "METADATA": {"RESULTS": {"TOTAL_COUNT": 0}}})
    q = urllib.parse.urlencode({"state": state, "limit": 50, "offset": offset, "full": "true"})
    req = urllib.request.Request(f"{RIDB_API}/{kind}?{q}", headers={"apikey": RIDB_API_KEY, "Accept": "application/json", "User-Agent": UA})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode("utf-8"))


def ridb_row(kind, rec):
    """One RIDB facility or recreation area in the shape the site takes."""
    if kind == "facilities":
        if rec.get("Enabled") is False:
            return None
        addr = next(iter(rec.get("FACILITYADDRESS") or []), {})
        fid = str(rec.get("FacilityID", ""))
        ftype = rec.get("FacilityTypeDescription", "") or ""
        url = rec.get("FacilityReservationURL") or ""
        if not url and rec.get("Reservable") and "campground" in ftype.lower():
            url = f"https://www.recreation.gov/camping/campgrounds/{fid}"
        return {"kind": "facility", "id": fid, "name": rec.get("FacilityName", ""), "type": ftype,
                "description": rec.get("FacilityDescription", ""), "phone": rec.get("FacilityPhone", ""),
                "lat": rec.get("FacilityLatitude"), "lon": rec.get("FacilityLongitude"), "url": url,
                "city": addr.get("City", ""), "state": addr.get("AddressStateCode", ""), "zip": addr.get("PostalCode", ""),
                "org": next((o.get("OrgName", "") for o in rec.get("ORGANIZATION") or []), ""),
                "activities": [a.get("ActivityName", "") for a in rec.get("ACTIVITY") or [] if a.get("ActivityName")]}
    if rec.get("Enabled") is False:
        return None
    addr = next(iter(rec.get("RECAREAADDRESS") or []), {})
    return {"kind": "recarea", "id": str(rec.get("RecAreaID", "")), "name": rec.get("RecAreaName", ""), "type": "Recreation area",
            "description": rec.get("RecAreaDescription", ""), "phone": rec.get("RecAreaPhone", ""),
            "lat": rec.get("RecAreaLatitude"), "lon": rec.get("RecAreaLongitude"), "url": rec.get("RecAreaReservationURL") or "",
            "city": addr.get("City", ""), "state": addr.get("AddressStateCode", ""), "zip": addr.get("PostalCode", ""),
            "org": next((o.get("OrgName", "") for o in rec.get("ORGANIZATION") or []), ""),
            "activities": [a.get("ActivityName", "") for a in rec.get("ACTIVITY") or [] if a.get("ActivityName")]}


def rec_bulk():
    """Loads Recreation.gov places for the site's states when it's due. Returns True if this run did a load."""
    if not RIDB_API_KEY and not os.environ.get("RIDB_FIXTURE"):
        return False
    st = site_call("/api/crawl/rec.php")
    if not st.get("due"):
        return False
    file = "ridb-" + time.strftime("%Y-%m")
    lid = site_call("/api/crawl/rec.php", {"start": file})["load_id"]
    seen = calls = 0
    deadline = time.monotonic() + NPI_TIME
    for state in [s.upper() for s in st.get("states", ["AZ", "NM"])]:
        for kind in ("facilities", "recareas"):
            offset = 0
            while True:
                if time.monotonic() > deadline:
                    raise RuntimeError("Recreation.gov load ran out of time")
                reply = ridb_get(kind, state, offset)
                calls += 1
                recs = reply.get("RECDATA") or []
                rows = [r for r in (ridb_row(kind, x) for x in recs) if r]
                for r in rows:
                    r["state"] = (r.get("state") or state)[:2].upper()   # the address can be missing; the query was by state
                if rows:
                    seen += site_call("/api/crawl/rec.php", {"load_id": lid, "file": file, "rows": rows}).get("saved", 0)
                total = int(((reply.get("METADATA") or {}).get("RESULTS") or {}).get("TOTAL_COUNT") or 0)
                offset += 50
                if not recs or offset >= total:
                    break
                if not os.environ.get("RIDB_FIXTURE"):
                    time.sleep(1.3)                # Recreation.gov allows about 50 requests a minute
    if seen == 0:
        raise RuntimeError("Recreation.gov returned no places the site accepted")
    res = site_call("/api/crawl/rec.php", {"load_id": lid, "file": file, "done": True, "seen": seen})
    log(f"Recreation.gov: {file} done: {seen:,} places from {calls} requests, {res.get('removed', 0)} old records removed")
    return True


# ---- natural landmarks (decisions/0058 §24) --------------------------------------------------------------------
# USGS Geographic Names (GNIS) gives every named natural feature with its point. Most are minor (a draw, a tank), so the
# worker keeps the notable ones: a Wikipedia article or a freely licensed photo, or a landscape word in the
# name. The Census reservation boundaries (AIANNH) mark which are on a sovereign nation's land; the site never lists
# those without a person. Photos must be freely licensed and are credited.

STATE_NAMES = {"AL": "Alabama", "AK": "Alaska", "AZ": "Arizona", "AR": "Arkansas", "CA": "California", "CO": "Colorado",
               "CT": "Connecticut", "DE": "Delaware", "FL": "Florida", "GA": "Georgia", "HI": "Hawaii", "ID": "Idaho",
               "IL": "Illinois", "IN": "Indiana", "IA": "Iowa", "KS": "Kansas", "KY": "Kentucky", "LA": "Louisiana",
               "ME": "Maine", "MD": "Maryland", "MA": "Massachusetts", "MI": "Michigan", "MN": "Minnesota",
               "MS": "Mississippi", "MO": "Missouri", "MT": "Montana", "NE": "Nebraska", "NV": "Nevada",
               "NH": "New Hampshire", "NJ": "New Jersey", "NM": "New Mexico", "NY": "New York", "NC": "North Carolina",
               "ND": "North Dakota", "OH": "Ohio", "OK": "Oklahoma", "OR": "Oregon", "PA": "Pennsylvania",
               "RI": "Rhode Island", "SC": "South Carolina", "SD": "South Dakota", "TN": "Tennessee", "TX": "Texas",
               "UT": "Utah", "VT": "Vermont", "VA": "Virginia", "WA": "Washington", "WV": "West Virginia",
               "WI": "Wisconsin", "WY": "Wyoming"}
GNIS_FILE = "https://prd-tnm.s3.amazonaws.com/StagedProducts/GeographicNames/DomesticNames/DomesticNames_{state}_Text.zip"
AIANNH_FILE = "https://www2.census.gov/geo/tiger/TIGER2024/AIANNH/tl_2024_us_aiannh.zip"
WIKIDATA_SPARQL = "https://query.wikidata.org/sparql"
COMMONS_API = "https://commons.wikimedia.org/w/api.php"
WIKIPEDIA_API = "https://en.wikipedia.org/w/api.php"
LANDMARKS = os.environ.get("LANDMARKS", "on").lower() != "off"
# GNIS classes that can be a natural landmark; the rest (streams, reservoirs, canals, populated places …) are left out
LANDMARK_CLASSES = {"Area", "Arch", "Basin", "Bench", "Cliff", "Crater", "Falls", "Flat", "Gap", "Island", "Lake",
                    "Pillar", "Plain", "Range", "Rapids", "Ridge", "Spring", "Summit", "Valley"}
# the same landscape words the site uses (api/lib/Landmarks.php NOTABLE, NOTABLE_SHAPE)
NOTABLE_RE = re.compile(r"\b(badlands?|natural bridge|falls|waterfall|hot springs?|sand dunes|dunes|craters?|volcano|hoodoos?|"
                        r"gorge|box canyon|slot canyon|lava|malpais|caldera|petrified|painted desert|caves?|caverns?|"
                        r"sinkhole|blue hole|gardens? of the gods|valley of fires)\b", re.I)
SHAPE_RE = re.compile(r"\b(arch|arches|rocks?|spires?|pinnacles?|towers?|needles?|monument|chimney|castle|cathedral|wilderness)\b", re.I)


def notable(r):
    return (r["class"] in ("Arch", "Falls", "Crater", "Pillar") or bool(NOTABLE_RE.search(r["name"]))
            or (r["class"] in ("Area", "Cliff", "Summit") and bool(SHAPE_RE.search(r["name"]))))


OK_LICENSE = re.compile(r"^(cc0|cc[ -]by(-sa)?([ -][0-9.]+)?|public domain|pd\b|pd-)", re.I)


def fetch_bytes(url, timeout=180):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()


def read_dbf(data):
    """The records of a dBase file, as dicts of strings."""
    n = int.from_bytes(data[4:8], "little")
    hlen = int.from_bytes(data[8:10], "little")
    rlen = int.from_bytes(data[10:12], "little")
    fields, pos = [], 32
    while data[pos] != 0x0D:
        name = data[pos:pos + 11].split(b"\0")[0].decode("ascii")
        fields.append((name, data[pos + 16]))
        pos += 32
    out = []
    for i in range(n):
        rec, off = data[hlen + i * rlen: hlen + (i + 1) * rlen], 1
        row = {}
        for name, size in fields:
            row[name] = rec[off:off + size].decode("utf-8", "replace").strip()
            off += size
        out.append(row)
    return out


def read_polygons(data):
    """The polygons of a shapefile: [(bbox, [ring, …]) or None], each ring a list of (lon, lat)."""
    import struct
    out, pos = [], 100
    while pos + 8 <= len(data):
        clen = struct.unpack(">i", data[pos + 4:pos + 8])[0] * 2
        rec = data[pos + 8:pos + 8 + clen]
        pos += 8 + clen
        if len(rec) < 44 or struct.unpack("<i", rec[0:4])[0] not in (5, 15, 25):
            out.append(None)
            continue
        bbox = struct.unpack("<4d", rec[4:36])
        nparts, npts = struct.unpack("<2i", rec[36:44])
        parts = list(struct.unpack(f"<{nparts}i", rec[44:44 + 4 * nparts])) + [npts]
        pts = struct.unpack(f"<{2 * npts}d", rec[44 + 4 * nparts:44 + 4 * nparts + 16 * npts])
        rings = [[(pts[2 * k], pts[2 * k + 1]) for k in range(parts[j], parts[j + 1])] for j in range(nparts)]
        out.append((bbox, rings))
    return out


def nations_map(boxes):
    """Tribal nations' boundaries (Census AIANNH) that touch these [minlon, minlat, maxlon, maxlat] boxes: [(name, bbox, rings)]."""
    if os.environ.get("AIANNH_FIXTURE"):
        with open(os.environ["AIANNH_FIXTURE"], "rb") as f:
            raw = f.read()
    else:
        raw = fetch_bytes(AIANNH_FILE, 300)
    z = zipfile.ZipFile(io.BytesIO(raw))
    shp = next(n for n in z.namelist() if n.endswith(".shp"))
    dbf = next(n for n in z.namelist() if n.endswith(".dbf"))
    recs, polys = read_dbf(z.read(dbf)), read_polygons(z.read(shp))
    out = []
    for rec, poly in zip(recs, polys):
        if not poly:
            continue
        b = poly[0]
        if any(b[0] <= x2 and b[2] >= x1 and b[1] <= y2 and b[3] >= y1 for x1, y1, x2, y2 in boxes):
            name = rec.get("NAMELSAD") or rec.get("NAME") or ""
            # the nation's own name, without the Census's land words ("Navajo Nation Reservation and Off-Reservation Trust Land" -> "Navajo Nation")
            name = re.sub(r"\s+(and\s+)?Off-Reservation Trust Land$", "", name).strip()
            name = re.sub(r"\s+(Indian\s+)?Reservation$", "", name).strip()
            out.append((name, b, poly[1]))
    return out


def inside(lon, lat, rings):
    """Even-odd point in polygon (holes count as outside)."""
    hit = False
    for ring in rings:
        j = len(ring) - 1
        for i in range(len(ring)):
            xi, yi = ring[i]
            xj, yj = ring[j]
            if (yi > lat) != (yj > lat) and lon < (xj - xi) * (lat - yi) / (yj - yi) + xi:
                hit = not hit
            j = i
    return hit


def simplify(ring, tol=0.0005):
    """Douglas-Peucker, iterative: drops points that sit within tol degrees (about 50 m) of the line between their neighbours."""
    if len(ring) < 5:
        return ring
    if ring[0] == ring[-1]:                        # a closed ring: split it at its farthest point, simplify both halves
        x0, y0 = ring[0]
        k = max(range(len(ring)), key=lambda i: (ring[i][0] - x0) ** 2 + (ring[i][1] - y0) ** 2)
        if 0 < k < len(ring) - 1:
            return simplify(ring[:k + 1], tol) + simplify(ring[k:], tol)[1:]
    keep = [False] * len(ring)
    keep[0] = keep[-1] = True
    stack = [(0, len(ring) - 1)]
    while stack:
        a, b = stack.pop()
        (x1, y1), (x2, y2) = ring[a], ring[b]
        dx, dy = x2 - x1, y2 - y1
        norm = math.hypot(dx, dy) or 1e-12
        far, at = 0.0, -1
        for i in range(a + 1, b):
            x, y = ring[i]
            d = abs(dy * x - dx * y + x2 * y1 - y2 * x1) / norm
            if d > far:
                far, at = d, i
        if far > tol and at > 0:
            keep[at] = True
            stack += [(a, at), (at, b)]
    return [p for p, k in zip(ring, keep) if k]


def send_nation_shapes(file, nations):
    """The nations' boundaries, simplified, to the site: it keeps journey and photo pins off a nation's land except at its
    public places (decisions/0058 §26). One area per call."""
    sent = 0
    for name, b, rings in nations:
        rs = [[[round(x, 5), round(y, 5)] for x, y in simplify(r)] for r in rings]
        rs = [r for r in rs if len(r) >= 4]
        if rs:
            sent += site_call("/api/crawl/landmarks.php", {"file": file, "shape": {"name": name, "bbox": list(b), "rings": rs}}).get("saved", 0)
    if sent:
        site_call("/api/crawl/landmarks.php", {"file": file, "shapes_done": True})
    return sent


def nation_at(lon, lat, nations):
    for name, b, rings in nations:
        if b[0] <= lon <= b[2] and b[1] <= lat <= b[3] and inside(lon, lat, rings):
            return name
    return ""


def gnis_rows(state):
    """The state's named features in the landmark classes: [{gnis_id, name, class, county, lat, lon}]."""
    if os.environ.get("GNIS_FIXTURE_DIR"):
        with open(os.path.join(os.environ["GNIS_FIXTURE_DIR"], f"DomesticNames_{state}_Text.zip"), "rb") as f:
            raw = f.read()
    else:
        raw = fetch_bytes(GNIS_FILE.format(state=state), 300)
    z = zipfile.ZipFile(io.BytesIO(raw))
    name = next(n for n in z.namelist() if n.endswith(".txt"))
    out = []
    for r in csv.DictReader(io.TextIOWrapper(z.open(name), encoding="utf-8-sig"), delimiter="|"):
        if r.get("state_name") != STATE_NAMES.get(state) or r.get("feature_class") not in LANDMARK_CLASSES:
            continue
        if "(historical)" in r.get("feature_name", ""):
            continue
        try:
            lat, lon = float(r["prim_lat_dec"]), float(r["prim_long_dec"])
        except (TypeError, ValueError):
            continue
        if lat == 0 or lon == 0:
            continue
        out.append({"gnis_id": int(r["feature_id"]), "name": r["feature_name"], "class": r["feature_class"],
                    "county": r.get("county_name", ""), "lat": lat, "lon": lon, "state": state})
    return out


def wiki_fixture():
    if not os.environ.get("WIKI_FIXTURE"):
        return None
    with open(os.environ["WIKI_FIXTURE"]) as f:
        return json.load(f)


def wikidata_for(box):
    """Wikidata items with a GNIS id inside a box: {gnis_id: {qid, description, image, wiki_title}}."""
    fx = wiki_fixture()
    if fx is not None:
        bindings = fx.get("sparql", [])
    else:
        x1, y1, x2, y2 = box
        q = ("SELECT ?item ?gnis ?desc ?img ?title WHERE { SERVICE wikibase:box { ?item wdt:P625 ?c . "
             f'bd:serviceParam wikibase:cornerSouthWest "Point({x1} {y1})"^^geo:wktLiteral ; '
             f'wikibase:cornerNorthEast "Point({x2} {y2})"^^geo:wktLiteral . }} ?item wdt:P590 ?gnis . '
             'OPTIONAL { ?item schema:description ?desc FILTER(LANG(?desc) = "en") } OPTIONAL { ?item wdt:P18 ?img } '
             'OPTIONAL { ?a schema:about ?item ; schema:isPartOf <https://en.wikipedia.org/> ; schema:name ?title } }')
        req = urllib.request.Request(WIKIDATA_SPARQL, data=urllib.parse.urlencode({"query": q, "format": "json"}).encode(),
                                     headers={"User-Agent": UA, "Accept": "application/sparql-results+json"})
        with urllib.request.urlopen(req, timeout=120) as r:
            bindings = json.loads(r.read().decode("utf-8")).get("results", {}).get("bindings", [])
    out = {}
    for b in bindings:
        try:
            gid = int(b["gnis"]["value"])
        except (KeyError, ValueError):
            continue
        cur = out.setdefault(gid, {"qid": b["item"]["value"].rsplit("/", 1)[-1], "description": "", "image": "", "wiki_title": ""})
        cur["description"] = cur["description"] or b.get("desc", {}).get("value", "")
        cur["image"] = cur["image"] or urllib.parse.unquote(b.get("img", {}).get("value", "").rsplit("/", 1)[-1])
        cur["wiki_title"] = cur["wiki_title"] or b.get("title", {}).get("value", "")
    return out


def commons_info(files):
    """{file name: {url, page, artist, license}} for freely licensed Commons photos (others left out)."""
    fx = wiki_fixture()
    if fx is not None:
        return {k: v for k, v in fx.get("images", {}).items() if k in files and OK_LICENSE.match(v.get("license", ""))}
    out = {}
    files = list(files)
    for i in range(0, len(files), 50):
        q = urllib.parse.urlencode({"action": "query", "format": "json", "prop": "imageinfo", "iiprop": "url|extmetadata",
                                    "iiurlwidth": 1280, "titles": "|".join("File:" + f for f in files[i:i + 50])})
        with urllib.request.urlopen(urllib.request.Request(f"{COMMONS_API}?{q}", headers={"User-Agent": UA}), timeout=60) as r:
            pages = json.loads(r.read().decode("utf-8")).get("query", {}).get("pages", {})
        for p in pages.values():
            ii = (p.get("imageinfo") or [{}])[0]
            meta = ii.get("extmetadata") or {}
            lic = (meta.get("LicenseShortName") or {}).get("value", "")
            if not OK_LICENSE.match(lic) or not ii.get("thumburl"):
                continue
            artist = re.sub(r"<[^>]+>", "", (meta.get("Artist") or {}).get("value", "")).strip()
            out[p.get("title", "")[5:].replace(" ", "_")] = {"url": ii["thumburl"], "page": ii.get("descriptionurl", ""),
                                                              "artist": html.unescape(artist)[:200], "license": lic}
            out[p.get("title", "")[5:]] = out[p.get("title", "")[5:].replace(" ", "_")]
        time.sleep(1)
    return out


def wikipedia_extracts(titles):
    """{title: the article's opening, in plain text (about three sentences)}."""
    fx = wiki_fixture()
    if fx is not None:
        return {k: v for k, v in fx.get("extracts", {}).items() if k in titles}
    out = {}
    titles = list(titles)
    for i in range(0, len(titles), 20):
        q = urllib.parse.urlencode({"action": "query", "format": "json", "prop": "extracts", "exintro": 1, "explaintext": 1,
                                    "exsentences": 3, "exlimit": 20, "redirects": 1, "titles": "|".join(titles[i:i + 20])})
        with urllib.request.urlopen(urllib.request.Request(f"{WIKIPEDIA_API}?{q}", headers={"User-Agent": UA}), timeout=60) as r:
            d = json.loads(r.read().decode("utf-8")).get("query", {})
        back = {x["to"]: x["from"] for x in d.get("redirects", []) + d.get("normalized", [])}
        for p in d.get("pages", {}).values():
            t = p.get("title", "")
            if p.get("extract"):
                out[back.get(t, t)] = p["extract"].strip()[:1200]
        time.sleep(1)
    return out


def landmarks_bulk():
    """Loads the pilot states' natural landmarks when they're due. Returns True if this run did a load."""
    st = site_call("/api/crawl/landmarks.php")
    if not st.get("due"):
        return False
    file = "gnis-" + time.strftime("%Y-%m")
    deadline = time.monotonic() + NPI_TIME
    states = [s.upper() for s in st.get("states", ["AZ", "NM"]) if s.upper() in STATE_NAMES]
    feats = {s: gnis_rows(s) for s in states}
    boxes = {}
    for s, rows in feats.items():
        if rows:
            boxes[s] = [min(r["lon"] for r in rows), min(r["lat"] for r in rows), max(r["lon"] for r in rows), max(r["lat"] for r in rows)]
    nations = nations_map(list(boxes.values()))
    lid = site_call("/api/crawl/landmarks.php", {"start": file})["load_id"]
    seen = 0
    for s in states:
        rows, box = feats[s], boxes.get(s)
        if not box:
            continue
        # Wikidata, in four tiles so no one query is too big
        wd = {}
        mx, my = (box[0] + box[2]) / 2, (box[1] + box[3]) / 2
        for tile in ([box[0], box[1], mx, my], [mx, box[1], box[2], my], [box[0], my, mx, box[3]], [mx, my, box[2], box[3]]):
            if time.monotonic() > deadline:
                raise RuntimeError("the landmarks load ran out of time")
            try:
                wd.update(wikidata_for(tile))
            except Exception as e:                 # a slow tile only loses its descriptions
                log(f"landmarks: Wikidata tile {tile} for {s} skipped: {e!r}"[:300])
            if not os.environ.get("WIKI_FIXTURE"):
                time.sleep(2)
        keep = []
        for r in rows:
            w = wd.get(r["gnis_id"])
            if (w and (w["image"] or w["wiki_title"])) or notable(r):   # Wikidata describes nearly every GNIS feature ("summit in Arizona"): an article or a photo counts
                keep.append((r, w))
        photos = commons_info({w["image"] for _, w in keep if w and w["image"]})
        texts = wikipedia_extracts({w["wiki_title"] for _, w in keep if w and w["wiki_title"]})
        out = []
        for r, w in keep:
            row = dict(r, nation=nation_at(r["lon"], r["lat"], nations))
            if w:
                img = photos.get(w["image"]) or photos.get(w["image"].replace(" ", "_"))
                row.update(qid=w["qid"], description=w["description"], wiki_title=w["wiki_title"],
                           extract=texts.get(w["wiki_title"], ""), image=img or {})
            out.append(row)
        for i in range(0, len(out), 500):
            seen += site_call("/api/crawl/landmarks.php", {"load_id": lid, "file": file, "rows": out[i:i + 500]}).get("saved", 0)
        log(f"landmarks: {s}: {len(rows):,} features, {len(wd):,} on Wikidata, {len(out):,} sent")
    try:
        log(f"landmarks: {send_nation_shapes(file, nations)} nations' boundaries sent")
    except Exception as e:                         # the landmarks still finish; the boundaries come next month
        log(f"landmarks: boundaries not sent: {e!r}"[:300])
    if seen == 0:
        raise RuntimeError("no landmarks the site accepted")   # never finish (and remove the old copy) on an empty load
    res = site_call("/api/crawl/landmarks.php", {"load_id": lid, "file": file, "done": True, "seen": seen})
    log(f"landmarks: {file} done: {seen:,} kept, {res.get('removed', 0)} old records removed")
    return True


def run():
    if not SITE or not TOKEN:
        log("TRAVERSENCE_URL and CRAWLER_API_TOKEN must be set.")
        return 2
    if NPI_BULK:
        try:
            if npi_bulk():                         # a monthly load is a whole run on its own
                return 0
        except Exception as e:                     # the load never stops the rest; the site retries it in 6 hours
            log(f"NPI load stopped: {e!r}"[:300])
    if IRS_BULK:
        try:
            if irs_bulk():
                return 0
        except Exception as e:                     # never stops the rest; the site retries it in 6 hours
            log(f"IRS load stopped: {e!r}"[:300])
    try:
        if rec_bulk():
            return 0
    except Exception as e:                         # never stops the rest; the site retries it in 6 hours
        log(f"Recreation.gov load stopped: {e!r}"[:300])
    if LANDMARKS:
        try:
            if landmarks_bulk():
                return 0
        except Exception as e:                     # never stops the rest; the site retries it in 6 hours
            log(f"landmarks load stopped: {e!r}"[:300])
    if VERIFY_PER_RUN > 0:
        try:
            second_look(started + min(VERIFY_TIME, TIME_BUDGET))
        except Exception as e:                     # the second look never stops the crawl jobs
            log(f"second look stopped: {e!r}"[:300])
    if TARGETS_PER_RUN > 0:
        try:
            targets(time.monotonic() + TARGETS_TIME)
        except Exception as e:                     # search targets never stop the crawl jobs
            log(f"targets stopped: {e!r}"[:300])
    if GEOCODE_PER_RUN > 0:
        try:
            geocode(time.monotonic() + GEOCODE_TIME)
        except Exception as e:                     # placing map points never stops the crawl jobs (first: one batch even when the run is short)
            log(f"map points stopped: {e!r}"[:300])
    if IDENTITY_PER_RUN > 0:
        try:
            identity_jobs(time.monotonic() + IDENTITY_TIME)
        except Exception as e:                     # filling in identities never stops the crawl jobs
            log(f"identities stopped: {e!r}"[:300])
    if REFRESH_PER_RUN > 0:
        try:
            refresh_listings(time.monotonic() + REFRESH_TIME)
        except Exception as e:                     # refreshing never stops the crawl jobs
            log(f"refresh stopped: {e!r}"[:300])
    if LEARN_PER_RUN > 0:
        try:
            learn(time.monotonic() + LEARN_TIME)
        except Exception as e:                     # learning never stops the crawl jobs
            log(f"learning stopped: {e!r}"[:300])
    if GUIDES_PER_RUN > 0:
        try:
            guides(time.monotonic() + GUIDES_TIME)
        except Exception as e:                     # building guides never stops the crawl jobs
            log(f"guides stopped: {e!r}"[:300])
    if SOURCE_TIME > 0:
        try:
            read_source(time.monotonic() + SOURCE_TIME)
        except Exception as e:                     # reading a source never stops the crawl jobs
            log(f"reading a source stopped: {e!r}"[:300])
    d = site_call(f"/api/crawl/jobs.php?limit={JOBS_PER_RUN}")
    if d.get("paused"):
        log("Queue paused on the site (CRAWL_QUEUE=off).")
        return 0
    jobs = d.get("jobs", [])
    log(f"{len(jobs)} job(s)")
    for job in jobs:
        jid = job["job_id"]
        if out_of_time():
            log(f"job {jid}: out of time, left for its lease to return it")
            break
        try:
            elements = overpass(job) + wikidata(job)   # Tier 1: OpenStreetMap, and Wikidata for official places
            seen, cands = set(), []
            for el in elements:
                if len(cands) >= MAX_PER_JOB or out_of_time():
                    break
                c = candidate(el, job)
                if c and (c["name"].lower(), c["where"]["zip"]) not in seen:
                    seen.add((c["name"].lower(), c["where"]["zip"]))
                    cands.append(c)
            res = site_call("/api/crawl/results.php", {"job_id": jid, "candidates": cands, "pages": fetches,
                                                       "log": [f"osm elements: {len(elements)}", f"candidates: {len(cands)}"]})
            log(f"job {jid} {job['looking_for']['group']} in {job['place']['name']}: {len(elements)} open-data, "
                f"{len(cands)} sent -> {res.get('auto_imported', 0)} published, {res.get('staged', 0)} to review")
        except Exception as e:                     # report and let the queue retry it
            log(f"job {jid}: {e!r}")
            try:
                site_call("/api/crawl/results.php", {"job_id": jid, "error": repr(e)[:400]})
            except Exception:
                pass
        time.sleep(3)                              # be gentle with Overpass between jobs
    return 0


def report_usage():
    """Tell the site which web search is on and how many searches this run made, for the admin dashboard's System
    status (it keeps the month's total). Never fails the run."""
    if not SITE or not TOKEN:
        return
    engine = "tavily" if TAVILY_API_KEY else ("brave" if BRAVE_API_KEY else "")
    try:
        site_call("/api/crawl/usage.php", {"engine": engine, "searches": searches, "fetches": fetches, "monthly": SEARCH_MONTHLY})
    except Exception as e:
        log(f"usage report skipped: {e!r}"[:200])


if __name__ == "__main__":
    try:
        code = run()
        report_usage()
        sys.exit(code)
    except urllib.error.HTTPError as e:
        # Exit cleanly: the next scheduled run tries again. A failing exit makes Railway restart the
        # container at once, which would hammer the site every second.
        log(f"site said {e.code}: {e.read()[:300]!r}")
        report_usage()                             # searches already made still count toward the month
        sys.exit(0)
    except Exception as e:                         # site unreachable etc.: same, wait for the next run
        log(f"run stopped: {e!r}")
        report_usage()
        sys.exit(0)
