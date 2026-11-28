#!/usr/bin/env python3
"""No work without a ticket (Jason, 2026-11-28: "Work queue is our only development lane so never bypass it!").

send.py refuses a release.json without "ticket"; tools/batch/batch.py refuses a job without one. A ticket is:
  - its Work-queue id: 123 or "#123" (the number on /admin/control-board's Work queue), or
  - its exact title, for a ticket whose id isn't known in the repo: one of the titles the seeds wrote
    (handoffs/task-seed-*/items.json).
A title nobody seeded is refused: add the ticket to the Work queue first, then name it by its id.
"""
import glob, json, os, re

DOCS = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
HOW = ('Name its Work-queue ticket: "ticket": 123 (its id on the Work queue, https://traversence.com/admin/control-board), '
       'or the exact title of a ticket the seeds wrote (handoffs/task-seed-5/items.json). '
       'No ticket yet? Add it to the Work queue first: the queue is the only development lane (Jason, 2026-11-28).')


def seeded(docs=DOCS):
    """The titles the seeds wrote: goals and their tickets."""
    out = set()
    try:
        with open(os.path.join(docs, 'handoffs', 'task-seed-4', 'items.json'), encoding='utf-8') as f:
            for g in json.load(f)['goals']:
                out.add(g['what'])
                out.update(c['what'] for c in g.get('children', []))
    except (OSError, ValueError, KeyError):
        pass
    for seed in sorted(glob.glob(os.path.join(docs, 'handoffs', 'task-seed-*', 'items.json'))):   # task-seed-5 and every later seed
        try:
            with open(seed, encoding='utf-8') as f:
                out.update(x['what'] for x in json.load(f).get('new', []))
        except (OSError, ValueError, KeyError, AttributeError):
            pass
    return out


def check(v, docs=DOCS):
    """'' when v names a ticket; else why it's refused."""
    if isinstance(v, bool) or v is None or v == '' or v == 0:
        return 'no "ticket". ' + HOW
    if isinstance(v, int):
        return '' if v > 0 else '"ticket" is %r, which is no Work-queue id. ' % v + HOW
    if isinstance(v, str):
        s = v.strip()
        if re.fullmatch(r'#?[1-9][0-9]*', s):
            return ''
        if s in seeded(docs):
            return ''
        return '"ticket" is "%s", which is neither a Work-queue id nor a title the seeds wrote. ' % s[:80] + HOW
    return '"ticket" must be a Work-queue id or a ticket\'s exact title. ' + HOW
