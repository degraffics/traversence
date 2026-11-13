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
Since contract 2 (decisions/0160, 2026-11-13) the site plans each run: POST /api/crawl/service.php {start} gives the capture
tasks due, each with its share of the run's time, fetches and paid calls (finding businesses first, then whoever waited
longest); the worker works each inside its share, gives tasks that took a full batch a second turn with the time left, and
reports each task (done, idle, not reached, failed, off) with {report}. A run that couldn't work (the site unreachable, no
token, everything starved) exits 1, so Railway shows it failed. The bulk loads run only at night, after the run's work.
Against a site without the service, the fixed order below runs as before.
Our own index (decisions/0045, 0157): each run reads one "Read regularly" source (GET/POST /api/crawl/sources.php),
asking each page whether it changed since the page log last saw it, reading its structured data first, then its text,
and splitting a list page into its places; every page fetched goes back to the page log.
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
  MAX_SEARCHES         web searches per run at most, default 10; the site may allow fewer (the month's pace)
  LEARN_PER_RUN        Tier 2 web searches for situation gaps per run, default 3 (0 turns it off)
  LEARN_TIME           seconds of each run for them, default 45
  GOOGLE_PLACES_KEY    Google Places API (New) key: with it, each run checks a few listings against Google (decisions/0085)
  PLACES_PER_RUN       listings checked against Google per run, default 5 (0 turns it off)
  GOOGLE_PLACES_MONTHLY  checks a month at most, default 3000; the site stops at 90% and paces it over the days
  SEARCH_MONTHLY       the search plan's monthly allowance (default 1000). The site stops web search at 90% of it and paces
                       the rest over the days left (decisions/0084)
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
import gzip
import hashlib
try:
    import pypdf                                   # PDFs' text (decisions/0157 §7); without it PDFs are skipped
except Exception:
    pypdf = None
import html
from html.parser import HTMLParser
import io
import ipaddress
import json
import math
import os
import re
import signal
import socket
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
                             "https://overpass.kumi.systems/api/interpreter",
                             "https://maps.mail.ru/osm/tools/overpass/api/interpreter"] if u]
# overpass.openstreetmap.fr is left out: it answers 403 "only available to white-listed usages" (2026-11-13's runs)


class OutOfTime(Exception):
    """The task's share ran out before an outside service answered: the work goes back untouched, never a failure."""
VERIFY_PER_RUN = int(os.environ.get("VERIFY_PER_RUN", "10"))
VERIFY_TIME = int(os.environ.get("VERIFY_TIME", "100"))
IDENTITY_PER_RUN = int(os.environ.get("IDENTITY_PER_RUN", "5"))   # listings looked up per run for a missing phone or website (decisions/0062); 0 turns it off
IDENTITY_TIME = int(os.environ.get("IDENTITY_TIME", "60"))
TAVILY_API_KEY = os.environ.get("TAVILY_API_KEY", "")
BRAVE_API_KEY = os.environ.get("BRAVE_API_KEY", "")
SEARCH_ON = bool(TAVILY_API_KEY or BRAVE_API_KEY or os.environ.get("SEARCH_FIXTURE"))
MAX_SEARCHES = int(os.environ.get("MAX_SEARCHES", "10"))
SEARCH_MONTHLY = int(os.environ.get("SEARCH_MONTHLY", "1000"))  # the search plan's monthly allowance: the site stops searching at 90% of it
GOOGLE_PLACES_KEY = os.environ.get("GOOGLE_PLACES_KEY", "")   # Google Places API (New) key (decisions/0085): checks listings against Google
PLACES_PER_RUN = int(os.environ.get("PLACES_PER_RUN", "5"))       # listings checked against Google per run, at most; the site paces the month
PLACES_TIME = int(os.environ.get("PLACES_TIME", "40"))            # seconds of each run for it
PLACES_MONTHLY = int(os.environ.get("GOOGLE_PLACES_MONTHLY", "3000"))   # checks a month at most (the site stops at 90% and paces it)
places_used = 0
search_allow = 0      # searches this run may make: the site says, from the month's use so far (decisions/0084); 0 until it does
LEARN_PER_RUN = int(os.environ.get("LEARN_PER_RUN", "3"))         # Tier 2 web searches for situation gaps per run (decisions/0061); 0 turns it off
LEARN_TIME = int(os.environ.get("LEARN_TIME", "45"))
HERITAGE_PER_RUN = int(os.environ.get("HERITAGE_PER_RUN", "2"))  # places searched at the Library of Congress per run (decisions/0133); 0 turns it off
HERITAGE_TIME = int(os.environ.get("HERITAGE_TIME", "40"))
LOC_SEARCH = "https://www.loc.gov/search/"
LOC_DELAY = 3.0                     # seconds between Library of Congress requests (its API asks for a gentle pace)
LOC_NEWS = "https://www.loc.gov/collections/chronicling-america/"   # historic newspapers (public domain), searched by place and state
LOC_NEWS_PER_PLACE = int(os.environ.get("LOC_NEWS_PER_PLACE", "15"))  # newspaper pages read per place; 0 turns them off
GUIDES_PER_RUN = int(os.environ.get("GUIDES_PER_RUN", "2"))      # how-to guides looked for per run (decisions/0063 §8); 0 turns it off
GUIDES_TIME = int(os.environ.get("GUIDES_TIME", "45"))
TARGETS_PER_RUN = int(os.environ.get("TARGETS_PER_RUN", "2"))    # search targets (a place and a kind of business, decisions/0066) per run; 0 turns it off
TARGETS_TIME = int(os.environ.get("TARGETS_TIME", "90"))
GEOCODE_PER_RUN = int(os.environ.get("GEOCODE_PER_RUN", "2"))    # batches of up to 500 street addresses placed on the map per run (the site asks the Census); 0 turns it off
GEOCODE_TIME = int(os.environ.get("GEOCODE_TIME", "420"))
WIKIDATA_SPARQL = "https://query.wikidata.org/sparql"
SOURCE_TIME = int(os.environ.get("SOURCE_TIME", "60"))            # seconds per run for reading one of our sources
SOURCE_PAGES = int(os.environ.get("SOURCE_PAGES", "25"))          # pages read per source
CC_TIME = int(os.environ.get("CC_TIME", "60"))                    # seconds a run for Common Crawl (decisions/0157 §8.3); 0 turns it off
CC_PAGES = int(os.environ.get("CC_PAGES", "40"))                  # pages read from its archive a run, at most
CC_PER_SITE = int(os.environ.get("CC_PER_SITE", "50"))            # pages a site, at most, each new crawl
CC_INDEX = "https://index.commoncrawl.org"
CC_DATA = "https://data.commoncrawl.org/"
SOURCE_PDFS = int(os.environ.get("SOURCE_PDFS", "5"))             # of them PDFs, at most (decisions/0157 §7)
PDF_BYTES = 6_000_000                                               # a PDF larger than this is left for later
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
WORKER_V = "2.1"           # the service's contract 2 (decisions/0160 §2): the site plans each run, the worker reports it
RUN_END = None             # when this run's own work must stop (started + TIME_BUDGET, or a bulk load's end)
TASK_END = None            # when the capture task being worked must stop (its share of the run)
FETCH_CAP = MAX_FETCHES    # fetches this run may have made by the end of the task being worked
SEARCH_CAP = None          # searches this run may have made by the end of the task being worked (None: the run's own cap)
task_got = 0               # items the site handed out to the task being worked (counted in site_call)
searches = 0
last_search = 0.0
fetches = 0
last_fetch = 0.0
robots_cache = {}


def log(*a):
    print(time.strftime("%H:%M:%S"), *a, flush=True)


def out_of_time():
    now = time.monotonic()
    if TASK_END is not None and now > TASK_END:
        return True                        # the task's share is used: the rest go back untouched
    return now > (RUN_END if RUN_END is not None else started + TIME_BUDGET)


GOT_KEYS = ("listings", "targets", "tasks", "places", "guides", "jobs", "hosts", "discover", "describe", "scans")


def site_call(path, payload=None, timeout=60):
    """GET or POST JSON to the Traversence site with the worker token."""
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(SITE + path, data=data, method="POST" if data else "GET", headers={
        "X-Crawler-Token": TOKEN, "Authorization": "Bearer " + TOKEN,
        "Content-Type": "application/json", "User-Agent": UA, "Accept": "application/json"})
    global task_got
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            d = json.loads(r.read().decode("utf-8"))
            if payload is None and isinstance(d, dict):   # work the site handed out: how the run knows a task had work
                task_got += sum(len(d[k]) for k in GOT_KEYS if isinstance(d.get(k), list)) + (1 if d.get("source") else 0)
            return d
    except urllib.error.HTTPError as e:
        # Say which call failed and what the site said (Bluehost's firewall answers 406 with an HTML page).
        raise RuntimeError(f"site {path} said {e.code}: {e.read()[:200]!r}") from None


def time_left(deadline=None):
    """Seconds left for the task being worked: its share, the run's end and `deadline`, whichever comes first."""
    ends = [x for x in (TASK_END, RUN_END if RUN_END is not None else started + TIME_BUDGET, deadline) if x is not None]
    return min(ends) - time.monotonic()


OVERPASS_MIN = 20     # seconds: a lookup with less than this left isn't sent (the job goes back for a later run)


def overpass(job, deadline=None):
    """The job's places from OpenStreetMap, inside the task's time (2026-11-13: five mirrors at 125 seconds each held a
    run for seven minutes, and every other task went unreached). Each try gets only the time left; when the time runs out
    before any server has answered, OutOfTime hands the job back untried."""
    if os.environ.get("OVERPASS_FIXTURE"):
        with open(os.environ["OVERPASS_FIXTURE"]) as f:
            return json.load(f).get("elements", [])
    pts = [p for p in job["place"].get("zip_points", []) if p.get("lat") is not None] or \
          [{"lat": job["place"]["center"]["lat"], "lon": job["place"]["center"]["lon"]}]
    tried, timed_out = [], False
    for url in OVERPASS_URLS:
        left = time_left(deadline)
        if left < OVERPASS_MIN:
            raise OutOfTime(f"{int(max(0, left))}s left for OpenStreetMap" + (": " + " | ".join(tried) if tried else ""))
        # the server stops its own work a little before we stop waiting
        query = overpass_query(job, pts, max(15, min(110, int(left) - 8)))
        body = urllib.parse.urlencode({"data": query}).encode()
        req = urllib.request.Request(url, data=body, headers={
            "User-Agent": UA, "Accept": "*/*",   # a strict "application/json" can earn a 406 Not Acceptable
            "Content-Type": "application/x-www-form-urlencoded"})
        try:
            with urllib.request.urlopen(req, timeout=max(5, min(125, left - 3))) as r:
                d = json.loads(r.read().decode("utf-8"))
            # Overpass answers 200 with no elements and a remark when it ran out of time or memory: that's a failure, not
            # "nothing here" (2026-11-10: Dana Point's places to eat were recorded as done, 0 found)
            rem = str(d.get("remark") or "")
            if re.search(r"runtime (error|remark)|timed out|out of memory", rem, re.I):
                tried.append(f"{urllib.parse.urlparse(url).netloc} remark {rem[:100]!r}")
                continue
            return d.get("elements", [])
        except urllib.error.HTTPError as e:
            if e.code == 406 and len(query) < 1800 and time_left(deadline) >= OVERPASS_MIN:   # some servers refuse the form POST but take a short query as a GET (2026-10-14); a long one is 414
                try:
                    g = urllib.request.Request(url + "?" + urllib.parse.urlencode({"data": query}), headers={"User-Agent": UA, "Accept": "*/*"})
                    with urllib.request.urlopen(g, timeout=max(5, min(125, time_left(deadline) - 3))) as r:
                        return json.loads(r.read().decode("utf-8")).get("elements", [])
                except Exception as e2:
                    tried.append(f"{urllib.parse.urlparse(url).netloc} GET {e2!r}"[:160])
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e.code} {e.read()[:120]!r}")
        except (urllib.error.URLError, TimeoutError, ValueError, OSError) as e:
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e!r}"[:160])
            timed_out = timed_out or "timed out" in repr(e).lower()
    if timed_out and time_left(deadline) < OVERPASS_MIN:
        raise OutOfTime("OpenStreetMap didn't answer in the time left: " + " | ".join(tried))   # slow, not refused
    raise RuntimeError("OpenStreetMap lookup failed: " + " | ".join(tried))


