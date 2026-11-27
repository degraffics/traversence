"""Open loads in numbered batches (2.17.0, IR-0001: 500 rows took 101-130 s at the site, past the worker's 120 s read):
a batch that gets no answer is sent once more with the same number and a longer read; a refusal (an HTTP answer) is not
sent again; each site call that fails says how long it waited. Run: python3 tests/test_load_batches.py"""
import inspect, io, os, sys, urllib.error
os.environ.setdefault("TRAVERSENCE_URL", "http://127.0.0.1:1")
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

fails = 0
def check(name, ok, got=None):
    global fails
    print(("  ok  " if ok else "FAIL  ") + name + ("" if ok or got is None else f" — {got!r}"))
    fails += 0 if ok else 1

calls = []
def fake(answers):
    def site_call(path, payload=None, timeout=60):
        calls.append((path, dict(payload or {}), timeout))
        a = answers.pop(0)
        if isinstance(a, BaseException):
            raise a
        return a
    return site_call

p = {"dataset": "overture", "release": "r", "key": "cluster:1", "batch": 3, "rows": [{}] * 100}
crawler.site_call = fake([TimeoutError("The read operation timed out"), {"ok": True, "seen": 100, "again": True}])
res = crawler.load_batch(p)
check("a timeout: sent once more", len(calls) == 2, calls)
check("…with the same batch number and rows", calls[0][1] == calls[1][1] == p)
check("…the first read 120 s, the second 240 s", (calls[0][2], calls[1][2]) == (120, 240), [c[2] for c in calls])
check("…and the site's answer for the taken batch comes back", res.get("again") is True)

calls.clear()
crawler.site_call = fake([TimeoutError("x"), TimeoutError("x")])
try:
    crawler.load_batch(p); raised = None
except Exception as e:
    raised = e
check("two timeouts: only one retry, then the error as before", len(calls) == 2 and isinstance(raised, TimeoutError), calls)

calls.clear()
crawler.site_call = fake([urllib.error.HTTPError("u", 500, "x", {}, io.BytesIO(b""))])
try:
    crawler.load_batch(p); raised = None
except Exception as e:
    raised = e
check("an HTTP answer (500) isn't sent again", len(calls) == 1 and isinstance(raised, urllib.error.HTTPError), calls)

# 2.17.1 (W1 run #2148): a 502, 503 or 504 from the host waits and sends the same batch again, 3 times at most
slept = []
crawler.time.sleep = slept.append
busy = lambda code: RuntimeError(f"site /api/crawl/loads.php said {code}: b'<!DOCTYPE HTML PUBLIC'")
calls.clear()
crawler.site_call = fake([busy(503), busy(503), {"ok": True, "seen": 100}])
res = crawler.load_batch(p)
check("a 503: waited and sent again, its answer back", len(calls) == 3 and res.get("seen") == 100, calls)
check("…the same batch number and rows each time", all(c[1] == p for c in calls))
check("…after 30 s, then 90 s", slept == [30, 90], slept)

calls.clear(); slept.clear()
crawler.site_call = fake([busy(502), busy(504), busy(503), busy(503)])
try:
    crawler.load_batch(p); raised = None
except Exception as e:
    raised = e
check("four busy answers: 3 waits (30, 90, 180), then the error as before", len(calls) == 4 and slept == [30, 90, 180] and isinstance(raised, RuntimeError) and "said 503" in str(raised), (calls, slept, raised))

calls.clear(); slept.clear()
crawler.site_call = fake([RuntimeError("site /api/crawl/loads.php said 500: b'x'")])
try:
    crawler.load_batch(p); raised = None
except Exception as e:
    raised = e
check("a 500 (our code's error) isn't waited out or sent again", len(calls) == 1 and not slept and isinstance(raised, RuntimeError), calls)

calls.clear(); slept.clear()
crawler.site_call = fake([RuntimeError("site /api/crawl/loads.php said 422: b'Which dataset'")])
try:
    crawler.load_batch(p); raised = None
except Exception as e:
    raised = e
check("a refusal (422) isn't sent again", len(calls) == 1 and not slept and isinstance(raised, RuntimeError), calls)

calls.clear(); slept.clear()
crawler.site_call = fake([busy(503)])
res = crawler.load_batch(p, crawler.time.monotonic() + 60)
check("a wait the task's time can't cover: no wait, the town stops for this run", len(calls) == 1 and not slept and res == {"busy": True}, (calls, slept, res))

# open_load hands the deadline on and stops the town (not marked done) on busy; work_task says it in words, never failed
src = inspect.getsource(crawler.open_load)
check("open_load passes its deadline to each batch and stops on busy before marking the town done",
      "load_batch({" in src and "}, deadline)" in src and src.index('res.get("busy")') < src.index('"done": True'))
item = {"counts": {}}
crawler.HANDLERS["zz_busy"] = (lambda d: "busy", None, lambda: "")
crawler.work_task({"task": "zz_busy"}, item, 60)
check("a task stopped by a busy host is idle with its reason, not failed", item.get("outcome") == "idle" and "503" in item.get("why", ""), item)
del crawler.HANDLERS["zz_busy"]

src = inspect.getsource(crawler.open_load)
check("open_load sends LOAD_BATCH (100) rows a batch, numbered", crawler.LOAD_BATCH == 100 and '"batch": n' in src and "rows[i:i + LOAD_BATCH]" in src and "500" not in src)

# a site call with no answer says how long it waited
logged = []
crawler.log = logged.append
def boom(*a, **k):
    raise TimeoutError("The read operation timed out")
crawler.urllib.request.urlopen = boom
crawler.SITE_GAP = 0
try:
    crawler._site_request("/api/crawl/loads.php", b'{"a":1}', 1)
except TimeoutError:
    pass
check("a call with no answer is logged with its path, size and wait", any("/api/crawl/loads.php: POST 7 bytes, no answer after" in x for x in logged), logged)

print(f"\n{'all passed' if not fails else str(fails) + ' failed'}")
sys.exit(1 if fails else 0)
