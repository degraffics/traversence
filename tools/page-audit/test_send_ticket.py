#!/usr/bin/env python3
"""No release without its Work-queue ticket (Jason, 2026-11-28: "Work queue is our only development lane so never bypass it!").

send.py refuses a zip whose release.json has no "ticket" before anything is claimed, checked, recorded or put in the inbox;
an id, "#id" or a seeded ticket's exact title passes the rule. Run: python3 tools/page-audit/test_send_ticket.py
"""
import json, os, subprocess, sys, tempfile, unittest, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import ticket  # noqa: E402

DOCS = os.path.normpath(os.path.join(HERE, '..', '..'))


def send(plan):
    """Run send.py on a one-file zip with this release.json, against an empty mirror: (exit code, what it said)."""
    t = tempfile.mkdtemp()
    z = os.path.join(t, 'ticket-rule-test.zip')
    with zipfile.ZipFile(z, 'w') as f:
        f.writestr('release.json', json.dumps(plan))
        f.writestr('app/lib/Nothing.php', '<?php\n')
    os.makedirs(os.path.join(t, 'mirror'))
    r = subprocess.run([sys.executable, os.path.join(HERE, 'send.py'), z, '--mirror', os.path.join(t, 'mirror'), '--note', 'test'],
                       capture_output=True, text=True, env={**os.environ, 'CLAUDE_CODE_REMOTE_SESSION_ID': '', 'CLAUDE_SESSION_ID': ''})
    return r.returncode, r.stdout + r.stderr


class TicketRule(unittest.TestCase):
    def test_refused_without_a_ticket(self):
        for plan in ({'title': 't', 'why': 'w', 'changes': [{'what': 'a', 'where': 'b'}]}, {'title': 't', 'ticket': ''}, {'title': 't', 'ticket': 0},
                     {'title': 't', 'ticket': None}, {'title': 't', 'ticket': -4}, {'title': 't', 'ticket': 'a title nobody seeded'}, {'title': 't', 'ticket': True}):
            code, said = send(plan)
            self.assertNotEqual(code, 0, plan)
            self.assertIn('Refused (nothing was claimed, nothing sent)', said, plan)
            self.assertIn('ticket', said)

    def test_the_refusal_says_the_way_forward(self):
        _, said = send({'title': 't'})
        self.assertIn('https://traversence.com/admin/control-board', said)   # never a dead end
        self.assertIn('Add it to the Work queue first', said)

    def test_nothing_is_recorded_or_put_in_the_inbox(self):
        send({'title': 't'})
        self.assertFalse(os.path.exists(os.path.join(DOCS, 'deliveries', 'ticket-rule-test.json')))
        self.assertFalse(os.path.exists(os.path.join(DOCS, 'releases', 'inbox', 'ticket-rule-test.zip')))

    def test_a_ticket_passes_the_rule(self):
        title = sorted(ticket.seeded())[0]
        for v in (7, '7', '#131', title, ' ' + title + ' '):
            self.assertEqual(ticket.check(v), '', v)

    def test_the_seeds_titles_are_known(self):
        s = ticket.seeded()
        self.assertIn('task-seed-5: every current item a ticket under its goal, in its lane, at its real phase', s)
        self.assertTrue(any(x.startswith('The daily goal check:') for x in s))
        self.assertTrue(any(x.startswith('Launch gate 1:') for x in s))

    def test_every_built_release_json_in_handoffs_sent_from_now_names_one(self):
        plan = json.load(open(os.path.join(DOCS, 'handoffs', 'task-seed-5', 'release.json'), encoding='utf-8'))
        self.assertEqual(ticket.check(plan.get('ticket')), '')


if __name__ == '__main__':
    unittest.main()
