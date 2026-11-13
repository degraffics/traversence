#!/usr/bin/env python3
"""Tests for check-out (decisions/0161): checkout.py. Its hooks: test_checkout_hooks.py.

    python3 tools/page-audit/test_checkout.py
"""
import datetime, json, os, shutil, subprocess, sys, tempfile, unittest, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.normpath(os.path.join(HERE, '..', '..'))
sys.path.insert(0, HERE)
import checkout  # noqa: E402

WORDS = """<?php
class LocalWords {
    const LIMIT = 5;
    public static function match($q) {
        return strtolower($q);
    }
    public static function other($a, $b = 1) {
        return $a;
    }
}
"""
MANIFEST = """<?php
return [
    'crawler' => [
        'title' => 'Crawler',
    ],
    'pulse' => [
        'title' => 'Pulse',
    ],
];
"""
CALLER = "<?php\necho LocalWords::match('x');\necho LocalWords::other(1);\n"


def run(args, env=None, cwd=None):
    e = dict(os.environ, CHECKOUT_LOCAL='1', **(env or {}))
    e.pop('CLAUDE_CODE_REMOTE_SESSION_ID', None)
    return subprocess.run([sys.executable] + args, capture_output=True, text=True, env=e, cwd=cwd)


class Base(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix='checkout-test-')
        self.mirror = os.path.join(self.tmp, 'mirror')
        for rel, body in {'sync-app/lib/LocalWords.php': WORDS, 'sync-app/admin/manifests.php': MANIFEST,
                          'sync-storage/a.php': CALLER, 'sync-storage/directory/index.php': '<?php echo 1;\n',
                          'sync-app/ui/faq.md': '### Q one\nA.\n'}.items():
            p = os.path.join(self.mirror, rel)
            os.makedirs(os.path.dirname(p), exist_ok=True)
            open(p, 'w').write(body)
        self.docs = os.path.join(self.tmp, 'docs')
        os.makedirs(os.path.join(self.docs, 'site-audit'))
        os.makedirs(os.path.join(self.docs, 'deliveries'))
        os.makedirs(os.path.join(self.docs, 'releases'))
        open(os.path.join(self.docs, 'site-audit', 'pages.md'), 'w').write(
            '# Pages\n\n## 1. Public\n\n| Route | Purpose |\n|---|---|\n| `/directory/` | Get Local |\n| `/` (`index.php`) | Home |\n\n'
            '## Editor zones\n\n| Zone | Files |\n|---|---|\n| `home.after-doors` | `website/index.php` |\n')

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def zip(self, files, name='build.zip'):
        p = os.path.join(self.tmp, name)
        with zipfile.ZipFile(p, 'w') as z:
            z.writestr('release.json', json.dumps({'title': 't', 'why': 'w', 'proposals': [], 'switch': ''}))
            for n, body in files.items():
                z.writestr(n, body)
        return p


