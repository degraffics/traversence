"""Our copy only (2.13.0; Jason, 2026-11-19, "Stop entirely"): Overpass is retired. A kind the site's OpenStreetMap copy
holds for the town (the job's "ours") is read from it; a kind or town it lacks is counted as not in our copy and reported
with the run (osm_not_in_copy), never fetched, and the job goes on with what the copy holds. No server is ever asked,
whatever the plan sends. The Geofabrik load sends 500 places a batch and waits out the host's 502, 503 or 504.
Run: python3 tests/test_ours.py"""
import os
import sys
import unittest
import urllib.request

os.environ.setdefault("CRAWLER_API_TOKEN", "t")
os.environ.pop("OVERPASS_FIXTURE", None)
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

EAT = 'nwr["amenity"~"^(restaurant|cafe|fast_food|bar|pub|ice_cream|biergarten)$"]'
SHOP = 'nwr["shop"~"^(bakery|deli|coffee)$"]'
CARE = 'nwr["healthcare"~"^(hospital|clinic|urgent_care)$"]'
PUB = {"type": "node", "id": 1, "lat": 36.72, "lon": -110.25, "tags": {"name": "Kayenta Pub"}}


def job(filters, ours=None, jid=7):
    j = {"job_id": jid, "looking_for": {"group": "town:eat", "osm": filters},
         "place": {"name": "Kayenta, AZ", "towns": [{"town": "Kayenta", "state": "AZ"}], "zips": ["86033"],
                   "zip_points": [{"zip": "86033", "lat": 36.72, "lon": -110.25}], "center": {"lat": 36.72, "lon": -110.25}}}
    if ours is not None:
        j["ours"], j["ours_note"] = ours, "1 of 2 kind(s) from our OpenStreetMap copy"
    return j


class NoNetwork:
    """Any request to an Overpass host fails the test: retired means never asked."""
    def __enter__(self):
        self.old = urllib.request.urlopen
        def guard(req, *a, **k):
            url = req.full_url if hasattr(req, "full_url") else str(req)
            raise AssertionError("a request went out: " + url)
        urllib.request.urlopen = guard
        return self

    def __exit__(self, *a):
        urllib.request.urlopen = self.old


class Ours(unittest.TestCase):
    def setUp(self):
        crawler.OSM_INCOMPLETE.clear(); crawler.OSM_PENDING.clear()
        crawler.OURS_USED["kinds"] = 0
        crawler.NOT_IN_COPY.update({"kinds": 0, "jobs": []})

    def test_no_server_list(self):
        self.assertEqual(crawler.OVERPASS_URLS, [])

    def test_ours_read(self):
        with NoNetwork():
            els = crawler.overpass(job([EAT, SHOP], {EAT: [PUB], SHOP: []}))
        self.assertEqual([e["tags"]["name"] for e in els], ["Kayenta Pub"])
        self.assertEqual(crawler.OURS_USED["kinds"], 2)
        self.assertEqual(crawler.NOT_IN_COPY["kinds"], 0)
        self.assertNotIn(7, crawler.OSM_INCOMPLETE)

    def test_gap_is_not_in_our_copy_never_fetched(self):
        with NoNetwork():
            els = crawler.overpass(job([CARE, EAT], {EAT: [PUB]}))
        self.assertEqual([e["tags"]["name"] for e in els], ["Kayenta Pub"])
        self.assertEqual(crawler.NOT_IN_COPY["kinds"], 1)
        self.assertEqual(crawler.NOT_IN_COPY["jobs"][0]["kinds"], [CARE])
        self.assertNotIn(7, crawler.OSM_INCOMPLETE)                # not waiting for a server: there is none

    def test_town_outside_our_copy(self):
        with NoNetwork():
            els = crawler.overpass(job([CARE, EAT], {}))
        self.assertEqual(els, [])
        self.assertEqual(crawler.NOT_IN_COPY["kinds"], 2)

    def test_coverage_works_every_job(self):
        sent = []
        jobs = [job([CARE], {}, jid=1), job([EAT], {EAT: [PUB]}, jid=2)]

        def site(path, payload=None, timeout=60):
            if payload is None:
                return {"jobs": jobs}
            sent.append(payload)
            return {"auto_imported": 0, "staged": 1}
        old = (crawler.site_call, crawler.wikidata, crawler.candidate, crawler.time.sleep)
        crawler.site_call = site
        crawler.wikidata = lambda j, d=None: []
        crawler.candidate = lambda el, j: {"name": el["tags"]["name"], "where": {"zip": "86033"}}
        crawler.time.sleep = lambda s: None
        try:
            with NoNetwork():
                crawler.coverage(crawler.time.monotonic() + 600)
        finally:
            crawler.site_call, crawler.wikidata, crawler.candidate, crawler.time.sleep = old
        self.assertEqual(sorted(p["job_id"] for p in sent), [1, 2])     # both worked: none goes back waiting for Overpass
        by = {p["job_id"]: p for p in sent}
        self.assertEqual([c["name"] for c in by[2]["candidates"]], ["Kayenta Pub"])


