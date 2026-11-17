"""2.11.0: a website that doesn't load for the refresh is looked up in Common Crawl's newest crawl; with a copy, the site
hears "archived" with its capture time, and only an empty description is offered from it. Run: python3 tests/test_refresh_archive.py"""
import json
import os
import sys
import tempfile
import unittest

os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

PAGE = '<html><head><meta name="description" content="Family diner on Main Street since 1962, open daily."></head><body>Hi</body></html>'


class Archive(unittest.TestCase):
    def setUp(self):
        self.fx = tempfile.NamedTemporaryFile("w", suffix=".json", delete=False)
        json.dump({"https://gone.example/": {"html": PAGE, "at": "20261020101010"}}, self.fx); self.fx.close()
        os.environ["CC_FIXTURE"] = self.fx.name
        self.sent = []
        self._site, self._fetch, self._allowed = crawler.site_call, crawler.fetch_page, crawler.allowed
        lsts = [{"entity_id": 1, "name": "Gone Diner", "website": "https://gone.example/", "missing": ["description"], "address": "1 Main St"},
                {"entity_id": 2, "name": "Really Gone", "website": "https://nothing.example/", "missing": [], "address": "2 Main St"}]

        def site(path, payload=None, timeout=60):
            if payload is None:
                return {"listings": lsts}
            self.sent.append(payload)
            return {"site": "ok"}
        crawler.site_call = site
        crawler.fetch_page = lambda url: None
        crawler.allowed = lambda url: True

    def tearDown(self):
        crawler.site_call, crawler.fetch_page, crawler.allowed = self._site, self._fetch, self._allowed
        os.environ.pop("CC_FIXTURE", None)
        os.unlink(self.fx.name)

    def test_archive_then_unreachable(self):
        crawler.refresh_listings(crawler.time.monotonic() + 300)
        self.assertEqual(self.sent[0]["site"], {"status": "archived", "archive": {"crawl": "CC-TEST", "at": "20261020101010"}})
        self.assertIn("Family diner", self.sent[0]["found"]["description"])
        self.assertEqual(self.sent[1]["site"]["status"], "unreachable")   # no copy: as before


if __name__ == "__main__":
    unittest.main()