def overpass_query(job, pts, timeout=110):
    """The Overpass query for a job: a circle around each ZIP centre when there are few, else one box around them all
    (2026-11-10: Phoenix's ~60 ZIPs made a query too large to send or run). Names only; 120 places at most."""
    filters = job["looking_for"].get("osm") or GROUP_QUERIES.get(job["looking_for"]["group"], [])
    if len(pts) * max(1, len(filters)) <= 12:
        parts = [f'{q}["name"](around:{ZIP_RADIUS_M},{p["lat"]},{p["lon"]});' for q in filters for p in pts]
        return f"[out:json][timeout:{int(timeout)}];(" + "".join(parts) + ");out center tags 120;"
    pad_lat = min(ZIP_RADIUS_M, 8000) / 111000.0
    mid = sum(p["lat"] for p in pts) / len(pts)
    pad_lon = pad_lat / max(0.2, math.cos(math.radians(mid)))
    s_, n_ = min(p["lat"] for p in pts) - pad_lat, max(p["lat"] for p in pts) + pad_lat
    w_, e_ = min(p["lon"] for p in pts) - pad_lon, max(p["lon"] for p in pts) + pad_lon
    parts = [f'{q}["name"];' for q in filters]
    return f"[out:json][timeout:{int(timeout)}][bbox:{s_:.4f},{w_:.4f},{n_:.4f},{e_:.4f}];(" + "".join(parts) + ");out center tags 120;"


def wikidata(job, deadline=None):
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
            if time_left(deadline) < 10:
                return []                          # no time for it this turn: OpenStreetMap's answer still counts
            with urllib.request.urlopen(req, timeout=max(5, min(60, time_left(deadline) - 5))) as r:
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


MAX_CRAWL_DELAY = 30                # a site asking for more than this between pages is left alone (decisions/0133)
host_last = {}                      # when we last fetched from each host, for its Crawl-delay


def public_host(url):
    """Only the open web: http(s) to a name whose every address is public. Checked before each fetch and each
    redirect, so no page can send us to a private, loopback, link-local or cloud-metadata address (decisions/0133)."""
    p = urllib.parse.urlsplit(url)
    if p.scheme not in ("http", "https") or not p.hostname:
        return False
    try:
        infos = socket.getaddrinfo(p.hostname, p.port or (443 if p.scheme == "https" else 80), proto=socket.IPPROTO_TCP)
    except Exception:
        return False
    try:
        return bool(infos) and all(ipaddress.ip_address(i[4][0].split("%")[0]).is_global for i in infos)
    except ValueError:
        return False


def crawl_delay(url):
    """The site's robots.txt Crawl-delay for us, in seconds (0 when it sets none)."""
    p = urllib.parse.urlsplit(url)
    rp = robots_cache.get(f"{p.scheme}://{p.netloc}")
    try:
        d = rp.crawl_delay(UA) if rp else None
        return float(d) if d else 0.0
    except Exception:
        return 0.0


def allowed(url):
    host = urllib.parse.urlsplit(url)
    base = f"{host.scheme}://{host.netloc}"
    if base not in robots_cache:
        robots_cache[base] = read_robots(base)
    rp = robots_cache[base]
    if crawl_delay(url) > MAX_CRAWL_DELAY:
        return False                       # it asks us to crawl slower than we can: we don't
    return rp is None or rp.can_fetch(UA, url)


class PublicRedirect(urllib.request.HTTPRedirectHandler):
    """robots.txt's own redirects: each hop to the open web only (review C15)."""
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not public_host(newurl):
            raise urllib.error.URLError("redirect to a private address")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


ROBOTS_OPENER = urllib.request.build_opener(PublicRedirect)


class _DenyAll:
    """A robots.txt we couldn't read (a server error, no answer, 401/403): nothing is fetched from that site this run."""
    def can_fetch(self, *_):
        return False

    def crawl_delay(self, *_):
        return None


def read_robots(base):
    """A site's robots.txt, read as ourselves (our user agent, review C15), its redirects checked hop by hop. None when the
    site has none (404/410: everything allowed, as the standard says); _DenyAll when it couldn't be read (a server error,
    a timeout, 401/403), so an unreachable robots.txt is never taken as permission (RFC 9309 §2.3.1)."""
    url = base + "/robots.txt"
    if not public_host(url):
        return _DenyAll()
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "text/plain,*/*;q=0.5"})
    try:
        with ROBOTS_OPENER.open(req, timeout=15) as r:
            body = r.read(500_000).decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        if e.code in (401, 403):
            return _DenyAll()
        if 400 <= e.code < 500:
            return None                    # no robots.txt: allowed
        return _DenyAll()                  # 5xx: unreachable, so not allowed this run
    except Exception:
        return _DenyAll()
    rp = urllib.robotparser.RobotFileParser(url)
    rp.parse(body.splitlines())
    return rp


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    """Every hop is checked like the first: the open web only, and robots.txt."""
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not public_host(newurl) or not allowed(newurl):
            raise urllib.error.URLError("redirect to a private or disallowed address")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


SAFE_OPENER = urllib.request.build_opener(SafeRedirect)


def fetch_page(url):
    """One polite GET of a homepage: robots.txt, a delay, a size and time cap. None on any failure."""
    return fetch_page_meta(url)["body"]


def fetch_page_meta(url, etag="", modified="", pdf=False, xml=False):
    """fetch_page with what the page log keeps (decisions/0157 §1): {body, status, type, bytes, etag, modified}. With the
    entity tag or last-changed date the server gave last time, it asks first whether the page changed: an unchanged page
    answers 304 with no body, and costs nothing to download. status 0: not fetched (a budget, robots.txt, an error)."""
    global fetches, last_fetch
    out = {"body": None, "status": 0, "type": "", "bytes": 0, "etag": "", "modified": "", "why": ""}
    if os.environ.get("WEB_FIXTURE"):
        with open(os.environ["WEB_FIXTURE"]) as f:
            fx = json.load(f).get(url)
        if isinstance(fx, dict):                       # {"status": 304} or {"body": ..., "etag": ...}
            out.update(fx); out["bytes"] = len(out.get("body") or "")
            if out["status"] == 304:
                out.update(etag=etag, modified=modified)
            return out
        if fx is not None:
            out.update(body=fx, status=200, type="text/html", bytes=len(fx))
        return out
    if fetches >= min(MAX_FETCHES, FETCH_CAP) or out_of_time():
        out["why"] = "budget"
        return out
    if not public_host(url) or not allowed(url):
        out["why"] = "robots"                          # robots.txt or a private address: never read another way
        return out
    host = urllib.parse.urlsplit(url).netloc.lower()
    wait = max(FETCH_DELAY - (time.monotonic() - last_fetch), crawl_delay(url) - (time.monotonic() - host_last.get(host, -1e9)))
    if wait > 0:
        time.sleep(wait)
    fetches += 1
    last_fetch = host_last[host] = time.monotonic()
    head = {"User-Agent": UA, "Accept": "text/html"}
    if etag:
        head["If-None-Match"] = etag
    if modified:
        head["If-Modified-Since"] = modified
    try:
        req = urllib.request.Request(url, headers=head)
        with SAFE_OPENER.open(req, timeout=12) as r:
            ctype = (r.headers.get("Content-Type") or "")[:80]
            out.update(status=r.status, type=ctype, etag=(r.headers.get("ETag") or "")[:190],
                       modified=(r.headers.get("Last-Modified") or "")[:60])
            if pdf and "pdf" in ctype.lower():
                data = r.read(PDF_BYTES + 1)
                out["bytes"] = len(data)
                if len(data) <= PDF_BYTES:
                    out["body"], out["scan"] = pdf_page(data, url)
                return out
            if "html" not in ctype and not (xml and "xml" in ctype.lower()):
                return out
            body = r.read(2_000_000 if xml else 400_000)
            out.update(bytes=len(body), body=body.decode("utf-8", "replace"))
            # a page that shows nothing without a browser (its text drawn by scripts): "empty"
            if not xml and len(re.sub(r"\s+", " ", " ".join(page_lines(out["body"])))) < 150 and len(re.findall(r"(?i)<script", out["body"])) >= 3:
                out["why"] = "empty"
            return out
    except urllib.error.HTTPError as e:
        out["status"] = e.code                         # 304: unchanged since last time; 404, 410: gone
        if e.code == 304:
            out.update(etag=etag, modified=modified)
        elif e.code in (401, 403, 451):
            out["why"] = "refused"                     # the site said no: never read another way
        elif e.code >= 500 or e.code in (408, 429):
            out["why"] = "error"
        return out
    except Exception:
        out["why"] = "error"                           # a timeout, a broken certificate, a dropped connection
        return out


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
    # a town built on demand (decisions/0149): OpenStreetMap is a lead only (0133), so a place with no website there gets
    # one search for its own site (Jason, 2026-11-10), confirmed below the same way as any website
    if not website and str(job["looking_for"].get("group", "")).startswith("town:") and SEARCH_ON:
        found = find_own_site(name, street, phone, c["where"]["city"], c["where"]["state"])
        if found:
            website = found
            c["actions"]["website"] = found
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
    # OpenStreetMap is a lead and a link, never copied (decisions/0065; review C11): its phone, hours and street stay only
    # where the business's own website shows them; without its website, a find is a lead (a name, a ZIP, the map link)
    src = c["sources"]
    if "openstreetmap.org" not in osm_url:
        pass                                   # a Wikidata item (CC0): its facts may be kept, as before
    elif not c["corroboration"]:
        c["actions"]["phone"], c["when"]["hours"], c["where"]["address"] = "", [], ""
        c["sources"] = {"name": [osm_url]}
        c["lead"] = osm_url
        score = 15 + 5
    else:
        own = [website]
        if src.get("phone") != own:
            c["actions"]["phone"] = ""
            src.pop("phone", None)
        if src.get("hours") != own:
            c["when"]["hours"] = []
            src.pop("hours", None)
        if src.get("address") != own:
            c["where"]["address"] = ""
            src.pop("address", None)
        score = 15 + 5 + 20 + (10 if c["what"]["description"] else 0) + (25 if c["where"]["address"] else 0) \
            + (15 if c["actions"]["phone"] else 0) + (10 if c["when"]["hours"] else 0)
    c["confidence"] = min(100, score)
    return c


# ---------------------------------------------------------------------------------------------------
# Second look: more references for listings waiting for review
# ---------------------------------------------------------------------------------------------------

def find_own_site(name, street, phone, town, state):
    """The business's own website, from one web search: a result whose domain is the business's (never a directory) and
    whose page names the place and shows its town, street or phone, so a namesake elsewhere isn't taken. "" when none,
    or when this run's share of the month's free searches is used (search() returns nothing then)."""
    query = " ".join(x for x in (f'"{name}"', street, town, state) if x)
    for r in search(query)[:6]:
        url = r.get("url") or ""
        if not url.startswith("http") or kind_of(url, r.get("title", ""), {"website": "", "name": name}) != "own_site":
            continue
        page = fetch_page(url)
        if not page or not names_place(page, name):
            continue
        text = html.unescape(re.sub(r"<[^>]+>", " ", page)).lower()
        if (town and town.lower() in text) or (street and address_on(page, street)) or (phone and digits(phone) in phones_on(page)):
            log(f"  own site found for {name}: {url}"[:200])
            return url
    return ""


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
        if not (TAVILY_API_KEY or BRAVE_API_KEY) or searches >= min(MAX_SEARCHES, search_allow, SEARCH_CAP if SEARCH_CAP is not None else MAX_SEARCHES):
            return []                                 # the run's share of the month is used up (or the site didn't say)
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
    try:
        return re.sub(r"^www\.", "", urllib.parse.urlsplit(url).netloc.lower())
    except ValueError:                     # a malformed address ("http://[bad/…", 2026-11-13): no host, skipped by the callers
        return ""


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


