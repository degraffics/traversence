"""A task stopped by a held service reports "held", never "failed" (2.12.1): the call that trips a hold raises
ServiceHeld, and Common Crawl's task checks its holds first, so a run whose only trouble is a held service ends ok on
Railway, not Crashed. Run: python3 tests/test_held_task.py"""
import os, sys, urllib.request
os.environ.setdefault("TRAVERSENCE_URL", "http://127.0.0.1:1")
os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

fails = 0
def check(name, ok):
    global fails
    print(("  ok  " if ok else "FAIL  ") + name)
    fails += 0 if ok else 1

host = "index.commoncrawl.org"
def boom(*a, **k):
    raise TimeoutError("timed out")
crawler._urlopen = boom
url = urllib.request.Request(f"https://{host}/collinfo.json")

# first timeout: one strike, the error as it was; second: the hold trips, and that call says held
try:
    crawler._counted_urlopen(url); first = None
except Exception as e:
    first = e
check("one timeout: not held yet, the error as before", isinstance(first, TimeoutError) and host not in crawler.HELD)
try:
    crawler._counted_urlopen(url); second = None
except Exception as e:
    second = e
check("the timeout that trips its hold raises ServiceHeld", isinstance(second, crawler.ServiceHeld) and host in crawler.HELD)

# the task: held, never failed
item = {"task": "commoncrawl", "allotted": 60, "counts": {}}
crawler.HANDLERS["commoncrawl"] = (crawler.common_crawl, None, lambda: "")
crawler.work_task({"task": "commoncrawl", "seconds": 60}, item, 60)
check("Common Crawl held from the start: its task reports held", item.get("outcome") == "held")

# a hold the site sent at the start of the run
crawler.HELD.clear(); crawler.HELD["data.commoncrawl.org"] = "open issue #13"
item = {"task": "commoncrawl", "allotted": 60, "counts": {}}
crawler.work_task({"task": "commoncrawl", "seconds": 60}, item, 60)
check("held by the site (issue #13): held, and no call made", item.get("outcome") == "held" and "#13" in item.get("why", ""))

# an ordinary failure is still a failure
crawler.HELD.clear()
def bad(deadline):
    raise ValueError("a bug")
crawler.HANDLERS["oops"] = (bad, None, lambda: "")
item = {"task": "oops", "allotted": 60, "counts": {}}
crawler.work_task({"task": "oops", "seconds": 60}, item, 60)
check("a real error is still failed", item.get("outcome") == "failed")
print("all passed" if not fails else f"{fails} failed")
sys.exit(1 if fails else 0)
