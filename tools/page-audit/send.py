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
import json, argparse, os, re, shutil, subprocess, sys, zipfile

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
    why = ''   # a claim taken earlier keeps its own why; a new one says 'release <zip>'
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


# What the site's Releases page refuses or strands, caught before sending (session manager, 2026-11-18, after #70, #106,
# #108 and #109): the same add-only rule as Releases.php's checks, a switch the site knows, a name never sent before.
NOT_ADD_ONLY = re.compile(r'\b(DROP\s+(TABLE|COLUMN|INDEX|KEY|DATABASE|VIEW|TRIGGER)|RENAME\s+(TABLE|COLUMN|TO)|TRUNCATE|DELIMITER'
                          r'|CREATE\s+(DEFINER\S*\s+)?PROCEDURE|MODIFY\s+(COLUMN\s+)?\w+|CHANGE\s+(COLUMN\s+)?\w+)\b', re.I)


def guard(a, name, plan, z):
    stem = name[:-4]
    if os.path.exists(os.path.join(DOCS, 'deliveries', stem + '.json')):
        sys.exit(name + ' was sent before (deliveries/' + stem + '.json): the site won\'t take the same name twice. '
                 'Give the rebuilt zip a new name (e.g. ' + stem + '-2.zip).')
    sw = str(plan.get('switch', '') or '').strip()
    if sw:
        known = False
        for src in (os.path.join(a.mirror, 'sync-app', 'lib', 'Switches.php'),):
            if os.path.exists(src) and ("'" + sw + "' =>") in open(src, encoding='utf-8', errors='replace').read():
                known = True
        if 'app/lib/Switches.php' in z.namelist() and ("'" + sw + "' =>") in z.read('app/lib/Switches.php').decode('utf-8', 'replace'):
            known = True
        if not known:
            sys.exit('release.json\'s "switch" is "' + sw + '", which isn\'t a switch on the live site or in this zip\'s '
                     'Switches.php: the release would stop at Planned. Use "" for a release without one.')
    removes = plan.get('removes') or []
    for p in z.namelist():
        if not (p.startswith('app/migrations/') and p.endswith('.sql')):
            continue
        sql = re.sub(r'--[^\n]*|/\*.*?\*/', ' ', z.read(p).decode('utf-8', 'replace'), flags=re.S)
        m = NOT_ADD_ONLY.search(sql)
        if m and not removes:
            sys.exit(p + ' isn\'t add-only ("' + m.group(0) + '"): the site\'s "Migrations only add" check fails it. Add a new '
                     'column or table instead (decisions/0162); a removal only of a rollback\'s leftover, named under "removes".')
        if re.search(r'\b(UPDATE|DELETE\s+FROM)\b', sql, re.I) and not plan.get('data_reason'):
            sys.exit(p + ' changes data (UPDATE/DELETE): release.json needs "data_reason" (decisions/0162).')


