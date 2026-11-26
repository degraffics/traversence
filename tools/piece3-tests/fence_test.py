"""The fence's tests (P3-6; decisions/0179's note "agents work on the backend first", Jason, 2026-11-26) on a small mirror
made here: reach.py's fence both ways (/admin/ only, a public reach, a mixed zip, migrations, new files, the map) and
send.py's refusal for an agent's brief (nothing sent, the refusal kept for the site, Everywhere not checked).
Run: python3 tools/piece3-tests/fence_test.py"""
import json, os, sys, tempfile, zipfile, io, contextlib
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, os.path.join(HERE, '..', 'page-audit'))
import reach as R
import send as S
ok = bad = 0
def t(n, c, got=None):
    global ok, bad
    if c: ok += 1; print('  ok  ' + n)
    else: bad += 1; print('FAIL  ' + n + ('' if got is None else ' — ' + json.dumps(got, default=list)[:300]))
m = tempfile.mkdtemp()
def w(p, s):
    full = os.path.join(m, p); os.makedirs(os.path.dirname(full), exist_ok=True); open(full, 'w').write(s)
w('sync-app/lib/Orders.php', "<?php\nfinal class Orders { public static function all() {} }\n")
w('sync-app/lib/AdminReport.php', "<?php\nfinal class AdminReport { public static function rows() {} }\n")
w('sync-app/ui/cart.php', "<?php\nfunction tv_cart_box() { return Orders::all(); }\n")
w('sync-app/admin/report-console.php', "<?php\nfunction tv_report_console() { return AdminReport::rows(); }\n")
w('sync-storage/cart.php', "<?php\nrequire_once __DIR__ . '/../app/ui/cart.php';\necho tv_cart_box();\n")
w('sync-storage/admin/router.php', "<?php\nrequire_once __DIR__ . '/../../app/admin/report-console.php';\necho tv_report_console();\n")
w('sync-storage/js/admin-report.js', "window.TvAdminReport = {};\n")
w('sync-storage/admin/report.php', "<?php\necho '<script src=\"/js/admin-report.js\"></script>';\n")
def zipf(files):
    z = os.path.join(m, 'r%d.zip' % len(files))
    with zipfile.ZipFile(z, 'w') as f:
        for k, v in files.items(): f.writestr(k, v)
        f.writestr('release.json', '{}')
    return z
def fz(files):
    with zipfile.ZipFile(zipf(files)) as z:
        names = [n for n in z.namelist() if n != 'release.json']
        return R.fence(m, names, {n: z.read(n).decode() for n in names if n.endswith('.sql')})
t('/admin/ only: an admin page, its console part, a library only the admin reaches, a script only an admin page loads: backend',
  fz({'website/admin/router.php': 'x', 'app/admin/report-console.php': 'x', 'app/lib/AdminReport.php': 'x', 'website/js/admin-report.js': 'x'}) == {})
pub = fz({'app/lib/Orders.php': 'x'})
t('a library a public page reaches: stopped, naming the page', pub == {'app/lib/Orders.php': ['/cart.php']}, pub)
mix = fz({'app/lib/AdminReport.php': 'x', 'app/ui/cart.php': 'x'})
t('a mixed zip: only its public file is named', list(mix) == ['app/ui/cart.php'] and mix['app/ui/cart.php'] == ['/cart.php'], mix)
t('a public page itself, or an endpoint: front end', fz({'website/cart.php': 'x'}) == {'website/cart.php': ['/cart.php']} and list(fz({'website/api/x.php': 'x'})) == ['website/api/x.php'])
t('trvgo.us: front end', list(fz({'trvgo/index.php': 'x'})) == ['trvgo/index.php'])
t('a worker: backend', fz({'workers/crawler/run.py': 'x'}) == {})
t('a new library (its callers decide): backend', fz({'app/lib/NewThing.php': '<?php'}) == {})
t('a migration that only adds (ON UPDATE CURRENT_TIMESTAMP in it): backend', fz({'app/migrations/2026-12-01_a.sql': 'CREATE TABLE IF NOT EXISTS a (at DATETIME ON UPDATE CURRENT_TIMESTAMP);'}) == {})
t('a migration that writes rows: not proved backend', 'INSERT' in json.dumps(fz({'app/migrations/2026-12-01_b.sql': "INSERT INTO a VALUES (1);"})))
back = R.fence_map(m)
t('the map: the admin\'s files and what only it reaches, never a public page\'s', 'app/lib/AdminReport.php' in back and 'website/admin/router.php' in back and 'website/js/admin-report.js' in back and 'app/lib/Orders.php' not in back and 'website/cart.php' not in back, back)
# send.py's refusal (a temp copy of the repo's folders it reads and writes)
d = tempfile.mkdtemp(); os.makedirs(os.path.join(d, 'site-audit', 'results')); os.makedirs(os.path.join(d, 'releases'))
S.DOCS = d
def reqs(fence='backend', brief_to='build'):
    json.dump({'requests': [{'id': 12, 'build_brief': 'b', 'brief_to': brief_to, 'fence': fence}]}, open(os.path.join(d, 'site-audit', 'results', 'build-requests.json'), 'w'))
def send(z, ticket=12):
    out = io.StringIO()
    try:
        with contextlib.redirect_stdout(out): S.fence_check(z, m, ticket)
        return 'passed', out.getvalue()
    except SystemExit as x: return 'refused', str(x)
reqs()
r, why = send(zipf({'app/lib/AdminReport.php': 'x'}))
t('send: a backend build for an agent\'s brief goes on', r == 'passed', why)
r, why = send(zipf({'app/lib/AdminReport.php': 'x', 'app/ui/cart.php': 'x'}))
rf = json.load(open(os.path.join(d, 'releases', 'fence-refusals.json')))
t('send: a mixed build refused with the reason, nothing sent; the refusal kept for the site (ticket, zip, reason)', r == 'refused' and 'reaches a public page' in why and rf['refusals'][-1]['ticket'] == 12 and 'app/ui/cart.php' in rf['refusals'][-1]['reason'], [why, rf])
send(zipf({'app/lib/AdminReport.php': 'x', 'app/ui/cart.php': 'x'}))
t('… the same zip refused twice is kept once', len(json.load(open(os.path.join(d, 'releases', 'fence-refusals.json')))['refusals']) == 1)
reqs(fence='everywhere'); r, why = send(zipf({'app/lib/Orders.php': 'x'}))
t('send: with Everywhere, not checked', r == 'passed' and 'Everywhere' in why, why)
reqs(brief_to='founder'); r, why = send(zipf({'app/lib/AdminReport.php': 'x'}))
t('send: a brief waiting for the Founder isn\'t a build session\'s to send', r == 'refused' and 'waits for the Founder' in why, why)
r, why = send(zipf({'app/lib/AdminReport.php': 'x'}), ticket=99)
t('send: a ticket not in build-requests.json: refused', r == 'refused' and '#99' in why, why)
os.remove(os.path.join(d, 'site-audit', 'results', 'build-requests.json')); json.dump({'requests': [{'id': 12, 'build_brief': 'b', 'brief_to': 'build'}]}, open(os.path.join(d, 'site-audit', 'results', 'build-requests.json'), 'w'))
r, why = send(zipf({'app/lib/Orders.php': 'x'}))
t('send: no fence named in the request: Backend only (the safe side)', r == 'refused', why)
print('\n%d passed, %d failed' % (ok, bad)); sys.exit(1 if bad else 0)
