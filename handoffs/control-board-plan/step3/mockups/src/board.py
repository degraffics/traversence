# The interactive board mockup (step 3): step3/board/index.html, one self-contained page.
# Generated from the specs and the real readings: 03-cards (through the generated card mockups), 02, site-audit/pages.md,
# site-audit/results/*.json, the live mirror's app/admin/manifests.php, Preflight::NEEDS and Automation::WORKFLOWS.
# Static design mockup; not site code. Rebuild: python3 board.py (the card mockups must be generated first).
import html, json, os, re, sys
from datetime import datetime, timedelta, timezone

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import kit  # CSS only: importing kit generates nothing

E = html.escape
ROOT = '/home/user/traversence/'
MIRROR = '/home/user/traverse-3way-sync/'
STEP3 = ROOT + 'handoffs/control-board-plan/step3/'
MOCK = STEP3 + 'mockups/'
OUT = STEP3 + 'board/'
RES = ROOT + 'site-audit/results/'
ADMIN = 'https://traversence.com'


def mst(iso):
    t = datetime.fromisoformat(iso.replace('Z', '+00:00')).astimezone(timezone(timedelta(hours=-7)))
    return t.strftime('%b %-d, %-I:%M %p MST')


def jload(n):
    return json.load(open(RES + n))


# ------------------------------------------------------------------ real readings
ALARMS = jload('alarms.json'); RENDERED = jload('rendered.json'); PATHS = jload('paths.json'); PHIST = jload('paths-history.json')
SCORE = jload('search-scorecard.json'); PROBE = jload('search-probe.json'); CRAWL = jload('crawler-snapshot.json')
T_AL, T_RE, T_PA, T_SC = mst(ALARMS['at']), mst(RENDERED['at']), mst(PATHS['at']), mst(SCORE['at'])
LAYOUT_MD = open(ROOT + 'site-audit/layout-probe.md').read()
LAYOUT_PAGES = sorted(set(re.findall(r'\*\*Page:\*\* (\S+)', LAYOUT_MD)))
T_LA = re.search(r'on (\d{4}-\d\d-\d\d \d\d:\d\d) UTC', LAYOUT_MD).group(1)
T_LA = mst(T_LA.replace(' ', 'T') + ':00Z')

# ------------------------------------------------------------------ pages.md and the admin manifest
def strip_q(r):
    return r.split('?')[0].split('#')[0]


def parse_pages():
    groups, cur, sub = [], None, ''
    for line in open(ROOT + 'site-audit/pages.md'):
        line = line.rstrip('\n')
        m = re.match(r'^## (\d+\.\s+.*)$', line)
        if m:
            cur = {'name': m.group(1), 'rows': []}; groups.append(cur); sub = ''; continue
        if re.match(r'^## ', line):
            cur = None; continue
        m = re.match(r'^### (.*)$', line)
        if m:
            sub = m.group(1); continue
        if cur is None or not line.startswith('| ') or line.startswith('| Route') or line.startswith('| File') or line.startswith('|---'):
            continue
        cells = [c.strip() for c in line.strip('|').split('|')]
        if len(cells) < 4:
            continue
        routes = re.findall(r'`([^`]+)`', cells[0]) or [re.sub(r'[*()]', '', cells[0]).strip()]
        coming = cur['name'].startswith('8.')
        status = 'Coming: designed, not built' + (f' ({cells[3]})' if cells[3] else '') if coming else cells[3]
        cur['rows'].append({'route': routes[0], 'also': routes[1:], 'purpose': re.sub(r'`', '', cells[1]), 'adr': cells[2], 'status': status, 'sub': sub})
    return [g for g in groups if g['rows']]


def parse_manifest():
    s = open(MIRROR + 'sync-app/admin/manifests.php').read()
    mods = []
    for m in re.finditer(r"\n    '([a-z]+)' => \['sections' => \[(.*?)\n    \], 'at_hand'", s, re.S):
        mod, body = m.group(1), m.group(2)
        secs = []
        for x in re.finditer(r"\n        '([a-z0-9]+)' => \$(W|B|C)\('((?:[^'\\]|\\.)*)'", body):
            secs.append({'route': f'/admin/{mod}/{x.group(1)}', 'label': x.group(3).replace("\\'", "'"), 'kind': {'W': 'Live (widgets)', 'B': 'Live (bridges an older page)', 'C': 'Coming: a concept in the manifest'}[x.group(2)]})
        mods.append({'mod': mod, 'secs': secs})
    return mods


PAGES_MD = parse_pages()
MANIFEST = parse_manifest()
ARCHIVE = open(ROOT + 'archive.md').read()

# probes by path
RENDER_BY = {}
for r in RENDERED['results']:
    RENDER_BY.setdefault(strip_q(r['url']), []).append(r)
PATH_BY = {}
for p in PATHS['results']:
    for pg in p.get('pages', []):
        u = pg if isinstance(pg, str) else pg.get('url', '')
        PATH_BY.setdefault(strip_q(u), set()).add(f"{p['id']} ({p['status']})")
LAYOUT_BY = {strip_q(u) for u in LAYOUT_PAGES}


def probes_for(route):
    k = strip_q(route)
    out = []
    rs = RENDER_BY.get(k)
    if rs:
        st = sorted({x['status'] for x in rs})
        bad = [x for x in rs if x['scroll'] or x['edges']]
        out.append(f"rendered ({T_RE}): {len(rs)} views ({', '.join(sorted({x['size'] for x in rs}))}), status {', '.join(map(str, st))}"
                   + (f"; {sum(1 for x in rs if x['scroll'])} sideways scroll, {sum(x['edges'] for x in rs)} card edges off" if bad else '; no sideways scroll'))
    if k in PATH_BY:
        out.append(f"paths ({T_PA}): " + ', '.join(sorted(PATH_BY[k])))
    if k in LAYOUT_BY:
        out.append(f"layout ({T_LA}): measured at 390")
    if k == '/api/search.php':
        out.append(f"search ({T_SC}): the scorecard, {SCORE['pass']} of {SCORE['of']} cases pass")
    return out


def page_state(route, status):
    k = strip_q(route)
    rs = RENDER_BY.get(k, [])
    if status.startswith('Coming'):
        return 'n', 'Coming'
    if rs:
        if any(x['status'] == 0 for x in rs):
            return 'r', 'No answer'
        if any(x['scroll'] for x in rs):
            return 'a', 'Less than optimal: sideways scroll'
        if any(x['edges'] for x in rs):
            return 'a', 'Less than optimal: card edges'
        if all(x['status'] == 200 for x in rs):
            return 'g', 'Working'
        if '?' in route or route.endswith('='):
            return 'g', f"Answers ({'/'.join(sorted({str(x['status']) for x in rs}))} without its id)"
        return 'a', 'Answered ' + '/'.join(sorted({str(x['status']) for x in rs}))
    if k in PATH_BY or k in LAYOUT_BY:
        return 'g', 'Working'
    return 'n', 'not checked yet'


# the module a page depends on: hosting is Bluehost's for every page (Preflight 'hosting'); the rest inferred from purpose
DEP_RULES = [(r'storefront|trvgo|domain', 'fly'), (r'pay|checkout|order|billing|invoice|plan', 'quickbooks'), (r'upload|photo|media|file|gallery', 'aws'),
             (r'video', 'video'), (r'sign.?in|log.?in|password|sign.?up', 'cloudflare'), (r'guide', 'anthropic'), (r'/api/crawl/', 'railway'),
             (r'email|mail|notice|push|message', 'dispatcher'), (r'release|staging|sync', 'github')]


def deps_for(route, purpose):
    d = ['bluehost']
    t = (route + ' ' + purpose).lower()
    for rx, m in DEP_RULES:
        if re.search(rx, t) and m not in d:
            d.append(m)
    return d


def archived_note(route):
    k = strip_q(route)
    for line in ARCHIVE.split('\n'):
        if k in line and line.startswith('|'):
            return re.sub(r'`', '', ' '.join(c.strip() for c in line.strip('|').split('|')[1:3]))[:220]
    return ''


PAGES = []  # one record per page snapshot
GROUPS = []
for g in PAGES_MD:
    rows = []
    for r in g['rows']:
        st, words = page_state(r['route'], r['status'])
        rep = re.search(r'replaced by ([^;.]+)', r['status']) or re.search(r'Moved to ([^;.]+)', r['status'])
        rec = dict(id=len(PAGES), group=g['name'], sub=r['sub'], route=r['route'], also=r['also'], purpose=r['purpose'], adr=r['adr'], status=r['status'],
                   state=st, words=words, probes=probes_for(r['route']), deps=deps_for(r['route'], r['purpose']),
                   endpoints=re.findall(r'/api/[\w/.-]+', r['purpose']), replaces=(rep.group(1).strip() if rep else ''), archived=archived_note(r['route']) if 'Archived' in r['status'] else '',
                   access='admin (staff)' if r['route'].startswith('/admin') else ('signed in' if r['route'].startswith(('/user', '/business')) else ('worker token' if '/api/crawl/' in r['route'] else 'public')),
                   src='site-audit/pages.md')
        PAGES.append(rec); rows.append(rec['id'])
    GROUPS.append({'name': g['name'], 'rows': rows, 'src': 'pages.md'})
for m in MANIFEST:
    rows = []
    for sct in m['secs']:
        st, words = page_state(sct['route'], sct['kind'])
        rec = dict(id=len(PAGES), group=f"Admin · {m['mod']}", sub='', route=sct['route'], also=[], purpose=sct['label'], adr='0109', status=sct['kind'], state=st, words=words,
                   probes=probes_for(sct['route']), deps=deps_for(sct['route'], sct['label']), endpoints=[f"/admin/api/{m['mod']}/<tool>"], replaces='', archived='', access='admin (staff)',
                   src='app/admin/manifests.php (mirror aeb5f31)')
        PAGES.append(rec); rows.append(rec['id'])
    GROUPS.append({'name': f"Admin · {m['mod']} (manifest)", 'rows': rows, 'src': 'manifests.php'})

# ------------------------------------------------------------------ modules from the generated card mockups
def balanced(s, start, tag):
    i, depth = start, 0
    pat = re.compile(rf'<{tag}\b|</{tag}>')
    for m in pat.finditer(s, start):
        depth += 1 if m.group(0) != f'</{tag}>' else -1
        if depth == 0:
            return s[start:m.end()]
    return s[start:]


def articles(fname):
    s = open(MOCK + fname).read()
    return [(m.group(1), balanced(s, m.start(), 'article')) for m in re.finditer(r'<article class="card[^"]*" aria-label="([^"]*)"', s)], s


def intro_of(s):
    m = re.search(r'<div class="intro">(.*?)<div class="legend"', s, re.S)
    return re.sub(r'\s+', ' ', re.sub('<[^>]+>', '', m.group(1))).strip() if m else ''