class Geofabrik(unittest.TestCase):
    def test_busy_host_is_waited_out(self):
        calls, waits = [], []
        def site(path, payload=None, timeout=60):
            calls.append(len(payload["osm"]))
            if len(calls) < 3:
                raise RuntimeError("site /api/crawl/loads.php said 503: b'<!DOCTYPE HTML'")
            return {"kept": len(payload["osm"])}
        old = (crawler.site_call, crawler.time.sleep)
        crawler.site_call, crawler.time.sleep = site, waits.append
        try:
            r = crawler.geofabrik_send("rel", "arizona", [{"id": 1}] * 3)
        finally:
            crawler.site_call, crawler.time.sleep = old
        self.assertEqual(r["kept"], 3)
        self.assertEqual(waits, [30, 90])

    def test_other_errors_not_retried(self):
        old = (crawler.site_call, crawler.time.sleep)
        def site(path, payload=None, timeout=60):
            raise RuntimeError("site /api/crawl/loads.php said 422: b'bad'")
        crawler.site_call, crawler.time.sleep = site, lambda s: self.fail("waited on a 422")
        try:
            with self.assertRaises(RuntimeError):
                crawler.geofabrik_send("rel", "arizona", [{"id": 1}])
        finally:
            crawler.site_call, crawler.time.sleep = old

    def test_batch_size(self):
        self.assertEqual(crawler.GEOFABRIK_BATCH, 500)

    def test_health_care_and_guides_in_the_extract(self):   # 2.14.0 (Jason, 2026-11-19: "Yes, widen it")
        self.assertIn("nwr/healthcare", crawler.OSM_TAG_FILTER)
        self.assertIn("nwr/office=guide", crawler.OSM_TAG_FILTER)
        self.assertTrue({"healthcare", "office"} <= crawler.OSM_KEEP_TAGS)

    def test_one_state_a_run(self):                         # 2.14.0: five states due at once aren't a gigabyte in one run
        tried = []
        def site(path, payload=None, timeout=60):
            return {"paused": False, "states": [{"key": "utah", "release": "r"}, {"key": "colorado", "release": "r"}, {"key": "nevada", "release": "r"}]}
        def urlopen(req, *a, **k):
            tried.append(req.full_url)
            raise OSError("no network in tests")
        old = (crawler.site_call, urllib.request.urlopen)
        crawler.site_call, urllib.request.urlopen = site, urlopen
        try:
            crawler.geofabrik_load()
        finally:
            crawler.site_call, urllib.request.urlopen = old
        self.assertEqual(len(tried), 1)
        self.assertTrue(tried[0].endswith("/utah-latest.osm.pbf"))


if __name__ == "__main__":
    unittest.main()
