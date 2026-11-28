#!/usr/bin/env python3
"""No batch job without its Work-queue ticket (Jason, 2026-11-28): batch.py refuses the whole file before anything is sent
(before the key is even read), naming the line. Run: python3 tools/batch/test_batch_ticket.py
"""
import json, os, subprocess, sys, tempfile, unittest

HERE = os.path.dirname(os.path.abspath(__file__))
ENV = {k: v for k, v in os.environ.items() if k not in ('BATCH_API_KEY', 'TRV_ANTHROPIC_API_KEY', 'ANTHROPIC_API_KEY')}   # no key: nothing can be sent


def submit(jobs):
    f = os.path.join(tempfile.mkdtemp(), 'jobs.jsonl')
    open(f, 'w').write('\n'.join(json.dumps(j) for j in jobs) + '\n')
    r = subprocess.run([sys.executable, os.path.join(HERE, 'batch.py'), 'submit', f, '--max-usd', '1'], capture_output=True, text=True, env=ENV)
    return r.returncode, r.stdout + r.stderr


class BatchTicket(unittest.TestCase):
    def test_a_job_without_a_ticket_is_refused(self):
        for bad in ({}, {'ticket': ''}, {'ticket': 0}, {'ticket': 'a title nobody seeded'}):
            code, said = submit([{'id': 'a', 'prompt': 'hello', 'ticket': 12}, {'id': 'b', 'prompt': 'hello', **bad}])
            self.assertNotEqual(code, 0, bad)
            self.assertIn('Refused (nothing was sent): line 2 (b)', said, bad)
            self.assertIn('Add it to the Work queue first', said)
            self.assertNotIn('requests, at most', said)   # stopped before the estimate and the send

    def test_jobs_with_tickets_pass_the_rule(self):
        code, said = submit([{'id': 'a', 'prompt': 'hello', 'ticket': 12}, {'id': 'b', 'prompt': 'hello', 'ticket': '#40'},
                             {'id': 'c', 'prompt': 'hello', 'ticket': 'task-seed-5: every current item a ticket under its goal, in its lane, at its real phase'}])
        self.assertIn('3 requests, at most', said)
        self.assertNotIn('Refused (nothing was sent)', said)
        self.assertIn("BATCH_API_KEY isn't set", said)   # the next stop is the key, not the ticket


if __name__ == '__main__':
    unittest.main()