MODS = [  # id, aria-label, role, section, scenario pages (in order), admin pages (manifest routes)
    ('bluehost', 'Bluehost', 'Host', '§14', ['bluehost-b-working', 'bluehost-c1-pages-down', 'bluehost-c2-database', 'bluehost-c3-cpanel-key', 'bluehost-c4-cron', 'bluehost-c5-email', 'bluehost-c6-env'],
     ['/admin/config/control', '/admin/config/scheduler', '/admin/config/dbupdates', '/admin/config/settings']),
    ('railway', 'Railway', 'Host', '§1–§11', ['railway-b-working', 'railway-c1-deploy-failed', 'railway-c2-crawler-down', 'railway-c3-w0-silent', 'railway-c4-key-missing', 'railway-c5-spend', 'railway-c6-switch-left-off'],
     ['/admin/config/control', '/admin/insights/crawler', '/admin/config/workflows', '/admin/config/scheduler']),
    ('github', 'GitHub', 'Host', '§12', ['github-b-working', 'github-c1-api-down', 'github-c2-token', 'github-c3-sync-stuck', 'github-c4-sync-missed', 'github-c5-check-failed', 'github-c6-release-look'],
     ['/admin/config/releases', '/admin/config/staging', '/admin/config/decisions']),
    ('fly', 'Fly.io', 'Host', '§25', ['flyio-a-working', 'flyio-b-cert-failing'], ['/admin/market/domains', '/admin/market/storefronts']),
    ('anthropic', 'Anthropic (Claude)', 'Service', '§15', ['anthropic-a-working', 'anthropic-b-near-cap', 'anthropic-c-at-cap', 'anthropic-d-key-refused'], ['/admin/config/ai', '/admin/config/costs', '/admin/config/thresholds']),
    ('googlecloud', 'Google Cloud', 'Service', '§16', ['google-a-working'], ['/admin/config/register', '/admin/config/costs']),
    ('gemini', 'Google AI Studio (Gemini)', 'Service', '§16', ['google-a-working', 'google-b-quota', 'google-d-privacy-guard'], ['/admin/config/ai', '/admin/config/register']),
    ('quickbooks', 'QuickBooks (Intuit)', 'Service', '§19', ['quickbooks-a-working', 'quickbooks-b-token', 'quickbooks-c-down', 'quickbooks-d-test-guard'], ['/admin/config/register', '/admin/market/gateways', '/admin/config/costs']),
    ('dispatcher', 'The Communications Dispatcher', 'Service', '§20', ['dispatcher-a-working', 'dispatcher-b-email', 'dispatcher-c-push', 'dispatcher-d-nobody'], ['/admin/config/communications', '/admin/pulse/stream']),
    ('aws', 'Amazon Web Services', 'Service', '§21', ['aws-a-working', 'aws-b-ses-paused', 'aws-c-s3-slowdown', 'aws-d-key-refused'], ['/admin/config/register', '/admin/config/communications', '/admin/config/costs']),
    ('video', 'The video worker', 'Service', '§23', ['video-a-working', 'video-b-silent', 'video-c-job-failed'], ['/admin/config/control', '/admin/config/register']),
    ('cloudflare', 'Cloudflare', 'Service', '§24', ['cloudflare-a-working', 'cloudflare-b-turnstile-refused'], ['/admin/config/preflight', '/admin/config/register']),
    ('places', 'Google Places', 'Source', '§16', ['google-a-working', 'google-b-quota', 'google-c-key-refused'], ['/admin/config/register', '/admin/config/costs']),
    ('tavily', 'Tavily', 'Source', '§17', ['tavily-a-working', 'tavily-b-plan-limit', 'tavily-c-key-refused', 'tavily-d-server'], ['/admin/config/thresholds', '/admin/config/costs']),
    ('brave', 'Brave Search', 'Source', '§18', ['brave-a-working', 'brave-b-quota', 'brave-c-key-refused', 'brave-d-both-down'], ['/admin/config/thresholds', '/admin/config/costs']),
    ('overture', 'Overture Maps (Places)', 'Source', '§22.1', ['sources-b-overture'], ['/admin/insights/crawler', '/admin/insights/data']),
    ('foursquare', 'Foursquare open places', 'Source', '§22.2', ['sources-c-foursquare'], ['/admin/insights/crawler', '/admin/insights/data']),
    ('geofabrik', 'Geofabrik (OpenStreetMap extracts)', 'Source', '§22.3', ['sources-d-geofabrik'], ['/admin/insights/crawler', '/admin/insights/data']),
    ('cms', 'NPI Registry (CMS)', 'Source', '§22.4', ['sources-e-npi'], ['/admin/insights/crawler', '/admin/dashboard/today']),
    ('irs', 'IRS (exempt organizations)', 'Source', '§22.5', ['sources-f-irs'], ['/admin/insights/crawler', '/admin/insights/data']),
]
MOD_IDS = {m[0] for m in MODS}
PANELS = {}  # id -> aside html (setup panels once; page panels renamed per scenario)
MODULES = []
for mid, label, role, sec, pages_, admin in MODS:
    scns = []
    for pg in pages_:
        arts, src = articles(pg + '.html')
        mine = [a for (lab, a) in arts if lab == label]
        title = re.search(r'<title>([^<]*)', src).group(1)
        for i, a in enumerate(mine):
            sid = f'{mid}-{len(scns)}'
            for pid in set(re.findall(r'data-open-panel="([^"]+)"', a)):
                m = re.search(rf'<aside class="panel" id="{re.escape(pid)}"', src)
                if not m:
                    continue
                aside = balanced(src, m.start(), 'aside')
                if pid.startswith('p-setup-'):
                    PANELS.setdefault(pid, aside)
                else:
                    nid = f'{pid}--{sid}'
                    PANELS[nid] = aside.replace(f'id="{pid}"', f'id="{nid}"', 1)
                    a = a.replace(f'data-open-panel="{pid}"', f'data-open-panel="{nid}"')
            light = re.search(r'<span class="lt (\w)"><i aria-hidden="true"></i>([^<]*)</span>', a)
            scns.append(dict(id=sid, title=html.unescape(title) + (f' ({i + 1} of {len(mine)})' if len(mine) > 1 else ''), file=pg + '.html',
                             intro=intro_of(src), card=a, light=(light.group(1), html.unescape(light.group(2))) if light else ('n', ''),
                             real=('working' in pg or pg in ('sources-c-foursquare', 'sources-e-npi', 'anthropic-b-near-cap'))))
    crit = re.search(r'<span class="badge crit">(.*?)</span>', scns[0]['card'])
    MODULES.append(dict(id=mid, label=label, role=role, sec=sec, scns=scns, admin=admin, crit=re.sub('<[^>]+>', '', crit.group(1)) if crit else ''))
N_SCN = sum(len(m['scns']) for m in MODULES)


def rad_of(card):
    i = card.find('<div class="thread"')
    j = card.find('<div class="rad">', i if i >= 0 else 0)
    if j < 0:
        return '<p class="sub">No radius drawn on this card.</p>'
    return balanced(card, j, 'div')


def slots_of(card):
    j = card.find('<div class="slots">')
    return balanced(card, j, 'div') if j >= 0 else ''


# ------------------------------------------------------------------ workflows and needs (live code)
def parse_workflows():
    s = open(MIRROR + 'sync-app/lib/Automation.php').read()
    return re.findall(r"'(W\d+)' => \['((?:[^'\\]|\\.)*)', '((?:[^'\\]|\\.)*)', '((?:[^'\\]|\\.)*)'\]", s)


def parse_needs():
    s = open(MIRROR + 'sync-app/lib/Preflight.php').read()
    a = s.index('public const NEEDS = ['); b = s.index('\n    ];', a)
    out = []
    for m in re.finditer(r"'(\w+)' => \['(needs|siteneeds)', '((?:[^'\\]|\\.)*)', \[([^\]]*)\], \[(.*?)\]\], '((?:[^'\\]|\\.)*)', (true|false)", s[a:b]):
        srcs = []
        for it in re.findall(r"\[([^\[\]]*)\]", '[' + m.group(5) + ']'):
            q = re.findall(r"'((?:[^'\\]|\\.)*)'", it)
            if len(q) >= 2 and q[0] in ('svc', 'line', 'copy', 'ours', 'paid'):
                srcs.append((q[0], q[1], q[2] if len(q) > 2 else ''))
        out.append(dict(key=m.group(1), kind=m.group(2), label=m.group(3).replace("\\'", "'"), wfs=re.findall(r"'(W\d+)'", m.group(4)),
                        srcs=[(k, v, (lab or v).replace("\\'", "'")) for k, v, lab in srcs], fallback=m.group(6).replace("\\'", "'"), soft=m.group(7) == 'true'))
    return out


WORKFLOWS = parse_workflows()
NEEDS = parse_needs()
SVC2MOD = {'anthropic': 'anthropic', 'gemini': 'gemini', 'google': 'places', 'tavily': 'tavily', 'brave': 'brave', 'quickbooks': 'quickbooks', 'aws': 'aws',
           'cloudflare': 'cloudflare', 'fly': 'fly', 'github': 'github', 'bluehost': 'bluehost', 'overture': 'overture', 'foursquare': 'foursquare',
           'geofabrik': 'geofabrik', 'cms': 'cms', 'irs': 'irs'}
import svc26
WFNAMES = {w: n for w, n, _, _ in WORKFLOWS}
for _n, (key, role, name, scs) in enumerate(svc26.scenarios(NEEDS, WFNAMES), 1):
    MODULES.append(dict(id=key, label=name, role=role, sec=f'§26.{_n}', admin=['/admin/config/register'] + (['/admin/insights/sources'] if role == 'Source' else []),
                        crit='Not critical: no duty stops without it', more=True,
                        scns=[dict(id=f'{key}-{i}', title=t, file='', intro=('A real fault from the alarms snapshot (3:19 AM MST).' if i else 'Its record, needs and callers in the live code (03 §26).'),
                                   card=c, light=l, real=r) for i, (t, c, l, r) in enumerate(scs)]))
    SVC2MOD[key] = key
N_SCN = sum(len(m['scns']) for m in MODULES)
LINE2MOD = {'rw:beat': 'railway', 'rw:crawler': 'railway', 'rw:api': 'railway', 'site:db': 'bluehost', 'osm': 'geofabrik'}
WF_ALARMS = {}
for a in ALARMS['open']:
    if a['process'].startswith('wf:'):
        WF_ALARMS.setdefault(a['process'][3:], []).append(a)


def mod_for(kind, key):
    if kind == 'svc':
        return SVC2MOD.get(key)
    if kind in ('line', 'copy'):
        return LINE2MOD.get(key)
    return None


# coverage: services the needs name with no card yet
NO_CARD = sorted({v for n in NEEDS for (k, v, _) in n['srcs'] if k == 'svc' and v not in SVC2MOD})

# ------------------------------------------------------------------ the delivery promises (for-jason-review item 9)
def sparkline(vals, w=120, h=26):
    if len(vals) < 2:
        return '<span class="nohist">No history yet</span>'
    lo, hi = min(vals), max(vals)
    pts = ' '.join(f'{i * (w - 4) / (len(vals) - 1) + 2:.1f},{h - 3 - (v - lo) / ((hi - lo) or 1) * (h - 6):.1f}' for i, v in enumerate(vals))
    return f'<svg class="spark" viewBox="0 0 {w} {h}" width="{w}" height="{h}" aria-label="trend"><polyline points="{pts}" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>'


