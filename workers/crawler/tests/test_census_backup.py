"""The Census geocoder's backup (2.16.0; decisions/0175's note, Jason 2026-11-20, "Yes, build it"): when the site can't
reach the Census on its connection, the worker sends the same batch to the Census itself and hands the reply to the site
to place; if the Census doesn't answer here either, the batch waits (held, never failed). Run: python3 tests/test_census_backup.py"""
import base64, io, os, sys, time
os.environ.setdefault("TRAVERSENCE_URL", "http://127.0.0.1:1")
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

fails = 0
def check(name, ok, got=None):
    global fails
    print(("  ok  " if ok else "FAIL  ") + name + ("" if ok or got is None else f" — {got!r}"[:400]))
    fails += 0 if ok else 1

CSV = b'1,120 W Main St,St. Johns,AZ,85936\n2,455 E Cleveland St,St. Johns,AZ,85936\n'
REPLY = b'"1","120 W Main St, St. Johns, AZ, 85936","Match","Exact","120 W MAIN ST","-109.37,34.50","1","L"\n"2","x","No_Match"\n'
BACKUP = {"url": "https://geocoding.geo.census.gov/geocoder/locations/addressbatch", "benchmark": "Public_AR_Current", "ids": [1, 2], "csv": base64.b64encode(CSV).decode()}
calls, sent = [], []

class Resp(io.BytesIO):
    def __enter__(self): return self
    def __exit__(self, *a): return False

def site(responses):
    def call(path, payload=None, timeout=60):
        calls.append((path, payload))
        return responses.pop(0)
    return call

# 1. the site waits for its connection and hands the batch on: sent from here, the reply handed back
def census_ok(req, *a, **kw):
    sent.append(req)
    return Resp(REPLY)
crawler._urlopen = census_ok
crawler.site_call = site([{"ok": True, "waits": True, "why": "The site couldn't reach the Census geocoder: no answer", "backup": BACKUP, "sent": 0, "left": 2},
                          {"ok": True, "via": "railway", "sent": 2, "found": 1, "not_found": 1, "left": 0}])
crawler.HELD.clear(); crawler.CUR_TASK = "geocode"
crawler.geocode(time.monotonic() + 60)
req = sent[0] if sent else None
check("the batch goes to the Census's own batch address, from Railway", req is not None and req.full_url == BACKUP["url"] and req.get_method() == "POST")
body = req.data if req else b""
check("…as the Census's form: benchmark and the same address file", b'name="benchmark"\r\n\r\nPublic_AR_Current' in body and b'filename="addresses.csv"' in body and CSV in body)
check("…with the worker's agent", req is not None and req.get_header("User-agent") == crawler.UA)
back = calls[1][1] if len(calls) > 1 else {}
check("the reply is handed to the site as it came, with the batch's ids", calls[1][0] == "/api/crawl/geocode.php" and back.get("ids") == [1, 2] and base64.b64decode(back.get("answers_b64", "")) == REPLY, back)
check("one batch, then done (nothing left)", len(calls) == 2)

# 2. the Census doesn't answer here either: held, the batch waits on the site
calls.clear(); sent.clear()
def census_down(req, *a, **kw):
    raise ConnectionResetError("reset")
crawler._urlopen = census_down
crawler.site_call = site([{"ok": True, "waits": True, "why": "no answer", "backup": BACKUP, "sent": 0, "left": 2}])
crawler.HELD.clear()
try:
    crawler.geocode(time.monotonic() + 60); err = None
except Exception as e:
    err = e
check("the Census down from here too: the task is held, never failed", isinstance(err, crawler.ServiceHeld), err)
check("…and nothing is handed to the site", len(calls) == 1)

# 3. the backup names another address: never called
calls.clear(); sent.clear(); crawler._urlopen = census_ok
crawler.site_call = site([{"ok": True, "waits": True, "why": "x", "backup": dict(BACKUP, url="https://example.com/x"), "left": 2}])
try:
    crawler.geocode(time.monotonic() + 60); err = None
except Exception as e:
    err = e
check("a backup naming another address is never called", isinstance(err, crawler.ServiceHeld) and not sent, err)

# 4. a wait with no backup (another kind of hold): held as before (2.12.0)
calls.clear()
crawler.site_call = site([{"ok": True, "waits": True, "why": "The Census geocoder didn't answer (HTTP 503)", "left": 2}])
try:
    crawler.geocode(time.monotonic() + 60); err = None
except Exception as e:
    err = e
check("a wait without a backup: held, as before", isinstance(err, crawler.ServiceHeld) and "503" in str(err) and not sent, err)
check("the worker's version says it (2.16.0 or later)", tuple(map(int, crawler.WORKER_V.split("."))) >= (2, 16, 0))
print("\n" + ("all passed" if not fails else f"{fails} failed")); sys.exit(1 if fails else 0)