def fence_check(zpath, mirror, ticket):
    """The fence (P3-6; decisions/0179's note "agents work on the backend first"): a build for an agent's brief, while the
    site's Team says Implementation reaches Backend only (build-requests.json's "fence" for the ticket; Backend only when it
    isn't there), must reach no public page (reach.py on the live mirror). Refused: nothing is sent, the refusal is kept in
    releases/fence-refusals.json, and gauges.py carries it to the site, which puts the ticket back at its brief for the Founder."""
    try:
        reqs = json.load(open(os.path.join(DOCS, 'site-audit', 'results', 'build-requests.json'))).get('requests', [])
    except Exception:
        reqs = []
    req = next((r for r in reqs if int(r.get('id', 0) or 0) == ticket), None)
    if req is None:
        sys.exit(f'Ticket #{ticket} isn\'t in build-requests.json: a build for an agent\'s brief starts from its request there.')
    if req.get('brief_to') == 'founder':
        sys.exit(f'Ticket #{ticket}\'s brief waits for the Founder (front end, behind the fence): it isn\'t a build session\'s to send.')
    try:
        rj = json.loads(zipfile.ZipFile(zpath).read('release.json'))
    except Exception:
        rj = {}
    if int(rj.get('team_ticket') or 0) != ticket:
        sys.exit(f'release.json needs "team_ticket": {ticket}: the site reads it to hold an agent\'s release to the advocates\' check and the backend (P3-6).')
    if (req.get('fence') or 'backend') == 'everywhere':
        print('The fence: the Team says Implementation reaches Everywhere: not checked.')
        return
    sys.path.insert(0, HERE)
    import reach
    z = zipfile.ZipFile(zpath)
    names = [n for n in z.namelist() if not n.endswith('/') and n != 'release.json']
    out = reach.fence(mirror, names, {n: z.read(n).decode('utf-8', 'replace') for n in names if n.endswith('.sql')})
    if not out:
        print(f'The fence: every file stays in the backend (ticket #{ticket}).')
        return
    reason = '; '.join(f + ' reaches ' + ', '.join(p[:3]) for f, p in list(out.items())[:4])
    rf = os.path.join(DOCS, 'releases', 'fence-refusals.json')
    try:
        d = json.load(open(rf))
    except Exception:
        d = {'note': 'Builds refused at the fence (P3-6): send.py --team-ticket writes them; gauges.py carries them to the site, which puts each ticket back at its brief for the Founder.', 'refusals': []}
    import datetime
    d['refusals'] = [r for r in d.get('refusals', []) if not (r.get('ticket') == ticket and r.get('zip') == os.path.basename(zpath))]
    d['refusals'].append({'ticket': ticket, 'zip': os.path.basename(zpath), 'reason': reason[:300], 'at': datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')})
    json.dump(d, open(rf, 'w'), indent=1)
    open(rf, 'a').write('\n')
    sys.exit(f'Refused at the fence: the Team says Implementation reaches Backend only, and this build reaches a public page ({reason[:400]}). '
             f'Nothing was sent; ticket #{ticket} goes back to its brief for the Founder (releases/fence-refusals.json: commit it, and run gauges.py).')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('zip')
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--note', required=True, help='what it is, ending "built on Sync: <time>"')
    ap.add_argument('--drops', default='')
    ap.add_argument('--session', default='')
    ap.add_argument('--callers-checked', default='')
    ap.add_argument('--allow-pending', default='', help="pending zips (by name) this one is built on, e.g. a release of yours whose files the sync put back")
    ap.add_argument('--team-ticket', type=int, default=0, help='P3-6: the Work queue ticket whose agent brief this build is; behind the fence (Backend only) a zip that reaches a public page is refused and the ticket goes back to its brief')
    a = ap.parse_args()
    name = os.path.basename(a.zip)
    if not name.endswith('.zip') or not all(c.isalnum() or c in '._-' for c in name):
        sys.exit('The zip\'s name may hold letters, digits, ".", "_" and "-" only (the site refuses others).')
    with zipfile.ZipFile(a.zip) as z:
        if 'release.json' not in z.namelist():
            sys.exit('No release.json at the zip\'s top: give it a title, why, proposals and switch.')
        try:
            plan = json.loads(z.read('release.json'))
        except ValueError:
            sys.exit('release.json isn\'t valid JSON.')
        if not plan.get('changes'):   # the review email, Pulse case and site manager list these (Jason, 2026-11-15: legible on a phone)
            print('Warning: release.json has no "changes" list ([{"what": "a few words", "where": "where to look"}]): the review email falls back to "why" split into its numbered parts.')
        guard(a, name, plan, z)
    take_claims(a, name)
    if a.team_ticket:
        fence_check(a.zip, a.mirror, a.team_ticket)
    check = [sys.executable, os.path.join(HERE, 'zipcheck.py'), a.zip, '--mirror', a.mirror, '--docs', DOCS] + (['--drops', a.drops] if a.drops else []) \
        + (['--session', a.session] if a.session else []) + (['--callers-checked', a.callers_checked] if a.callers_checked else []) \
        + (['--allow-pending', a.allow_pending] if a.allow_pending else [])
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