paths_trend = [sum(p['status'] == 'passed' for p in r['paths']) / len(r['paths']) * 100 for r in PHIST]
pub_trend = [CRAWL[d]['finds']['published'] for d in sorted(CRAWL)]
signin_trend = [100 if any(p['id'] == 'sign-in' and p['status'] == 'passed' for p in r['paths']) else 0 for r in PHIST]
r200 = sum(1 for x in RENDERED['results'] if x['status'] == 200); rN = len(RENDERED['results'])
noans = sorted({x['url'] for x in RENDERED['results'] if x['status'] == 0})
phone = [x for x in RENDERED['results'] if x['size'] == 'phone']
scroll_pages = sorted({x['url'] for x in phone if x['scroll']}); edge_pages = sorted({x['url'] for x in phone if x['edges']})
pc = {k: sum(1 for p in PATHS['results'] if p['status'] == k) for k in ('passed', 'issues', 'failed', 'skipped')}
ran = pc['passed'] + pc['issues'] + pc['failed']
fails_sc = [c for c in SCORE['cases'] if not c['pass']]
c21 = CRAWL['2026-11-21']
TODAY = ' · '.join(ALARMS['summary'])


def obj(oid, name, measure, target, reading, state, pct=None, trend='', chain=None, fix=None, improve=None, basis='obs', when=''):
    return dict(id=oid, name=name, measure=measure, target=target, reading=reading, state=state, pct=pct, trend=trend, chain=chain, fix=fix or [], improve=improve or [], basis=basis, when=when)


SET = 'target: set by Jason'
PROMISES = [
    ('1–2', 'The site is there, and people find it', [
        obj('o-pages', 'Pages answer', 'the rendered check: every page as each kind of visitor, at 1280 and 390', SET,
            f'{r200} of {rN} views answered 200 ({r200 / rN * 100:.1f}%). {len(noans)} page gave no answer ({", ".join(noans)}); the rest were pages opened without their id (404) or needing a sign-in (401, 403, 405).',
            'a', r200 / rN * 100, sparkline([]), fix=['bluehost'], when=T_RE),
        obj('o-speed', 'Pages load fast', 'pages under about 2.5 s, 99.5% of the time', 'about 2.5 s, 99.5% (the session manager\'s proposal; ' + SET + ')',
            'not checked yet: the paths check times each step, but nothing measures 2.5 s across pages', 'n', None, sparkline([]), fix=['bluehost']),
        obj('o-signin', 'Sign-in works end to end', 'the paths check: sign in, then sign out', 'both pass on every run',
            'Sign-in and sign-out passed', 'g', 100, sparkline(signin_trend), fix=['bluehost', 'cloudflare', 'dispatcher'], when=T_PA),
        obj('o-phone', 'The phone layout', 'the rendered check at 390 and the layout probe', 'no sideways scroll; 3px card edges (decisions/0058 §11)',
            f'{len(scroll_pages)} page scrolls sideways ({", ".join(scroll_pages)}); card edges off on {len(edge_pages)} admin pages; the layout probe\'s typing panel timed out ({T_LA})',
            'a', (len(phone) - len(scroll_pages)) / len(phone) * 100, sparkline([]), fix=['bluehost'], improve=['Fix the 13 card edges and the one sideways scroll in a design release'], when=T_RE),
        obj('o-search', 'Search finds what they came for', 'the search scorecard (decisions/0060 and the probe\'s cases)', SET + ' (the probe\'s own bar: every case passes)',
            f'{SCORE["pass"]} of {SCORE["of"]} cases pass ({SCORE["pass"] / SCORE["of"] * 100:.1f}%): {len(fails_sc)} short, 2 with no results (Our index), 3 slow while typing',
            'a', SCORE['pass'] / SCORE['of'] * 100, sparkline([]), chain='c2', fix=['geofabrik', 'overture'], improve=['Schedule a crawl for the towns that fall short', 'Queue the gaps for search learning (W12)'], when=T_SC),
        obj('o-near', '3 within 5 mi', 'at least 3 results within 5 mi (30 for the outdoors), decisions/0060', '3 within 5 mi; 30 for the outdoors (decisions/0060)',
            'not checked yet as defined. The scorecard\'s "Near me" group passes 9 of 9, at 10 mi reach in Phoenix', 'n', None, sparkline([]), chain='c2', fix=['geofabrik', 'overture']),
        obj('o-paths', 'Paths complete', 'the paths check: a visitor\'s whole task, start to finish', SET,
            f'{pc["passed"]} passed, {pc["issues"]} with issues, {pc["failed"]} failed of {ran} run ({pc["skipped"]} skipped). Failed: set-place, "no distance to set once the place is chosen" (/directory/)',
            'a', pc['passed'] / ran * 100, sparkline(paths_trend), fix=['bluehost'], improve=['Give /directory/ a distance once the place is chosen (rule J01)'], when=T_PA),
    ]),
    ('3 and 5', 'What they see is true, and knowledge keeps growing', [
        obj('o-fresh', 'Fresh', 'each copy\'s last load inside its cadence (the health report\'s status)', SET,
            'the health report: "NPI Registry: warn" (its monthly file is off since Nov 2, CMS took the page down)', 'a', None, sparkline([]), fix=['cms'], when=T_AL),
        obj('o-complete', 'Complete', 'listings with an exact map point (the crawler snapshot)', SET,
            f'{c21["map_points"]["public"]:,} public map points, {c21["map_points"]["approximate"]:,} approximate ({c21["map_points"]["approximate"] / c21["map_points"]["public"] * 100:.0f}%); {c21["staged"]["no_kind"]:,} staged finds with no kind',
            'a', (1 - c21['map_points']['approximate'] / c21['map_points']['public']) * 100, sparkline([]), fix=['geofabrik', 'railway'], improve=['Geocode the approximate points (W2 Map points)'], when=mst(c21['at'])),
        obj('o-leak', 'Nothing private or sacred leaks', 'held words stay held (decisions/0066, 0164 R7)', 'none, ever',
            'not checked yet: no probe reads it', 'n', None, sparkline([])),
        obj('o-photos', 'Photos load', 'the photos check (daily, site-checks.yml)', 'every kind loads', 'not checked yet: its results aren\'t in site-audit/results', 'n', None, sparkline([]), fix=['aws', 'cloudflare']),
        obj('o-gather', 'Gathering reconciles', 'workflows running, and records in and stored (the alarms snapshot)', 'every workflow on; records in every day',
            f'W1 paused whole by two trips (overture_bulk #1290, foursquare_bulk #1288); W3 tripped (Common Crawl #35). "Records: 0 in, 0 stored, 0 refused" in 24 hours',
            'r', None, sparkline([]), chain='c1', fix=['railway', 'bluehost', 'overture', 'foursquare'], when=T_AL),
        obj('o-grow', 'Knowledge keeps growing', 'published listings (the crawler snapshot, daily)', SET,
            f'{c21["finds"]["published"]:,} published (from {pub_trend[0]:,} on Nov 13); {c21["staged"]["review"]} waiting in Review; the Review queue\'s age: not checked yet',
            'g', None, sparkline(pub_trend), fix=['railway'], when=mst(c21['at'])),
    ]),
    ('4 and 6', 'Money, orders, email and caps', [
        obj('o-pay', 'Payments go through', 'QuickBooks\' token and a test order (the paths check\'s send-order)', 'every order paid or invoiced',
            'not checked yet: the send-order path was skipped; QuickBooks\' token isn\'t read here', 'n', None, sparkline([]), fix=['quickbooks']),
        obj('o-renew', 'Renewals run', 'billing_tick, a critical job (payments)', 'runs on time',
            'the health report: "Scheduled tasks: bad" (which job isn\'t named). While QuickBooks is down, renewals are held (03 §19)', 'a', None, sparkline([]), fix=['bluehost', 'quickbooks'], when=T_AL),
        obj('o-email', 'Email is delivered', 'SES\'s bounce and complaint rates; SMTP sends', '5% bounce, 0.1% complaint (inf)', 'not checked yet: the rates aren\'t read', 'n', None, sparkline([]), fix=['dispatcher', 'aws']),
        obj('o-claude', 'Within our means: Claude (AI)', 'spend against its monthly cap', '$40.00 a month (ai_month_usd)',
            'Claude (AI): $27.05 of $40.00 (68%), past its 50% warning', 'a', 68, sparkline([]), fix=['anthropic'], improve=['Raise the cap, or pace on-demand guides'], when=T_AL),
        obj('o-places', 'Within our means: Google Places', 'checks against the monthly cap', '1,000 checks a month',
            'Google Places check: 544 of 1,000 checks (54%), past its 50% warning', 'a', 54.4, sparkline([]), fix=['places'], improve=['Raise the cap, or check fewer listings a run'], when=T_AL),
        obj('o-search-cap', 'Within our means: web search', 'Tavily\'s searches against websearch_monthly', 'websearch_monthly (not read yet)',
            'Tavily, this month: 26 searches · 20.8¢ (the approved report)', 'n', None, sparkline([]), fix=['tavily', 'brave']),
    ]),
    ('7', 'The team is told, and things recover', [
        obj('o-told', 'Trips reach a person', 'each trip\'s email and push', 'every trip, at once',
            'the four open trips were sent by email and push (live L2, L3)', 'g', 100, sparkline([]), fix=['dispatcher'], when=T_AL),
        obj('o-uptime', 'Things recover', 'the whole system\'s uptime, last 24 hours (the historian)', SET, 'uptime 97.5%; 2 trips raised; 21 alarms open now', 'a', 97.5, sparkline([]), chain='c1', fix=['railway'], when=T_AL),
        obj('o-ir', 'Incident Reports close', 'open IRs and their root cause', 'every IR closed with a confirmed cause',
            'IR-0001 open: root cause Unconfirmed until the endpoint\'s timings are read', 'a', None, sparkline([]), chain='c1', fix=['bluehost'], when=T_AL),
        obj('o-selfrec', 'The system recovers by itself', 'the self-recovery rate', SET, 'not checked yet', 'n', None, sparkline([])),
    ]),
]
N_OBJ = sum(len(g[2]) for g in PROMISES)
LT = {'g': 'Working', 'a': 'Less than optimal', 'r': 'Trip', 'n': 'not checked yet'}
# a compact metric per objective (Jason, 2026-11-21: visual-forward, scannable first); the full reading stays in its detail
pct = lambda a, b: f'{a / b * 100:.1f}%'
METRIC = {'o-pages': f'{r200}/{rN} answered · {pct(r200, rN)}', 'o-signin': 'sign-in and sign-out passed', 'o-phone': f'{len(scroll_pages)} sideways · {len(edge_pages)} edges off',
          'o-search': f'{SCORE["pass"]}/{SCORE["of"]} cases · {pct(SCORE["pass"], SCORE["of"])}', 'o-paths': f'{pc["passed"]}/{ran} passed · {pc["failed"]} failed',
          'o-fresh': 'NPI Registry: warn', 'o-complete': f'{c21["map_points"]["approximate"]:,} of {c21["map_points"]["public"]:,} approximate', 'o-gather': 'W1, W3 paused · 0 records in',
          'o-grow': f'{c21["finds"]["published"]:,} published', 'o-renew': 'Scheduled tasks: bad', 'o-claude': '$27.05 / $40 · 68%', 'o-places': '544 / 1,000 · 54%',
          'o-search-cap': '26 searches · 20.8¢', 'o-told': '4 of 4 trips sent', 'o-uptime': '97.5% uptime · 24 h', 'o-ir': 'IR-0001 open · Unconfirmed'}
