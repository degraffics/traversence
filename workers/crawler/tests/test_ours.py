"""Ours first (2.9.0, decisions/0163's source map): a kind the site's OpenStreetMap copy holds for the town (the job's
"ours") is read from it, never asked of Overpass; Overpass only for the rest; with every server held, the job goes on with
what our copy answered (partial), and a job our copy can't answer goes back with its lease. Run: python3 tests/test_ours.py"""
import os
import sys
import unittest

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


class Ours(unittest.TestCase):
    def setUp(self):
        self.asked = []
        self.all_held = False
        crawler.OSM_INCOMPLETE.clear(); crawler.OSM_RECENT.clear(); crawler.OSM_PENDING.clear()
        crawler.OURS_USED["kinds"] = 0

        def one(j, pts, flt, deadline):
            self.asked.append(flt)
            if self.all_held:
                raise crawler.ServiceHeld("every OpenStreetMap server is held: overpass-api.de (406)")
            return [{"type": "node", "id": 99, "lat": 36.7, "lon": -110.2, "tags": {"name": "From Overpass"}}]
        self._one, self._held = crawler.overpass_one, crawler.osm_all_held
        crawler.overpass_one = one
        crawler.osm_all_held = lambda: self.all_held

    def tearDown(self):
        crawler.overpass_one, crawler.osm_all_held = self._one, self._held

    def test_ours_never_asks_overpass(self):
        els = crawler.overpass(job([EAT, SHOP], {EAT: [PUB], SHOP: []}))
        self.assertEqual(self.asked, [])
        self.assertEqual([e["tags"]["name"] for e in els], ["Kayenta Pub"])
        self.assertEqual(crawler.OURS_USED["kinds"], 2)
        self.assertNotIn(7, crawler.OSM_INCOMPLETE)
        self.assertEqual(crawler.OSM_PENDING[7], {})          # nothing recorded as asked of Overpass

    def test_gap_goes_to_overpass(self):
        els = crawler.overpass(job([CARE, EAT], {EAT: [PUB]}))
        self.assertEqual(self.asked, [CARE])
        self.assertEqual(sorted(e["tags"]["name"] for e in els), ["From Overpass", "Kayenta Pub"])

    def test_held_keeps_ours_partial(self):
        self.all_held = True
        els = crawler.overpass(job([CARE, EAT], {EAT: [PUB]}))   # the gap first in the job: ours still read first
        self.assertEqual([e["tags"]["name"] for e in els], ["Kayenta Pub"])
        self.assertEqual(self.asked, [])
        self.assertIn(7, crawler.OSM_INCOMPLETE)               # sent as partial: the gap goes round again

    def test_held_no_ours_goes_back(self):
        self.all_held = True
        with self.assertRaises(crawler.ServiceHeld):
            crawler.overpass(job([CARE]))

    def test_coverage_works_on_while_held(self):
        self.all_held = True
        sent = []
        jobs = [job([CARE], jid=1), job([EAT], {EAT: [PUB]}, jid=2)]

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
            crawler.coverage(crawler.time.monotonic() + 600)
        finally:
            crawler.site_call, crawler.wikidata, crawler.candidate, crawler.time.sleep = old
        self.assertEqual([p["job_id"] for p in sent], [2])      # job 1 waits with its lease; job 2 worked from ours
        self.assertEqual([c["name"] for c in sent[0]["candidates"]], ["Kayenta Pub"])

    def test_coverage_held_when_nothing_worked(self):
        self.all_held = True
        old = (crawler.site_call, crawler.wikidata)
        crawler.site_call = lambda path, payload=None, timeout=60: {"jobs": [job([CARE], jid=1)]}
        crawler.wikidata = lambda j, d=None: []
        try:
            with self.assertRaises(crawler.ServiceHeld):
                crawler.coverage(crawler.time.monotonic() + 600)
        finally:
            crawler.site_call, crawler.wikidata = old


if __name__ == "__main__":
    unittest.main()
