#!/usr/bin/env python3
"""Send a build to the site through git (decisions/0159 §5, Phase 6).

    python3 tools/page-audit/send.py <zip> --mirror <traverse-3way-sync checkout> --note "…built on Sync: <time>"

It runs the zip check (it must pass), records the zip in the delivery log, and copies it into releases/inbox/. Then
commit, open a PR and merge it into main: within 10 minutes the live site takes it onto staging as a release (or at once
with Releases ▾ → Check for builds on the site manager). After Jason publishes it, run the sync and
`deliveries.py verify` as for any delivery.

Check-out (decisions/0161 §8): before the zip check it takes the zip's claims for this session (each new file and each
kept-line edit as add, each edit of existing code as a change on the functions or entries it touches), tagged with the
zip's name, so a session that forgot to claim still can't collide. A clash stops the send. The claims are released when
`deliveries.py verify` finds the release live.
"""
import argparse, os, shutil, subprocess, sys, zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import checkout  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.normpath(os.path.join(HERE, '..', '..'))


def take_claims(a, name):
    """Claim the zip's files for this session and this release (decisions/0161 §8). A clash stops the send; a claim that
    can't be written (offline, a refused push) is said and the zip check still checks the clashes."""
    session = checkout.my_session(a.session)
    if not session:
        print('Check-out: no session id (--session), so the zip\'s files weren\'t claimed.')
        return
    items = [(p, names, kind) for p, kind, names in checkout.zip_changes(a.zip, a.mirror)]
    if not items:
        return
    why = 'release ' + name
    try:
        res = checkout.save(lambda d: checkout.take(d, session, items, why, name, a.mirror),
                            'Check-out: %s claims the files of %s' % (session, name))
    except SystemExit as e:
        print('Check-out: the claims weren\'t written (%s). Sending anyway; the zip check still checks for clashes.' % e)
        return
    if res.get('refused'):
        print('Check-out refused (nothing was claimed, nothing sent):')
        for r in res['refused']:
            print('  ' + r)
        sys.exit(1)
    for c in res['taken']:
        print('Claimed: ' + checkout.describe(c))
    for n in res['notes']:
        print('Note: ' + n)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('zip')
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--note', required=True, help='what it is, ending "built on Sync: <time>"')
    ap.add_argument('--drops', default='')
    ap.add_argument('--session', default='')
    ap.add_argument('--callers-checked', default='')
    a = ap.parse_args()
    name = os.path.basename(a.zip)
    if not name.endswith('.zip') or not all(c.isalnum() or c in '._-' for c in name):
        sys.exit('The zip\'s name may hold letters, digits, ".", "_" and "-" only (the site refuses others).')
    with zipfile.ZipFile(a.zip) as z:
        if 'release.json' not in z.namelist():
            sys.exit('No release.json at the zip\'s top: give it a title, why, proposals and switch.')
    take_claims(a, name)
    check = [sys.executable, os.path.join(HERE, 'zipcheck.py'), a.zip, '--mirror', a.mirror, '--docs', DOCS] + (['--drops', a.drops] if a.drops else []) \
        + (['--session', a.session] if a.session else []) + (['--callers-checked', a.callers_checked] if a.callers_checked else [])
    if subprocess.call(check) != 0:
        sys.exit('The zip check didn\'t pass: nothing was sent.')
    if subprocess.call([sys.executable, os.path.join(HERE, 'deliveries.py'), 'record', a.zip, '--mirror', a.mirror, '--note', a.note + ' Sent through git (releases/inbox).']) != 0:
        sys.exit('Couldn\'t record it in the delivery log: nothing was sent.')
    to = os.path.join(DOCS, 'releases', 'inbox', name)
    if os.path.exists(to):
        sys.exit(to + ' is in the inbox already.')
    shutil.copyfile(a.zip, to)
    print('In the inbox: releases/inbox/' + name + '. Commit, PR and merge into main; the site takes it onto staging within 10 minutes.')


if __name__ == '__main__':
    main()