SHORT = lambda lab: re.sub(r'^The Communications ', '', re.sub(r'\s*\(.*?\)', '', lab)) or lab

# ------------------------------------------------------------------ HTML pieces
ICON = {
    'board': '<path d="M3 3h7v7H3zM14 3h7v4h-7zM14 10h7v11h-7zM3 13h7v8H3z"/>',
    'mods': '<path d="M4 4h16v6H4zM4 14h16v6H4z"/>',
    'wf': '<path d="M5 6h6M5 12h14M5 18h9"/><circle cx="15" cy="6" r="2"/><circle cx="18" cy="18" r="2"/>',
    'diag': '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
    'pages': '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/>',
    'gap': '<path d="M12 4l9 16H3z"/><path d="M12 10v5M12 17v1"/>',
    'chev': '<path d="M9 6l6 6-6 6"/>',
}


def ico(n, s=16):
    return f'<svg class="ic" viewBox="0 0 24 24" width="{s}" height="{s}" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">{ICON[n]}</svg>'


def lt(k, w):
    return f'<span class="lt {k}"><i aria-hidden="true"></i>{E(w)}</span>'


def bar(pct, k):
    if pct is None:
        return '<div class="pbar none"><span>no bar: not a share</span></div>' if k != 'n' else '<div class="pbar none"><span>not checked yet</span></div>'
    return f'<div class="pbar {k}" role="img" aria-label="{pct:.0f}%"><b style="width:{min(100, pct):.1f}%"></b></div>'


def modlink(m, scn=0):
    lab = next((x['label'] for x in MODULES if x['id'] == m), m)
    return f'<a href="#" class="mlink" data-go-mod="{m}" data-scn="{scn}">{E(lab)}</a>'


def objective(o):
    short = lambda m: SHORT(next(x["label"] for x in MODULES if x["id"] == m))
    fix = ''.join(f'<button class="btn" data-go-mod="{m}">Fix it: {E(short(m))}</button>' for m in o['fix'])
    imp = (f'<details class="imp pop"><summary class="btn imp">Improve</summary><div class="popb"><button class="px" type="button" aria-label="Close">×</button>' + ''.join(f'<button class="btn imp" title="Drawn: inert in the mockup">{E(t)}</button>' for t in o['improve']) + '</div></details>') if o['improve'] else ''
    ch = f'<button class="btn f" data-open-panel="p-{o["chain"]}-0">Drill down</button>' if o['chain'] else ''
    live = f'<span class="live">live · <time>{E(o["when"].split(", ", 1)[-1])}</time></span>' if o['when'] else '<span class="live none">not checked yet</span>'
    metric = METRIC.get(o['id'], 'not checked yet')
    return f"""<div class="obj s-{o['state']}" id="{o['id']}"><div class="oh"><b>{E(o['name'])}</b>{lt(o['state'], LT[o['state']])}</div>
<div class="om2"><span class="metric">{E(metric)}</span>{live}</div>
<div class="orow">{bar(o['pct'], o['state'])}<span class="trend">{o['trend']}</span></div>
<div class="btns">{ch}{fix}{imp}</div>
<details class="more"><summary>Measure, target, reading</summary><dl class="dl"><dt>Measure</dt><dd>{E(o['measure'])}</dd><dt>Target</dt><dd>{E(o['target'])}</dd>
<dt>Reading</dt><dd>{E(o['reading'])}{' · <time>' + E(o['when']) + '</time>' if o['when'] else ''} <span class="basis">{o['basis']}</span></dd></dl></details></div>"""


def board_view():
    boxes = ''
    for num, title, objs in PROMISES:
        worst = 'r' if any(o['state'] == 'r' for o in objs) else 'a' if any(o['state'] == 'a' for o in objs) else 'g'
        boxes += f'<section class="pbox s-{worst}"><h2><span class="pn">{num}</span>{E(title)}</h2>{"".join(objective(o) for o in objs)}</section>'
    grp = ''
    for gi, g in enumerate(GROUPS):
        ps = [PAGES[i] for i in g['rows']]
        cnt = {k: sum(1 for p in ps if p['state'] == k) for k in 'grna'}
        grp += (f'<div class="sg"><button class="sgh" data-group="{gi}" aria-expanded="false">{ico("pages")}<b>{E(g["name"])}</b>'
                f'<span class="cnt">{len(ps)} pages · {cnt["g"]} working · {cnt["a"]} less than optimal · {cnt["r"]} failing · {cnt["n"]} not checked yet</span>{ico("chev")}</button>'
                f'<div class="rt" id="rt-{gi}" hidden></div></div>')
    return f"""<section class="view" id="view-board" data-title="Board: is it delivering?">
<div class="vh"><div><h1>Is it delivering?</h1><p class="sub">The seven delivery promises (for-jason-review item 9), as four boxes. Each objective: its measure, its target, the reading, a bar, its trend, and Fix it or Improve to its parts. Each is a warning first. Readings are the real ones, each with its time.</p></div>
<div class="seg" role="group" aria-label="Layout"><button data-layout="grid" aria-pressed="true">2 columns</button><button data-layout="rows" aria-pressed="false">Stacked</button></div></div>
<div class="pgrid" id="pgrid">{boxes}</div>
<div class="vh" style="margin-top:1.2rem"><div><h2 class="h2">The pages, as monitored units</h2><p class="sub">Every page in <code>site-audit/pages.md</code> (live and coming) and every admin route in the mirror's <code>app/admin/manifests.php</code>: {len(PAGES)} pages in {len(GROUPS)} groups. Open a group for its route table; Manage opens a page.</p></div></div>
<div class="sgs">{grp}</div></section>"""


def _plain(t):
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', '', t))).strip()


def pill_rad(rad):
    """Held · On a backup · Unaffected as structured pills, each list one tap away (balanced, so nested blocks survive)."""
    inner = rad[len('<div class="rad">'):-len('</div>')]
    out, i = '', 0
    for m in re.finditer(r'<div class="([hou])"><b>([^<]*)</b>', inner):
        if m.start() < i:
            continue
        blk = balanced(inner, m.start(), 'div')
        out += inner[i:m.start()]
        i = m.start() + len(blk)
        c, lab, body = m.group(1), m.group(2), blk[m.end() - m.start():-len('</div>')]
        items = re.findall(r'<li>(.*?)</li>', body, re.S)
        if items:
            first = _plain(items[0])
            first = first if len(first) <= 60 else first[:60].rsplit(' ', 1)[0] + '…'
            out += f'<details class="rp {c}"><summary><span class="rpill {c}">{lab}</span><span class="rn">{len(items)}</span><span class="rf">{E(first)}</span></summary>{body}</details>'
        else:
            out += f'<div class="rp {c}"><span class="rpill {c}">{lab}</span><span class="rf">{E(_plain(body))}</span></div>'
    return '<div class="rad">' + out + inner[i:] + '</div>'


def _rads(card):
    out, i = '', 0
    for m in re.finditer(r'<div class="rad">', card):
        if m.start() < i:
            continue
        blk = balanced(card, m.start(), 'div')
        out += card[i:m.start()] + pill_rad(blk)
        i = m.start() + len(blk)
    return out + card[i:]


HTML_TAGS = {'a', 'b', 'i', 'em', 'strong', 'code', 'span', 'div', 'p', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'details', 'summary',
             'button', 'article', 'section', 'aside', 'h1', 'h2', 'h3', 'h4', 'br', 'small', 'sub', 'sup', 'label', 'input', 'svg', 'path', 'nav', 'img', 'u', 's', 'kbd'}


def unplaceholder(h):
    """Spec placeholders written as <time>, <name>, <SENDER> in the card mockups are text, not tags: escaped here."""
    def one(m):
        t = m.group(1)
        if t.split()[0].lower() in HTML_TAGS:
            return m.group(0)
        if t == 'time' and '</time>' in h[m.end():m.end() + 120]:
            return m.group(0)
        return '&lt;' + t + '&gt;'
    return re.sub(r'<([A-Za-z][\w ]*?)>', one, h)


def compact(card):
    """Jason, 2026-11-21: visual-forward. Structure moves to pills and step tags; the prose moves one tap away."""
    card = unplaceholder(card)
    card = _rads(card)
    card = re.sub(r'<div class="doing"><b>What the system is doing:</b>\s*(.*?)</div>',
                  r'<details class="more doing"><summary><span class="pill">What the system is doing</span></summary>\1</details>', card, flags=re.S)
    card = re.sub(r'<div class="sub" style="[^"]*border-left[^"]*"><b>Today(?:&#x27;|\'|’)s words, beside the new:</b>(.*?)</div>',
                  r'<details class="more copy"><summary><span class="pill">Copy pair: today · new</span></summary>\1</details>', card, flags=re.S)
    fx = re.search(r'<div class="slot[^"]* fix[^"]*">.*?<div class="ty">([^<·]+)', card, re.S)
    btn = re.search(r'<button class="btn f"[^>]*>([^<]+)</button>', card)
    tags = []
    if fx:
        tags.append(f'Open {fx.group(1).strip()}')
    if btn:
        tags.append(btn.group(1).strip())
    st = '<span class="steps">' + ' <span aria-hidden="true">→</span> '.join(f'<span class="step"><b>{i}</b> {E(t)}</span>' for i, t in enumerate(tags, 1)) + '</span>' if tags else ''
    card = re.sub(r'<div class="then">(.*?)</div>', lambda m: f'<div class="then">{st}<details class="more"><summary>{"Why these steps" if tags else "Notes"}</summary>{m.group(1)}</details></div>', card, flags=re.S)
    return card


