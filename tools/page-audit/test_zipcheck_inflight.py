#!/usr/bin/env python3
"""zipcheck's in-flight guard (the session manager, 2026-11-23): today's order of events, reproduced.

board-fixes-4 (#269) was sent from its branch at 15:49 UTC: its delivery record sat on claude/board-fixes-2 and its
claims (tools.php::config/alarms, kit-css.php) were on main. orders-release-a (#270) claimed tools.php::backoffice/orders
and (top), took kit-css.php as an add, and was checked at 18:33 from its own branch. The check read only the working
copy's deliveries/*.json, saw nothing, and passed; #269 merged at 18:37:14, #270 at 18:37:33, and #270's whole files
took #269's lines out of tools.php, kit-css.php and faq.md. Checked the same way now, #270 must block, naming #269 and
all three files. Run: python3 tools/page-audit/test_zipcheck_inflight.py
"""
import json, os, shutil, subprocess, sys, tempfile, unittest, zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import zipcheck  # noqa: E402

BOARD = 'session_board'
ME = 'session_orders'
FILES = ['app/admin/tools.php', 'app/admin/kit-css.php', 'app/ui/faq.md']


def git(d, *a, env=None):
    return subprocess.run(['git', '-C', d, *a], check=True, capture_output=True, text=True,
                          env=dict(os.environ, **(env or {}))).stdout.strip()


def record(files, status='pending'):
    return {'zip': 'x.zip', 'status': status, 'files': {p: {'live': status == 'live'} for p in files}}


def claim(session, path, fns, kind, release):
    return {'path': path, 'functions': fns, 'kind': kind, 'session': session, 'why': 'test', 'since': '2026-11-23T15:06:08Z',
            'expires': '2099-01-01T00:00:00Z', 'release': release, 'callers': {}}


