"""W0 Indexer: Phase 1's engine, always on (decisions/0164 §1.4's note of 2026-11-17, R3, R14, R18).

One continuous loop on Railway (its own service, restart on failure; not the crawler's cron). It checks in with the site
(GET /api/crawl/index.php), and while W0 is on it reads the URLs the site hands out, politely, and sends back:
  - every link on every page read (same site or another, with the page it came from and its link text), as found URLs;
  - each page's answer (status, fingerprint, title, and why when it wasn't read: robots, a block, parked, thin, paywall);
  - what a new site's home page says it is, so the machine can classify it (no person waits on it).
It only indexes: extracting, resolving, publishing and every other workflow stay scheduled on the site (0163).

The rules before any fetch (§1.2) are the crawler's own (crawler.py): the open web only (every hop checked against private
addresses), robots.txt (cached 24 hours; a site asking for more than 30 s between pages is left alone), a polite pace
(1.5 s at least between pages, or the site's Crawl-delay), our own user agent, never a sign-in or account page, never a
host the site says is held or paused. A block (401, 403, 406, 451) is reported and the site holds the whole site for a
person; it's never tried another way. Facebook and Instagram are never handed out by the site (R14).

Batches to the site (the Bluehost bot check, 2026-11-15): 250 found URLs or 25 read pages, 100 KB at most, at most one
batch every 10 s (all settings from the site's Thresholds). A 409, 413, 503 or slow answer halves the batch and doubles
the gap; an hour of clean answers grows it back, never past the ceiling. Links travel wrapped (tvSafe), with the worker's
cookie and pace (site_call). Every batch gets a receipt (count and checksum): what's missing is sent again (the site's
records are keyed, so a resend changes nothing); three mismatches in a row slow it down and the site opens a Pulse case.
It sleeps when there's nothing to do and when W0 is off, never spinning.

    python -u indexer.py               the loop (Railway: railway.indexer.json)
    INDEXER_TEST=1 python indexer.py   the pre-flight: one full-size test batch (marked test, never stored), then exit
"""
import collections
import hashlib
import json
import os
import re
import signal
import sys
import time
import urllib.parse

import crawler as C

VERSION = "1.0.0"
C.WORKER_V = "indexer " + VERSION
# the crawler's per-run budgets don't apply: the indexer runs all the time, paced by the site and by each site's rules
C.MAX_FETCHES = C.FETCH_CAP = 10 ** 12
C.TIME_BUDGET = 10 ** 12
C.RUN_END = None

ENDPOINT = "/api/crawl/index.php"
ROBOTS_TTL = 24 * 3600            # robots.txt kept a day (§1.2, 3)
SLOW_S = 8.0                      # an answer slower than this counts as slow: halve and double the gap
CLEAN_GROW_S = 3600               # an hour of clean answers grows the batch back
CHECK_IN_S = 60                   # a check-in at least this often (its time is W0's Railway time on the ledger)
MIN_BATCH = 25
MAX_GAP = 120.0
SKIP_HREF = re.compile(r"(?i)^(#|mailto:|tel:|sms:|javascript:|data:|about:|ftp:|file:)")
ADMIN_PATH = re.compile(r"(?i)^/(wp-admin|wp-login\.php|login|log-in|signin|sign-in|signup|sign-up|register|account|my-account|cart|checkout|auth|oauth|dashboard|user/login)(/|$)")
A_RE = re.compile(r'(?is)<a\b[^>]*?\bhref\s*=\s*["\']?([^"\'\s>]+)["\']?[^>]*>(.*?)</a>')
CANON_RE = re.compile(r'(?is)<link\b[^>]*rel=["\']?canonical["\']?[^>]*>')
HREF_RE = re.compile(r'(?is)\bhref\s*=\s*["\']?([^"\'\s>]+)')
CHALLENGE_RE = re.compile(r"(?i)cf-chl|challenge-platform|just a moment\.\.\.|attention required! \| cloudflare|captcha")

stop = False


def on_term(*_):
    global stop
    stop = True                     # Railway stopping the service: send what's queued, then exit


signal.signal(signal.SIGTERM, on_term)
signal.signal(signal.SIGINT, on_term)


