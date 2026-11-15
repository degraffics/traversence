"""The host's bot check (2026-11-15): the worker sends its cookie, takes a new cookie name from the challenge, retries
once, paces its calls, and reports a refusal as held (SiteBotCheck), never a job failure. Run: python3 tests/test_site_call.py"""
import http.server, json, os, sys, threading, time

PORT = 8765
os.environ["TRAVERSENCE_URL"] = f"http://127.0.0.1:{PORT}"
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

MODE = {"m": "once", "seen": [], "n": 0}


class H(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def do_GET(self):
        MODE["n"] += 1
        MODE["seen"].append((time.monotonic(), self.headers.get("Cookie", "")))
        ok = MODE["m"] == "never" and False
        if MODE["m"] == "always" or (MODE["m"] in ("once", "rename") and MODE["n"] == 1) or (MODE["m"] == "rename" and "humans_777=1" not in (self.headers.get("Cookie") or "")):
            name = "humans_777" if MODE["m"] == "rename" else "humans_21909"
            body = f'<script>document.cookie = "{name}=1"; document.location.reload(true)</script>'.encode()
            self.send_response(409); self.end_headers(); self.wfile.write(body); return
        self.send_response(200); self.send_header("Content-Type", "application/json"); self.end_headers()
        self.wfile.write(json.dumps({"ok": True}).encode())


srv = http.server.HTTPServer(("127.0.0.1", PORT), H)
threading.Thread(target=srv.serve_forever, daemon=True).start()
fails = 0


def check(name, cond):
    global fails
    print(("  ok  " if cond else "FAIL  ") + name)
    fails += 0 if cond else 1


crawler.SITE_GAP = 0.3
MODE.update(m="once", seen=[], n=0)
check("a challenge then the answer: retried once with the cookie", crawler.site_call("/api/x") == {"ok": True} and MODE["n"] == 2)
check("the cookie is always sent", all("humans_21909=1" in c for _, c in MODE["seen"]))
check("calls are paced", MODE["seen"][1][0] - MODE["seen"][0][0] >= 0.29)
MODE.update(m="rename", seen=[], n=0)
check("a new cookie name is read from the challenge", crawler.site_call("/api/x") == {"ok": True} and "humans_777=1" in MODE["seen"][-1][1])
MODE.update(m="always", seen=[], n=0)
try:
    crawler.site_call("/api/x"); held = False
except crawler.SiteBotCheck:
    held = True
check("still refused: held (SiteBotCheck, a ServiceHeld), after one retry only", held and MODE["n"] == 2 and issubclass(crawler.SiteBotCheck, crawler.ServiceHeld))
check("counted for the run's report", crawler.SITE_BLOCKED == 1)
srv.shutdown()
print("all passed" if not fails else f"{fails} failed")
sys.exit(1 if fails else 0)
