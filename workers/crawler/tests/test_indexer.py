"""W0 Indexer (indexer.py, decisions/0164 §1.4's note): every link on a page logged with its page and words, a page's
answer and why it wasn't read, batches that fit the size cap, receipts compared (a cut batch sent again), the backoff on
409/413/503 and the growth after an hour clean, the check-in, and the pre-flight's test batch.
Run: python3 tests/test_indexer.py"""
import hashlib, http.server, json, os, sys, tempfile, threading, time

PORT = 8767
os.environ["TRAVERSENCE_URL"] = f"http://127.0.0.1:{PORT}"
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
pages = {
    "https://county.example.gov/": '<html><head><title>County</title><link rel="canonical" href="https://county.example.gov/"></head><body>'
        + "<p>" + "Our county serves the people of the plateau with roads, parks and public health. " * 6 + "</p>"
        + '<a href="/services">Services</a> <a href="https://chamber.example.org/members?id=7">Chamber members</a>'
        + '<a href="mailto:clerk@county.example.gov">Email</a> <a href="#top">Top</a> <a href="https://www.facebook.com/county">Facebook</a></body></html>',
    "https://parked.example.com/": "<html><body>This domain is for sale. Buy this domain today.</body></html>",
    "https://big.example.org/": "<html><body>" + "".join(f'<a href="/p{i}">P{i}</a>' for i in range(30)) + "</body></html>",
}
fx = tempfile.NamedTemporaryFile("w", suffix=".json", delete=False)
json.dump(pages, fx); fx.close()
os.environ["WEB_FIXTURE"] = fx.name
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402
import indexer  # noqa: E402
crawler.SITE_GAP = 0

S = {"mode": "ok", "posts": [], "gets": []}


def unwrap(v):
    if isinstance(v, dict):
        return {k: unwrap(x) for k, x in v.items()}
    if isinstance(v, list):
        return [unwrap(x) for x in v]
    if isinstance(v, str) and v.startswith("tvb64:"):
        import base64
        return base64.b64decode(v[6:]).decode()
    return v


