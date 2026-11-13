#!/usr/bin/env python3
"""Tests for check-out's hooks (decisions/0161 §4, §6, §8): zipcheck.py, send.py and deliveries.py.

    python3 tools/page-audit/test_checkout_hooks.py
"""
import json, os, shutil, sys, unittest

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import checkout  # noqa: E402
from test_checkout import Base, WORDS, CALLER, run  # noqa: E402


class ZipCheck(Base):
    """zipcheck.py with claims: another session's change claim and a broken live caller block; adds never do."""
    def check(self, z, session='B', extra=()):
        claims = os.path.join(self.docs, 'releases', 'checkouts.json')
        if not os.path.exists(claims):
            checkout.write_local(checkout.empty(), self.docs)
        return run([os.path.join(HERE, 'zipcheck.py'), z, '--mirror', self.mirror, '--docs', self.docs, '--session', session] + list(extra))

    def claim_a(self):
        d = checkout.empty()
        checkout.take(d, 'A', [('app/lib/LocalWords.php', ['match'], 'change')], 'fix match')
        checkout.write_local(d, self.docs)

    def test_other_sessions_change_claim_blocks(self):
        self.claim_a()
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('strtolower', 'trim')})
        r = self.check(z)
        self.assertEqual(r.returncode, 1, r.stdout + r.stderr)
        self.assertIn('checked out by another session', r.stdout)
        self.assertEqual(self.check(z, session='A').returncode, 0, self.check(z, session='A').stdout)

    def test_add_beside_never_blocks(self):
        self.claim_a()
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('    const LIMIT = 5;\n', '    const LIMIT = 5;\n    public static function matchWords($q) { return $q; }\n')})
        r = self.check(z)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertNotIn('Warning check-out', r.stdout)

    def test_unclaimed_change_warns(self):
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('return $a;', 'return $a + 1;')})
        r = self.check(z)
        self.assertIn('no change claim', r.stdout)

    def test_broken_caller_blocks(self):
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('function match($q)', 'function match($q, $mode)')})
        r = self.check(z, session='A')
        self.assertEqual(r.returncode, 1, r.stdout)
        self.assertIn('match() called by website/a.php', r.stdout)
        # moving the caller in the same zip fixes it
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('function match($q)', 'function match($q, $mode)'),
                      'website/a.php': CALLER.replace("match('x')", "match('x', 1)")}, 'b2.zip')
        self.assertNotIn('a live caller would break', self.check(z, session='A').stdout)
        # an optional parameter at the end keeps old calls working
        z = self.zip({'app/lib/LocalWords.php': WORDS.replace('function match($q)', 'function match($q, $mode = 0)')}, 'b3.zip')
        self.assertNotIn('a live caller would break', self.check(z, session='A').stdout)


class SendAndVerify(Base):
    def test_send_takes_claims_and_verify_releases(self):
        # send.py and deliveries.py write next to themselves: run copies inside the fake docs repo
        tools = os.path.join(self.docs, 'tools', 'page-audit')
        shutil.copytree(HERE, tools)
        os.makedirs(os.path.join(self.docs, 'releases', 'inbox'), exist_ok=True)
        checkout.write_local(checkout.empty(), self.docs)
        newbody = WORDS.replace('strtolower', 'trim')
        z = self.zip({'app/lib/LocalWords.php': newbody}, 'fix_20261113.zip')
        r = run([os.path.join(tools, 'send.py'), z, '--mirror', self.mirror, '--note', 'n built on Sync: x', '--session', 'A'])
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        d = json.load(open(os.path.join(self.docs, 'releases', 'checkouts.json')))
        self.assertEqual([(c['path'], c['functions'], c['kind'], c['release']) for c in d['claims']],
                         [('app/lib/LocalWords.php', ['match'], 'change', 'fix_20261113.zip')])
        # another session's send of the same function is refused before the zip check
        z2 = self.zip({'app/lib/LocalWords.php': WORDS.replace('strtolower', 'ucfirst')}, 'other_20261113.zip')
        r = run([os.path.join(tools, 'send.py'), z2, '--mirror', self.mirror, '--note', 'n', '--session', 'B'])
        self.assertEqual(r.returncode, 1)
        self.assertIn('being changed by A', r.stdout)
        # the release goes live: verify releases the claim
        open(os.path.join(self.mirror, 'sync-app/lib/LocalWords.php'), 'w').write(newbody)
        r = run([os.path.join(tools, 'deliveries.py'), 'verify', '--mirror', self.mirror])
        self.assertIn('Released (live)', r.stdout, r.stdout + r.stderr)
        self.assertEqual(json.load(open(os.path.join(self.docs, 'releases', 'checkouts.json')))['claims'], [])


if __name__ == '__main__':
    unittest.main(verbosity=1)
