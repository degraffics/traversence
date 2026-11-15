"""osm_places: a state file's places for the site's kinds, one each, with a point (2.7.4, Geofabrik's weekly load)."""
import os
import shutil
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402

FIX = os.path.join(os.path.dirname(__file__), "fixtures", "places.osm")


@unittest.skipUnless(shutil.which("osmium"), "osmium-tool not installed")
class OsmPlaces(unittest.TestCase):
    def test_places(self):
        with tempfile.TemporaryDirectory() as d:
            pbf = os.path.join(d, "t.osm.pbf")
            subprocess.run(["osmium", "cat", "-o", pbf, FIX], check=True)
            got = list(crawler.osm_places(pbf, d))
        refs = [(p["type"], p["id"]) for p in got]
        self.assertEqual(sorted(refs), [("node", 1), ("node", 6), ("way", 10)])   # the road is out; the closed way once
        way = [p for p in got if p["type"] == "way"][0]
        self.assertAlmostEqual(way["lat"], 34.505); self.assertAlmostEqual(way["lon"], -109.305)


if __name__ == "__main__":
    unittest.main()
