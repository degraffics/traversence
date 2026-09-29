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
  - With BRAVE_API_KEY: a web search for the place and one aimed at chamber of commerce listings. A
    result counts only if it names the place AND shows its street address or phone. Each is labelled:
    chamber of commerce, well-known directory (BBB, Yelp, Yellow Pages, Healthgrades...), government,
    the place's own website, or another website. Each website counts once.
The site decides what the references are worth and re-runs the guardrails.
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
  BRAVE_API_KEY        Brave Search API key; without it the second look uses the NPI Registry only
  MAX_SEARCHES         web searches per run, default 20
Test hooks (not for production): OVERPASS_FIXTURE=file.json, WEB_FIXTURE=file.json ({url: html}),
NPI_FIXTURE=file.json (an NPI API reply), SEARCH_FIXTURE=file.json ({query: Brave reply}).
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
# Public Overpass servers, tried in order: the main one sometimes refuses (406/429/504), so fall back to mirrors.
OVERPASS_URLS = [u for u in [os.environ.get("OVERPASS_URL"),
                             "https://overpass-api.de/api/interpreter",
                             "https://overpass.private.coffee/api/interpreter",
                             "https://overpass.kumi.systems/api/interpreter"] if u]
VERIFY_PER_RUN = int(os.environ.get("VERIFY_PER_RUN", "10"))
VERIFY_TIME = int(os.environ.get("VERIFY_TIME", "100"))
BRAVE_API_KEY = os.environ.get("BRAVE_API_KEY", "")
MAX_SEARCHES = int(os.environ.get("MAX_SEARCHES", "20"))
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


def site_call(path, payload=None):
    """GET or POST JSON to the Traversence site with the worker token."""
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(SITE + path, data=data, method="POST" if data else "GET", headers={
        "X-Crawler-Token": TOKEN, "Authorization": "Bearer " + TOKEN,
        "Content-Type": "application/json", "User-Agent": UA, "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
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
    for q in GROUP_QUERIES.get(job["looking_for"]["group"], []):
        for p in pts:
            parts.append(f'{q}["name"](around:{ZIP_RADIUS_M},{p["lat"]},{p["lon"]});')
    query = "[out:json][timeout:60];(" + "".join(parts) + ");out center tags 80;"
    body = urllib.parse.urlencode({"data": query}).encode()
    tried = []
    for url in OVERPASS_URLS:
        req = urllib.request.Request(url, data=body, headers={
            "User-Agent": UA, "Accept": "application/json",
            "Content-Type": "application/x-www-form-urlencoded"})
        try:
            with urllib.request.urlopen(req, timeout=90) as r:
                return json.loads(r.read().decode("utf-8")).get("elements", [])
        except urllib.error.HTTPError as e:
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e.code} {e.read()[:120]!r}")
        except (urllib.error.URLError, TimeoutError, ValueError) as e:
            tried.append(f"{urllib.parse.urlparse(url).netloc} {e!r}"[:160])
    raise RuntimeError("OpenStreetMap lookup failed: " + " | ".join(tried))


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
                log(f"  NPI lookup failed: {e!r}"[:200])
                return []
            replies.append(reply)
            if reply.get("result_count"):
                break
    want = words(lst["name"])
    found = []
    for reply in replies:
        for rec in reply.get("results", []):
            names = [(rec.get("basic") or {}).get("organization_name", "")] + [o.get("organization_name", "") for o in rec.get("other_names", [])]
            if not want or not any(len(want & words(n)) / len(want) >= 0.6 for n in names if n):
                continue
            for a in rec.get("addresses", []):
                if a.get("address_purpose") != "LOCATION":
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
    """One Brave Search API query; a list of {url, title, description}."""
    global searches, last_search
    if os.environ.get("SEARCH_FIXTURE"):
        with open(os.environ["SEARCH_FIXTURE"]) as f:
            data = json.load(f)
            reply = data.get(query) or data.get("*", {})
    else:
        if not BRAVE_API_KEY or searches >= MAX_SEARCHES:
            return []
        wait = 1.1 - (time.monotonic() - last_search)        # free plans allow about one query a second
        if wait > 0:
            time.sleep(wait)
        searches += 1
        last_search = time.monotonic()
        q = urllib.parse.urlencode({"q": query, "count": 20, "country": "us"})
        req = urllib.request.Request("https://api.search.brave.com/res/v1/web/search?" + q, headers={
            "Accept": "application/json", "X-Subscription-Token": BRAVE_API_KEY, "User-Agent": UA})
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                reply = json.loads(r.read().decode("utf-8"))
        except Exception as e:
            log(f"  search failed: {e!r}"[:200])
            return []
    out = []
    for r in (reply.get("web") or {}).get("results", []):
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
    for q in queries:
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


def second_look(deadline):
    d = site_call(f"/api/crawl/verify.php?limit={VERIFY_PER_RUN}")
    if d.get("paused"):
        return
    listings = d.get("listings", [])
    if listings:
        log(f"second look: {len(listings)} listing(s)" + ("" if BRAVE_API_KEY else " (NPI Registry only; no BRAVE_API_KEY)"))
    for lst in listings:
        if time.monotonic() > deadline or out_of_time():
            log("second look: out of time, the rest go back when their lease ends")
            break
        have = set(lst.get("known_hosts") or [])
        found, checked = [], ["npi"]
        try:
            found += npi_lookup(lst)
            if BRAVE_API_KEY or os.environ.get("SEARCH_FIXTURE"):
                checked.append("search")
                found += web_references(lst, have | {h for f in found for h in [host_of(f["url"])]})
            res = site_call("/api/crawl/verify.php", {"row_id": lst["row_id"], "found": found, "checked": checked})
            kinds = ", ".join(f"{f['kind']} {host_of(f['url'])}" for f in found) or "nothing more"
            log(f"second look {lst['name']} ({lst['city']}): {kinds} -> score {res.get('score')}"
                + (", published" if res.get("auto_imported") else ""))
        except Exception as e:
            log(f"second look {lst.get('name')}: {e!r}"[:300])


def run():
    if not SITE or not TOKEN:
        log("TRAVERSENCE_URL and CRAWLER_API_TOKEN must be set.")
        return 2
    if VERIFY_PER_RUN > 0:
        try:
            second_look(started + min(VERIFY_TIME, TIME_BUDGET))
        except Exception as e:                     # the second look never stops the crawl jobs
            log(f"second look stopped: {e!r}"[:300])
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
        # Exit cleanly: the next scheduled run tries again. A failing exit makes Railway restart the
        # container at once, which would hammer the site every second.
        log(f"site said {e.code}: {e.read()[:300]!r}")
        sys.exit(0)
    except Exception as e:                         # site unreachable etc.: same, wait for the next run
        log(f"run stopped: {e!r}")
        sys.exit(0)