def mod_card(m):
    seg = ''.join(f'<button class="sc-b" data-mod="{m["id"]}" data-scn="{i}" aria-pressed="{"true" if i == 0 else "false"}">{E(s["title"].split(": ", 1)[-1] if ": " in s["title"] else s["title"])}{"" if s["real"] else " · scenario"}</button>' for i, s in enumerate(m['scns']))
    cards = ''.join(f'<div class="scn" data-mod="{m["id"]}" data-scn="{i}" data-real="{1 if s["real"] else 0}"{"" if i == 0 else " hidden"}><details class="more scn-intro"><summary>About this state</summary><p class="sub">{E(s["intro"])}{f' <a href="../mockups/{s["file"]}">Its static mockup</a>' if s["file"] else ''}</p></details>{compact(s["card"])}</div>' for i, s in enumerate(m['scns']))
    l0 = m['scns'][0]['light']
    return f"""<details class="mod" id="mod-{m['id']}" data-role="{m['role']}"><summary><span class="mn">{E(m['label'])}</span><span class="badge role">{m['role']}</span><span class="mstate" data-mod-state="{m['id']}">{lt(*l0)}{' <span class="live">live</span>' if m['scns'][0]['real'] else ' <span class="sctag">scenario</span>'}</span><span class="sub">03 {m['sec']} · {len(m['scns'])} state{'s' if len(m['scns']) > 1 else ''}</span></summary>
<div class="seg wrapseg" role="group" aria-label="Scenario: {E(m['label'])}">{seg}</div>{'<p class="sub">The sync\'s OneDrive side: <a href="#" class="mlink" data-go-mod="microsoft">Microsoft\'s card</a> (03 §26).</p>' if m['id'] == 'github' else ''}{cards}</details>"""


def modules_view():
    by = {mm['id']: mm for mm in MODULES}
    hosts = ''.join(mod_card(by[i]) for i in ('bluehost', 'railway', 'github', 'fly'))
    svcs = ''.join(mod_card(by[i]) for i in ('anthropic', 'googlecloud', 'gemini', 'quickbooks', 'dispatcher', 'aws', 'video', 'cloudflare'))
    chain = f"""<div class="chainbox" data-role="Source"><h3>Web search: one chain</h3><p class="sub">Web search (W8, W10–W12): Tavily first, then Brave Search; both paused → "queued" (<code>Preflight::NEEDS</code> websearch). Each keeps its own manifest, radius and card below.</p>
<div class="chain">{modlink('tavily')}<span aria-hidden="true">→</span>{modlink('brave')}<span aria-hidden="true">→</span><span class="sub">queued</span></div>{mod_card(by['places'])}{mod_card(by['tavily'])}{mod_card(by['brave'])}</div>"""
    grp = f"""<div class="chainbox" data-role="Source"><h3>The batched sources: one group</h3><p class="sub">Five free bulk files the Railway worker loads for W1. <a href="../mockups/sources-a-list.html">The list</a>. Each keeps its own card below.</p>{''.join(mod_card(by[i]) for i in ('overture', 'foursquare', 'geofabrik', 'cms', 'irs'))}</div>"""
    more_s = ''.join(mod_card(m) for m in MODULES if m.get('more') and m['role'] == 'Service')
    more_r = ''.join(mod_card(m) for m in MODULES if m.get('more') and m['role'] == 'Source')
    grp += (f'<div class="chainbox" data-role="Service"><h3>More services (03 §26)</h3><p class="sub">Named by the source map with no card before; drawn from their records and callers. Never drawn as working unless they\'re called.</p>{more_s}</div>'
            f'<div class="chainbox" data-role="Source"><h3>More sources (03 §26)</h3><p class="sub">The same, for sources. Common Crawl, Wikimedia and the Census carry their real faults from the alarms snapshot.</p>{more_r}</div>')
    return f"""<section class="view" id="view-modules" hidden data-title="Modules">
<div class="vh"><div><h1>Modules</h1><p class="sub">Every module, the hosts included: {len(MODULES)} cards and {N_SCN} states, each the four-zone card of 03 §1–§26. Each card's switch flips between its states and the faults already specified. A state marked "scenario" is drawn, not read.</p></div>
<div class="seg" role="group" aria-label="Role"><button data-role-f="all" aria-pressed="true">All</button><button data-role-f="Host" aria-pressed="false">Hosts</button><button data-role-f="Service" aria-pressed="false">Services</button><button data-role-f="Source" aria-pressed="false">Sources</button></div></div>
<h2 class="h2 rh" data-role="Host">Hosts</h2>{hosts}<h2 class="h2 rh" data-role="Service">Services</h2>{svcs}<h2 class="h2 rh" data-role="Source">Sources</h2>{chain}{grp}</section>"""


def workflows_view():
    rows = ''
    for w, name, what, where in WORKFLOWS:
        needs = [n for n in NEEDS if w in n['wfs']]
        al = WF_ALARMS.get(w, [])
        if any(a['level'] == 'trip' for a in al):
            st = lt('r', 'Paused by the system') + ''.join(f'<div class="sub">{E(a["title"][:140])}</div>' for a in al if a['level'] == 'trip')
        elif al:
            st = lt('a', 'Warning') + ''.join(f'<div class="sub">{E(a["title"][:140])}</div>' for a in al)
        else:
            st = lt('n', 'no open alarm at ' + T_AL)
        nh = ''
        for n in needs:
            parts = []
            for k, v, lab in n['srcs']:
                mm = mod_for(k, v)
                parts.append(modlink(mm) if mm else (f'<span class="own">{E(lab)}</span>' if k in ('ours', 'paid') else f'<span class="nocard" title="no card yet">{E(lab if k != "svc" else v)}</span>'))
            nh += f'<li><b>{E(n["label"])}</b>: {" → ".join(parts)} <span class="sub">· else {E(n["fallback"])}</span></li>'
        rows += f'<tr><td><b>{w}</b> {E(name.replace(chr(92), ""))}<div class="sub">{E(what.replace(chr(92), ""))[:180]}</div></td><td><ul class="needs">{nh or "<li class=sub>no need in the map</li>"}</ul></td><td>{st}</td></tr>'
    duty = ''
    for n in [n for n in NEEDS if n['kind'] == 'siteneeds']:
        parts = []
        for k, v, lab in n['srcs']:
            mm = mod_for(k, v)
            parts.append(modlink(mm) if mm else (f'<span class="own">{E(lab)}</span>' if k in ('ours', 'paid') else f'<span class="nocard" title="no card yet">{E(lab if k != "svc" else v)}</span>'))
        duty += f'<tr><td><b>{E(n["label"])}</b></td><td>{" → ".join(parts)}<div class="sub">else {E(n["fallback"])}{" (soft: its fallback always works)" if n["soft"] else ""}</div></td></tr>'
    return f"""<section class="view" id="view-workflows" hidden data-title="Workflows">
<div class="vh"><div><h1>Workflows</h1><p class="sub">W0–W14 from the live <code>Automation::WORKFLOWS</code>, each need from <code>Preflight::NEEDS</code> in the map's order, linked to its card; the fallback is the need's own rule. State: the alarms snapshot, <time>{T_AL}</time>. A source with no card yet is greyed and listed under Coverage.</p></div></div>
<div class="tw"><table class="wt"><thead><tr><th>Workflow</th><th>Its needs: sources in order, then the fallback</th><th>State</th></tr></thead><tbody>{rows}</tbody></table></div>
<h2 class="h2">The operational duties (the site's needs)</h2><div class="tw"><table class="wt"><thead><tr><th>Duty</th><th>Its modules in order, then the fallback</th></tr></thead><tbody>{duty}</tbody></table></div></section>"""


def diag_view():
    out = ''
    for m in MODULES:
        rads = ''.join(f'<div class="scn" data-mod="{m["id"]}" data-scn="{i}"{"" if i == 0 else " hidden"}><div class="micro">{E(s["title"])} {'<span class="live">live</span>' if s["real"] else '<span class="sctag">scenario</span>'}</div>{compact(rad_of(s["card"]) if rad_of(s["card"]).startswith('<div class="rad">') else rad_of(s["card"]))}</div>' for i, s in enumerate(m['scns']))
        adm = ''.join(f'<li><a href="{ADMIN}{a}" target="_blank" rel="noopener">{ADMIN}{a}</a></li>' for a in m['admin'])
        out += f"""<details class="mod diag" data-role="{m['role']}"><summary><span class="mn">{E(m['label'])}</span><span class="badge role">{m['role']}</span><span class="mstate" data-mod-state="{m['id']}">{lt(*m['scns'][0]['light'])}{' <span class="live">live</span>' if m['scns'][0]['real'] else ' <span class="sctag">scenario</span>'}</span></summary>
<h3 class="h3">Incident Radius, following the selected state</h3>{rads}
<h3 class="h3">Where the work gets done</h3><ul class="adm">{adm}</ul><div class="sub">At the provider, its slots:</div>{slots_of(m['scns'][0]['card'])}</details>"""
    return f"""<section class="view" id="view-diagnostics" hidden data-title="Diagnostics">
<div class="vh"><div><h1>Diagnostics</h1><p class="sub">Each module's Incident Radius (Held, On a backup, Unaffected) follows the state selected on its card in Modules. Then where the work gets done: our admin pages (routes from <code>manifests.php</code>) and the provider's slots.</p></div></div>{out}</section>"""


# ------------------------------------------------------------------ the two drill-down chains
def first_sentence(t, n=110):
    plain = re.sub(r'<[^>]+>', '', t)
    m = re.match(r'(.+?[.:;])(\s|$)', plain)
    one = (m.group(1) if m else plain)
    return E(one if len(one) <= n else one[:n].rsplit(' ', 1)[0] + '…')


def level(cid, i, n, kind, name, state, why, evidence, nexts, root=None):
    back = f'<button class="btn back" data-open-panel="p-{cid}-{i - 1}">← Back</button>' if i else ''
    nx = ''.join(f'<button class="btn f" data-open-panel="p-{cid}-{i + 1}">{E(t)} →</button>' if t else '' for t in nexts)
    rt = ''
    if root:
        rt = '<div class="rootbox"><b>At the root</b><div class="btns">' + ''.join(root) + '</div></div>'
    color = "red" if "lt r" in state else "amber" if "lt a" in state or "lt h" in state else "green" if "lt g" in state else "grey"
    return (f'<aside class="panel" id="p-{cid}-{i}" hidden role="dialog" aria-label="{E(name)}"><button class="x" aria-label="Close">×</button>'
            f'<div class="crumb">Level {i + 1} of {n} · {E(kind)}</div><div class="lvrow">{state}<b class="lvn">{E(name)}</b></div>'
            f'<div class="why"><span class="micro">Why it\'s {color}</span> {first_sentence(why)}</div>'
            f'<details class="more"><summary>Why, in full</summary><p>{why}</p></details>'
            f'<details class="more ev"><summary><span class="pill">Evidence</span></summary>{evidence}</details>{rt}<div class="btns navb">{back}{nx}</div></aside>')


