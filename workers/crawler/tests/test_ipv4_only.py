"""ipv4_only: names resolve to IPv4 only inside it, and the lookup is put back after (2.7.3, Geofabrik from Railway)."""
import os
import socket
import sys
import unittest
from unittest import mock

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402


class Ipv4Only(unittest.TestCase):
    def test_asks_for_ipv4_and_restores(self):
        seen = []
        def fake(host, port, family=0, *a, **k):
            seen.append(family)
            return [(family or socket.AF_INET6, socket.SOCK_STREAM, 6, "", ("::1", port))]
        with mock.patch.object(socket, "getaddrinfo", fake):
            with crawler.ipv4_only():
                socket.getaddrinfo("example.org", 443)
            socket.getaddrinfo("example.org", 443)
        self.assertEqual(seen, [socket.AF_INET, 0])

    def test_geofabrik_names(self):
        urls = []
        class Resp:
            status = 206
            def read(self): return b"x" * 10
            def __enter__(self): return self
            def __exit__(self, *a): return False
        def fake_open(req, timeout=0):
            urls.append(req.full_url); return Resp()
        with mock.patch.object(crawler.urllib.request, "urlopen", fake_open), mock.patch.object(crawler, "site_call", lambda *a, **k: {}), \
             mock.patch.object(crawler.socket, "getaddrinfo", lambda *a, **k: [(socket.AF_INET, 1, 6, "", ("1.2.3.4", 443))]), \
             mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop("OSM_EXTRACT_BASE", None)
            crawler.geofabrik_probe()
        self.assertEqual(urls, ["https://download.geofabrik.de/north-america/us/arizona-latest.osm.pbf",
                                "https://download.geofabrik.de/north-america/us/new-mexico-latest.osm.pbf"])


if __name__ == "__main__":
    unittest.main()
