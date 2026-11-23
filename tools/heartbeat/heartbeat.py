#!/usr/bin/env python3
"""The outside heartbeat (the state model, 02 §0a ruling 6 and O3; rows 53–55; .github/workflows/heartbeat.yml).

When Bluehost is down the board can't load, so this runs on GitHub's runners every 20 minutes (O3: 15–30), a light check:
- page:     the home page answers 200 (row 53);
- database: /api/health.php (HEALTH_TOKEN) answers with the database's version (the databases);
- cron:     the board's watch (control_tick, every 5 minutes) ran within 10 minutes, as health.php reports it (row 55).

A part that fails is a critical trip at once (rows 53, 55): it opens an unnumbered record in
site-audit/heartbeat/records.json (times, what failed, its checks) and emails the trip through SES only (ruling 6: the
push keys stay on Bluehost, so no push), "SYSTEM ALARM NOTICE: <part>: Down: the system is still trying" (row 53's words).
While it stays down, each check is added to the record and nothing more is sent (one message per alarm). When it answers
again, the record closes and "Recovered: …" goes in the same email thread. The site imports closed records when it's back
and numbers them then (ControlIncidents::importHeartbeat): Pulse shows "raised while the site was down, by the outside
heartbeat", with the real times.

Email needs three GitHub secrets (close.md A1): ALARM_TO (the Founder's address trips already reach, R126) and an SES
send-only key (HEARTBEAT_SES_KEY_ID, HEARTBEAT_SES_SECRET). Until they're set, the record is still written and the run
says "not set up (ALARM_TO)" as a warning. SES's fraction of a cent is the system-email exception (Jason, 2026-11-09).

    python3 tools/heartbeat/heartbeat.py [--records <file>] [--site https://traversence.com]
"""
import argparse, base64, datetime, json, os, subprocess, sys, time, urllib.request, uuid

PARTS = {'page': 'Page load', 'database': 'The databases', 'cron': "The site's scheduled jobs"}
KEEP_DAYS = 90          # alarm history 90 days (02 §0a, Q19); the site keeps its imported IRs 365
WATCH_LATE_MIN = 10     # row 55: the watch hasn't ticked in 10 minutes
UA = 'Traversence-heartbeat/1 (+https://github.com/degraffics/traversence)'
SES_FROM = os.environ.get('ALARM_FROM') or 'Traversence <noreply@traversence.com>'   # the site's system sender (Mailer.php:11)


def now():
    return datetime.datetime.now(datetime.timezone.utc)


def iso(t):
    return t.strftime('%Y-%m-%dT%H:%M:%SZ')


def get(url, headers=None, timeout=25):
    """(status, body, ms); status 0 when nothing answered."""
    t0 = time.time()
    req = urllib.request.Request(url, headers={'User-Agent': UA, **(headers or {})})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, r.read(400000).decode('utf-8', 'replace'), int((time.time() - t0) * 1000)
    except urllib.error.HTTPError as e:
        return e.code, '', int((time.time() - t0) * 1000)
    except Exception:
        return 0, '', int((time.time() - t0) * 1000)


def probe(site, token, fetch=get):
    """{part: (ok, check)} for each part checked. Without HEALTH_TOKEN only the page is checked."""
    at = iso(now())
    out = {}
    st, _, ms = fetch(site.rstrip('/') + '/')
    out['page'] = (st == 200, {'at': at, 'part': 'page', 'url': site.rstrip('/') + '/', 'status': st, 'ms': ms})
    if not token:
        return out
    url = site.rstrip('/') + '/api/health.php'
    st, body, ms = fetch(url, {'Authorization': 'Bearer ' + token})
    try:
        h = json.loads(body) if st == 200 else {}
    except ValueError:
        h = {}
    if st != 200 or not out['page'][0]:
        # the host itself is gone or health.php can't answer: the page's trip covers it (the databases and the watch
        # can't be read from outside), so they aren't raised as separate trips
        return out
    out['database'] = (bool(h.get('mysql')), {'at': at, 'part': 'database', 'url': url, 'status': st, 'ms': ms, 'mysql': bool(h.get('mysql'))})
    late = None
    try:
        server_now = datetime.datetime.fromisoformat(str(h.get('at')))
        for j in h.get('jobs') or []:
            if j.get('job') == 'control_tick' and j.get('last'):
                last = datetime.datetime.fromisoformat(str(j['last']).replace(' ', 'T'))
                # health.php's 'last' is the server's own clock without a zone, as its 'at' is before its offset
                late = (server_now.replace(tzinfo=None) - last.replace(tzinfo=None)).total_seconds() / 60
    except (ValueError, TypeError):
        late = None
    if late is not None:
        out['cron'] = (late <= WATCH_LATE_MIN, {'at': at, 'part': 'cron', 'url': url, 'status': st, 'ms': ms, 'watch_minutes_ago': round(late, 1)})
    return out


def load(path):
    try:
        with open(path) as f:
            d = json.load(f)
        d.setdefault('records', [])
        return d
    except (OSError, ValueError):
        return {'about': 'The outside heartbeat\'s records (tools/heartbeat/heartbeat.py). The site imports closed ones and numbers them (ControlIncidents::importHeartbeat).', 'records': []}