class Claims(Base):
    def test_add_is_shared(self):
        d = checkout.empty()
        r1 = checkout.take(d, 'A', [('app/lib/LocalWords.php', [], 'add')], 'new fn')
        r2 = checkout.take(d, 'B', [('app/lib/LocalWords.php', [], 'add')], 'another fn')
        self.assertTrue(r1['taken'] and r2['taken'])
        self.assertEqual(len(d['claims']), 2)

    def test_change_is_exclusive_per_function(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix match')
        self.assertIn('refused', checkout.take(d, 'B', [('app/lib/LocalWords.php', ['match'], 'change')], 'x'))
        self.assertIn('taken', checkout.take(d, 'B', [('app/lib/LocalWords.php', ['other'], 'change')], 'y'))
        self.assertIn('taken', checkout.take(d, 'C', [('app/lib/LocalWords.php', [], 'add')], 'z'))
        # a whole-file change clashes with any change
        self.assertIn('refused', checkout.take(d, 'C', [('app/lib/LocalWords.php', [], 'change')], 'z'))

    def test_all_or_nothing(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix')
        r = checkout.take(d, 'B', [('website/a.php', [], 'add'), ('app/lib/LocalWords.php', ['match'], 'change')], 'x')
        self.assertIn('refused', r)
        self.assertEqual([c['session'] for c in d['claims']], ['A'])

    def test_release_keeps_the_why(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('website/a.php', [], 'add')], 'the real why')
        checkout.take(d, 'A', [('website/a.php', [], 'add'), ('website/b.php', [], 'add')], '', release='x.zip')
        self.assertEqual([(c['why'], c['release']) for c in d['claims']], [('the real why', 'x.zip'), ('release x.zip', 'x.zip')])

    def test_own_claims_merge_and_never_block(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix')
        r = checkout.take(d, 'A', [('app/lib/LocalWords.php', ['other'], 'change')], 'fix')
        self.assertIn('taken', r)
        self.assertEqual(d['claims'][0]['functions'], ['match', 'other'])

    def test_shared_never_claimed(self):
        d = checkout.empty()
        r = checkout.take(d, 'A', [('app/ui/faq.md', [], 'change')], 'faq')
        self.assertEqual(d['claims'], [])
        self.assertTrue(r['skipped'])

    def test_hotspot_change_needs_an_entry(self):
        d = checkout.empty()
        self.assertIn('refused', checkout.take(d, 'A', [('app/admin/manifests.php', [], 'change')], 'x'))
        self.assertIn('taken', checkout.take(d, 'A', [('app/admin/manifests.php', ["'crawler'"], 'change')], 'x'))
        self.assertIn('taken', checkout.take(d, 'B', [('app/admin/manifests.php', ["'pulse'"], 'change')], 'y'))
        self.assertIn('taken', checkout.take(d, 'C', [('app/admin/manifests.php', [], 'add')], 'z'))

    def test_expiry_lapses_and_reports(self):
        d = checkout.empty()
        t0 = checkout.now()
        checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix', at=t0)
        later = t0 + datetime.timedelta(hours=4, minutes=1)
        self.assertIn('taken', checkout.take(d, 'B', [('app/lib/LocalWords.php', ['match'], 'change')], 'mine now', at=later))
        self.assertEqual(d['lapsed'][0]['session'], 'A')

    def test_callers_found_and_noted(self):
        d = checkout.empty()
        r = checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix', mirror=self.mirror)
        self.assertEqual(r['taken'][0]['callers'], {'match': ['website/a.php']})
        r = checkout.take(d, 'B', [('website/a.php', [], 'add')], 'page')
        self.assertTrue(any('LocalWords.php::match is being changed by A' in n for n in r['notes']))

    def test_release_and_release_live(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('website/a.php', [], 'add')], 'x', release='one_20261113.zip')
        checkout.take(d, 'B', [('website/b.php', [], 'add')], 'y', release='two_20261113.zip')
        json.dump({'zip': 'one_20261113.zip', 'status': 'live', 'files': {}}, open(os.path.join(self.docs, 'deliveries', 'one_20261113.json'), 'w'))
        gone, late = checkout.release_live(d, self.docs)
        self.assertEqual([c['session'] for c in gone], ['A'])
        self.assertEqual([c['session'] for c in d['claims']], ['B'])
        self.assertEqual(len(checkout.release_where(d, session='B')), 1)


class Diffs(Base):
    def test_kept_lines_are_an_add(self):
        new = WORDS.replace('    const LIMIT = 5;\n', '    const LIMIT = 5;\n    public static function matchWords($q) { return $q; }\n')
        self.assertEqual(checkout.changed_names('app/lib/LocalWords.php', WORDS, new), ('add', []))

    def test_edit_names_the_function(self):
        new = WORDS.replace('strtolower($q)', 'strtoupper($q)')
        self.assertEqual(checkout.changed_names('app/lib/LocalWords.php', WORDS, new), ('change', ['match']))

    def test_hotspot_edit_names_the_entry(self):
        new = MANIFEST.replace("'title' => 'Crawler'", "'title' => 'The crawler'")
        self.assertEqual(checkout.changed_names('app/admin/manifests.php', MANIFEST, new), ('change', ["'crawler'"]))

    def test_signatures_and_calls(self):
        self.assertEqual(checkout.signatures(WORDS), {'match': (1, 1), 'other': (1, 2)})
        self.assertEqual(checkout.call_counts(CALLER, 'other'), [1])
        self.assertEqual(checkout.call_counts("f(a(1,2), 'x,y', [3,4])", 'f'), [3])


class Pages(Base):
    def test_page_files_and_zones(self):
        self.assertEqual(checkout.page_files('/directory/', docs=self.docs, mirror=self.mirror), ['website/directory/index.php'])
        self.assertEqual(checkout.zones_for('website/index.php', docs=self.docs), ['home.after-doors'])
        with self.assertRaises(SystemExit):
            checkout.page_files('/nowhere/', docs=self.docs)


class Cli(Base):
    def test_claim_list_release_local(self):
        tool = os.path.join(HERE, 'checkout.py')
        cwd = self.tmp
        # the CLI writes the working copy of REPO in local mode: point it at a copy
        shutil.copytree(os.path.join(HERE), os.path.join(self.docs, 'tools', 'page-audit'))
        t = os.path.join(self.docs, 'tools', 'page-audit', 'checkout.py')
        r = run([t, 'claim', 'app/lib/LocalWords.php::match', '--change', '--why', 'fix', '--session', 'A', '--mirror', self.mirror], cwd=cwd)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertIn('called on the live site by 1 file', r.stdout)
        r = run([t, 'claim', 'app/lib/LocalWords.php::match', '--change', '--why', 'mine', '--session', 'B'], cwd=cwd)
        self.assertEqual(r.returncode, 1)
        self.assertIn('being changed by A', r.stdout)
        r = run([t, 'list'], cwd=cwd)
        self.assertIn('LocalWords.php::match (change) by A', r.stdout)
        r = run([t, 'release', '--session', 'A'], cwd=cwd)
        self.assertIn('Released 1', r.stdout)
        self.assertEqual(tool and json.load(open(os.path.join(self.docs, 'releases', 'checkouts.json')))['claims'], [])


class ToMain(Base):
    """The claim is a commit pushed straight to main; a lost push fetches and tries again."""
    def test_push_and_race(self):
        bare = os.path.join(self.tmp, 'remote.git')
        subprocess.run(['git', 'init', '-q', '--bare', '-b', 'main', bare], check=True)
        a, b = os.path.join(self.tmp, 'a'), os.path.join(self.tmp, 'b')
        seed = os.path.join(self.tmp, 'seed')
        subprocess.run(['git', 'init', '-q', '-b', 'main', seed], check=True)
        open(os.path.join(seed, 'x'), 'w').write('x')
        g = ['git', '-C', seed, '-c', 'user.name=t', '-c', 'user.email=t@t']
        subprocess.run(g + ['add', 'x'], check=True); subprocess.run(g + ['commit', '-qm', 'x'], check=True)
        subprocess.run(g + ['push', '-q', bare, 'main'], check=True)
        for c in (a, b):
            subprocess.run(['git', 'clone', '-q', bare, c], check=True)
            subprocess.run(['git', '-C', c, 'config', 'user.name', 't']); subprocess.run(['git', '-C', c, 'config', 'user.email', 't@t'])
        env_old = os.environ.pop('CHECKOUT_LOCAL', None)
        try:
            checkout.commit_to_main(lambda d: checkout.take(d, 'A', [('website/a.php', ['f'], 'change')], 'x'), 'claim A', docs=a)
            # b's origin/main is stale: its push loses, it fetches and sees A's claim, and is refused
            r = checkout.commit_to_main(lambda d: checkout.take(d, 'B', [('website/a.php', ['f'], 'change')], 'y'), 'claim B', docs=b)
            self.assertIn('refused', r)
            checkout.commit_to_main(lambda d: checkout.take(d, 'B', [('website/a.php', ['g'], 'change')], 'y'), 'claim B', docs=b)
            d = checkout.load(docs=a)
            self.assertEqual(sorted(c['session'] for c in d['claims']), ['A', 'B'])
            self.assertFalse(os.path.exists(os.path.join(a, checkout.REL)))   # the working tree is never touched
        finally:
            if env_old is not None:
                os.environ['CHECKOUT_LOCAL'] = env_old


if __name__ == '__main__':
    unittest.main(verbosity=1)
