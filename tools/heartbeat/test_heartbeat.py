#!/usr/bin/env python3
"""Tests for the outside heartbeat (tools/heartbeat/heartbeat.py; 02 §0a ruling 6, rows 53–55). No network: fetch and
send are stand-ins.   python3 tools/heartbeat/test_heartbeat.py"""
import datetime, json, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import heartbeat as hb

P = F = 0
def t(name, ok, got=None):
    global P, F
    if ok: P += 1; print('  ok  ' + name)
    else: F += 1; print('FAIL  ' + name + ('' if got is None else ' — ' + repr(got)[:300]))

def T(h, m=0):
    return datetime.datetime(2026, 11, 23, h, m, tzinfo=datetime.timezone.utc)

def fetcher(page=200, health=200, mysql='10.6', watch_min=3):
    def f(url, headers=None, timeout=25):
        if url.endswith('/api/health.php'):
            if health != 200: return health, '', 5
            last = (datetime.datetime(2026, 11, 23, 1, 0) - datetime.timedelta(minutes=watch_min)).strftime('%Y-%m-%d %H:%M:%S')
            return 200, json.dumps({'ok': True, 'at': '2026-11-23T01:00:00-07:00', 'mysql': mysql, 'jobs': [{'job': 'control_tick', 'last': last}]}), 5
        return page, '<html>', 5
    return f

sent = []
def mailer(to, subject, text, mid, reply_to=None):
    sent.append((to, subject, text, mid, reply_to))
    return (bool(to), '' if to else 'not set up (ALARM_TO)')

# probe
r = hb.probe('https://x.test', 'tok', fetcher())
t('a healthy site: page, database and the watch all answer', all(ok for ok, _ in r.values()) and set(r) == {'page', 'database', 'cron'}, r)
r = hb.probe('https://x.test', '', fetcher())
t('without HEALTH_TOKEN only the page is checked', set(r) == {'page'}, r)
r = hb.probe('https://x.test', 'tok', fetcher(page=503, health=503))
t('the host gone: one page trip, the databases and the watch not raised separately', set(r) == {'page'} and not r['page'][0], r)
r = hb.probe('https://x.test', 'tok', fetcher(mysql=''))
t('the database down: its own part fails, the page still answers', r['page'][0] and not r['database'][0], r)
r = hb.probe('https://x.test', 'tok', fetcher(watch_min=25))
t('the watch quiet 25 minutes (row 55: 10): the scheduled jobs fail', not r['cron'][0] and r['cron'][1]['watch_minutes_ago'] == 25.0, r)

# a trip at once, one message while it stays down, Recovered in the same thread
d = {'records': []}
log = hb.step(d, hb.probe('https://x.test', 'tok', fetcher(page=503, health=503)), 'founder@x.test', 'https://x.test', mailer, T(1))
t('a page down is a trip at once: one record, open', len(d['records']) == 1 and d['records'][0]['closed_at'] is None and d['records'][0]['part'] == 'page', d)
t('its email says row 53\'s words', sent and sent[-1][1] == 'SYSTEM ALARM NOTICE: Page load: Down: the system is still trying', sent)
hb.step(d, hb.probe('https://x.test', 'tok', fetcher(page=503, health=503)), 'founder@x.test', 'https://x.test', mailer, T(1, 20))
t('still down: the check is added, nothing more is sent', len(sent) == 1 and len(d['records'][0]['checks']) == 2, (len(sent), d))
hb.step(d, hb.probe('https://x.test', 'tok', fetcher()), 'founder@x.test', 'https://x.test', mailer, T(1, 40))
rec = d['records'][0]
t('answering again closes the record', rec['closed_at'] == '2026-11-23T01:40:00Z', rec)
t('Recovered goes in the same thread, with the minutes', len(sent) == 2 and sent[1][4] == rec['message_id'] and 'was down 40 min' in sent[1][2], sent[-1:])
t('nothing else was opened by the healthy round', len(d['records']) == 1, d)

# not set up
sent.clear(); d = {'records': []}
log = hb.step(d, {'page': (False, {'at': 'x', 'url': 'https://x.test/', 'status': 0})}, '', 'https://x.test', mailer, T(2))
t('not set up (ALARM_TO): the record is still written, the log says so', len(d['records']) == 1 and any('not set up (ALARM_TO)' in l for l in log), log)
os.environ.pop('AWS_ACCESS_KEY_ID', None)
t('the real sender refuses without ALARM_TO, and without the SES key', hb.send('', 's', 't', '<m>')[1] == 'not set up (ALARM_TO)' and 'SES' in hb.send('a@x.test', 's', 't', '<m>')[1])

# kept 90 days
d = {'records': [{'id': 'old', 'part': 'page', 'opened_at': '2026-07-01T00:00:00Z', 'closed_at': '2026-07-01T01:00:00Z', 'checks': []}]}
hb.step(d, {'page': (True, {})}, 'a', 'https://x.test', mailer, T(3))
t('closed records older than 90 days are dropped (Q19)', d['records'] == [], d)

print(f'\n{P} passed, {F} failed')
sys.exit(1 if F else 0)