def send(to_addr, subject, text, msg_id, reply_to=None, runner=subprocess.run):
    """One email through SES (aws sesv2, the send-only key). Returns (ok, why)."""
    if not to_addr:
        return False, 'not set up (ALARM_TO)'
    if not (os.environ.get('AWS_ACCESS_KEY_ID') and os.environ.get('AWS_SECRET_ACCESS_KEY')):
        return False, 'not set up (the SES send-only key)'
    head = [f'From: {SES_FROM}', f'To: {to_addr}', f'Subject: {subject}', f'Message-ID: {msg_id}', 'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8']
    if reply_to:
        head += [f'In-Reply-To: {reply_to}', f'References: {reply_to}']
    raw = ('\r\n'.join(head) + '\r\n\r\n' + text).encode('utf-8')
    arg = json.dumps({'Raw': {'Data': base64.b64encode(raw).decode()}})
    r = runner(['aws', 'sesv2', 'send-email', '--content', arg, '--region', os.environ.get('SES_REGION', 'us-east-2')],
               capture_output=True, text=True)
    return r.returncode == 0, (r.stderr or '').strip()[:300]


def step(d, results, to_addr, site, mailer=send, t=None):
    """Apply one round of checks to the records. Returns the lines to log."""
    t = t or now()
    log = []
    recs = d['records']
    for part, (ok, check) in results.items():
        name = PARTS[part]
        rec = next((r for r in recs if r.get('part') == part and not r.get('closed_at')), None)
        if not ok and rec is None:
            rid = 'hb-' + t.strftime('%Y%m%dT%H%MZ') + '-' + part
            mid = f'<{rid}.{uuid.uuid4().hex[:8]}@heartbeat.traversence.com>'
            what = f'{name}: the outside heartbeat got {check.get("status") or "no answer"} from {check.get("url")}'
            rec = {'id': rid, 'part': part, 'opened_at': iso(t), 'closed_at': None, 'what': what, 'checks': [check], 'message_id': mid}
            recs.append(rec)
            ok_m, why = mailer(to_addr, f'SYSTEM ALARM NOTICE: {name}: Down: the system is still trying',
                               f'{what} at {iso(t)}.\n\nThis was raised from outside the site (GitHub), since the board can\'t load while the host is down. '
                               f'It checks again every 20 minutes and writes here when it answers again.\n\nWhen the site is back, the board numbers it '
                               f'(T- and IR-) and shows it as raised while the site was down, by the outside heartbeat:\n{site.rstrip("/")}/admin/config/control\n', mid)
            rec['mailed'] = ok_m
            log.append(f'{name}: Down (trip at once). Email: ' + ('sent' if ok_m else why))
        elif not ok:
            rec['checks'] = (rec.get('checks') or [])[-39:] + [check]
            log.append(f'{name}: still down since {rec["opened_at"]} (no new message)')
        elif rec is not None:
            rec['checks'] = (rec.get('checks') or [])[-39:] + [check]
            rec['closed_at'] = iso(t)
            mins = max(1, round((t - datetime.datetime.strptime(rec['opened_at'], '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)).total_seconds() / 60))
            ok_m, why = mailer(to_addr, f'SYSTEM ALARM NOTICE: {name}: Recovered',
                               f'Recovered: {name} was down {mins} min; the site answers again ({iso(t)}).\nNothing to press.\n',
                               f'<{rec["id"]}.r@heartbeat.traversence.com>', rec.get('message_id'))
            log.append(f'{name}: Recovered after {mins} min. Email: ' + ('sent' if ok_m else why))
    cut = t - datetime.timedelta(days=KEEP_DAYS)
    d['records'] = [r for r in recs if not r.get('closed_at') or datetime.datetime.strptime(r['closed_at'], '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc) >= cut]
    return log


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--records', default='site-audit/heartbeat/records.json')
    ap.add_argument('--site', default=os.environ.get('SITE', 'https://traversence.com'))
    a = ap.parse_args()
    d = load(a.records)
    before = json.dumps(d, sort_keys=True)
    res = probe(a.site, os.environ.get('HEALTH_TOKEN', ''))
    to_addr = os.environ.get('ALARM_TO', '')
    log = step(d, res, to_addr, a.site)
    os.makedirs(os.path.dirname(a.records), exist_ok=True)
    if json.dumps(d, sort_keys=True) != before or not os.path.exists(a.records):   # written only when something changed
        with open(a.records, 'w') as f:
            json.dump(d, f, indent=1)
            f.write('\n')
    for part, (ok, _) in res.items():
        print(f'{PARTS[part]}: {"answers" if ok else "DOWN"}')
    for line in log:
        print(line)
    if not to_addr:
        print('::warning::The outside heartbeat is not set up (ALARM_TO): its records are written, no email is sent. '
              'Set ALARM_TO and the SES send-only key at https://github.com/degraffics/traversence/settings/secrets/actions')
    return 0


if __name__ == '__main__':
    sys.exit(main())
