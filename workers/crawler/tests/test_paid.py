"""2.10.0 (decisions/0163's source map): web search falls to Brave when Tavily fails or is held, each switch logged; Google
Places confirms a business only as the paid backup a director switched on (the plan's allowance), only after nothing free
found it, and only its Maps link goes back. Run: python3 tests/test_paid.py"""
import io
import json
import os
import sys
import tempfile
import unittest

os.environ.setdefault("CRAWLER_API_TOKEN", "t")
os.environ["TAVILY_API_KEY"] = "x"
os.environ["BRAVE_API_KEY"] = "y"
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

LST = {"name": "Kayenta Trading Post", "address": "12 Main St", "city": "Kayenta", "state": "AZ", "zip": "86033"}


class Resp(io.BytesIO):
    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False


class Search(unittest.TestCase):
    def setUp(self):
        self.calls = []
        self._open = crawler.urllib.request.urlopen
        crawler.search_allow = 100
        crawler.searches = 0
        crawler.last_search = 0.0
        crawler.SEARCH_SWITCHES.clear(); crawler.HELD.clear()
        crawler.time_sleep = None

    def tearDown(self):
        crawler.urllib.request.urlopen = self._open
        crawler.HELD.clear()

    def fake(self, tavily_ok):
        def op(req, timeout=None):
            url = req.full_url
            self.calls.append(url)
            if "tavily" in url:
                if not tavily_ok:
                    raise crawler.urllib.error.URLError("down")
                return Resp(json.dumps({"results": [{"url": "https://a.example", "title": "A", "content": "x"}]}).encode())
            return Resp(json.dumps({"web": {"results": [{"url": "https://b.example", "title": "B", "description": "y"}]}}).encode())
        crawler.urllib.request.urlopen = op

    def test_tavily_first(self):
        self.fake(True)
        r = crawler.search("q")
        self.assertEqual([x["url"] for x in r], ["https://a.example"])
        self.assertEqual(len(self.calls), 1)

    def test_brave_when_tavily_fails(self):
        self.fake(False)
        r = crawler.search("q")
        self.assertEqual([x["url"] for x in r], ["https://b.example"])
        self.assertEqual(crawler.SEARCH_SWITCHES, {"brave": 1})

    def test_brave_when_tavily_held(self):
        self.fake(True)
        crawler.HELD["api.tavily.com"] = "quota"
        r = crawler.search("q")
        self.assertEqual([x["url"] for x in r], ["https://b.example"])
        self.assertFalse(any("tavily" in c for c in self.calls))


class Google(unittest.TestCase):
    def setUp(self):
        self.fx = tempfile.NamedTemporaryFile("w", suffix=".json", delete=False)
        json.dump({LST["name"]: {"places": [{"id": "ChIJ1", "displayName": {"text": "Kayenta Trading Post"},
                                             "formattedAddress": "12 Main Street, Kayenta, AZ 86033", "businessStatus": "OPERATIONAL"}]}}, self.fx)
        self.fx.close()
        os.environ["PLACES_FIXTURE"] = self.fx.name
        crawler.PAID["places_confirm"] = 0
        crawler.PAID_USED["places_confirm"] = 0

    def tearDown(self):
        os.environ.pop("PLACES_FIXTURE", None)
        os.unlink(self.fx.name)

    def test_off_without_override(self):
        self.assertFalse(crawler.paid_left("places_confirm"))

    def test_confirms_within_allowance(self):
        crawler.PAID["places_confirm"] = 1
        self.assertTrue(crawler.paid_left("places_confirm"))
        f = crawler.google_confirm(LST)
        self.assertEqual(len(f), 1)
        self.assertEqual(f[0]["kind"], "evidence")
        self.assertIn("google.com/maps", f[0]["url"])
        self.assertEqual(crawler.PAID_USED["places_confirm"], 1)
        self.assertFalse(crawler.paid_left("places_confirm"))   # the cap holds

    def test_other_address_not_confirmed(self):
        crawler.PAID["places_confirm"] = 5
        self.assertEqual(crawler.google_confirm({**LST, "address": "400 Elm St"}), [])


if __name__ == "__main__":
    unittest.main()