class InFlight(unittest.TestCase):
    def setUp(self):
        self.t = tempfile.mkdtemp()
        self.origin = os.path.join(self.t, 'origin.git')
        subprocess.run(['git', 'init', '-q', '--bare', '-b', 'main', self.origin], check=True)
        self.seed = os.path.join(self.t, 'seed')
        subprocess.run(['git', 'clone', '-q', self.origin, self.seed], check=True, capture_output=True)
        for d in (self.seed,):
            git(d, 'config', 'user.email', 't@t'); git(d, 'config', 'user.name', 't')
        git(self.seed, 'checkout', '-q', '-b', 'main')
        os.makedirs(os.path.join(self.seed, 'deliveries')); os.makedirs(os.path.join(self.seed, 'releases'))
        self.write(self.seed, 'deliveries/README.md', 'log\n')
        self.claims(self.seed, [claim(BOARD, 'app/admin/tools.php', ['config/alarms'], 'change', 'board-fixes-4.zip'),
                                claim(BOARD, 'app/admin/kit-css.php', [], 'change', 'board-fixes-4.zip')])
        self.commit(self.seed, 'main: the board\'s claims (15:06)'); git(self.seed, 'push', '-q', 'origin', 'main')
        # the board sends board-fixes-4 from its branch: the record is there, not on main
        git(self.seed, 'checkout', '-q', '-b', 'claude/board-fixes-2')
        self.write(self.seed, 'deliveries/board-fixes-4.json', json.dumps(record(FILES)))
        self.commit(self.seed, 'board-fixes: sent as board-fixes-4.zip (15:49)')
        git(self.seed, 'push', '-q', 'origin', 'claude/board-fixes-2')
        # this session works on its own branch from main
        self.docs = os.path.join(self.t, 'docs')
        subprocess.run(['git', 'clone', '-q', self.origin, self.docs], check=True, capture_output=True)
        git(self.docs, 'checkout', '-q', '-b', 'claude/orders-release-a', 'origin/main')
        self.zip = os.path.join(self.t, 'orders-release-a.zip')
        with zipfile.ZipFile(self.zip, 'w') as z:
            for p in FILES + ['app/lib/ClientOrders.php']:
                z.writestr(p, 'x')
            z.writestr('release.json', '{}')

    def tearDown(self):
        shutil.rmtree(self.t, ignore_errors=True)

    def write(self, d, rel, body):
        with open(os.path.join(d, rel), 'w') as f:
            f.write(body)

    def claims(self, d, cs):
        self.write(d, 'releases/checkouts.json', json.dumps({'about': '', 'claims': cs, 'lapsed': []}))

    def commit(self, d, msg, env=None):
        git(d, 'add', '-A'); git(d, 'commit', '-qm', msg, env=env)

    def claims_on_main(self):
        return json.loads(git(self.docs, 'show', 'origin/main:releases/checkouts.json'))

    def check(self, allowed=(), claims=None):
        return zipcheck.others_pending(self.docs, self.zip, set(allowed), ME,
                                       claims if claims is not None else self.claims_on_main())

    def test_the_old_check_saw_nothing(self):
        """What ran at 18:33: only this copy's deliveries/*.json, and the board's record wasn't in it."""
        self.assertFalse(os.path.exists(os.path.join(self.docs, 'deliveries', 'board-fixes-4.json')))

    def test_todays_case_blocks_with_every_file(self):
        hits = self.check()
        self.assertEqual([h[0] for h in hits], ['board-fixes-4'])
        name, files, where = hits[0]
        self.assertEqual(files, sorted(FILES))   # faq.md too: add-only is no exemption from a whole-file overwrite
        self.assertIn('origin/claude/board-fixes-2', where)
        self.assertIn('claimed by ' + BOARD, where)

    def test_claims_alone_block_before_any_record(self):
        """Different functions in tools.php and an 'add' on kit-css.php still overwrite whole files."""
        git(self.seed, 'push', '-q', 'origin', '--delete', 'claude/board-fixes-2')
        hits = self.check()
        self.assertEqual(hits, [('board-fixes-4', ['app/admin/kit-css.php', 'app/admin/tools.php'], 'claimed by ' + BOARD)])

    def test_record_on_main_blocks_too(self):
        git(self.seed, 'checkout', '-q', 'main'); git(self.seed, 'merge', '-q', 'claude/board-fixes-2')
        git(self.seed, 'push', '-q', 'origin', 'main')
        self.assertEqual(self.check()[0][0], 'board-fixes-4')

    def test_passes_once_the_board_is_live(self):
        git(self.seed, 'checkout', '-q', 'main'); git(self.seed, 'merge', '-q', 'claude/board-fixes-2')
        self.write(self.seed, 'deliveries/board-fixes-4.json', json.dumps(record(FILES, 'live')))
        self.commit(self.seed, 'Deliveries: board-fixes-4.zip live'); git(self.seed, 'push', '-q', 'origin', 'main')
        self.assertEqual(self.check(), [])   # its claims still listed until the check-in: a live release doesn't count

    def test_allow_pending_names_it_once_built_on(self):
        self.assertEqual(self.check(allowed={'board-fixes-4'}), [])

    def test_my_own_claims_never_block(self):
        cs = {'claims': [claim(ME, 'app/admin/tools.php', ['(top)'], 'change', 'orders-release-a.zip'),
                         claim(ME, 'app/lib/ClientOrders.php', [], 'change', 'other-of-mine.zip')]}
        git(self.seed, 'push', '-q', 'origin', '--delete', 'claude/board-fixes-2')
        self.assertEqual(self.check(claims=cs), [])

    def test_an_old_branch_is_not_in_flight(self):
        git(self.seed, 'push', '-q', 'origin', '--delete', 'claude/board-fixes-2')
        git(self.seed, 'checkout', '-q', '-b', 'claude/abandoned', 'main')
        self.write(self.seed, 'deliveries/old-thing.json', json.dumps(record(['app/admin/tools.php'])))
        old = '2026-01-01T00:00:00+00:00'
        self.commit(self.seed, 'an old send', env={'GIT_COMMITTER_DATE': old, 'GIT_AUTHOR_DATE': old})
        git(self.seed, 'push', '-q', 'origin', 'claude/abandoned')
        self.assertEqual(self.check(claims={'claims': []}), [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
