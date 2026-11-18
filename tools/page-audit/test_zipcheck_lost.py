#!/usr/bin/env python3
"""zipcheck's lost-lines guard (the session manager, 2026-11-18): today's case, reproduced in a small mirror.

control-board-3f was built on Sync 22:06 and sent; comms-gaps (#147) added FAQ entries and went live at 22:18; 3f went
live at 22:26 with its 22:06 copy of faq.md and took #147's lines out. Checked again before its merge, 3f must block, and
pass once its faq.md is rebuilt on the newest sync. Run: python3 tools/page-audit/test_zipcheck_lost.py
"""
import json, os, shutil, subprocess, sys, tempfile, unittest, zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import zipcheck  # noqa: E402

BASE = "### What is Traversence?\nA guide to the Four Corners.\n\n### Can I pass a message on?\nNot yet.\n"
COMMS = "### What is Traversence?\nA guide to the Four Corners.\n\n### Can I pass a message on?\nYes: Pass it on, on any row.\n\n### Can I report, block or mute someone?\nYes, from their message.\n"


def git(d, *a):
    return subprocess.run(['git', '-C', d, *a], check=True, capture_output=True, text=True).stdout.strip()


class LostLines(unittest.TestCase):
    def setUp(self):
        self.t = tempfile.mkdtemp()
        self.m = os.path.join(self.t, 'mirror'); os.makedirs(os.path.join(self.m, 'sync-app', 'ui'))
        git(self.m, 'init', '-q'); git(self.m, 'config', 'user.email', 't@t'); git(self.m, 'config', 'user.name', 't')
        self.faq = os.path.join(self.m, 'sync-app', 'ui', 'faq.md')
        open(self.faq, 'w').write(BASE); git(self.m, 'add', '-A'); git(self.m, 'commit', '-qm', 'Sync: 22:06 UTC')
        self.s0606 = git(self.m, 'rev-parse', '--short', 'HEAD')
        open(self.faq, 'w').write(COMMS); git(self.m, 'commit', '-qam', 'Sync: 22:18 UTC')   # #147 live
        self.docs = os.path.join(self.t, 'docs'); os.makedirs(os.path.join(self.docs, 'deliveries'))

    def tearDown(self):
        shutil.rmtree(self.t, ignore_errors=True)

    def zip(self, name, faq, built_on):
        p = os.path.join(self.t, name + '.zip')
        with zipfile.ZipFile(p, 'w') as z:
            z.writestr('app/ui/faq.md', faq)
        json.dump({'zip': name + '.zip', 'built_on': built_on + ' Sync: x', 'status': 'pending', 'files': {}}, open(os.path.join(self.docs, 'deliveries', name + '.json'), 'w'))
        return p

    def test_todays_case_blocks(self):
        mine = BASE + "\n### What is the System Control Board?\nThe control room.\n"   # 3f's own entry, on the 22:06 copy
        lost = zipcheck.lost_lines(self.docs, self.zip('control-board-3f', mine, self.s0606), self.m)
        self.assertEqual(len(lost), 1)
        n, base, gone, syncs = lost[0]
        self.assertEqual(n, 'app/ui/faq.md')
        self.assertIn('### Can I report, block or mute someone?', gone)
        self.assertIn('Yes: Pass it on, on any row.', gone)
        self.assertTrue(any('22:18' in s for s in syncs))

    def test_rebuilt_on_the_newest_sync_passes(self):
        mine = COMMS + "\n### What is the System Control Board?\nThe control room.\n"
        self.assertEqual(zipcheck.lost_lines(self.docs, self.zip('control-board-3f', mine, self.s0606), self.m), [])

    def test_a_line_the_zip_itself_rewords_is_its_own_business(self):
        mine = COMMS.replace('A guide to the Four Corners.', 'A guide to the Four Corners region.')   # a base line, changed on purpose
        self.assertEqual(zipcheck.lost_lines(self.docs, self.zip('words', mine, self.s0606), self.m), [])

    def test_no_record_no_base_nothing_to_compare(self):
        p = os.path.join(self.t, 'new.zip')
        with zipfile.ZipFile(p, 'w') as z:
            z.writestr('app/ui/faq.md', BASE)
        self.assertEqual(zipcheck.lost_lines(self.docs, p, self.m), [])
        self.assertEqual(len(zipcheck.lost_lines(self.docs, p, self.m, base=self.s0606)), 1)   # --base names it


if __name__ == '__main__':
    unittest.main(verbosity=2)