# Reading a page (decisions/0157 §2), cheapest and surest first: the page's own structured data (schema.org JSON-LD
# and microdata), then its text. Each fact says how it was read ("structured", "list", "text"), what kind of page it was
# on, and the item's own words (what it does, the heading it sits under), so the site can tell what kind of place it is.
PLACE_TYPES = re.compile(r"(LocalBusiness|Organization|Place|Store|Restaurant|Service|Office|Center|Clinic|Hospital|Dentist|"
                         r"Physician|Pharmacy|Agency|Library|School|Museum|Park|Lodging|Hotel|Motel|Campground|Church|"
                         r"Government|Attorney|Contractor|Plumber|Electrician|Repair|Dealer|Shop|Salon|Bakery|Cafe|Bar)", re.I)
DAYS = {"mo": "Mon", "tu": "Tue", "we": "Wed", "th": "Thu", "fr": "Fri", "sa": "Sat", "su": "Sun"}


def ld_type(d):
    t = d.get("@type") or d.get("type") or ""
    if isinstance(t, list):
        t = next((x for x in t if isinstance(x, str) and x not in ("Thing",)), "")
    return str(t).rsplit("/", 1)[-1][:80]


def ld_hours(d):
    """openingHours ("Mo-Fr 08:00-17:00") or OpeningHoursSpecification, as one short line."""
    oh = d.get("openingHours")
    if isinstance(oh, list):
        return "; ".join(str(x) for x in oh)[:500]
    if isinstance(oh, str):
        return oh[:500]
    out = []
    for spec in d.get("openingHoursSpecification") or []:
        if not isinstance(spec, dict):
            continue
        days = spec.get("dayOfWeek") or []
        days = days if isinstance(days, list) else [days]
        names = [DAYS.get(str(x).rsplit("/", 1)[-1][:2].lower(), "") for x in days]
        o, c = str(spec.get("opens", ""))[:5], str(spec.get("closes", ""))[:5]
        if o or c:
            out.append(",".join(n for n in names if n) + f" {o}-{c}")
    return "; ".join(out)[:500]


def ld_fact(d, url, how="structured"):
    """One schema.org thing as a fact, or None when it isn't a place we can find (no name, or no address or phone)."""
    name, addr, phone = d.get("name"), d.get("address"), d.get("telephone")
    if isinstance(phone, list):
        phone = phone[0] if phone else ""
    if not isinstance(name, str) or not name.strip() or not (addr or phone):
        return None
    street, zp, town, state = "", "", "", ""
    if isinstance(addr, list):
        addr = addr[0] if addr else ""
    if isinstance(addr, dict):
        street = " ".join(str(addr.get(k, "")) for k in ("streetAddress", "addressLocality", "addressRegion", "postalCode")).strip()
        street = re.sub(r"\s+", " ", street)
        zp = str(addr.get("postalCode", ""))[:5]
        town, state = str(addr.get("addressLocality", ""))[:120], str(addr.get("addressRegion", ""))[:2].upper()
    elif isinstance(addr, str):
        street = addr
    geo = d.get("geo") if isinstance(d.get("geo"), dict) else {}
    same = d.get("sameAs") or []
    site = d.get("url") if isinstance(d.get("url"), str) else ""
    desc = d.get("description") if isinstance(d.get("description"), str) else ""
    kinds = [ld_type(d)] + [str(x) for x in (d.get("additionalType") or [])][:2] if isinstance(d.get("additionalType"), list) else [ld_type(d)]
    return {"url": url, "name": html.unescape(name.strip())[:255], "address": street[:255], "phone": str(phone or ""), "zip": zp,
            "town": town, "state": state, "website": site[:500], "hours": ld_hours(d),
            "lat": geo.get("latitude"), "lng": geo.get("longitude"),
            "schema_type": ld_type(d), "words": re.sub(r"\s+", " ", html.unescape(desc))[:255],
            "kinds": [re.sub(r"(?<=[a-z])(?=[A-Z])", " ", k) for k in kinds if k][:3],
            "same_as": [x for x in (same if isinstance(same, list) else [same]) if isinstance(x, str)][:5], "how": how}


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
            f = ld_fact(d, url)
            if f:
                out.append(f)
    return out + microdata_facts(page, url)


class _Microdata(HTMLParser):
    """schema.org microdata (itemscope / itemtype / itemprop): each item as a dict like JSON-LD's."""
    VOID = {"meta", "link", "img", "br", "hr", "input", "source", "area", "base", "col", "embed", "param", "track", "wbr"}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.items, self.stack, self.text = [], [], []          # stack: [tag, item or None, prop or None]

    def _item(self):
        return next((x[1] for x in reversed(self.stack) if x[1] is not None), None)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        prop = (a.get("itemprop") or "").split()[0] if a.get("itemprop") else None
        item = None
        if "itemscope" in a:
            item = {"@type": (a.get("itemtype") or "").split()[0] if a.get("itemtype") else ""}
            parent = self._item()
            if parent is not None and prop:
                parent[prop] = item
            else:
                self.items.append(item)
        elif prop:
            val = a.get("content") or a.get("href") or a.get("src") or a.get("datetime")
            owner = self._item()
            if owner is not None and val:
                owner.setdefault(prop, val)
                prop = None if tag in self.VOID else prop
        if tag in self.VOID:
            return
        self.stack.append([tag, item, prop if item is None else None, len(self.text)])

    def handle_endtag(self, tag):
        while self.stack:
            t, item, prop, at = self.stack.pop()
            if prop:
                owner = self._item()
                val = re.sub(r"\s+", " ", " ".join(self.text[at:])).strip()
                if owner is not None and val:
                    owner.setdefault(prop, val[:300])
            if t == tag:
                break

    def handle_data(self, data):
        self.text.append(data)