def chains():
    c1 = [
        ('Snapshot (promise 5)', 'Gathering reconciles', lt('r', 'Trip'),
         f'W1 is paused whole by two task trips, and W3 by Common Crawl\'s trip. "Records: 0 in, 0 stored, 0 refused" in the last 24 hours.',
         f'<ul><li>The alarms snapshot, <time>{T_AL}</time>: 4 trips open (pf:wf:W3, task:failed:overture_bulk, task:failed:foursquare_bulk, svc:issue:35)</li><li>Summary: {E(TODAY)}</li></ul>', ['Workflow W1 Listings']),
        ('Workflow', 'W1 Listings', lt('r', 'Paused by the system (twice)'),
         'Each task trip paused all of W1 ("W1 (its tasks wait with it)"). Under the contract a timeout reading our own reply is a warning on the Site endpoint, and the tasks aren\'t held (02 rows 11–14).',
         '<ul><li>system_pauses wf:W1, two rows (live L2, L3)</li><li>"Its tries ran out at 09:31 UTC with no fix: it\'s a trip for an admin"</li></ul>', ['Its tasks: overture_bulk, foursquare_bulk']),
        ('Tasks', 'overture_bulk (run #1290) and foursquare_bulk (run #1288)', lt('r', 'Trip (today)'),
         'Both failed before their first town, the same way: TimeoutError reading a reply.',
         '<ul><li>Railway\'s log, deploy 560ff94b: <code>08:20:34 foursquare_bulk stopped: TimeoutError(\'The read operation timed out\')</code></li>'
         '<li><code>08:31:41 overture_bulk stopped: TimeoutError(\'The read operation timed out\')</code> (run 4fbc278e, started 08:25:30)</li>'
         '<li>Jason\'s screenshot of run #1290\'s page: W1 · Added 205 · Changed 4 · Sent to Review 709; overture_bulk failed at 380 s</li>'
         '<li>No "records sent" line in either run</li></ul>' + f'<div class="btns">{modlink("overture")} {modlink("foursquare")}</div>', ['The endpoint they share: /api/crawl/loads.php']),
        ('Endpoint (a Site part)', '/api/crawl/loads.php', lt('a', 'Warning (Slow), under the contract'),
         'The only calls the two failures share are to our load endpoint: the opening GET (60 s), each 500-row POST (120 s), the town\'s done POST (60 s). The site stored rows from run #1290, while the worker never got the reply: that points at the response path.',
         '<ul><li><code>step2/ir-0001.md</code>: "Confirmed: the worker sent its request to our site\'s load endpoint, and no reply came within its timeout"</li><li>02 rows 11–13: B "/api/crawl/loads is slow · It didn\'t reply to the crawler within 120 s for 500 rows at 01:31 MST."</li></ul>', ['Its host: Bluehost']),
        ('Host', 'Bluehost (the site and its databases)', lt('a', 'Root cause Unconfirmed'),
         'Inside our load handler, or on the outbound/response path? It needs the endpoint\'s own timings for 08:15–08:21 and 08:26–08:32 UTC.',
         f'<ul><li>IR-0001: <a href="../mockups/railway-e-ir-0001.html">the Incident Report</a></li><li>asks.md, visit 1: the Ledger</li></ul><div class="btns">{modlink("bluehost")}</div>', [],
         [f'<a class="btn f" href="{ADMIN}/admin/config/ledger" target="_blank" rel="noopener">Fix it: read the Ledger for those minutes ↗</a>',
          '<button class="btn" title="Drawn: inert in the mockup">Fix it: Check and start again on W1\'s trips</button>',
          '<button class="btn imp" title="Drawn: inert">Improve: the worker logs each site call\'s duration and size (step 4)</button>']),
    ]
    oi = [c for c in SCORE['cases'] if c['group'] == 'Our index']
    ty = [c for c in SCORE['cases'] if c['group'] == 'Typing']
    c2 = [
        ('Snapshot (promise 2)', 'Search finds what they came for', lt('a', 'Less than optimal'),
         f'{SCORE["pass"]} of {SCORE["of"]} cases pass: below its own bar (every case passes), not failing. The target is to be set by Jason.',
         f'<ul><li>The search scorecard, <time>{T_SC}</time></li></ul>', ['The groups that fall short']),
        ('Probe groups', 'Our index and Typing', lt('a', 'Less than optimal'),
         f'Our index: {sum(c["pass"] for c in oi)} of {len(oi)}. Typing: {sum(c["pass"] for c in ty)} of {len(ty)}. Every other group passes.',
         '<ul>' + ''.join(f'<li>{E(c["group"])}: “{E(c["q"])}” {E((c.get("ctx") or ""))}: {E("; ".join(c["fails"]))}</li>' for c in fails_sc) + '</ul>', ['The places and words that fall short']),
        ('Places and words', 'Saint Johns, AZ ("places to shoot", 25 mi) and "Besh-Ba-Gowah"', lt('a', 'No results'),
         'Both return 0. Saint Johns is a pilot town; Besh-Ba-Gowah is an archaeological park, and archaeological words are held like the sacred unless the managing authority\'s page says it\'s open to visitors (decisions/0164 R7; inf).',
         '<ul><li>total 0 for both (the scorecard)</li><li>The typing cases are slow, not empty: 326–408 ms against the probe\'s 300 ms</li></ul>', ['The workflows that feed them']),
        ('Workflows', 'W1 Listings (places by kind and town), W9 Landmarks, W12 Search learning', lt('r', 'W1 paused by the system'),
         'Places by kind and town: our OpenStreetMap copy (Geofabrik), then Overture; else it waits. W1 is paused today, so no new town loads (the fault chain).',
         f'<ul><li><code>Preflight::NEEDS</code> places, landmarks; <code>Automation::WORKFLOWS</code> W12 "gaps queued for the crawler"</li><li><a href="#" data-open-panel="p-c1-1">W1\'s own chain (IR-0001)</a></li></ul>', ['The sources']),
        ('Modules', 'Geofabrik (our OpenStreetMap copy) and Overture', lt('a', 'Last loads: not read yet'),
         'Whether Saint Johns\' places are in our copy isn\'t read here (asks.md, visit 10: open_loads).',
         f'<div class="btns">{modlink("geofabrik")} {modlink("overture")}</div>', [],
         ['<button class="btn imp" title="Drawn: inert">Improve: schedule a crawl for Saint Johns, AZ (W1, places by kind)</button>',
          '<button class="btn imp" title="Drawn: inert">Improve: queue "places to shoot" for search learning (W12)</button>',
          '<button class="btn imp" title="Drawn: inert">Improve: check Besh-Ba-Gowah against 0164 R7 (open to visitors?)</button>',
          '<button class="btn imp" title="Drawn: inert">Improve: typing suggestions under 300 ms</button>']),
    ]
    h = ''
    for cid, lv in (('c1', c1), ('c2', c2)):
        for i, x in enumerate(lv):
            h += level(cid, i, len(lv), *x)
    return h, [x[1] for x in c1], [x[1] for x in c2]


# ------------------------------------------------------------------ coverage
def coverage():
    unmeasured = [o for _, _, objs in PROMISES for o in objs if o['state'] == 'n']
    nochk = [p for p in PAGES if p['state'] == 'n' and not p['status'].startswith('Coming')]
    coming = [p for p in PAGES if p['status'].startswith('Coming')]
    li = lambda xs: ''.join(f'<li>{x}</li>' for x in xs)
    sec = lambda n, of, words, body: (f'<details class="covd"><summary><span class="pill cnt">{n}</span> {words}{" of " + str(of) if of else ""}</summary>{body}</details>')
    h = (f'<aside class="panel" id="p-coverage" hidden role="dialog" aria-label="Coverage"><button class="x" aria-label="Close">×</button><h2 class="h2">Coverage: every gap named</h2>'
         f'<div class="micro">{N_OBJ} objectives · {len(PAGES)} pages · every one a snapshot; none silently missing</div>'
         + sec(len(unmeasured), N_OBJ, 'objectives not checked yet', f'<ul>{li(E(o["name"]) + ": " + E(o["reading"]) for o in unmeasured)}</ul>')
         + sec(len(nochk), len(PAGES), 'pages no probe covers yet', f'<ul class="cols2">{li("<code>" + E(p["route"]) + "</code>" for p in nochk)}</ul>')
         + sec(len(coming), 0, 'coming, designed not built', f'<ul>{li("<code>" + E(p["route"]) + "</code>" for p in coming)}</ul>')
         + sec(len(NO_CARD), 0, 'services the needs name with no card yet', f'<ul class="cols2">{li("<code>" + E(s) + "</code>" for s in NO_CARD)}</ul>') + '</aside>')
    return h, len(unmeasured), len(nochk), len(coming)


def manage_panel():
    return ('<aside class="panel" id="p-manage" hidden role="dialog" aria-label="Manage a page"><button class="x" aria-label="Close">×</button>'
            '<div id="manage-body"></div></aside>')


def quick():
    q = [
        ('c', 'p-c1-0', 'W1 paused: IR-0001', 'r', '2 trips'),
        ('m', 'anthropic:1', 'Claude cap $27.05 of $40', 'a', '68%'),
        ('m', 'places:0', 'Google Places 544 of 1,000', 'a', '54%'),
        ('m', 'commoncrawl:1', 'W3: Common Crawl #35', 'r', 'trip'),
        ('w', '', 'Paused now: W3\'s source and commoncrawl tasks left off', 'a', '2 off'),
        ('c', 'p-c2-0', 'Search: 101 of 106', 'a', '95%'),
        ('o', 'o-paths', 'Paths: set-place failed', 'a', '1 failed'),
        ('m', 'irs:0', 'IRS: Fix in the build, pending', 'a', 'step 4'),
        ('o', 'o-renew', 'Scheduled tasks: bad', 'a', 'health'),
    ]
    out = ''
    for kind, tgt, t, k, n in q:
        if kind == 'c':
            attr = f'data-open-panel="{tgt}"'
        elif kind == 'm':
            mm, sn = tgt.split(':')
            attr = f'data-go-mod="{mm}" data-scn="{sn}"'
        elif kind == 'w':
            attr = 'data-go-view="workflows"'
        else:
            attr = f'data-go-obj="{tgt}"'
        out += f'<button class="qj {k}" {attr}><span>{E(t)}</span><b>{E(n)}</b></button>'
    return out


