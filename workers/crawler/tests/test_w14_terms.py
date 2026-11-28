"""W14's terms read (2.18.0; decisions/0164 §7.1; handoffs/adhs-source.md A1): robots.txt first, then the source's page and
its terms page through fetch_page_meta, the terms' words and any list file sent to the site; nothing read past a disallow
or an unreadable robots.txt; a paused answer reads nothing. No network. Run: python3 tests/test_w14_terms.py"""
import os, sys
os.environ.setdefault("TRAVERSENCE_URL", "http://127.0.0.1:1")
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402
import urllib.robotparser  # noqa: E402

fails = 0
def check(name, ok):
    global fails
    print(("  ok  " if ok else "FAIL  ") + name)
    fails += 0 if ok else 1

SRC = {"id": 9, "url": "https://azcarecheck.azdhs.gov/s/", "terms_url": "https://www.azdhs.gov/licensing/index.php"}
posted, fetched = [], []
def fake_site(path, payload=None, timeout=60):
    if payload is None:
        return reply[0]
    posted.append(payload); return {"ok": True}
crawler.site_call = fake_site
PAGES = {SRC["url"]: {"body": '<html><a href="/files/facilities.xlsx">List</a><script></script></html>', "status": 200, "why": ""},
         SRC["terms_url"]: {"body": "<html><p>Use of this site.</p><script>x</script><p>Automated access is allowed.</p></html>", "status": 200, "why": ""}}
def fake_fetch(url, *a, **k):
    fetched.append(url)
    return dict({"body": None, "status": 0, "why": "error"}, **PAGES.get(url, {}))
crawler.fetch_page_meta = fake_fetch

def robots(text):
    rp = urllib.robotparser.RobotFileParser(); rp.parse(text.splitlines()); return rp

# allowed
crawler.robots_cache.clear()
crawler.robots_cache["https://azcarecheck.azdhs.gov"] = robots("User-agent: *\nCrawl-delay: 2\nAllow: /")
reply = [{"ok": True, "paused": False, "sources": [SRC]}]
did = crawler.w14_terms({})
it = posted[-1]["read"][0] if posted else {}
check("allowed: both pages read, once each", did and fetched == [SRC["url"], SRC["terms_url"]])
check("…robots allowed with its Crawl-delay", it.get("robots") == "allowed" and it.get("crawl_delay") == 2.0)
check("…the terms' words, scripts dropped", "Automated access is allowed." in it.get("terms_text", "") and "x" not in it.get("terms_text", "").split())
check("…the list file linked, made absolute", it.get("lists") == ["https://azcarecheck.azdhs.gov/files/facilities.xlsx"])

# disallowed: nothing fetched
posted.clear(); fetched.clear(); crawler.robots_cache.clear()
crawler.robots_cache["https://azcarecheck.azdhs.gov"] = robots("User-agent: *\nDisallow: /")
crawler.w14_terms({})
it = posted[-1]["read"][0]
check("disallowed: no page read, sent as disallowed", fetched == [] and it["robots"] == "disallowed" and it["terms_text"] == "")

# unreadable robots.txt: never taken as permission
posted.clear(); fetched.clear(); crawler.robots_cache.clear()
crawler.robots_cache["https://azcarecheck.azdhs.gov"] = crawler._DenyAll()
crawler.w14_terms({})
check("unreadable robots.txt: no page read, sent as unreadable", fetched == [] and posted[-1]["read"][0]["robots"] == "unreadable")

# paused: nothing read, nothing posted
posted.clear(); fetched.clear()
reply = [{"ok": True, "paused": True, "why": "W14 is paused"}]
check("paused: nothing read or posted", crawler.w14_terms({}) is False and fetched == [] and posted == [])

# a source that isn't https is skipped; at most 3 a run
posted.clear(); fetched.clear(); crawler.robots_cache.clear()
for h in ("a", "b", "c", "d"):
    crawler.robots_cache[f"https://{h}.example.gov"] = robots("User-agent: *\nAllow: /")
srcs = [{"id": 1, "url": "http://insecure.example.gov/"}] + [{"id": i, "url": f"https://{h}.example.gov/"} for i, h in enumerate("abcd", 2)]
reply = [{"ok": True, "paused": False, "sources": srcs}]
crawler.w14_terms({})
check("at most 3 a run (the first three handed out), http skipped", [x["id"] for x in posted[-1]["read"]] == [2, 3])
check("the handler is registered, its switches on the site", "w14_terms" in crawler.HANDLERS and crawler.HANDLERS["w14_terms"][2]() == "")

print("all passed" if not fails else f"{fails} failed")
sys.exit(1 if fails else 0)