class Batcher:
    """The queues of found URLs, read pages and site descriptions, sent in self-adjusting batches with receipts."""

    def __init__(self):
        self.found = collections.deque()
        self.pages = collections.deque()
        self.about = collections.deque()
        self.s = {"found_batch": 250, "page_batch": 25, "batch_kb": 100, "gap_s": 10, "batch_max": 500, "per_host": 3, "link_net": 2000}
        self.size = 250            # found URLs a batch, now
        self.gap = 10.0            # seconds between batches, now
        self.last = 0.0
        self.clean_since = time.monotonic()
        self.mismatches = 0
        self.n = 0

    def settings(self, s):
        if isinstance(s, dict):
            for k in self.s:
                if isinstance(s.get(k), (int, float)) and s[k] > 0:
                    self.s[k] = s[k]
        target = min(int(self.s["found_batch"]), int(self.s["batch_max"]))
        self.size = max(MIN_BATCH, min(self.size, int(self.s["batch_max"])))
        if self.size > target:
            self.size = target
        self.gap = max(float(self.s["gap_s"]), min(self.gap, MAX_GAP))

    def backoff(self, why):
        self.size = max(MIN_BATCH, self.size // 2)
        self.gap = min(MAX_GAP, self.gap * 2)
        self.clean_since = time.monotonic()
        C.log(f"site {why}: batch {self.size}, one every {self.gap:.0f} s")

    def grow(self):
        if time.monotonic() - self.clean_since < CLEAN_GROW_S:
            return
        target = min(int(self.s["found_batch"]), int(self.s["batch_max"]))
        if self.size < target or self.gap > float(self.s["gap_s"]):
            self.size = min(target, self.size * 2)
            self.gap = max(float(self.s["gap_s"]), self.gap / 2)
            C.log(f"an hour clean: batch {self.size}, one every {self.gap:.0f} s")
        self.clean_since = time.monotonic()

    def queued(self):
        return len(self.found) + len(self.pages) + len(self.about)

    def due(self):
        return time.monotonic() - self.last >= self.gap

    def take(self, q, limit):
        """Up to `limit` records from a queue, fitting the size cap once wrapped."""
        cap = int(self.s["batch_kb"]) * 1024 - 400
        rows, size = [], 2
        while q and len(rows) < limit:
            one = len(json.dumps(C.tv_safe(q[0]))) + 1
            if rows and size + one > cap:
                break
            rows.append(q.popleft())
            size += one
        return rows

    def send(self, force=False):
        """One batch when it's due (found URLs first: a page's links reach the site before the page's answer)."""
        if not self.queued() or (not force and not self.due()):
            return False
        if self.found and (len(self.found) >= self.size or force or not self.pages):
            q, kind, limit = self.found, "found", self.size
        elif self.about:
            q, kind, limit = self.about, "about", 10
        elif self.pages and not self.found:
            q, kind, limit = self.pages, "pages", int(self.s["page_batch"])
        else:
            q, kind, limit = self.found, "found", self.size
        rows = self.take(q, limit)
        if not rows:
            return False
        self.post(kind, rows, q)
        return True

    def post(self, kind, rows, q, test=False):
        self.n += 1
        key = hashlib.sha1(f"{kind}|{time.time()}|{self.n}|{os.getpid()}".encode()).hexdigest()
        self.last = time.monotonic()
        t0 = time.monotonic()
        try:
            res = C.site_call(ENDPOINT, C.tv_safe({"key": key, "kind": kind, "n": len(rows), "rows": rows}), timeout=60)
        except C.SiteBotCheck:
            q.extendleft(reversed(rows)); self.backoff("409 (the host's bot check)"); return None
        except RuntimeError as e:
            q.extendleft(reversed(rows))
            m = re.search(r" said (\d{3})", str(e))
            self.backoff(f"said {m.group(1) if m else '?'}" if m else f"unreachable ({str(e)[:80]})")
            return None
        except Exception as e:     # a timeout, a dropped connection: the batch goes back
            q.extendleft(reversed(rows)); self.backoff(f"didn't answer ({e!r}"[:80] + ")"); return None
        took = time.monotonic() - t0
        r = (res or {}).get("receipt") or {}
        sent = [hashlib.sha1(str(x.get("u") if kind != "about" else x.get("host")).encode()).hexdigest() for x in rows]
        rejected = {k for k, _ in (r.get("rejected") or []) if isinstance(k, str)}
        expect = sorted(k for k in sent if k not in rejected)
        good = r.get("received") == len(rows) and r.get("stored") == len(expect) and r.get("checksum") == hashlib.sha1(",".join(expect).encode()).hexdigest()
        if test:
            return {"ok": good, "receipt": r, "seconds": round(took, 2)}
        if not good:
            self.mismatches += 1
            C.log(f"receipt for {kind} didn't match (sent {len(rows)}, it stored {r.get('stored')}): sent again, {self.mismatches} in a row")
            q.extendleft(reversed(rows))   # keyed on the site: a resend changes nothing
            self.backoff("mismatched")
            if self.mismatches >= 3:
                self.gap = MAX_GAP          # three in a row: slow right down (the site opens the Pulse case)
            return False
        self.mismatches = 0
        if took > SLOW_S:
            self.backoff(f"slow ({took:.1f} s)")
        else:
            self.grow()
        if r.get("new") or r.get("new_sites"):
            C.log(f"{kind}: {len(rows)} sent, {r.get('new', 0)} new, {r.get('new_sites', 0)} new site(s), {r.get('junk', 0)} junk, {r.get('skipped', 0)} skipped")
        return True


B = Batcher()
robots_at = {}
holds = {}


def tidy_caches():
    """robots.txt for a day; pace notes kept small."""
    now = time.time()
    for base in [b for b, t in robots_at.items() if now - t > ROBOTS_TTL]:
        C.robots_cache.pop(base, None)
        robots_at.pop(base, None)
    if len(C.host_last) > 20000:
        C.host_last.clear()


def links_of(page, url):
    """Every link on the page: <a href> with its words, the canonical link. [(url, text)], in page order, once each."""
    out, seen = [], set()
    for m in CANON_RE.finditer(page):
        h = HREF_RE.search(m.group(0))
        if h:
            full = C.join_url(url, C.html.unescape(h.group(1).strip()))
            if full.startswith("http") and full not in seen:
                seen.add(full); out.append((full, "(canonical)"))
    for m in A_RE.finditer(page):
        href = C.html.unescape(m.group(1).strip())
        if not href or SKIP_HREF.match(href):
            continue
        full = C.join_url(url, href).split("#", 1)[0]
        if not full.startswith("http") or full in seen or len(full) > 1000:
            continue
        seen.add(full)
        text = re.sub(r"\s+", " ", C.html.unescape(re.sub(r"<[^>]+>", " ", m.group(2)))).strip()[:160]
        out.append((full, text))
    return out


def why_not_read(r, page):
    """Why a page wasn't read, in the site's words: robots, auth, ban, challenge, parked, thin; '' when it was."""
    st = r.get("status") or 0
    if r.get("why") == "robots" and not st:
        return "robots"
    if st in (401, 403, 451):
        return "auth"
    if st in (406, 418):
        return "ban"
    if page and CHALLENGE_RE.search(page[:5000]) and st in (200, 403, 503):
        return "challenge"
    if page:
        text = " ".join(C.page_lines(page))
        if C.DEAD_RE.search(page) or C.DEAD_TEXT_RE.search(text):
            return "parked"
        words = len(text.split())
        if words < 100 and len(A_RE.findall(page)) > max(20, words):
            return "thin"
    return ""


def read_one(w):
    """One URL the site handed out: fetched under the rules, its links and its answer queued."""
    url = w.get("u") or ""
    host = (urllib.parse.urlsplit(url).hostname or "").lower()
    if not url or host in holds or (host.startswith("www.") and host[4:] in holds):
        return                                         # held or paused: the lease runs out and it waits
    path = urllib.parse.urlsplit(url).path or "/"
    if ADMIN_PATH.match(path):
        B.pages.append({"u": url, "s": 0, "why": "admin"}); return
    base = f"{urllib.parse.urlsplit(url).scheme}://{urllib.parse.urlsplit(url).netloc}"
    if base not in robots_at:
        robots_at[base] = time.time()
    r = C.fetch_page_meta(url, w.get("etag") or "", w.get("modified") or "")
    page = r.get("body") or ""
    why = why_not_read(r, page)
    row = {"u": url, "s": int(r.get("status") or 0), "ty": r.get("type") or "", "b": int(r.get("bytes") or 0),
           "e": r.get("etag") or "", "m": r.get("modified") or ""}
    if why:
        row["why"] = why
    if page and why not in ("parked", "challenge"):
        text = re.sub(r"\s+", " ", " ".join(C.page_lines(page)))
        row.update(fp=hashlib.sha1(text.encode("utf-8", "replace")).hexdigest(), ti=C.page_title(page))
        links = links_of(page, url)
        row["n"] = len(links)
        if len(links) > int(B.s["link_net"]):
            row["fl"] = 1                              # an absurd number: all logged, over several batches, and flagged
            C.log(f"{url[:120]}: {len(links)} links, flagged (all logged)")
        for u, t in links:
            B.found.append({"u": u, "f": url, "t": t})
        if path in ("", "/") and not w.get("again"):   # a site's home page, first time: its sitemap's pages too
            try:
                for u in C.sitemap_urls(url, C.host_of(url), limit=5000):
                    B.found.append({"u": u, "f": url, "t": "(sitemap)"})
            except Exception as e:
                C.log(f"sitemap {host}: {e!r}"[:160])
    B.pages.append(row)


def describe(d):
    """A new site's home page: what it says it is (title, description, name, schema.org types), so the machine can
    classify it. Sent even when the page couldn't be read, so the site isn't left waiting."""
    home = d.get("home_url") or ""
    entry = {"host": d.get("host") or "", "title": "", "description": "", "site_name": "", "types": []}
    page = C.fetch_page(home) if home else None
    if page:
        meta = lambda key: (re.search(r'(?is)<meta[^>]+(?:name|property)=["\']' + key + r'["\'][^>]*content=["\']([^"\']*)', page) or [None, ""])[1]
        types = []
        for block in re.findall(r'(?is)<script[^>]+application/ld\+json[^>]*>(.*?)</script>', page):
            try:
                data = json.loads(block.strip())
            except Exception:
                continue
            for x in (data if isinstance(data, list) else data.get("@graph", [data]) if isinstance(data, dict) else []):
                if isinstance(x, dict):
                    types.append(C.ld_type(x))
        entry.update(title=C.page_title(page), site_name=C.html.unescape(meta("og:site_name"))[:190],
                     description=C.html.unescape(meta("description") or meta("og:description"))[:400], types=[t for t in types if t][:5])
        if C.DEAD_RE.search(page) or C.DEAD_TEXT_RE.search(" ".join(C.page_lines(page))):
            entry["description"] = (entry["description"] + " · this domain is for sale or parked").strip(" ·")
        for u, t in links_of(page, home):
            B.found.append({"u": u, "f": home, "t": t})
    B.about.append(entry)


def check_in(last_beat, want):
    q = urllib.parse.urlencode({"n": want, "secs": int(time.monotonic() - last_beat), "v": VERSION, "queue": B.queued(),
                                "mismatches": B.mismatches, "batch": B.size, "gap": round(B.gap, 1)})
    return C.site_call(f"{ENDPOINT}?{q}", timeout=30)


def flush(deadline):
    """Send queued batches until the queues are empty or the deadline passes, at the batches' pace."""
    while B.queued() and time.monotonic() < deadline:
        if not B.due():
            time.sleep(min(1.0, max(0.05, B.gap - (time.monotonic() - B.last))))
            continue
        B.send(force=True)


def preflight():
    """The pre-flight's full-size test batch (0164 §1.4): marked test, checked and answered, never stored."""
    rows = [{"u": f"https://example.org/w0-preflight/{i}?n={i}", "f": "https://example.org/", "t": "pre-flight"} for i in range(B.size)]
    rows = B.take(collections.deque(rows), B.size)
    res = B.post("test", rows, collections.deque(), test=True)
    C.log(f"pre-flight: {len(rows)} URLs in one batch: {json.dumps(res)[:400]}")
    return 0 if res and res.get("ok") else 1


def main():
    if not C.SITE or not C.TOKEN:
        C.log("TRAVERSENCE_URL and CRAWLER_API_TOKEN must be set.")
        return 2
    if os.environ.get("INDEXER_TEST") == "1":
        return preflight()
    C.log(f"W0 Indexer {VERSION}: always on; it sleeps while W0 is off")
    last_beat = time.monotonic()
    while not stop:
        tidy_caches()
        try:
            d = check_in(last_beat, max(5, min(100, B.size // 5)))
            last_beat = time.monotonic()
        except C.SiteBotCheck:
            B.backoff("409 at check-in"); time.sleep(B.gap); continue
        except Exception as e:
            C.log(f"check-in failed: {e!r}"[:200]); time.sleep(60); continue
        B.settings(d.get("settings"))
        holds.clear(); holds.update({str(k).lower(): str(v) for k, v in (d.get("holds") or {}).items()} if isinstance(d.get("holds"), dict) else {})
        if not d.get("on"):
            flush(time.monotonic() + 30)              # what was gathered still goes (logging isn't reading)
            C.log(f"off: {d.get('why', '')}"[:200])
            sleep_until = time.monotonic() + int(d.get("sleep") or 60)
            while not stop and time.monotonic() < sleep_until:
                time.sleep(1)
            continue
        work, todo = d.get("work") or [], d.get("describe") or []
        if not work and not todo and not B.queued():
            sleep_until = time.monotonic() + int(d.get("sleep") or 30)   # nothing to do: sleep, never spin
            while not stop and time.monotonic() < sleep_until:
                time.sleep(1)
            continue
        round_end = time.monotonic() + CHECK_IN_S
        for t in todo[:5]:
            if stop:
                break
            try:
                describe(t)
            except Exception as e:
                C.log(f"describe {t.get('host')}: {e!r}"[:200])
            B.send()
        for w in work:
            if stop or time.monotonic() > round_end + 240:   # the leases last 15 minutes: never hold work that long
                break
            try:
                read_one(w)
            except Exception as e:
                C.log(f"read {str(w.get('u'))[:100]}: {e!r}"[:200])
            while B.due() and B.send():
                pass
        flush(time.monotonic() + max(5, round_end - time.monotonic()))
    flush(time.monotonic() + 20)
    C.log("stopped")
    return 0


if __name__ == "__main__":
    sys.exit(main())