CSS = kit.CSS + r"""
.top{position:sticky;top:0;z-index:40;background:var(--umber);color:var(--btn-text);padding:.5rem 16px;display:flex;flex-wrap:wrap;gap:.4rem .8rem;align-items:center}
.top b{font-family:var(--serif)}.top .tm{font-size:12px;color:#E3C9A8}.top .chip{border:1px solid #E3C9A866;border-radius:999px;padding:.05rem .55rem;font-size:12px}
.qbar{display:flex;gap:.4rem;overflow-x:auto;padding:.5rem 16px;background:var(--sec);border-bottom:1px solid var(--sec-edge);scrollbar-width:thin}
.qj{flex:0 0 auto;display:flex;gap:.45rem;align-items:center;border:1px solid var(--card-edge);background:var(--card);border-radius:.5rem;padding:.3rem .55rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.qj b{font-size:11.5px;border-radius:999px;padding:0 .45rem;color:#fff}.qj.r b{background:var(--red)}.qj.a b{background:var(--amber)}
.shell{display:grid;grid-template-columns:200px minmax(0,1fr);gap:0}
.rail{position:sticky;top:96px;align-self:start;padding:1rem .6rem;display:flex;flex-direction:column;gap:.25rem}
.rail button{display:flex;gap:.5rem;align-items:center;width:100%;text-align:left;border:0;background:none;padding:.45rem .55rem;border-radius:.45rem;font:inherit;color:var(--ink);cursor:pointer}
.rail button[aria-current="page"]{background:var(--card);box-shadow:0 0 0 1px var(--card-edge);font-weight:600}.rail .cnt{margin-left:auto;font-size:12px;color:var(--meta)}
.main{padding:1rem 16px 3rem;min-width:0}
.vh{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:space-between;align-items:flex-end;margin-bottom:.7rem}
h1{font:700 22px/1.2 var(--serif);margin:0 0 .2rem}.h2{font:700 17px/1.25 var(--serif);margin:.2rem 0 .4rem}.h3{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--brown);margin:.8rem 0 .3rem}
.seg{display:inline-flex;flex-wrap:wrap;gap:.25rem}.seg button{border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.2rem .7rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.seg button[aria-pressed="true"]{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}.wrapseg{margin:.5rem 0}
.pgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.pgrid.rows{grid-template-columns:minmax(0,1fr)}
.pbox{background:var(--card);border:1px solid var(--card-edge);border-radius:.6rem;padding:.7rem;border-top:4px solid var(--sage)}.pbox.s-a{border-top-color:var(--amber)}.pbox.s-r{border-top-color:var(--red)}
.pbox h2{font:700 16px/1.25 var(--serif);margin:0 0 .5rem;display:flex;gap:.5rem;align-items:baseline}.pn{font:600 12px var(--sans);background:var(--sec);border-radius:999px;padding:0 .5rem;color:var(--meta)}
.obj{border-top:1px solid var(--card-edge);padding:.55rem 0}.oh{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:space-between;align-items:center}
.om,.ord{font-size:13px}.om span,.ord span:first-child{color:var(--meta)}.basis{font:11px var(--mono);color:var(--taupe);border:1px solid var(--card-edge);border-radius:3px;padding:0 3px}
.orow{display:flex;gap:.7rem;align-items:center;margin:.3rem 0}.pbar{flex:1;height:9px;background:var(--sec);border-radius:999px;overflow:hidden;min-width:60px}.pbar b{display:block;height:100%;background:var(--sage)}
.pbar.a b{background:var(--amber)}.pbar.r b{background:var(--red)}.pbar.none{height:auto;background:none;font-size:12px;color:var(--taupe)}
.trend{color:var(--brown);font-size:12px;flex:0 0 auto}.nohist{color:var(--taupe)}
.btns{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.3rem}.btn.imp{border-style:dashed}
.lt.n i{background:#B9AE98}.lt.h i{background:var(--amber)}
.sgs{display:flex;flex-direction:column;gap:.4rem}.sg{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem}
.sgh{display:flex;flex-wrap:wrap;gap:.4rem .6rem;align-items:center;width:100%;border:0;background:none;padding:.55rem .7rem;font:inherit;color:var(--ink);cursor:pointer;text-align:left}
.sgh .cnt{font-size:12px;color:var(--meta)}.sgh .ic:last-child{margin-left:auto;transition:transform .15s}.sgh[aria-expanded="true"] .ic:last-child{transform:rotate(90deg)}
.rt{padding:0 .7rem .7rem}.tw{overflow-x:auto}.rtab,.wt{width:100%;border-collapse:collapse;font-size:13px}.rtab td,.rtab th,.wt td,.wt th{border-top:1px solid var(--card-edge);padding:.35rem .4rem;text-align:left;vertical-align:top}
.rtab th,.wt th{font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--meta)}
.mod{background:var(--card);border:1px solid var(--card-edge);border-radius:.55rem;margin:.45rem 0;padding:.1rem .6rem .4rem}.mod>summary{display:flex;flex-wrap:wrap;gap:.35rem .6rem;align-items:center;cursor:pointer;padding:.45rem 0;list-style:none}
.mod>summary::-webkit-details-marker{display:none}.mn{font:700 15px var(--serif)}.scn .card{margin:0}.scn-intro{margin:.2rem 0 .5rem}
.chainbox{border:1px dashed var(--field-edge);border-radius:.6rem;padding:.6rem;margin:.6rem 0}.chainbox h3{margin:0 0 .2rem;font:700 15px var(--serif)}
.chain{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.3rem 0}.nocard{color:var(--taupe);border-bottom:1px dotted var(--taupe)}
.needs{margin:0;padding-left:1rem}.own{color:var(--meta)}.adm{margin:.2rem 0;padding-left:1.1rem;font-size:13px}
.crumb{font-size:12px;color:var(--meta);margin:.2rem 0}.why,.ev,.rootbox{margin:.6rem 0;font-size:13.5px}.ev ul{margin:.3rem 0;padding-left:1.1rem}.rootbox{border:1px solid var(--copper);border-radius:.5rem;padding:.5rem}
.navb{border-top:1px solid var(--sec-edge);padding-top:.6rem}.cols2{columns:2;column-gap:1.2rem}
.panel{padding:1rem 1rem 2rem}.sctag{font-size:11px;border:1px dotted var(--copper);color:var(--copper);border-radius:3px;padding:0 4px}.panel .x{float:right}
.mrow td:first-child code{font-weight:600}
.om2{display:flex;flex-wrap:wrap;gap:.3rem .7rem;align-items:baseline;margin:.15rem 0}.metric{font:600 14px var(--sans)}
.live{font-size:11px;color:var(--meta);border:1px solid var(--card-edge);border-radius:999px;padding:0 .45rem;background:var(--sec)}.live.none{color:var(--taupe)}
.micro{font-size:11.5px;color:var(--meta);text-transform:none;letter-spacing:.02em}
details.more>summary,details.clamp>summary,details.covd>summary{cursor:pointer;font-size:12.5px;color:var(--brown);list-style:none;padding:.15rem 0}
details.more>summary::-webkit-details-marker,details.clamp>summary::-webkit-details-marker,details.covd>summary::-webkit-details-marker,details.rp>summary::-webkit-details-marker,details.pop>summary::-webkit-details-marker{display:none}
details.more>summary::before,details.clamp>summary::after,details.covd>summary::before{content:"▸ ";color:var(--taupe)}details.more[open]>summary::before,details.covd[open]>summary::before{content:"▾ "}
details.clamp>summary{color:var(--ink)}details.clamp>summary::after{content:" ▸ more";color:var(--brown);font-size:11.5px}details.clamp[open]>summary{display:none}
.pill{display:inline-block;font-size:11.5px;font-weight:600;border-radius:999px;padding:.05rem .55rem;background:var(--sec);border:1px solid var(--card-edge);color:var(--ink)}.pill.cnt{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}
.covd{border-top:1px solid var(--sec-edge);padding:.35rem 0}
.rad{display:flex;flex-direction:column;gap:.3rem}.rp{display:block;border:1px solid var(--card-edge);border-radius:.45rem;padding:.25rem .45rem;background:var(--card)}
.rp>summary{display:flex;gap:.45rem;align-items:center;cursor:pointer;list-style:none;flex-wrap:wrap}.rp ul{margin:.3rem 0 .1rem;padding-left:1.1rem;font-size:13px}
.rpill{font-size:11.5px;font-weight:700;border-radius:999px;padding:.05rem .55rem;color:#fff}.rpill.h{background:var(--amber)}.rpill.o{background:var(--blue)}.rpill.u{background:#6E9A82}
.rn{font:700 12px var(--sans);color:var(--meta)}.rf{font-size:12.5px;color:var(--meta)}
.steps{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin:.3rem 0}.step{font-size:12px;border:1px solid var(--copper);border-radius:999px;padding:.05rem .55rem;background:var(--card)}.step b{color:var(--copper);margin-right:.2rem}
.lvrow{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.3rem 0}.lvn{font:700 16px var(--serif)}
details.pop{display:inline-block;position:relative}details.pop>summary{list-style:none}.popb{position:absolute;z-index:30;left:0;top:110%;min-width:220px;max-width:min(320px,80vw);background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.5rem;box-shadow:0 6px 18px #231D1733;display:flex;flex-direction:column;gap:.3rem}
.popb .px{align-self:flex-end;border:0;background:none;font-size:16px;cursor:pointer;color:var(--meta)}
@media (max-width:767px){.popb{position:static;max-width:100%;box-shadow:none;margin-top:.3rem}}
@media (max-width:767px){.shell{grid-template-columns:minmax(0,1fr)}.rail{position:static;flex-direction:row;overflow-x:auto;padding:.4rem 16px;gap:.2rem}.rail button{width:auto;flex:0 0 auto}.rail .cnt{margin-left:.3rem}
.main{padding:.6rem 3px 3rem}.view>.vh,.view>.h2,.view>.sgs>.sub{padding:0 10px}.pgrid{grid-template-columns:minmax(0,1fr)}.pbox,.sg,.mod{border-radius:.5rem}.cols2{columns:1}
.rtab thead{display:none}.rtab tr{display:block;border-top:1px solid var(--card-edge);padding:.35rem 0}.rtab td{display:block;border:0;padding:.1rem .2rem}
.wt thead{display:none}.wt tr{display:block;border-top:1px solid var(--card-edge);padding:.35rem 0}.wt td{display:block;border:0;padding:.15rem .2rem}}
"""

