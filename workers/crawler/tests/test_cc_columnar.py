"""2.15.0 (decisions/0164's note of 2026-11-20, Jason: "Yes, allow and build it"): Common Crawl's bulk reads (our states'
public sites, a source's pages) query its URL Index (Parquet), never the CDXJ index server; the crawl's file map is read a
few files a run and kept on the site; DuckDB runs with one thread; a failed read holds data.commoncrawl.org.
Needs duckdb (the worker's image has it). Run: python3 tests/test_cc_columnar.py"""
import os
import sys
import tempfile
import time
import unittest

os.environ.setdefault("CRAWLER_API_TOKEN", "t")
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import crawler  # noqa: E402
import duckdb  # noqa: E402

ROWS = [  # url_surtkey, url, status, mime, file, offset, length
    ("com,example)/", "https://example.com/", 200, "text/html", "w/a.warc.gz", 10, 100),
    ("com,example)/directory", "https://example.com/directory", 200, "text/html", "w/a.warc.gz", 200, 100),
    ("com,example)/logo.png", "https://example.com/logo.png", 200, "image/png", "w/a.warc.gz", 300, 100),
    ("com,examples)/", "https://examples.com/", 200, "text/html", "w/a.warc.gz", 400, 100),
    ("us,az,apache)/", "https://apache.az.us/", 200, "text/html", "w/b.warc.gz", 10, 100),
    ("us,az,navajo)/parks", "https://navajo.az.us/parks", 200, "text/html", "w/b.warc.gz", 200, 100),
    ("us,az,gila)/old", "https://gila.az.us/old", 404, "text/html", "w/b.warc.gz", 300, 100),
    ("us,nm,santafe)/", "https://santafe.nm.us/", 200, "text/html", "w/c.warc.gz", 10, 100),
]


class Columnar(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        con = duckdb.connect()
        for name, rows in (("part-00000.parquet", ROWS[:4]), ("part-00001.parquet", ROWS[4:7]), ("part-00002.parquet", ROWS[7:])):
            con.execute("CREATE OR REPLACE TABLE t (url_surtkey VARCHAR, url VARCHAR, fetch_status SMALLINT, content_mime_type VARCHAR, warc_filename VARCHAR, warc_record_offset INTEGER, warc_record_length INTEGER)")
            con.executemany("INSERT INTO t VALUES (?, ?, ?, ?, ?, ?, ?)", rows)
            con.execute(f"COPY t TO '{os.path.join(self.dir, name)}' (FORMAT parquet)")
        os.environ["CC_COLUMNAR_CRAWL"] = "CC-MAIN-2026-43"
        crawler.CC_COLUMNAR = self.dir
        self.stored = {}
        self.sent = []
        self.index_asked = []
        self._s = (crawler.site_call, crawler.cc_latest, crawler.cc_list, crawler.cc_page, crawler.allowed, crawler.public_host, crawler.CC_MAP_PER_RUN)

        def site(path, payload=None, timeout=60):
            if payload is None:
                return {"discover": ["AZ"], "hosts": [{"host": "example.com", "read": []}], "urlmap": dict(self.stored)}
            if "urlmap" in payload:
                m = self.stored.setdefault("map", {}); m.update(payload["urlmap"]); self.stored["files"] = payload["files"]
                return {"have": len(m)}
            self.sent.append(payload)
            return {"new_sites": len(payload.get("discovered", [])), "kept": len(payload.get("facts", []))}
        crawler.site_call = site
        crawler.cc_latest = lambda: self.index_asked.append("collinfo") or ("x", "x")
        crawler.cc_list = lambda *a, **k: self.index_asked.append("cdx") or []
        crawler.cc_page = lambda rec: "<html><body><h1>Directory</h1>Main St Diner, 1 Main St, Phoenix AZ 85004, (602) 555-0100</body></html>"
        crawler.allowed = lambda url: True
        crawler.public_host = lambda url: True
        crawler.CC_MAP_PER_RUN = 2
        crawler.HELD.clear(); crawler.ISSUES.clear()

    def tearDown(self):
        (crawler.site_call, crawler.cc_latest, crawler.cc_list, crawler.cc_page, crawler.allowed, crawler.public_host, crawler.CC_MAP_PER_RUN) = self._s
        crawler.CC_COLUMNAR = crawler.CC_DATA
        os.environ.pop("CC_COLUMNAR_CRAWL", None)

    def test_map_then_reads(self):
        crawler.common_crawl(time.monotonic() + 60)
        self.assertEqual(len(self.stored.get("map", {})), 2, "the first run reads 2 files' key ranges and keeps them on the site")
        self.assertEqual(self.sent, [], "no reads until the map is whole")
        crawler.common_crawl(time.monotonic() + 60)
        self.assertEqual(len(self.stored["map"]), 3, "the second run finishes the map")
        self.assertEqual(self.stored["map"]["part-00001.parquet"], ["us,az,apache", "us,az,navajo"], "each file's lowest and highest host key")
        disc = [p for p in self.sent if "state" in p]
        self.assertEqual(len(disc), 1)
        hosts = sorted(d["url"] for d in disc[0]["discovered"])
        self.assertEqual(hosts, ["https://apache.az.us/", "https://navajo.az.us/"], "every site under az.us with a 200 HTML page, nothing from nm.us or a 404")
        src = [p for p in self.sent if p.get("host") == "example.com"]
        self.assertEqual(len(src), 1)
        urls = sorted(r["url"] for r in src[0]["log"])
        self.assertEqual(urls, ["https://example.com/", "https://example.com/directory"], "a source's HTML pages only: not an image, not examples.com")
        self.assertEqual(self.index_asked, [], "the bulk reads never ask the index server")

    def test_surt_and_one_thread(self):
        self.assertEqual(crawler.cc_surt_host("www.Example.com"), "com,example,www")
        self.assertEqual(crawler._cc_duck().execute("SELECT current_setting('threads')").fetchone()[0], 1)

    def test_failed_read_holds(self):
        self.stored.update({"files": ["missing.parquet"], "map": {"missing.parquet": ["com,example", "com,example"]}})
        with self.assertRaises(crawler.ServiceHeld):
            crawler.cc_index_query("CC-MAIN-2026-43", self.stored, "example.com", ["url"], 10)
        self.assertIn("data.commoncrawl.org", crawler.ISSUES)

    def test_slowdown_is_a_rate_wait(self):
        try:
            crawler._cc_held_from(RuntimeError("HTTP Error: HTTP GET error on 'https://data.commoncrawl.org/x' (HTTP 503) SlowDown: Please reduce your request rate."))
        except crawler.ServiceHeld:
            pass
        self.assertEqual(crawler.ISSUES["data.commoncrawl.org"]["kind"], "rate")


if __name__ == "__main__":
    unittest.main()
