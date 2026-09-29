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
The run stops at whichever budget comes first: JOBS_PER_RUN jobs, MAX_FETCHES website fetches, or
TIME_BUDGET seconds. Unfinished jobs simply go back to the queue when their lease expires.

Settings (environment variables):
  TRAVERSENCE_URL      e.g. https://traversence.com                        (required)
  CRAWLER_API_TOKEN    same value as CRAWLER_API_TOKEN in the site's .env    (required)
  JOBS_PER_RUN         default 5 (the site hands out at most 10)
  MAX_FETCHES          website fetches per run, default 60
  TIME_BUDGET          seconds per run, default 240
  OVERPASS_URL         default https://overpass-api.de/api/interpreter
Test hooks (not for production): OVERPASS_FIXTURE=file.json, WEB_FIXTURE=file.json ({url: html}).
Standard library only.
"""
import html
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

SITE = os.environ.get("TRAVERSENCE_URL", "").rstrip("/")
TOKEN = os.environ.get("CRAWLER_API_TOKEN", "")
JOBS_PER_RUN = int(os.environ.get("JOBS_PER_RUN", "5"))
MAX_FETCHES = int(os.environ.get("MAX_FETCHES", "60"))
TIME_BUDGET = int(os.environ.get("TIME_BUDGET", "240"))
OVERPASS_URL = os.environ.get("OVERPASS_URL", "https://overpass-api.de/api/interpreter")
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

started = time.monotonic()
fetches = 0
last_fetch = 0.0
robots_cache = {}


def log(*a):
    print(time.strftime("%H:%M:%S"), *a, flush=True)


def out_of_time():
    return time.monotonic() - started > TIME_BUDGET


def site_call(path, payload=None):
    """GET or POST JSON to the Traversence site with the worker token."""
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(SITE + path, data=data, method="POST" if data else "GET", headers={
        "X-Crawler-Token": TOKEN, "Authorization": "Bearer " + TOKEN,
        "Content-Type": "application/json", "User-Agent": UA, "Accept": "application/json"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode("utf-8"))


def overpass(job):
    if os.environ.get("OVERPASS_FIXTURE"):
        with open(os.environ["OVERPASS_FIXTURE"]) as f:
            return json.load(f).get("elements", [])
    pts = [p for p in job["place"].get("zip_points", []) if p.get("lat") is not None] or \
          [{"lat": job["place"]["center"]["lat"], "lon": job["place"]["center"]["lon"]}]
    parts = []
    for q in GROUP_QUERIES.get(job["looking_for"]["group"], []):
        for p in pts:
            parts.append(f'{q}["name"](around:{ZIP_RADIUS_M},{p["lat"]},{p["lon"]});')
    query = "[out:json][timeout:60];(" + "".join(parts) + ");out center tags 80;"
    req = urllib.request.Request(OVERPASS_URL, data=urllib.parse.urlencode({"data": query}).encode(),
                                 headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=90) as r:
        return json.loads(r.read().decode("utf-8")).get("elements", [])


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
    return {w for w in re.findall(r"[a-z0-9]+", (s or "").lower()) if len(w) >= 3 and w not in {"the", "and", "inc", "llc", "center", "centre"}}


def names_place(page, name):
    """The page mentions the place: most of the name's meaningful words appear in its text."""
    text = html.unescape(re.sub(r"<[^>]+>", " ", re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", page))).lower()
    need = words(name)
    return bool(need) and sum(1 for w in need if w in text) / len(need) >= 0.75


def meta_description(page):
    m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']{20,400})', page, re.I) or \
        re.search(r'<meta[^>]+content=["\']([^"\']{20,400})["\'][^>]+name=["\']description["\']', page, re.I)
    return html.unescape(m.group(1)).strip() if m else ""


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
    osm_url = f"https://www.openstreetmap.org/{el['type']}/{el['id']}"
    kind = next((t.get(k) for k in ("amenity", "healthcare", "social_facility", "club") if t.get(k) in KIND_WORDS), None)
    street = " ".join(x for x in (t.get("addr:housenumber"), t.get("addr:street")) if x)
    website = t.get("website") or t.get("contact:website") or t.get("url") or ""
    phone = t.get("phone") or t.get("contact:phone") or ""
    c = {
        "name": name,
        "source": {"start_url": osm_url, "site": "openstreetmap.org", "crawled_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())},
        "where": {"address": street, "city": t.get("addr:city") or (towns[0]["town"] if towns else ""),
                  "state": t.get("addr:state") or (towns[0]["state"] if towns else ""), "zip": zp},
        "what": {"offerings": [KIND_WORDS[kind]] if kind else [], "description": ""},
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
    c["confidence"] = min(100, score)
    return c


def run():
    if not SITE or not TOKEN:
        log("TRAVERSENCE_URL and CRAWLER_API_TOKEN must be set.")
        return 2
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
            elements = overpass(job)
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
            log(f"job {jid} {job['looking_for']['group']} in {job['place']['name']}: {len(elements)} OSM, "
                f"{len(cands)} sent -> {res.get('auto_imported', 0)} published, {res.get('staged', 0)} to review")
        except Exception as e:                     # report and let the queue retry it
            log(f"job {jid}: {e!r}")
            try:
                site_call("/api/crawl/results.php", {"job_id": jid, "error": repr(e)[:400]})
            except Exception:
                pass
        time.sleep(3)                              # be gentle with Overpass between jobs
    return 0


if __name__ == "__main__":
    try:
        sys.exit(run())
    except urllib.error.HTTPError as e:
        log(f"site said {e.code}: {e.read()[:300]!r}")
        sys.exit(1)