JS = r"""<script>
(function(){
var b=document.body,P=window.__PAGES,G=window.__GROUPS,LT={g:'Working',a:'Less than optimal',r:'Failing',n:'not checked yet'};
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function close(){b.classList.remove('open');}
function openPanel(id){document.querySelectorAll('.panel').forEach(function(p){p.hidden=p.id!==id;});b.classList.add('open');var p=document.getElementById(id);if(p){p.scrollTop=0;var f=p.querySelector('.x');if(f)f.focus({preventScroll:true});}}
function show(v){document.querySelectorAll('.view').forEach(function(s){s.hidden=s.id!=='view-'+v;});document.querySelectorAll('[data-nav]').forEach(function(n){n.setAttribute('aria-current',n.getAttribute('data-nav')===v?'page':'false');});var s=document.getElementById('view-'+v);document.getElementById('trail').textContent=s?s.getAttribute('data-title'):'';try{localStorage.setItem('tvb-view',v);}catch(e){}}
function setScn(m,i){document.querySelectorAll('.scn[data-mod="'+m+'"]').forEach(function(x){x.hidden=x.getAttribute('data-scn')!==String(i);});
 document.querySelectorAll('.sc-b[data-mod="'+m+'"]').forEach(function(x){x.setAttribute('aria-pressed',x.getAttribute('data-scn')===String(i)?'true':'false');});
 var sc=document.querySelector('#view-modules .scn[data-mod="'+m+'"][data-scn="'+i+'"]'),sel=sc&&sc.querySelector('.lt');if(sel)document.querySelectorAll('[data-mod-state="'+m+'"]').forEach(function(x){x.innerHTML=sel.outerHTML+(sc.getAttribute('data-real')==='1'?' <span class="live">live</span>':' <span class="sctag">scenario</span>');});}
function goMod(m,i){close();show('modules');var d=document.getElementById('mod-'+m);if(!d)return;d.open=true;if(i!==null&&i!==undefined)setScn(m,i);setTimeout(function(){d.scrollIntoView({block:'start'});},0);}
function lt(k,w){return '<span class="lt '+k+'"><i aria-hidden="true"></i>'+esc(w)+'</span>';}
function table(gi){var g=G[gi],h='<div class="tw"><table class="rtab"><thead><tr><th>Route</th><th>What it does</th><th>Status</th><th>Probe</th><th></th></tr></thead><tbody>';
 g.rows.forEach(function(i){var p=P[i];h+='<tr class="mrow"><td><code>'+esc(p.route)+'</code>'+(p.sub?'<div class="sub">'+esc(p.sub)+'</div>':'')+'</td><td>'+esc(p.purpose).slice(0,220)+'</td><td>'+lt(p.state,p.words)+'<div class="sub">'+esc(p.status).slice(0,120)+'</div></td><td class="sub">'+(p.probes.length?esc(p.probes[0]).slice(0,90):'not checked yet')+'</td><td><button class="btn" data-manage="'+i+'">Manage</button></td></tr>';});
 return h+'</tbody></table></div>';}
function manage(i){var p=P[i],mods=window.__MODS;
 var h='<div class="crumb">'+esc(p.group)+'</div><h2 class="h2"><code>'+esc(p.route)+'</code></h2><div>'+lt(p.state,p.words)+'</div>'+
 '<dl class="dl"><dt>Route</dt><dd><code>'+esc(p.route)+'</code>'+(p.also.length?' · also '+p.also.map(function(a){return '<code>'+esc(a)+'</code>';}).join(', '):'')+'</dd>'+
 '<dt>Status</dt><dd>'+esc(p.status)+'</dd><dt>Access</dt><dd>'+esc(p.access)+'</dd><dt>What it does</dt><dd>'+esc(p.purpose)+(p.adr&&p.adr!=='—'?' <span class="sub">(decisions '+esc(p.adr)+')</span>':'')+'</dd>'+
 '<dt>What it replaces</dt><dd>'+(p.replaces?esc(p.replaces):(p.archived?esc(p.archived):'nothing named in pages.md or archive.md'))+'</dd>'+
 '<dt>Endpoints</dt><dd>'+(p.endpoints.length?p.endpoints.map(function(a){return '<code>'+esc(a)+'</code>';}).join(', '):'not named in pages.md')+'</dd>'+
 '<dt>Its probe</dt><dd>'+(p.probes.length?'<ul>'+p.probes.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>':'not checked yet: no search, layout, paths, photos or rendered check covers it')+'</dd>'+
 '<dt>Source</dt><dd>'+esc(p.src)+'</dd></dl>'+
 '<div class="btns"><button class="btn" disabled title="Drawn: inert in the mockup">Run probe</button>'+p.deps.map(function(m){return '<button class="btn f" data-go-mod="'+m+'">Fix it: '+esc(mods[m]||m)+'</button>';}).join('')+'</div>'+
 '<p class="sub">Fix it goes to the module it depends on: Bluehost hosts every page (Preflight hosting); the others are inferred from what it does (inf).</p>';
 document.getElementById('manage-body').innerHTML=h;openPanel('p-manage');}
document.addEventListener('click',function(e){var t=e.target.closest('[data-open-panel],[data-go-mod],[data-nav],[data-go-view],[data-go-obj],[data-manage],.sgh,.sc-b,[data-layout],[data-role-f],.x');
 if(!t){if(e.target.classList.contains('scrim'))close();return;}
 if(t.classList.contains('x')){close();return;}
 if(t.hasAttribute('data-open-panel')){e.preventDefault();openPanel(t.getAttribute('data-open-panel'));return;}
 if(t.hasAttribute('data-go-mod')){e.preventDefault();var s=t.getAttribute('data-scn');goMod(t.getAttribute('data-go-mod'),s===null?null:+s);return;}
 if(t.hasAttribute('data-nav')){show(t.getAttribute('data-nav'));window.scrollTo(0,0);return;}
 if(t.hasAttribute('data-go-view')){close();show(t.getAttribute('data-go-view'));window.scrollTo(0,0);return;}
 if(t.hasAttribute('data-go-obj')){close();show('board');var o=document.getElementById(t.getAttribute('data-go-obj'));if(o)o.scrollIntoView({block:'center'});return;}
 if(t.hasAttribute('data-manage')){manage(+t.getAttribute('data-manage'));return;}
 if(t.classList.contains('sgh')){var gi=t.getAttribute('data-group'),r=document.getElementById('rt-'+gi),op=t.getAttribute('aria-expanded')!=='true';t.setAttribute('aria-expanded',op);if(op&&!r.innerHTML)r.innerHTML=table(+gi);r.hidden=!op;return;}
 if(t.classList.contains('sc-b')){setScn(t.getAttribute('data-mod'),t.getAttribute('data-scn'));return;}
 if(t.hasAttribute('data-layout')){var L=t.getAttribute('data-layout');document.getElementById('pgrid').classList.toggle('rows',L==='rows');document.querySelectorAll('[data-layout]').forEach(function(x){x.setAttribute('aria-pressed',x===t);});try{localStorage.setItem('tvb-layout',L);}catch(e){}return;}
 if(t.hasAttribute('data-role-f')){var R=t.getAttribute('data-role-f');document.querySelectorAll('#view-modules [data-role]').forEach(function(x){x.hidden=R!=='all'&&x.getAttribute('data-role')!==R;});document.querySelectorAll('[data-role-f]').forEach(function(x){x.setAttribute('aria-pressed',x===t);});return;}
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){close();document.querySelectorAll('details.pop[open]').forEach(function(d){d.open=false;});}});
document.addEventListener('click',function(e){document.querySelectorAll('details.pop[open]').forEach(function(d){if(!d.contains(e.target)||e.target.closest('.px'))d.open=false;});},true);
// Clamp: any block of prose over 120 characters at a card's or an objective's top level moves into a detail, its first words as
// the summary (Jason, 2026-11-21: visual-forward, scannable first; nothing dropped, one tap away).
(function(){var BLK='DIV,P,UL,OL,LI,DL,DD,DT,TABLE,TR,TD,DETAILS,SECTION,ARTICLE,ASIDE,H2,H3';
 function leaf(e){for(var c=e.firstElementChild;c;c=c.nextElementSibling){if(BLK.indexOf(c.tagName)>=0)return false;}return true;}
 var els=document.querySelectorAll('article.card p,article.card li,article.card div,article.card dd,article.card td,article.card .spend>span,.obj div,.obj p,#view-workflows td,#view-workflows li,#view-diagnostics li,#view-diagnostics p');
 for(var i=els.length-1;i>=0;i--){var e=els[i];if(e.closest('details:not(.mod)')||e.closest('summary')||!leaf(e))continue;
  var t=e.textContent.replace(/\s+/g,' ').trim();if(t.length<=120)continue;
  var m=t.match(/^(.{20,90}?[.:;])\s/),first=m?m[1]:t.slice(0,80).replace(/\s+\S*$/,'')+'…';
  var d=document.createElement('details');d.className='clamp';var sm=document.createElement('summary');sm.textContent=first;var body=document.createElement('div');
  while(e.firstChild)body.appendChild(e.firstChild);d.appendChild(sm);d.appendChild(body);e.appendChild(d);}
})();
var L=null,V=null;try{L=localStorage.getItem('tvb-layout');V=localStorage.getItem('tvb-view');}catch(e){}
if(L==='rows'){var bb=document.querySelector('[data-layout="rows"]');if(bb)bb.click();}
show(V&&document.getElementById('view-'+V)?V:'board');
})();
</script>"""


def fold_intros(h):
    """Each view's intro sentence moves into "About this view" (Jason, 2026-11-21: scannable first)."""
    return re.sub(r'(<h[12] class="?[^>]*>[^<]*</h[12]>|<h1>[^<]*</h1>)<p class="sub">(.*?)</p>',
                  r'\1<details class="more"><summary>About this view</summary><p class="sub">\2</p></details>', h, flags=re.S)


def build():
    os.makedirs(OUT, exist_ok=True)
    chain_html, c1, c2 = chains()
    cov_html, n_unm, n_noch, n_coming = coverage()
    held = sum(1 for a in ALARMS['open'] if a['level'] == 'trip')
    mods_js = {m['id']: m['label'] for m in MODULES}
    pages_js = [{k: p[k] for k in ('group', 'sub', 'route', 'also', 'purpose', 'adr', 'status', 'state', 'words', 'probes', 'deps', 'endpoints', 'replaces', 'archived', 'access', 'src')} for p in PAGES]
    body = f"""<header class="top"><b>System Control Board</b><span class="chip">Design mockup · step 3 · not site code</span><span id="trail" class="tm"></span>
<span class="tm">Readings: alarms and caps <time>{T_AL}</time> · rendered <time>{T_RE}</time> · paths <time>{T_PA}</time></span>
<span class="sp" style="flex:1"></span><span class="chip">{held} trips open · 21 alarms</span><button class="chip" style="background:none;color:inherit;cursor:pointer;font:inherit" data-open-panel="p-coverage">{ico('gap', 14)} Coverage: {n_unm + n_noch} gaps named</button></header>
<nav class="qbar" aria-label="What's wrong now">{quick()}</nav>
<div class="shell"><nav class="rail" aria-label="Board views">
<button data-nav="board" aria-current="page">{ico('board')}Board<span class="cnt">{N_OBJ}</span></button>
<button data-nav="modules" aria-current="false">{ico('mods')}Modules<span class="cnt">{len(MODULES)}</span></button>
<button data-nav="workflows" aria-current="false">{ico('wf')}Workflows<span class="cnt">{len(WORKFLOWS)}</span></button>
<button data-nav="diagnostics" aria-current="false">{ico('diag')}Diagnostics<span class="cnt">{len(MODULES)}</span></button></nav>
<main class="main">{fold_intros(board_view() + modules_view() + workflows_view() + diag_view())}</main></div>
<div class="scrim"></div>{chain_html}{cov_html}{manage_panel()}{''.join(unplaceholder(v) for v in PANELS.values())}
<script>window.__PAGES={json.dumps(pages_js)};window.__GROUPS={json.dumps([{'rows': g['rows']} for g in GROUPS])};window.__MODS={json.dumps(mods_js)};</script>{JS}"""
    doc = f"""<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Control Board</title><meta name="description" content="Step 3's interactive board mockup: is it delivering, the pages, modules, workflows and diagnostics.">
<style>{CSS}</style></head><body>{body}</body></html>"""
    open(OUT + 'index.html', 'w').write(doc)
    stats = dict(pages=len(PAGES), groups=len(GROUPS), modules=len(MODULES), scenarios=N_SCN, objectives=N_OBJ, unmeasured=n_unm, pages_unchecked=n_noch,
                 coming=n_coming, no_card=NO_CARD, workflows=len(WORKFLOWS), chain1=c1, chain2=c2, size=len(doc))
    json.dump(stats, open(OUT + 'stats.json', 'w'), indent=1)
    print(json.dumps(stats, indent=1))


if __name__ == '__main__':
    build()