class H(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def reply(self, code, obj):
        b = json.dumps(obj).encode()
        self.send_response(code); self.send_header("Content-Type", "application/json"); self.end_headers(); self.wfile.write(b)

    def do_GET(self):
        S["gets"].append(self.path)
        self.reply(200, {"ok": True, "on": S["mode"] != "off", "why": "W0 is paused", "settings": {"found_batch": 250, "page_batch": 25, "batch_kb": 100, "gap_s": 10, "batch_max": 500, "per_host": 3, "link_net": 20},
                         "work": [], "describe": [], "holds": {}, "sleep": 1})

    def do_POST(self):
        b = unwrap(json.loads(self.rfile.read(int(self.headers["Content-Length"]))))
        S["posts"].append(b)
        if S["mode"] == "409":
            self.send_response(409); self.end_headers(); self.wfile.write(b'<script>document.cookie = "humans_21909=1"</script>'); return
        if S["mode"] == "413":
            self.reply(413, {"ok": False}); return
        rows = b["rows"]
        if S["mode"] == "cut":
            rows = rows[: len(rows) // 2]           # a firewall that swallows half without an error
        keys = sorted(hashlib.sha1(str(r.get("u") if b["kind"] != "about" else r.get("host")).encode()).hexdigest() for r in rows)
        self.reply(200, {"ok": True, "receipt": {"key": b["key"], "declared": b["n"], "received": len(rows), "stored": len(keys), "rejected": [],
                                                 "checksum": hashlib.sha1(",".join(keys).encode()).hexdigest(), "ok": len(rows) == b["n"], "new": len(rows)}})


srv = http.server.HTTPServer(("127.0.0.1", PORT), H)
threading.Thread(target=srv.serve_forever, daemon=True).start()
fails = 0


def check(name, cond):
    global fails
    print(("  ok  " if cond else "FAIL  ") + name)
    fails += 0 if cond else 1


B = indexer.B

# links and a page's answer
ls = indexer.links_of(pages["https://county.example.gov/"], "https://county.example.gov/")
urls = [u for u, _ in ls]
check("every link logged, same site or not, with its words", ("https://county.example.gov/services", "Services") in ls and ("https://chamber.example.org/members?id=7", "Chamber members") in ls)
check("Facebook is logged (the site never hands it out)", "https://www.facebook.com/county" in urls)
check("mailto and #anchors are not web addresses", not any(u.startswith("mailto") for u in urls) and "https://county.example.gov/#top" not in urls)
check("the canonical link too", ls[0] == ("https://county.example.gov/", "(canonical)"))
indexer.read_one({"u": "https://county.example.gov/services"})
check("an unknown page: answered with status 0, back to the site to try later", B.pages[-1]["s"] == 0)
B.pages.clear(); B.found.clear()
indexer.read_one({"u": "https://county.example.gov/", "again": True})
row = B.pages[-1]
check("a read page: its fingerprint and title, its links queued from it", row["s"] == 200 and len(row["fp"]) == 40 and row["ti"] == "County" and all(f["f"] == "https://county.example.gov/" for f in B.found))
indexer.read_one({"u": "https://parked.example.com/"})
check("a parked page says so (the site junks the site)", B.pages[-1].get("why") == "parked")
B.s["link_net"] = 20
indexer.read_one({"u": "https://big.example.org/"})
check("a page past the link net is flagged, every link still queued", B.pages[-1].get("fl") == 1 and sum(1 for f in B.found if f["f"] == "https://big.example.org/") == 30)
indexer.read_one({"u": "https://county.example.gov/wp-admin/"})
check("a sign-in path is never fetched", B.pages[-1] == {"u": "https://county.example.gov/wp-admin/", "s": 0, "why": "admin"})

# batches: found first, the size cap, receipts
B.found.clear(); B.pages.clear()
for i in range(600):
    B.found.append({"u": f"https://county.example.gov/page/{i}?q={i}", "f": "https://county.example.gov/", "t": "x" * 100})
B.pages.append({"u": "https://county.example.gov/", "s": 200})
B.last = 0; B.send(force=True)
first = S["posts"][-1]
check("found URLs go before pages' answers", first["kind"] == "found")
check("a batch fits the size cap once wrapped", len(json.dumps(crawler.tv_safe(first))) <= 100 * 1024 and first["n"] == len(first["rows"]) <= 250)
check("receipt matched: nothing left of that batch", B.mismatches == 0 and len(B.found) == 600 - first["n"])
S["mode"] = "cut"; before = len(B.found); B.last = 0; B.send(force=True)
check("a cut batch: the receipt doesn't match, all of it goes back to be sent again", B.mismatches == 1 and len(B.found) == before)
B.last = 0; B.send(force=True); B.last = 0; B.send(force=True)
check("three in a row: slowed right down", B.mismatches == 3 and B.gap == indexer.MAX_GAP)
S["mode"] = "ok"; B.gap = 10.0; B.size = 250; B.last = 0; B.send(force=True)
check("matched again: the count resets", B.mismatches == 0)
S["mode"] = "409"; B.size = 250; B.gap = 10.0; before = len(B.found); B.last = 0; B.send(force=True)
check("a 409 (the bot check) halves the batch and doubles the gap; nothing lost", B.size == 125 and B.gap == 20.0 and len(B.found) == before)
S["mode"] = "413"; B.last = 0; B.send(force=True)
check("a 413 halves it again", B.size == 62 and B.gap == 40.0 and len(B.found) == before)
S["mode"] = "ok"; B.clean_since = time.monotonic() - 3700; B.last = 0; B.send(force=True)
check("an hour clean grows it back", B.size == 124 and B.gap == 20.0)
B.settings({"found_batch": 250, "batch_max": 500, "gap_s": 10, "page_batch": 25, "batch_kb": 100, "per_host": 3, "link_net": 2000})
check("never past the setting", B.size <= 250 and B.gap >= 10)
while B.found or B.pages:
    B.last = 0; B.send(force=True)
check("pages' answers go once the found URLs are sent", S["posts"][-1]["kind"] == "pages")

# check-in and the pre-flight
d = indexer.check_in(time.monotonic() - 61, 25)
check("the check-in says its time, version, queue and pace", "secs=61" in S["gets"][-1] and "v=" + indexer.VERSION in S["gets"][-1] and d.get("on") is True)
B.size = 250
check("the pre-flight: one full-size test batch, matched", indexer.preflight() == 0 and S["posts"][-1]["kind"] == "test" and S["posts"][-1]["n"] >= 200)

os.unlink(fx.name)
srv.shutdown()
print("all passed" if not fails else f"{fails} failed")
sys.exit(1 if fails else 0)