def microdata_facts(page, url):
    if "itemscope" not in page:
        return []
    p = _Microdata()
    try:
        p.feed(re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", page)); p.close()
    except Exception:
        return []
    out = []
    for it in p.items:
        if not PLACE_TYPES.search(ld_type(it)) and not isinstance(it.get("address"), dict):
            continue
        f = ld_fact(it, url)
        if f:
            out.append(f)
    return out


def lines_page(lines, title):
    """Text lines as a plain page (one paragraph a line), so they're read like any page."""
    return "<html><head><title>" + html.escape(title[:200]) + "</title></head><body>" + "".join("<p>" + html.escape(l) + "</p>" for l in lines if l.strip()) + "</body></html>"


def tavily_extract(url):
    """The second tier for a page our fetch couldn't get or that shows nothing without a browser (decisions/0157 §1):
    Tavily Extract's text of it, as a page. Only within the allowance the site gives, never for a page robots.txt or the
    site itself refuses. None when it can't."""
    if not TAVILY_API_KEY or os.environ.get("WEB_FIXTURE"):
        return None
    try:
        req = urllib.request.Request("https://api.tavily.com/extract", method="POST",
                                     data=json.dumps({"urls": [url], "extract_depth": "basic", "format": "text"}).encode(),
                                     headers={"Authorization": "Bearer " + TAVILY_API_KEY, "Content-Type": "application/json",
                                              "Accept": "application/json", "User-Agent": UA})
        with urllib.request.urlopen(req, timeout=40) as r:
            res = json.loads(r.read().decode("utf-8")).get("results") or []
        text = (res[0].get("raw_content") or "") if res else ""
        if len(text.strip()) < 80:
            return None
        title = urllib.parse.unquote(url.rstrip("/").rsplit("/", 1)[-1]).replace("-", " ").replace("_", " ") or host_of(url)
        return lines_page([l.strip() for l in text.splitlines()], title)
    except Exception as e:
        log(f"extract {url[:80]}: {e!r}"[:200])
        return None


def pdf_page(data, url):
    """A PDF's text as a plain page (one paragraph a line), so it's read like any page (decisions/0157 §7). Free. A PDF with
    no text (pictures of pages) is a scan: (None, True), logged for the second tier (AWS Textract, with the caps)."""
    if pypdf is None:
        return None, False
    try:
        doc = pypdf.PdfReader(io.BytesIO(data))
        title = ((doc.metadata or {}).get("/Title") or "") if hasattr(doc, "metadata") else ""
        lines = []
        for pg in doc.pages[:40]:
            for l in (pg.extract_text() or "").splitlines():
                l = l.strip()
                if l:
                    lines.append(l)
        if sum(len(l) for l in lines) < 40:
            return None, True
        if str(title).strip().lower() in ("", "untitled", "microsoft word", "none"):
            title = ""
        title = str(title or urllib.parse.unquote(url.rsplit("/", 1)[-1]).rsplit(".", 1)[0].replace("-", " ").replace("_", " "))[:200]
        return lines_page(lines, title), False
    except Exception:
        return None, False


def page_lines(page):
    """The page's text, one block per line (headings, list items, paragraphs, cells)."""
    return [l for l, _ in page_blocks(page)]


def page_blocks(page):
    """page_lines with each line marked as a heading (h1-h6, a table or list caption) or not."""
    t = re.sub(r"(?is)<(script|style|noscript)[^>]*>.*?</\1>", " ", page)
    t = re.sub(r"(?i)<(h[1-6]|caption|legend|dt)\b[^>]*>", "\n\x01", t)
    t = re.sub(r"(?i)<br\s*/?>|</(p|div|li|h[1-6]|tr|td|th|address|section|article|dt|dd|caption|legend)>", "\n", t)
    t = html.unescape(re.sub(r"<[^>]+>", " ", t))
    out = []
    for l in t.split("\n"):
        head = "\x01" in l
        l = re.sub(r"\s+", " ", l.replace("\x01", " ")).strip()
        if l:
            out.append((l, head))
    return out


def _is_detail(line):
    return bool(PHONE_RE.search(line) or ADDR_RE.match(line) or CITY_ZIP_RE.search(line)
                or re.match(r"(?i)^(phone|tel|fax|email|e-mail|web(site)?|hours|address)\b", line))


def text_facts(page, url):
    """A name line followed closely by a street address and/or phone number. On a list page each one is an item, with
    its own words (the short lines that follow it: what it does) and the heading it sits under ("Solid waste")."""
    blocks = page_blocks(page)
    lines = [l for l, _ in blocks]
    out, used = [], set()
    for i, line in enumerate(lines):
        phone = PHONE_RE.search(line)
        is_addr = bool(ADDR_RE.match(line))
        if not phone and not is_addr:
            continue
        name, jname = "", i
        for j in range(i - 1, max(-1, i - 4), -1):              # the nearest short, word-like line above
            cand = lines[j]
            if 3 <= len(cand) <= 80 and re.search(r"[A-Za-z]{3}", cand) and not PHONE_RE.search(cand) and not ADDR_RE.match(cand) \
                    and not CITY_ZIP_RE.search(cand) and not re.match(r"(?i)^(phone|tel|fax|email|hours|address|open|closed)\b", cand) \
                    and not re.match(r"\d", cand):                # "3 mi North of St. Johns": directions, not a name
                name, jname = cand, j; break
        if not name or jname in used:
            continue
        used.add(jname)
        near = lines[jname + 1:i + 3]                           # this entry only: from its name down
        addr = next((l for l in near if ADDR_RE.match(l)), "")
        cz = next((CITY_ZIP_RE.search(l).group(0) for l in near if CITY_ZIP_RE.search(l)), "")
        ph = phone.group(0) if phone else next((PHONE_RE.search(l).group(0) for l in near if PHONE_RE.search(l)), "")
        # the heading above it
        section = next((blocks[k][0] for k in range(jname - 1, max(-1, jname - 40), -1) if blocks[k][1] and blocks[k][0] != name), "")
        tz = re.match(r"\s*([A-Za-z .'-]+),\s*([A-Z]{2})", cz) if cz else None
        out.append({"url": url, "name": name, "address": (addr + (", " + cz if cz else "")).strip(", "), "phone": ph,
                    "zip": (re.search(r"\d{5}", cz) or [""])[0] if cz else "",
                    "town": tz.group(1).strip()[:120] if tz else "", "state": tz.group(2) if tz else "",
                    "section": section[:120], "how": "text", "_at": jname})
    # its own words: up to two short lines between its name and the next item's that aren't details (what it does)
    for n, f in enumerate(out):
        stop = out[n + 1]["_at"] if n + 1 < len(out) else min(len(lines), f["_at"] + 8)
        words = [l for l, head in blocks[f.pop("_at") + 1:stop] if not head and not _is_detail(l) and 6 <= len(l) <= 160 and re.search(r"[a-z]{3}", l)]
        f["words"] = " · ".join(words[:2])[:255]
    # the items of a list page: three or more in one page
    if len(out) >= 3:
        for f in out:
            f["how"] = "list"
    return out


def page_town(page):
    """The town a page names most ("Saint Johns, AZ 85936"): the place for items with no address of their own."""
    seen = {}
    for m in re.finditer(r"([A-Z][A-Za-z .'-]{2,30}),\s*(AZ|NM|UT|CO|NV|CA|TX)\b", page):
        k = (m.group(1).strip(), m.group(2))
        seen[k] = seen.get(k, 0) + 1
    return max(seen, key=seen.get) if seen else ("", "")


def page_kind(page, url, facts, title):
    """What kind of page it is (decisions/0157 §2): own_site, list, events, notice, article or other."""
    low = (title + " " + url).lower()
    if re.search(r'"@type"\s*:\s*"(Event|EventSeries)"', page) or re.search(r"\b(events?|calendar)\b", low) and \
            len(re.findall(r"\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.? \d{1,2}\b", page)) >= 4:
        return "events"
    names = {re.sub(r"\W+", "", f["name"].lower()) for f in facts}
    if len(names) >= 3:
        return "list"
    if re.search(r"\.(gov|us)$", host_of(url)) and re.search(r"\b(notice|hearing|closure|closed|alert|advisory|ordinance)\b", low):
        return "notice"
    if re.search(r'og:type"?\s+content="article"|<article\b', page, re.I) and not facts:
        return "article"
    if len(names) == 1:
        return "own_site"
    return "other"


def page_title(page):
    m = re.search(r"(?is)<title[^>]*>(.*?)</title>", page)
    return re.sub(r"\s+", " ", html.unescape(m.group(1))).strip()[:200] if m else ""


def tv_safe(v):
    """Links travel wrapped past Bluehost's ModSecurity, as the site's own forms do (Response::unwrap() reads them)."""
    if isinstance(v, dict):
        return {k: tv_safe(x) for k, x in v.items()}
    if isinstance(v, list):
        return [tv_safe(x) for x in v]
    if isinstance(v, str) and re.match(r"(?i)^https?://", v) and re.search(r"[?=&]", v):
        return "tvb64:" + __import__("base64").b64encode(v.encode()).decode()
    return v


def read_page(page, url):
    """One page read (decisions/0157 §2): its structured data first, then its text; the kind of page; each place with the
    page's kind, title and town. ([facts], kind, title)"""
    title = page_title(page)
    here = jsonld_facts(page, url)
    if not here:                                         # its own structured data first; the text only when there's none
        here = text_facts(page, url)
    else:
        have = {re.sub(r"\W+", "", f["name"].lower()) for f in here}
        here += [f for f in text_facts(page, url) if re.sub(r"\W+", "", f["name"].lower()) not in have]
    kind = page_kind(page, url, here, title)
    pt = page_town(page)
    for f in here:
        f["page_kind"], f["title"] = kind, title
        if pt[0]:
            f.setdefault("page_town", pt[0]); f.setdefault("page_state", pt[1])
    return here, kind, title


# ---------------------------------------------------------------------------------------------------
# Common Crawl (decisions/0157 §8.3): the public archive of billions of pages, a new crawl each month. Its index lists the
# pages it captured on a site; each page is read from the archive by its byte range (free, and no load on the site). Our
# states' local public sites (az.us, nm.us) are found from its index and go on the register; each source we read regularly
# is read from the newest crawl once, up to CC_PER_SITE pages, through the same reading and page log. Never a page today's
# robots.txt refuses. Pages naming our towns would need a full-text search of the archive (AWS Athena, paid): not done.
# ---------------------------------------------------------------------------------------------------

last_cc = 0.0


def cc_get(url, headers=None, timeout=40):
    """One polite request to Common Crawl (a second apart, as it asks)."""
    global last_cc
    wait = 1.0 - (time.monotonic() - last_cc)
    if wait > 0:
        time.sleep(wait)
    last_cc = time.monotonic()
    req = urllib.request.Request(url, headers={"User-Agent": UA, **(headers or {})})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()


def cc_latest():
    """The newest crawl: (its id, its index's address)."""
    info = json.loads(cc_get(CC_INDEX + "/collinfo.json").decode("utf-8"))
    return info[0]["id"], info[0]["cdx-api"]


def cc_list(api, params, limit=1000):
    """Records from a crawl's index: [{url, filename, offset, length, ...}]."""
    q = urllib.parse.urlencode(params, doseq=True)
    try:
        raw = cc_get(api + "?" + q + "&output=json&limit=" + str(limit)).decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        if e.code == 404:                               # nothing captured for it
            return []
        raise
    out = []
    for line in raw.splitlines():
        try:
            out.append(json.loads(line))
        except Exception:
            pass
    return out


def cc_page(rec):
    """A captured page's HTML, from the archive by its byte range, or None."""
    off, ln = int(rec["offset"]), int(rec["length"])
    data = cc_get(CC_DATA + rec["filename"], {"Range": f"bytes={off}-{off + ln - 1}"})
    raw = gzip.decompress(data)
    parts = raw.split(b"\r\n\r\n", 2)                  # the archive's record header, the site's answer's header, the page
    if len(parts) < 3 or b"html" not in parts[1].lower():
        return None
    m = re.search(rb"charset=([\w-]+)", parts[1], re.I)
    try:
        return parts[2][:400_000].decode((m.group(1).decode() if m else "utf-8"), "replace")
    except LookupError:
        return parts[2][:400_000].decode("utf-8", "replace")


def common_crawl(deadline):
    crawl, api = cc_latest()
    d = site_call("/api/crawl/commoncrawl.php?crawl=" + urllib.parse.quote(crawl))
    # our states' local public sites: every site under az.us, nm.us … that the crawl captured, onto the register
    for st in (d.get("discover") or [])[:1]:
        if time.monotonic() > deadline or out_of_time():
            return
        recs = cc_list(api, {"url": st.lower() + ".us", "matchType": "domain", "fl": "url", "filter": ["status:200", "mime:text/html"]}, 5000)
        hosts = sorted({host_of(r.get("url", "")) for r in recs if r.get("url")})
        res = site_call("/api/crawl/commoncrawl.php", {"state": st, "crawl": crawl,
                                                      "discovered": [{"url": "https://" + h + "/", "text": ""} for h in hosts if h][:400]})
        log(f"Common Crawl {crawl}: {len(hosts)} site(s) under {st.lower()}.us, {res.get('new_sites', 0)} new on the register")
    left = CC_PAGES
    for h in (d.get("hosts") or [])[:3]:
        if left <= 0 or time.monotonic() > deadline or out_of_time():
            break
        host = h.get("host") or ""
        recs = cc_list(api, {"url": host, "matchType": "domain", "fl": "url,filename,offset,length", "filter": ["status:200", "mime:text/html"],
                             "collapse": "urlkey"}, 2000)
        already = set(h.get("read") or [])                # read from this crawl on an earlier run: not again
        recs = [r for r in recs if host_of(r.get("url", "")) == host and r.get("url") not in already]
        recs.sort(key=lambda r: 0 if any(k in r["url"].lower() for k in LINK_HINTS) else 1)   # directory-like pages first
        facts, rows, outlinks, n, tried = [], [], [], 0, 0
        todo = recs[:max(0, min(CC_PER_SITE - len(already), left))]
        for r in todo:
            if time.monotonic() > deadline or out_of_time():
                break
            tried += 1
            url = r["url"]
            if not public_host(url) or not allowed(url):    # today's robots.txt decides, not the crawl's
                continue
            try:
                page = cc_page(r)
            except Exception:
                page = None
            if not page:
                continue
            n += 1
            here, kind, title = read_page(page, url)
            facts += here
            outlinks += own_site_links(page, url, host, here)
            text = re.sub(r"\s+", " ", " ".join(page_lines(page)))
            rows.append({"url": url, "status": 200, "type": "text/html", "bytes": len(page), "etag": "", "modified": "", "via": "commoncrawl",
                         "fingerprint": hashlib.sha1(text.encode("utf-8", "replace")).hexdigest(), "page_kind": kind, "named": len(here), "title": title})
        left -= n
        finished = tried >= len(todo) and (len(todo) >= len(recs) or len(already) + len(todo) >= CC_PER_SITE)   # every page tried (read or not)
        uniq = {}
        for f in facts:
            uniq.setdefault((f["name"].lower(), re.sub(r"\D", "", f["phone"])[-10:], f["address"][:20].lower()), f)
        res = site_call("/api/crawl/commoncrawl.php", tv_safe({"host": host, "crawl": crawl, "done": finished, "facts": list(uniq.values()), "log": rows,
                                                               "outlinks": list({o["url"]: o for o in outlinks}.values())[:40]}))
        log(f"Common Crawl {crawl}: {host}, {n} of {len(recs)} page(s) read, {len(uniq)} place(s), {res.get('kept', 0)} kept")


def sitemap_urls(start, host, limit=500):
    """The pages a site lists in its sitemap (decisions/0157 §8): from robots.txt's Sitemap lines, else /sitemap.xml; a
    sitemap index is followed to three of its sitemaps. Only the site's own pages, at most `limit`."""
    p = urllib.parse.urlsplit(start)
    base = f"{p.scheme}://{p.netloc}"
    allowed(base + "/")                                  # reads robots.txt into the cache
    rp = robots_cache.get(base)
    maps = []
    try:
        maps = list(rp.site_maps() or []) if rp else []
    except Exception:
        maps = []
    maps = maps[:3] or [base + "/sitemap.xml"]
    out, seen_maps = [], set()
    while maps and len(out) < limit and len(seen_maps) < 4 and not out_of_time():
        m = maps.pop(0)
        if m in seen_maps or m.endswith(".gz"):
            continue
        seen_maps.add(m)
        r = fetch_page_meta(m, xml=True)
        body = r.get("body") or ""
        locs = re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", body)
        if "<sitemapindex" in body:
            maps += [html.unescape(x) for x in locs[:3]]
            continue
        for u in locs:
            u = html.unescape(u)
            if host_of(u) == host and not re.search(r"\.(jpe?g|png|gif|zip|docx?|mp4|mp3)$", u, re.I):
                out.append(u)
                if len(out) >= limit:
                    break
    return out


GENERIC_LINK_RE = re.compile(r"(?i)\s*(visit\s+(our\s+|their\s+|the\s+)?(web\s?site|site|page|homepage)|(view\s+)?web\s?site|homepage|home\s+page|more\s+info(rmation)?|learn\s+more|www\.\S+|https?://\S+|link)\s*[›»>→]?\s*")


def own_site_links(page, url, host, here):
    """Links on a page to other sites (decisions/0157 §8). One whose words name a place on the page is that place's own
    website (filled in on it); the rest go to the register as sites we may read. [{url, text, from}]"""
    out, hosts = [], set()
    low = page.lower()
    for m in re.finditer(r'(?is)<a\b[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)</a>', page):
        href, label = m.group(1), m.group(2)
        full = urllib.parse.urljoin(url, href.strip())
        h = host_of(full)
        if not full.startswith("http") or not h or h == host or base_domain(h) == base_domain(host) or not public_host(full):
            continue
        text = re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", label))).strip()[:120]
        mine = None
        for f in here:
            a, b = words(f["name"]), words(text)
            if a and b and len(a & b) / len(a) >= 0.6 and len(a & b) / len(b) >= 0.6:
                mine = f; break
        # "Visit website", "Website", or the address itself: the link belongs to the place named just before it on the page
        # (review §2: these landed on the register as sites to read, and the business never got its website)
        if mine is None and (GENERIC_LINK_RE.fullmatch(text) or text.lower().rstrip("/") in (full.lower().rstrip("/"), h)):
            best = None
            for f in here:
                at = low.rfind((f.get("name") or "").lower()[:60], 0, m.start()) if f.get("name") else -1
                if at >= 0 and m.start() - at <= 3000 and (best is None or at > best[0]):
                    best = (at, f)
            mine = best[1] if best else None
        if mine is not None:
            mine.setdefault("website", "")
            if not mine["website"]:
                mine["website"] = full[:500]
            continue
        if h in hosts:
            continue
        hosts.add(h)
        out.append({"url": full[:500], "text": text, "from": url})
    return out


def read_source(deadline):
    """One of our "Read regularly" sources, read politely (decisions/0045, 0157 §1-§2). The site sends what the page log
    knows of its pages (entity tag, last-changed date, links); a page that hasn't changed answers 304 and keeps its facts,
    and its links (kept in the log) still lead on. Every page fetched goes back to the log, whatever it named."""
    d = site_call("/api/crawl/sources.php")
    if d.get("describe"):
        try:
            describe_sites(d["describe"], min(deadline, time.monotonic() + 20))
        except Exception as e:
            log(f"describe sites: {e!r}")
    for sc in (d.get("scans") or [])[:3]:              # scanned PDFs AWS Textract read on the site (decisions/0157 §7.2)
        try:
            read_scan(sc)
        except Exception as e:
            log(f"scan {str(sc.get('url'))[:80]}: {e!r}"[:200])
    src = d.get("source")
    if not src:
        return
    start, host = src["home_url"], host_of(src["home_url"])
    known = src.get("pages") or {}                       # url -> {etag, modified, links}
    extract_left = int(d.get("extract") or 0)            # the second tier's pages this run (decisions/0157 §1), from the site's allowance
    extracted = 0
    queue, seen, facts, pages, log_rows, unchanged, pdfs, outlinks = [start], set(), [], 0, [], [], 0, []
    # its sitemap too, and pages not read yet before ones read already (decisions/0157 §8): each read covers more of the site
    try:
        queue += [u for u in sitemap_urls(start, host) if u not in known]
    except Exception as e:
        log(f"sitemap {host}: {e!r}"[:200])
    queue = [start] + sorted(dict.fromkeys(queue[1:]), key=lambda u: 0 if any(h in u.lower() for h in LINK_HINTS) else 1)
    while queue and pages < SOURCE_PAGES and time.monotonic() < deadline and not out_of_time():
        url = queue.pop(0)
        if url in seen:
            continue
        seen.add(url)
        k = known.get(url) or {}
        if k and url != start and k.get("due") is False:   # read lately and not due: its places kept, its links still lead on
            unchanged.append(url)
            queue += [l for l in (k.get("links") or []) if l not in seen]
            continue
        is_pdf = bool(re.search(r"\.pdf($|\?)", url, re.I))
        if is_pdf and pdfs >= SOURCE_PDFS:
            continue
        r = fetch_page_meta(url, k.get("etag", ""), k.get("modified", ""), pdf=True)
        if is_pdf or "pdf" in (r.get("type") or "").lower():
            pdfs += 1
        elif r.get("why") in ("error", "empty") and extracted < extract_left:
            body = tavily_extract(url)
            if body:
                extracted += 1
                r = {"body": body, "status": 200, "type": "text/html", "bytes": len(body), "etag": "", "modified": "", "why": "", "via": "tavily"}
        if not r["status"]:
            continue                                    # not fetched (a budget, robots.txt): nothing to log
        pages += 1
        row = {"url": url, "status": r["status"], "type": r["type"], "bytes": r["bytes"], "etag": r["etag"], "modified": r["modified"], "via": r.get("via", "")}
        if r["status"] == 304:
            unchanged.append(url)
            links = list(k.get("links") or [])
            row["links"] = links
        elif r["body"]:
            page = r["body"]
            here, kind, title = read_page(page, url)
            facts += here
            outlinks += own_site_links(page, url, host, here)
            links = []
            for href in re.findall(r'(?i)href=["\']([^"\'#]+)', page):
                full = urllib.parse.urljoin(url, href)
                if host_of(full) == host and full.startswith("http") and not re.search(r"\.(jpe?g|png|gif|zip|docx?)$", full, re.I):
                    links.append(full)
            links.sort(key=lambda u: 0 if any(h in u.lower() for h in LINK_HINTS) else 1)   # directory-like pages first
            links = list(dict.fromkeys(links))[:60]
            text = re.sub(r"\s+", " ", " ".join(page_lines(page)))
            row.update(fingerprint=hashlib.sha1(text.encode("utf-8", "replace")).hexdigest(), page_kind=kind, named=len(here),
                       links=links, title=title)
        else:
            links = []
            if r.get("scan"):
                row["page_kind"] = "scan"                 # a PDF of pictures: the second tier reads it (decisions/0157 §7)
        log_rows.append(row)
        new = [l for l in links if l not in seen]
        queue += [l for l in new if l not in known] + [l for l in new if l in known]   # pages not read yet first
    uniq = {}
    for f in facts:
        uniq.setdefault((f["name"].lower(), re.sub(r"\D", "", f["phone"])[-10:], f["address"][:20].lower()), f)
    res = site_call("/api/crawl/sources.php", tv_safe({"host": host, "pages": pages, "facts": list(uniq.values()),
                                                      "log": log_rows, "unchanged": unchanged, "extracted": extracted,
                                                      "outlinks": list({o["url"]: o for o in outlinks}.values())[:40]}))
    log(f"read source {host}: {pages} page(s), {pdfs} PDF(s), {extracted} extracted, {len(unchanged)} unchanged, {len(uniq)} place(s) found, "
        f"{res.get('kept', 0)} kept, {len(outlinks)} link(s) to other sites, {res.get('new_sites', 0)} new")


def read_scan(sc):
    """A scanned PDF's text, as Textract read it on the site: read like any page, its places sent back for that page only."""
    url, text = sc.get("url") or "", sc.get("text") or ""
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    if not url or not lines:
        return
    page = lines_page(lines, urllib.parse.unquote(url.rsplit("/", 1)[-1]).rsplit(".", 1)[0].replace("-", " ").replace("_", " "))
    facts = text_facts(page, url)
    kind = page_kind(page, url, facts, page_title(page))
    pt = page_town(page)
    for f in facts:
        f["page_kind"], f["title"] = kind, page_title(page)
        if pt[0]:
            f.setdefault("page_town", pt[0]); f.setdefault("page_state", pt[1])
    res = site_call("/api/crawl/sources.php", tv_safe({"scan_page": url, "host": sc.get("host") or host_of(url), "facts": facts}))
    log(f"scan {host_of(url)}: {len(facts)} place(s) read, {res.get('kept', 0)} kept")


def describe_sites(todo, deadline):
    """What sites waiting for a call say they are (decisions/0157 §7): each home page's title, its description line, its
    name and its schema.org types, so the register can decide on its own. One polite fetch each."""
    about = []
    for t in todo[:3]:
        if time.monotonic() > deadline or out_of_time():
            break
        page = fetch_page(t.get("home_url") or "")
        if not page:
            about.append({"host": t.get("host"), "title": "", "description": ""})   # tried: not asked again
            continue
        meta = lambda key: (re.search(r'(?is)<meta[^>]+(?:name|property)=["\']' + key + r'["\'][^>]*content=["\']([^"\']*)', page) or [None, ""])[1]
        types = []
        for block in re.findall(r'(?is)<script[^>]+application/ld\+json[^>]*>(.*?)</script>', page):
            try:
                data = json.loads(block.strip())
            except Exception:
                continue
            for d in (data if isinstance(data, list) else data.get("@graph", [data]) if isinstance(data, dict) else []):
                if isinstance(d, dict):
                    types.append(ld_type(d))
        entry = {"host": t.get("host"), "title": page_title(page), "site_name": html.unescape(meta("og:site_name"))[:190],
                 "description": html.unescape(meta("description") or meta("og:description"))[:400], "types": [x for x in types if x][:5]}
        # its terms page, when the home page links one: the second tier reads it (decisions/0157 §7.1)
        terms = None
        for href, label in re.findall(r'(?is)<a[^>]+href=["\']([^"\'#]+)["\'][^>]*>(.*?)</a>', page):
            text = re.sub(r"<[^>]+>", " ", label).strip().lower()
            if re.search(r"\b(terms( of (use|service))?|terms and conditions|legal|copyright|disclaimer|site policies|website policies)\b", text) \
                    or re.search(r"(terms|legal|copyright|disclaimer|policies)", href.lower()):
                full = urllib.parse.urljoin(t.get("home_url") or "", href)
                if host_of(full) == host_of(t.get("home_url") or ""):
                    terms = full
                    break
        if terms and time.monotonic() < deadline and not out_of_time():
            tp = fetch_page(terms)
            if tp:
                entry["terms_url"] = terms
                # wrapped whole: legal text can look like code to Bluehost's firewall (Response::unwrap() reads it)
                entry["terms"] = "tvb64:" + __import__("base64").b64encode(re.sub(r"\s+", " ", " ".join(page_lines(tp)))[:12000].encode()).decode()
        about.append(entry)
    if about:
        res = site_call("/api/crawl/sources.php", tv_safe({"about": about}))
        log(f"described {len(about)} site(s) waiting for a call; {res.get('judged', 0)} judged")


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
    what's found goes to Listing Intake, held for a person (the place may be outside our areas). A new kind of place
    search added (decisions/0067) is looked for in every town we cover by its own words: OpenStreetMap names, and a web
    search when there's a key; those are our towns, so Listing Intake's usual checks decide."""
    d = site_call(f"/api/crawl/targets.php?limit={TARGETS_PER_RUN}")
    for job in d.get("targets", []):
        if time.monotonic() > deadline or out_of_time():
            log("targets: out of time, the rest go back when their lease ends")
            break
        tid = job["target_id"]
        if time_left(deadline) < OVERPASS_MIN + 10:
            log("targets: out of time, the rest go back when their lease ends")
            break
        try:
            elements = overpass(job, deadline)
            seen, cands = set(), []
            for el in elements:
                if len(cands) >= MAX_PER_JOB or out_of_time():
                    break
                c = candidate(el, job)
                if c and (c["name"].lower(), c["where"]["zip"]) not in seen:
                    seen.add((c["name"].lower(), c["where"]["zip"]))
                    cands.append(c)
            web = web_candidates(job, seen) if job["looking_for"].get("search") else []   # a new kind: also a web search (decisions/0067)
            cands += web
            res = site_call("/api/crawl/targets.php", {"target_id": tid, "candidates": cands, "pages": fetches,
                                                       "log": [f"osm elements: {len(elements)}", f"web: {len(web)}", f"candidates: {len(cands)}"]})
            log(f"target {tid} {job['looking_for']['group']} in {job['place']['name']}: {len(elements)} open-data, {len(cands)} sent -> {res.get('staged', 0)} to review")
        except OutOfTime as e:                     # slow servers: back with its lease, not reported as an error
            log(f"target {tid}: handed back, {e}"[:300])
            break
        except Exception as e:
            log(f"target {tid}: {e!r}"[:300])
            try:
                site_call("/api/crawl/targets.php", {"target_id": tid, "candidates": [], "error": repr(e)[:300]})
            except Exception:
                pass


def web_candidates(job, seen):
    """A new kind's target (decisions/0067): a web search for it in the town. A result becomes a candidate only when it
    is a business's own site (not a directory, a review site or social media), names one of the place's towns, and
    carries the kind's words; its name, phone and address come from that site (its structured data first)."""
    out = []
    if not SEARCH_ON:
        return out
    look = job["looking_for"]
    kws = [w.lower() for w in look.get("words", []) if len(w) >= 4]
    stems = {re.sub(r"(es|s)$", "", w) for w in kws}
    towns = [t["town"].lower() for t in job["place"].get("towns", [])]
    zips = job["place"].get("zips", [])
    for q in look.get("search", [])[:1]:                # one search per target per run: searches are metered
        for r in search(q):
            if len(out) >= MAX_PER_JOB or out_of_time():
                break
            url = r.get("url", "")
            host = host_of(url)
            if not url.startswith("http") or evidence_only(host) or base_domain(host) in DIRECTORY_HOSTS or host in DIRECTORY_HOSTS:
                continue
            if host.endswith(".gov") or ".gov." in host or host.endswith(".edu"):
                continue                                   # an agency or a school's page about it, not the business
            page = fetch_page(url)
            if not page:
                continue
            text = page_text(page).lower()
            if not any(t in text for t in towns) or not any(st in text for st in stems):
                continue
            facts = jsonld_facts(page, url)
            f = facts[0] if facts else {}
            title = re.search(r"(?is)<title[^>]*>(.*?)</title>", page)
            name = (f.get("name") or (re.split(r"\s+[|\-–—:]\s+", html.unescape(title.group(1)).strip())[0] if title else "")).strip()
            if not name or len(name) > 120:
                continue
            zp = (f.get("zip") or "")[:5]
            if zp not in zips:
                zp = next((z for z in re.findall(r"\b(\d{5})\b", text) if z in zips), "")
            if not zp:
                continue                                   # can't be placed in this town
            if (name.lower(), zp) in seen:
                continue
            seen.add((name.lower(), zp))
            phones = phones_on(page)
            phone = f.get("phone") or (fmt_phone(next(iter(phones))) if len(phones) == 1 else "")
            street = f.get("address") or ""
            city = next((t["town"] for t in job["place"]["towns"] if t["town"].lower() in text), job["place"]["towns"][0]["town"])
            c = {
                "name": name[:255],
                "source": {"start_url": url, "site": host, "crawled_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())},
                "where": {"address": street, "city": city, "state": job["place"]["towns"][0]["state"], "zip": zp},
                "what": {"offerings": list(look.get("offerings") or [])[:3], "description": meta_description(page)},
                "when": {"hours": hours_from(page)},
                "actions": {"phone": phone, "website": url},
                "sources": {"name": [url], "address": [url] if street else [], "phone": [url] if phone else []},
                "corroboration": [url],
            }
            c["confidence"] = min(100, 20 + (25 if street else 0) + (15 if phone else 0) + (10 if c["when"]["hours"] else 0) + (10 if f else 0) + (10 if c["what"]["description"] else 0))
            out.append(c)
    return out


def geocode(deadline):
    """Map points: the site sends a batch of street addresses to the U.S. Census geocoder and places what it finds
    (api/crawl/geocode.php, api/lib/Geocoder.php), so listings don't stack on their ZIP's middle waiting for someone to
    press Find points. Only missing or ZIP-middle points change; confidential listings are never geocoded."""
    global task_got
    for _ in range(GEOCODE_PER_RUN):
        if time.monotonic() > deadline or out_of_time():
            break
        r = site_call("/api/crawl/geocode.php", {}, timeout=250)    # a batch of 500 can take a few minutes at the Census
        task_got += int(r.get("sent", 0) or 0)
        if r.get("paused"):
            log("map points: queue paused on the site")
            break
        log(f"map points: sent {r.get('sent', 0)}, placed {r.get('found', 0)}, not found {r.get('not_found', 0)}, "
            f"placed another way {r.get('skipped', 0)}, left {r.get('left', 0)}")
        if not r.get("left") or (r.get("sent", 0) + r.get("skipped", 0) + r.get("private", 0)) == 0:
            break


def fetch_json(url, timeout=30):
    """A JSON API on the open web, through the same checks as a page (public address on every hop). None on failure."""
    if os.environ.get("LOC_FIXTURE"):
        with open(os.environ["LOC_FIXTURE"]) as f:
            return json.load(f)
    if not public_host(url):
        return None
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
        with SAFE_OPENER.open(req, timeout=timeout) as r:
            return json.loads(r.read(3_000_000).decode("utf-8", "replace"))
    except Exception:
        return None


def heritage(deadline):
    """The Library of Congress for each guide's place (decisions/0133 Phase 1): the catalog records of its items (title,
    date, description, subjects, format, rights) and their links, never the items themselves. The site reads them into
    the heritage catalog."""
    d = site_call(f"/api/crawl/heritage.php?limit={HERITAGE_PER_RUN}")
    for pl in d.get("places", []):
        if time.monotonic() > deadline or out_of_time():
            break
        q = urllib.parse.urlencode({"q": pl["query"], "fo": "json", "c": "30", "at": "results"})
        data = fetch_json(f"{LOC_SEARCH}?{q}")
        time.sleep(0 if os.environ.get("LOC_FIXTURE") else LOC_DELAY)
        if data is None:
            log(f"heritage {pl['name']}: the Library of Congress didn't answer")
            continue
        flat = lambda v: "; ".join(str(x) for x in v) if isinstance(v, list) else str(v or "")
        items = []
        for it in (data.get("results") or [])[:30]:
            url = it.get("url") or it.get("id") or ""
            if not isinstance(url, str) or "loc.gov/" not in url:
                continue
            about = flat(it.get("description"))[:600]
            if it.get("subject"):
                about += (" Subjects: " if about else "Subjects: ") + flat(it.get("subject"))[:200]
            items.append({"url": url.replace("http://", "https://"), "title": flat(it.get("title"))[:500], "date": flat(it.get("date"))[:40], "about": about,
                          "rights": flat(it.get("rights") or it.get("rights_advisory"))[:500], "format": flat(it.get("original_format"))})
        news = loc_news(pl) if LOC_NEWS_PER_PLACE > 0 and time.monotonic() < deadline else []
        res = site_call("/api/crawl/heritage.php", {"key": pl["key"], "items": items + news})
        log(f"heritage {pl['name']}: {len(items)} Library of Congress records, {len(news)} newspaper pages, {res.get('kept', 0)} kept")


def loc_news(pl):
    """Free sources first (decisions/0133): historic newspaper pages that name the place, from the Library of Congress's
    Chronicling America (public domain), in its state. Each page's record and the text the API gives with it (its OCR),
    which the site cuts to the passages about the past before the cheapest model reads them."""
    params = {"q": f'"{pl["name"]}"', "fo": "json", "c": str(LOC_NEWS_PER_PLACE), "at": "results"}
    if pl.get("state"):
        params["fa"] = "location_state:" + pl["state"].lower()
    data = fetch_json(f"{LOC_NEWS}?{urllib.parse.urlencode(params)}")
    time.sleep(0 if os.environ.get("LOC_FIXTURE") else LOC_DELAY)
    if data is None:
        log(f"heritage {pl['name']}: Chronicling America didn't answer")
        return []
    flat = lambda v: " ".join(str(x) for x in v) if isinstance(v, list) else str(v or "")
    out = []
    for it in (data.get("results") or [])[:LOC_NEWS_PER_PLACE]:
        url = it.get("url") or it.get("id") or ""
        if not isinstance(url, str) or "loc.gov/" not in url:
            continue
        text = flat(it.get("description"))
        if not text.strip():
            continue                                   # a page with no text to read adds nothing
        paper = flat(it.get("partof_title") or it.get("partof"))[:200]
        out.append({"url": url.replace("http://", "https://"), "title": (flat(it.get("title")) + (f" ({paper})" if paper else ""))[:500],
                    "date": flat(it.get("date"))[:40], "about": re.sub(r"\s+", " ", text)[:6000], "rights": flat(it.get("rights") or it.get("rights_advisory"))[:500],
                    "format": "newspaper", "kind": "news"})
    return out


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


DEAD_RE = re.compile(r"suspendedpage|/cgi-sys/|parkingcrew|sedoparking|bodis\.com|domain-?for-?sale|hugedomains|dan\.com", re.I)
DEAD_TEXT_RE = re.compile(r"\b(account|site|website|domain|page)\s+(has\s+been\s+|is\s+(currently\s+)?)?(suspended|disabled|expired|deactivated)\b"
                          r"|this domain (is|may be) for sale|buy this domain|domain (is )?parked|parked free|default (web )?page|future home of", re.I)
CLOSED_HINT_RE = re.compile(r"permanently\s+closed|closed\s+permanently|closed\s+for\s+good|closed\s+(our|the)\s+(doors|business)|"
                            r"ceased\s+(operations|trading)|no\s+longer\s+in\s+business|out\s+of\s+business|closing\s+(our|its)\s+doors", re.I)
MOVED_HINT_RE = re.compile(r"\b(we|we\s+have|we've|we\s+are|we're|has|have)\s+(now\s+)?(moved|relocated)\b|\b(our|the)\s+new\s+(location|address|home|shop|store|office)\s+is\b|"
                           r"\b(now|newly)\s+located\s+at\b|\bnew\s+location\s*:|\bfind\s+us\s+at\s+our\s+new\b|\bmoved\s+to\s+a\s+new\s+(location|home|space|building)\b", re.I)


def dead_site(page):
    """A parked, suspended or empty page (the site's checks in app/lib/Dossier.php::dead() match this)."""
    if DEAD_RE.search(page):
        return True
    text = " ".join(page_lines(page))
    return len(text) < 150 or bool(DEAD_TEXT_RE.search(text))


def split_city(address):
    m = re.search(r"^(.*?),?\s*([A-Za-z .'-]+),\s*([A-Z]{2})\s+(\d{5})", address)
    return (m.group(1).strip(" ,"), m.group(2).strip(), m.group(3), m.group(4)) if m else (address, "", "", "")


def refresh_listings(deadline):
    d = site_call(f"/api/crawl/refresh.php?limit={REFRESH_PER_RUN}")
    for lst in d.get("listings", []):
        if time.monotonic() > deadline or out_of_time():
            break
        site, host = lst["website"], host_of(lst["website"])
        if not os.environ.get("WEB_FIXTURE") and (fetches >= min(MAX_FETCHES, FETCH_CAP) or out_of_time() or not allowed(site)):
            how = "skipped" if fetches >= min(MAX_FETCHES, FETCH_CAP) or out_of_time() else "blocked"   # this run's budget / its robots.txt
            site_call("/api/crawl/refresh.php", {"entity_id": lst["entity_id"], "website": site, "found": {}, "locations": [], "site": {"status": how}})
            continue                               # not read: never counts against the site
        home = fetch_page(site)
        if not home or dead_site(home):            # the site decides: tried again in a few days, then off the listing or to Review
            how = "dead" if home else "unreachable"
            res = site_call("/api/crawl/refresh.php", {"entity_id": lst["entity_id"], "website": site, "found": {}, "locations": [], "site": {"status": how}})
            log(f"refresh {lst['name']}: website {'shows a parked or suspended page' if home else 'did not load'}: {res.get('site', '')}")
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
        # the closed check (decisions/0133): lines where the business says it closed; the site keeps only one about itself
        closed = [l for pg in pages.values() for l in page_lines(pg) if CLOSED_HINT_RE.search(l) and len(l) <= 400][:3]
        # and the moved check: lines where it says it moved (the site sends it to Review with the new street, never changes it)
        moved = [l for pg in pages.values() for l in page_lines(pg) if MOVED_HINT_RE.search(l) and len(l) <= 400][:3]
        res = site_call("/api/crawl/refresh.php", {"entity_id": lst["entity_id"], "website": site, "found": found, "locations": locations[:25],
                                                   "site": {"status": "ok", "closed": " ".join(closed), "moved": " ".join(moved)}})
        log(f"refresh {lst['name']}: filled {', '.join(res.get('filled', [])) or 'nothing new'}; "
            f"{len(locations)} other location(s) -> {res.get('auto_imported', 0)} published, {res.get('staged', 0)} to review; site: {res.get('site', 'ok')}")


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
    if PLACES_PER_RUN > 0:
        try:
            places_check(time.monotonic() + PLACES_TIME)
        except Exception as e:                     # checking against Google never stops the crawl jobs
            log(f"Google check stopped: {e!r}"[:300])
    if LEARN_PER_RUN > 0:
        try:
            learn(time.monotonic() + LEARN_TIME)
        except Exception as e:                     # learning never stops the crawl jobs
            log(f"learning stopped: {e!r}"[:300])
    if HERITAGE_PER_RUN > 0:
        try:
            heritage(time.monotonic() + HERITAGE_TIME)
        except Exception as e:                     # the heritage reading never stops the crawl jobs
            log(f"heritage stopped: {e!r}"[:300])
    if GUIDES_PER_RUN > 0:
        try:
            guides(time.monotonic() + GUIDES_TIME)
        except Exception as e:                     # building guides never stops the crawl jobs
            log(f"guides stopped: {e!r}"[:300])
    if CC_TIME > 0:
        try:
            common_crawl(time.monotonic() + CC_TIME)
        except Exception as e:                     # Common Crawl never stops the rest
            log(f"Common Crawl stopped: {e!r}"[:300])
    if SOURCE_TIME > 0:
        try:
            read_source(time.monotonic() + SOURCE_TIME)
        except Exception as e:                     # reading a source never stops the crawl jobs
            log(f"reading a source stopped: {e!r}"[:300])
    if coverage(None) == "paused":
        log("Queue paused on the site (CRAWL_QUEUE=off).")
    return 0


def coverage(deadline):
    """The crawl jobs (W1: places of a kind in a town or cluster, decisions/0044, 0149): OpenStreetMap and Wikidata, then each
    place's own website; what's found goes to the intake. Returns "paused" when the site's queue is paused."""
    d = site_call(f"/api/crawl/jobs.php?limit={JOBS_PER_RUN}")
    if d.get("paused"):
        return "paused"
    jobs = d.get("jobs", [])
    log(f"{len(jobs)} job(s)")
    for job in jobs:
        jid = job["job_id"]
        if out_of_time() or time_left(deadline) < OVERPASS_MIN + 10:
            log(f"job {jid}: out of time, left for its lease to return it")
            break
        try:
            elements = overpass(job, deadline) + wikidata(job, deadline)   # Tier 1: OpenStreetMap, and Wikidata for official places
            seen, cands, partial = set(), [], False
            for el in elements:
                if len(cands) >= MAX_PER_JOB:
                    break
                if out_of_time():
                    partial = True   # the run's time ran out mid-job (2026-11-10: Dana Point's hotels were reported done, 0 found)
                    break
                c = candidate(el, job)
                if c and (c["name"].lower(), c["where"]["zip"]) not in seen:
                    seen.add((c["name"].lower(), c["where"]["zip"]))
                    cands.append(c)
            if partial and not cands:
                log(f"job {jid}: out of time before any of its {len(elements)} places were read; left for its lease to return it")
                break
            res = site_call("/api/crawl/results.php", {"job_id": jid, "candidates": cands, "pages": fetches,
                                                       "log": [f"osm elements: {len(elements)}", f"candidates: {len(cands)}"] + (["partial"] if partial else [])})
            log(f"job {jid} {job['looking_for']['group']} in {job['place']['name']}: {len(elements)} open-data, "
                f"{len(cands)} sent -> {res.get('auto_imported', 0)} published, {res.get('staged', 0)} to review")
        except OutOfTime as e:                     # slow servers, not a failure: the job goes back with its tries as they were
            log(f"job {jid}: handed back, {e}"[:400])
            break
        except Exception as e:                     # report and let the queue retry it
            log(f"job {jid}: {e!r}")
            try:
                site_call("/api/crawl/results.php", {"job_id": jid, "error": repr(e)[:400]})
            except Exception:
                pass
        time.sleep(3)                              # be gentle with Overpass between jobs
    return ""


def places_check(deadline):
    """Checks a few listings against Google (decisions/0085): one Text Search each, asking only for the place's ID, name,
    address and whether Google lists it as closed (the fewer fields, the cheaper the call). The site picks the listings
    (never one whose address is kept private), decides whether Google's place is the same one, keeps only Google's place
    ID, and asks a person about any Google lists as permanently closed. The site paces the month: never past 90% of
    GOOGLE_PLACES_MONTHLY, spread over the days; Google's own daily limits sit above that."""
    global places_used
    if not GOOGLE_PLACES_KEY and not os.environ.get("PLACES_FIXTURE"):
        return
    try:
        a = site_call("/api/crawl/usage.php", {"ask": 1, "service": "places", "engine": "google", "monthly": PLACES_MONTHLY}, timeout=20)
    except Exception as e:
        log(f"Google check off this run: the site couldn't say how many are left ({e!r})"[:200])
        return
    allow = min(PLACES_PER_RUN, max(0, int(a.get("allow", 0))))
    if allow <= 0:
        return
    batch = site_call(f"/api/crawl/places.php?limit={allow}").get("listings", [])
    if not batch:
        return
    results = []
    for L in batch:
        if time.monotonic() > deadline or places_used >= allow:
            break
        q = ", ".join(x for x in [L.get("name", ""), L.get("address", ""), L.get("city", ""), L.get("state", "")] if x)
        body = {"textQuery": q, "pageSize": 1}
        if L.get("lat") is not None and L.get("lon") is not None:
            body["locationBias"] = {"circle": {"center": {"latitude": L["lat"], "longitude": L["lon"]}, "radius": 5000.0}}
        try:
            if os.environ.get("PLACES_FIXTURE"):
                with open(os.environ["PLACES_FIXTURE"]) as f:
                    data = json.load(f).get(L.get("name", ""), {})
            else:
                req = urllib.request.Request("https://places.googleapis.com/v1/places:searchText", method="POST",
                                             data=json.dumps(body).encode(), headers={
                                                 "Content-Type": "application/json", "X-Goog-Api-Key": GOOGLE_PLACES_KEY,
                                                 "X-Goog-FieldMask": "places.id,places.displayName,places.formattedAddress,places.businessStatus"})
                with urllib.request.urlopen(req, timeout=20) as r:
                    data = json.loads(r.read().decode("utf-8"))
            places_used += 1
        except urllib.error.HTTPError as e:
            places_used += 1                                   # a refused call may still count with Google
            log(f"  Google check refused ({e.code}): {e.read()[:160]!r}")
            if e.code in (403, 429):                           # key not allowed, or Google's daily limit reached: stop for this run
                break
            continue
        except Exception as e:
            log(f"  Google check failed: {e!r}"[:200])
            continue
        p = (data.get("places") or [{}])[0]
        results.append({"id": L["id"], "found": bool(p.get("id")), "place_id": p.get("id", ""),
                        "name": (p.get("displayName") or {}).get("text", ""), "address": p.get("formattedAddress", ""),
                        "status": p.get("businessStatus", "")})
    if results:
        r = site_call("/api/crawl/places.php", {"results": results})
        log(f"Google check: {len(results)} listing(s): {r.get('matched', 0)} found, {r.get('closed', 0)} Google lists as closed")


# ---------------------------------------------------------------------------------------------------
# The crawler as one service (decisions/0160 §2, contract 2): the site plans the run, the worker works each capture
# task inside its share and reports what each did. The fixed order above (run()) is kept only for a site without it.
# ---------------------------------------------------------------------------------------------------

# task -> (what does it, the per-run setting it's limited by (None: none), why it's off here ("" when it's on))
HANDLERS = {
    "coverage": (coverage, "JOBS_PER_RUN", lambda: "" if JOBS_PER_RUN > 0 else "JOBS_PER_RUN is 0"),
    "second_look": (second_look, "VERIFY_PER_RUN", lambda: "" if VERIFY_PER_RUN > 0 else "VERIFY_PER_RUN is 0"),
    "targets": (targets, "TARGETS_PER_RUN", lambda: "" if TARGETS_PER_RUN > 0 else "TARGETS_PER_RUN is 0"),
    "refresh": (refresh_listings, "REFRESH_PER_RUN", lambda: "" if REFRESH_PER_RUN > 0 else "REFRESH_PER_RUN is 0"),
    "identity": (identity_jobs, "IDENTITY_PER_RUN", lambda: "" if IDENTITY_PER_RUN > 0 else "IDENTITY_PER_RUN is 0"),
    "geocode": (geocode, "GEOCODE_PER_RUN", lambda: "" if GEOCODE_PER_RUN > 0 else "GEOCODE_PER_RUN is 0"),
    "places": (places_check, "PLACES_PER_RUN", lambda: "" if (GOOGLE_PLACES_KEY or os.environ.get("PLACES_FIXTURE")) and PLACES_PER_RUN > 0 else "no Google Places key on Railway (GOOGLE_PLACES_KEY)"),
    "source": (read_source, None, lambda: "" if SOURCE_TIME > 0 else "SOURCE_TIME is 0"),
    "commoncrawl": (common_crawl, None, lambda: "" if CC_TIME > 0 else "CC_TIME is 0"),
    "heritage": (heritage, "HERITAGE_PER_RUN", lambda: "" if HERITAGE_PER_RUN > 0 else "HERITAGE_PER_RUN is 0"),
    # without a search key a how-to guide would be marked "no official page found": off, not a false answer
    "howto": (guides, "GUIDES_PER_RUN", lambda: "" if GUIDES_PER_RUN > 0 and SEARCH_ON else "no web search key on Railway (TAVILY_API_KEY or BRAVE_API_KEY)"),
    "learn": (learn, "LEARN_PER_RUN", lambda: "" if LEARN_PER_RUN > 0 else "LEARN_PER_RUN is 0"),
    "npi_bulk": (lambda _d: npi_bulk(), None, lambda: "" if NPI_BULK else "NPI_BULK is off"),
    "irs_bulk": (lambda _d: irs_bulk(), None, lambda: "" if IRS_BULK else "IRS_BULK is off"),
    "rec_bulk": (lambda _d: rec_bulk(), None, lambda: "" if RIDB_API_KEY or os.environ.get("RIDB_FIXTURE") else "no Recreation.gov key on Railway (RIDB_API_KEY)"),
    "landmarks_bulk": (lambda _d: landmarks_bulk(), None, lambda: "" if LANDMARKS else "LANDMARKS is off"),
}
MIN_TASK = 15          # seconds: a task with less than this left isn't started (it's "not reached", never a failure)


def work_task(p, item, seconds):
    """Works one capture task for up to `seconds`, inside its fetch and paid-call share; adds to `item` what it did."""
    global TASK_END, FETCH_CAP, SEARCH_CAP, task_got
    fn, setting, off = HANDLERS[p["task"]]
    why_off = off()
    if why_off:
        item.update(outcome="off", why=why_off)
        return
    t0, f0, s0, pl0, task_got = time.monotonic(), fetches, searches, places_used, 0
    TASK_END = t0 + seconds
    FETCH_CAP = fetches + max(0, int(p.get("fetches", 0))) + item.pop("_carry_f", 0)
    SEARCH_CAP = searches + max(0, int(p.get("paid", 0)))
    saved = None
    if setting:                                    # the setting on Railway is the most it may do; the plan may ask for fewer
        saved = globals()[setting]
        globals()[setting] = max(1, min(saved, int(p.get("per_run", saved))))
    try:
        r = fn(TASK_END)
        got = task_got + (1 if r is True else 0)
        if r == "paused":
            item.update(outcome="idle", why="the site's queue is paused")
        elif got > 0 or item.get("outcome") == "done":
            item["outcome"] = "done"
        else:
            item.setdefault("outcome", "idle")
        item["counts"]["items"] = item["counts"].get("items", 0) + got
        item["_more"] = bool(setting) and got >= globals()[setting]    # took a full batch: there's likely more
    except Exception as e:
        log(f"{p['task']} stopped: {e!r}"[:300])
        item.update(outcome="failed", why=repr(e)[:400], _more=False)
    finally:
        if setting:
            globals()[setting] = saved
        TASK_END, SEARCH_CAP = None, None
        item["seconds"] = item.get("seconds", 0) + int(time.monotonic() - t0)
        item["fetches"] = item.get("fetches", 0) + (fetches - f0)
        item["searches"] = item.get("searches", 0) + (searches - s0)
        item["places"] = item.get("places", 0) + (places_used - pl0)


def do_requests(reqs):
    """The one queue's requests leased to this run (decisions/0160 §2): done while there's time, the rest given back unchanged."""
    out = []
    for q in reqs:
        if q.get("kind") == "find_name" and time.monotonic() < (RUN_END or 0) - MIN_TASK:
            out.append(find_name(q))
            continue
        if q.get("kind") != "read_page" or time.monotonic() > (RUN_END or 0) - MIN_TASK:
            out.append({"id": q["id"], "outcome": "back", "why": "not done this run"})
            continue
        url = str((q.get("payload") or {}).get("url") or q.get("ref") or "")
        try:
            r = fetch_page_meta(url, pdf=True)
            if not r["status"]:
                out.append({"id": q["id"], "outcome": "back", "why": r.get("why") or "not fetched (robots.txt or the run's budget)"})
                continue
            facts, kind, title = read_page(r["body"], url) if r["body"] else ([], "", "")
            out.append({"id": q["id"], "outcome": "done", "result": tv_safe({"status": r["status"], "page_kind": kind, "title": title, "facts": facts[:200]})})
        except Exception as e:
            out.append({"id": q["id"], "outcome": "failed", "why": repr(e)[:300]})
    return out


def find_name(q):
    """A business looked up by its name in a town (Send the crawler, review E22): its own website by one web search (from the
    month's paced allowance), then what that site says about it. The site stages what comes back like any find."""
    p = q.get("payload") or {}
    name, town, state = str(p.get("name") or ""), str(p.get("city") or ""), str(p.get("state") or "")
    try:
        site = find_own_site(name, "", "", town, state)
        if not site:
            return {"id": q["id"], "outcome": "done", "result": {"found": False}, "why": "no website of its own found" + ("" if SEARCH_ON else " (no search key)")}
        page = fetch_page(site)
        if not page or not names_place(page, name):
            return {"id": q["id"], "outcome": "done", "result": {"found": False, "site": site}, "why": "its website didn't load or doesn't name it"}
        facts, _, title = read_page(page, site)
        f = next((x for x in facts if names_place(x.get("name", ""), name) or names_place(name, x.get("name", ""))), None) or {}
        street, city, st, zp = split_city(f.get("address", "")) if f.get("address") else ("", town, state, "")
        phones = phones_on(page)
        phone = f.get("phone") or (fmt_phone(next(iter(phones))) if len(phones) == 1 else "")
        c = {"name": f.get("name") or name,
             "source": {"start_url": site, "site": host_of(site), "crawled_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())},
             "where": {"address": street, "city": city or town, "state": st or state, "zip": zp, "location_page": site},
             "what": {"offerings": [], "description": meta_description(page)},
             "when": {"hours": hours_from(page) or []},
             "actions": {"phone": phone, "website": site},
             "sources": {k: [site] for k, v in (("name", 1), ("address", street), ("phone", phone)) if v},
             "corroboration": [{"url": site, "kind": "own_site"}],
             "confidence": 15 + 20 + (25 if street else 0) + (15 if phone else 0) + 5}
        return {"id": q["id"], "outcome": "done", "result": tv_safe({"found": True, "site": site, "candidate": c})}
    except Exception as e:
        return {"id": q["id"], "outcome": "failed", "why": repr(e)[:300]}


def run_service():
    """One run through the service. Returns the exit code, or None when the site has no service yet (the fixed order runs)."""
    global RUN_END, FETCH_CAP
    run_key = hashlib.sha1(f"{time.time()}-{os.getpid()}".encode()).hexdigest()[:16]
    try:
        st = site_call("/api/crawl/service.php", {"start": {"run_key": run_key, "worker_v": WORKER_V, "budget": TIME_BUDGET,
                                                           "fetches": MAX_FETCHES, "have": list(HANDLERS)}}, timeout=30)
    except RuntimeError as e:
        if " said 404" in str(e):
            return None                              # an older site: the fixed order, as before
        raise
    if not st.get("ready", True) or "plan" not in st:
        return None
    if st.get("paused"):
        log(f"paused: {st.get('why', '')}")
        site_call("/api/crawl/service.php", {"report": {"run_key": run_key, "status": "ok", "why": st.get("why", "paused"), "items": []}})
        return 0
    RUN_END = started + TIME_BUDGET
    plan = st.get("plan") or []
    items, by_task = [], {}
    log(f"run {run_key}: {len(plan)} task(s): " + ", ".join(f"{p['task']} {p['seconds']}s" for p in plan))
    carry_f = 0
    for p in plan:
        if p["task"] not in HANDLERS or p.get("mode") == "bulk":
            continue
        item = {"task": p["task"], "allotted": p["seconds"], "counts": {}, "_carry_f": carry_f}
        left = RUN_END - time.monotonic()
        if left < MIN_TASK:
            item.update(outcome="not_reached", why=f"{int(max(0, left))}s left of the run")
            item.pop("_carry_f", None)
        else:
            work_task(p, item, min(p["seconds"], left))
            carry_f = max(0, FETCH_CAP - fetches)    # fetches a task didn't use go to the next
        items.append(item); by_task[p["task"]] = (p, item)
    # time left over: a second turn for tasks that took a full batch, in the plan's order (finding businesses first)
    for task, (p, item) in by_task.items():
        left = RUN_END - time.monotonic()
        if left < MIN_TASK + 15:
            break
        if item.get("_more") and item.get("outcome") == "done":
            log(f"{task}: a second turn with {int(left)}s left")
            work_task(p, item, left - 5)
    reqs = do_requests(st.get("requests") or [])
    # one bulk load a night run, after the rest (never in place of it)
    for p in plan:
        if p.get("mode") != "bulk" or p["task"] not in HANDLERS:
            continue
        item = {"task": p["task"], "allotted": p["seconds"], "counts": {}}
        room = int(os.environ.get("RUN_LIMIT", "1800")) - int(time.monotonic() - started) - 120
        if room < 300:
            item.update(outcome="not_reached", why="too little of the run's limit left for a bulk load")
        else:
            RUN_END = time.monotonic() + min(p["seconds"], room)
            work_task(p, item, min(p["seconds"], room))
        items.append(item)
    for it in items:
        for k in [k for k in it if k.startswith("_")]:
            it.pop(k)
    engine = "tavily" if TAVILY_API_KEY else ("brave" if BRAVE_API_KEY else "")
    res = site_call("/api/crawl/service.php", {"report": {"run_key": run_key, "status": "ok", "seconds": int(time.monotonic() - started),
                                                         "fetches": fetches, "searches": searches, "engine": engine,
                                                         "items": items, "requests": reqs}})
    status = res.get("status", "")
    log(f"run {run_key}: {status}: " + ", ".join(f"{i['task']} {i.get('outcome')}" for i in items))
    # a run that couldn't do its work is a failed run on Railway, not "succeeded" (the review's F27)
    return 1 if status in ("failed", "starved") else 0


def ask_search_allowance():
    """How many web searches this run may make (decisions/0084). The site keeps the month's count and paces it: never
    past 90% of SEARCH_MONTHLY, and spread over the days left, so a plan's allowance can't run out early and nothing
    is ever billed past it. If the site can't be asked, none."""
    global search_allow
    search_allow = 0
    if not (TAVILY_API_KEY or BRAVE_API_KEY) or not SITE or not TOKEN:
        return
    engine = "tavily" if TAVILY_API_KEY else "brave"
    try:
        r = site_call("/api/crawl/usage.php", {"ask": 1, "engine": engine, "monthly": SEARCH_MONTHLY}, timeout=20)
        search_allow = max(0, int(r.get("allow", 0)))
        log(f"web search: {r.get('month_used', '?')} of {SEARCH_MONTHLY} used this month; this run may make {min(search_allow, MAX_SEARCHES)}")
    except Exception as e:
        log(f"web search off this run: the site couldn't say how many are left ({e!r})"[:200])


def report_usage():
    """Tell the site which web search is on and how many searches this run made, for the admin dashboard's System
    status (it keeps the month's total). Never fails the run."""
    if not SITE or not TOKEN:
        return
    engine = "tavily" if TAVILY_API_KEY else ("brave" if BRAVE_API_KEY else "")
    try:
        site_call("/api/crawl/usage.php", {"engine": engine, "searches": searches, "fetches": fetches, "monthly": SEARCH_MONTHLY})
        if places_used:
            site_call("/api/crawl/usage.php", {"service": "places", "engine": "google", "searches": places_used, "monthly": PLACES_MONTHLY})
    except Exception as e:
        log(f"usage report skipped: {e!r}"[:200])


if __name__ == "__main__":
    # Never hang (2026-10-14): Railway skips every scheduled run while one is still going, so a single stuck read froze
    # the crawler for days. Every network wait gives up after 60 s, and the whole run ends after 30 minutes (RUN_LIMIT),
    # whatever it's doing: past any step's own budget (the monthly NPI load allows 25), never days.
    socket.setdefaulttimeout(60)
    def _stop(*_):
        log("run stopped: over its time limit; the next scheduled run carries on (the site marks this run abandoned)")
        socket.setdefaulttimeout(10)
        report_usage()                             # the searches it made still count toward the month
        sys.stdout.flush()
        os._exit(1)                                # a failed run on Railway, never "succeeded" (the review's F27)
    if hasattr(signal, "SIGALRM"):
        signal.signal(signal.SIGALRM, _stop)
        signal.alarm(int(os.environ.get("RUN_LIMIT", "1800")))
    if not SITE or not TOKEN:
        log("TRAVERSENCE_URL and CRAWLER_API_TOKEN must be set.")
        sys.exit(2)                                # a run that can't work is a failed run on Railway
    try:
        ask_search_allowance()
        code = run_service()                       # the site plans the run (decisions/0160)
        if code is None:
            log("the site has no crawler service yet: the fixed order")
            code = run()
        report_usage()
        sys.exit(code)
    except urllib.error.HTTPError as e:
        # A failed run on Railway (the review's F27): it shows as failed, never "succeeded". Railway doesn't restart a
        # failed cron run (railway.json: restartPolicyType NEVER); the next scheduled run tries again.
        log(f"site said {e.code}: {e.read()[:300]!r}")
        report_usage()                             # searches already made still count toward the month
        sys.exit(1)
    except Exception as e:                         # the site unreachable, or the service refused the run
        log(f"run stopped: {e!r}")
        report_usage()
        sys.exit(1)
