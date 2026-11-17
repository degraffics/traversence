"""2.12.0 (decisions/0163's source map): the identity check starts from the site's own copies (the job's "local") and looks
outside only for what's still missing; a Census geocoder that didn't answer holds the map-points task (it waits); a
Wikidata failure sends the job round again (partial). Run: python3 tests/test_fallbacks.py"""
import os
import sys
import unittest

os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402


class Fallbacks(unittest.TestCase):
    def setUp(self):
        self.saved = {k: getattr(crawler, k) for k in ("site_call", "npi_lookup", "search", "own_site_facts")}
        self.sent = []

    def tearDown(self):
        for k, v in self.saved.items():
            setattr(crawler, k, v)

    def test_local_first(self):
        lst = {"job_id": 5, "name": "Kayenta Clinic", "city": "Kayenta", "state": "AZ", "zip": "86033", "address": "1 Main St",
               "need": ["phone"], "local": [{"fact": "phone", "value": "9285550100", "url": "https://npiregistry.cms.hhs.gov/provider-view/1",
                                             "kind": "registry", "evidence": "address", "label": "NPI Registry (our copy)"}]}

        def site(path, payload=None, timeout=60):
            if payload is None:
                return {"listings": [lst]}
            self.sent.append(payload)
            return {}
        crawler.site_call = site
        crawler.npi_lookup = lambda l: self.fail("asked the NPI Registry though our copy had the phone")
        crawler.search = lambda q: self.fail("searched though our copy had the phone")
        crawler.identity_jobs(crawler.time.monotonic() + 60)
        self.assertEqual(self.sent[0]["checked"], ["local"])
        self.assertEqual(self.sent[0]["found"][0]["value"], "9285550100")

    def test_geocode_waits(self):
        crawler.site_call = lambda path, payload=None, timeout=60: {"waits": True, "why": "The Census geocoder didn't answer", "left": 40}
        with self.assertRaises(crawler.ServiceHeld):
            crawler.geocode(crawler.time.monotonic() + 60)

    def test_wikidata_failure_partial(self):
        crawler.OSM_INCOMPLETE.clear()
        os.environ.pop("WIKIDATA_FIXTURE", None)
        old = crawler.urllib.request.urlopen

        def boom(*a, **k):
            raise crawler.urllib.error.URLError("busy")
        crawler.urllib.request.urlopen = boom
        try:
            job = {"job_id": 9, "looking_for": {"wikidata": ["Q16917"]}, "place": {"center": {"lat": 36.7, "lon": -110.2}}}
            self.assertEqual(crawler.wikidata(job, crawler.time.monotonic() + 120), [])
        finally:
            crawler.urllib.request.urlopen = old
        self.assertIn(9, crawler.OSM_INCOMPLETE)


if __name__ == "__main__":
    unittest.main()
