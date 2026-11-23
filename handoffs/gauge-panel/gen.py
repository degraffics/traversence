#!/usr/bin/env python3
"""The gauge panel redesign's mockup (handoffs/gauge-panel.md), drawn from the real gauge files.

Reads site-audit/results/gauges.json, gauges-items.json, findings.json and the check register, and writes
handoffs/gauge-panel/mockup.html: a development gauge's panel (Discovery) and a runtime gauge's (Search finds what they
came for), each as a visual drill-down where every item and chip leads somewhere. A mockup only: nothing here is the site.
Run: python3 handoffs/gauge-panel/gen.py [dev-id] [runtime-id]
"""
import html, json, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
R = ROOT / 'site-audit/results'
G = json.load(open(R / 'gauges.json'))
IT = json.load(open(R / 'gauges-items.json'))
FIND = {f['id']: f for f in json.load(open(R / 'findings.json'))['findings']}
REG = {c['id']: c for c in json.load(open(ROOT / 'handoffs/control-board-plan/step3/sections/check-register.json'))['checks']}
for _k, _v in IT.get('checks', {}).items():   # the gauges' own newest reading of each check wins over the register's
    REG.setdefault(_k, {}).update({x: _v[x] for x in ('status', 'result', 'latest', 'evidence') if x in _v})
PAGES = (ROOT / 'site-audit/pages.md').read_text().splitlines()
GH = 'https://github.com/degraffics/traversence/blob/main/'
RELS = sorted(p.name for p in (ROOT / 'deliveries/releases').glob('*.json'))
DECS = {p.name[:4]: p.name for p in (ROOT / 'decisions').glob('0*.md')}
e = html.escape

# the order the lifecycle reads an item (gauge-lifecycle.md §3): the earliest unmet criterion is where it is
CRIT = [('decided', 'Decided'), ('built', 'Built'), ('live', 'Live'), ('checked', 'Checked'), ('reviewed', 'Reviewed'), ('proved', 'Proved')]
BUCKETS = [  # key, words, colour token, what pressing it shows
    ('fail', 'Failing a check', 'amber', 'a check that ran and failed, or an open finding'),
    ('build', 'Not built yet', 'taupe', 'decided, or built and held'),
    ('sign', 'Awaiting your sign-off', 'copper', 'built and live, audited, waiting for Jason\'s sign-off'),
    ('check', 'Needs a check built', 'river', 'signed off, but a check it needs doesn\'t exist yet'),
    ('ok', 'Live and checked', 'sage', 'every configured criterion met'),
]


def route_of(item):
    """The page the item is: the first path in its pages.md row (gauges.py to add it as `route`)."""
    if item.get('route'):
        return item['route']
    m = re.search(r'site-audit/pages\.md:(\d+)', item.get('source', ''))
    if not m:
        return None
    row = PAGES[int(m.group(1)) - 1] if int(m.group(1)) <= len(PAGES) else ''
    p = re.search(r'`(/[^`\s]*)`', row)
    if not p:
        return None
    r = p.group(1)
    return re.sub(r'\?[^/]*=$', '', r).split('?id=')[0] if '=' in r and r.endswith('=') else r


def links(src):
    """Every reference in a source line as a link: a .md file and line, a release record, a decision."""
    out = []
    for m in re.finditer(r'([\w./-]+\.md)(?::(\d+))?', src):
        f = m.group(1) if '/' in m.group(1) or (ROOT / m.group(1)).exists() else None
        if f and (ROOT / f).exists():
            out.append((f + (':' + m.group(2) if m.group(2) else ''), GH + f + ('#L' + m.group(2) if m.group(2) else '')))
    for m in re.finditer(r'release #(\d+)', src):
        n = m.group(1)
        hit = [r for r in RELS if re.search(r'-' + n + r'-', r)]
        out.append(('release #' + n, GH + 'deliveries/releases/' + hit[0] if hit else GH + 'deliveries/releases'))
    for m in re.finditer(r'(?:decisions/)?\b(0\d{3})\b', src):
        if m.group(1) in DECS:
            out.append(('decisions/' + m.group(1), GH + 'decisions/' + DECS[m.group(1)]))
    seen, uniq = set(), []
    for t, u in out:
        if t not in seen:
            seen.add(t); uniq.append((t, u))
    return uniq


def finding_of(item):
    m = re.search(r'open finding: (f-[0-9a-f]+)', item.get('review') or '')
    return FIND.get(m.group(1)) if m else None


def bucket(item):
    fails = [c for c in item.get('checks', []) if c[1] == 'fail']
    if fails or finding_of(item):
        return 'fail'
    if item['status'] in ('decided', 'held'):
        return 'build'
    if (item.get('review') or '').startswith('audited, awaiting sign-off'):
        return 'sign'
    if item.get('no_check'):
        return 'check'
    return 'ok'


def dot_state(item, crit):
    met = crit in item.get('met', [])
    if met:
        return 'met'
    if crit == 'checked':
        if any(c[1] == 'fail' for c in item.get('checks', [])):
            return 'fail'
        if item.get('no_check'):
            return 'nocheck'
    if crit == 'reviewed' and finding_of(item):
        return 'fail'
    if crit == 'reviewed' and (item.get('review') or '').startswith('audited, awaiting sign-off'):
        return 'sign'
    return 'no'


DOTW = {'met': 'met', 'fail': 'failing', 'nocheck': 'no check yet', 'sign': 'awaiting your sign-off', 'no': 'not yet'}


def dots(item):
    return '<span class="dots" aria-hidden="true">' + ''.join(
        '<i class="d d-%s" title="%s: %s"></i>' % (dot_state(item, k), w, DOTW[dot_state(item, k)]) for k, w in CRIT) + '</span>'


def dots_label(item):
    return '; '.join('%s %s' % (w, DOTW[dot_state(item, k)]) for k, w in CRIT)


def gauge_svg(score, stage, big=False):
    s = max(0.0, min(100.0, float(score or 0)))
    w = 120 if big else 64
    r = 50
    import math
    a = math.pi * (1 - s / 100)
    x, y = 60 + r * math.cos(a), 60 - r * math.sin(a)
    arc = '' if s <= 0 else '<path d="M10 60 A50 50 0 0 1 %.1f %.1f" class="g-on g-%s"/>' % (x, y, stage)
    return ('<svg class="gs" width="%d" viewBox="0 0 120 68" role="img" aria-label="%s percent">'
            '<path d="M10 60 A50 50 0 0 1 110 60" class="g-off"/>%s<text x="60" y="58" text-anchor="middle" class="g-t">%d</text></svg>') % (w, int(s), arc, int(s))


def chip_for(check, item):
    cid, res = check
    reg = REG.get(cid) or REG.get('R01')
    if res == 'pass':
        return '<a class="chip c-pass" href="%s" target="_blank" rel="noopener" title="Its latest run">%s passes ↗</a>' % (e(reg['evidence'] if reg['evidence'].startswith('http') else GH + 'site-audit/scorecard.md'), e(cid))
    if res == 'fail':
        f = finding_of(item)
        return '<button type="button" class="chip c-fail" data-open="finding" data-id="%s">%s fails · open it</button>' % (e(f['id'] if f else cid), e(cid))
    if res == 'partial':
        return '<button type="button" class="chip c-part" data-open="check" data-id="%s">%s partly built · what\'s missing</button>' % (e(cid), e(cid))
    if (REG.get(cid) or {}).get('status', '').startswith('built'):
        return '<button type="button" class="chip c-none" data-open="reach" data-id="%s">%s doesn\'t reach it yet · why</button>' % (e(cid), e(cid))
    return '<button type="button" class="chip c-none" data-open="check" data-id="%s">%s: no check yet · what it needs</button>' % (e(cid), e(cid))


def item_row(i, n, item, paths):
    b = bucket(item)
    rt = route_of(item)
    name = e(item['name'])
    nm = ('<a class="nm" href="%s" data-peek-frame title="Open the page beside the board">%s</a>' % (e(rt), name)) if rt else (
        '<a class="nm" href="%s" target="_blank" rel="noopener" title="Its source">%s</a>' % (e(links(item['source'])[0][1]) if links(item['source']) else GH, name))
    f = finding_of(item)
    acts = []
    if b == 'fail':
        if f:
            acts.append('<button type="button" class="b go" data-open="finding" data-id="%s">Open the finding</button>' % e(f['id']))
        else:
            fc = [c[0] for c in item['checks'] if c[1] == 'fail'][0]
            acts.append('<button type="button" class="b go" data-open="check" data-id="%s">See what %s found</button>' % (e(fc), e(fc)))
        acts.append('<button type="button" class="b" data-send="%d">Send to a build</button>' % n)
    elif b == 'build':
        acts.append('<button type="button" class="b go" data-send="%d">Send to a build</button>' % n)
    elif b == 'sign':
        acts.append('<button type="button" class="b go" data-sign="%d">Sign off</button>' % n)
    elif b == 'check':
        acts.append('<button type="button" class="b go" data-open="%s" data-id="%s">What its check needs</button>' % ('reach' if REG.get(item['no_check'][0], {}).get('status', '').startswith('built') else 'check', e(item['no_check'][0])))
    if rt:
        acts.append('<a class="b" href="%s" data-peek-frame>Open the page</a>' % e(rt))
    if f:
        acts.append('<a class="b" href="#site-health-%s">Its row in Site health</a>' % e(f['id']))
    chips = ''.join(chip_for(c, item) for c in item.get('checks', [])) or '<button type="button" class="chip c-none" data-open="nocheck">No check applies until it\'s built · what will check it</button>'
    pth = ''.join('<button type="button" class="b sm" data-path="%s">%s · %d ▸</button>' % (k, w, len(paths.get(k, []))) for k, w in (('workflows', 'Workflows'), ('core', 'Core Services'), ('registered', 'Registered Services')))
    src = ''.join('<a href="%s" target="_blank" rel="noopener">%s ↗</a>' % (e(u), e(t)) for t, u in links(item['source']))
    return ('<li class="it" data-b="%s"><div class="ih"><span class="bk bk-%s" title="%s"></span>%s%s'
            '<button type="button" class="ex" aria-expanded="false" aria-label="Open %s: %s">▾</button></div>'
            '<div class="ib" hidden><p class="st">%s</p><div class="chips">%s</div><div class="acts">%s</div>'
            '<div class="acts paths">%s</div><details class="src"><summary>Where this comes from</summary><p>%s</p><p class="meta">%s</p></details></div></li>') % (
        b, b, e(dict((k, w) for k, w, *_ in BUCKETS)[b]), nm, dots(item), name, e(dots_label(item)),
        e(state_sentence(item, b)), chips, ''.join(acts), pth, src or 'its source line names no file', e(item['source']))


def state_sentence(item, b):
    f = finding_of(item)
    if b == 'fail':
        if f:
            return 'Open finding: %s. In the way of %d path%s.' % (f['title'], f['blocks'], '' if f['blocks'] == 1 else 's')
        fc = [c[0] for c in item['checks'] if c[1] == 'fail']
        return '%s failed on its last run: %s' % (', '.join(fc), REG.get(fc[0], {}).get('latest', ''))
    if b == 'build':
        return 'Built and held: it goes live with its release.' if item['status'] == 'held' else 'Decided, not built yet. It moves the gauge once a build makes it.'
    if b == 'sign':
        return 'Live and audited. It counts as reviewed once you sign it off (gauge-lifecycle.md §8, decided 2).'
    if b == 'check':
        return 'Signed off. Not yet checked by %s.' % ', '.join(item['no_check'])
    return 'Every configured criterion met.'


def dev_panel(gid):
    g = next(x for x in G['development'] if x['id'] == gid)
    d = IT['development'][gid]
    items = d['list']
    paths = g['paths']
    counts = {k: sum(1 for i in items if bucket(i) == k) for k, *_ in BUCKETS}
    n = len(items)
    bar = ''.join('<button type="button" class="seg s-%s" style="flex:%d" data-f="%s" aria-label="%s: %d of %d" title="%s: %d">%s</button>' % (
        k, counts[k], k, e(w), counts[k], n, e(w), counts[k], counts[k] if counts[k] * 100 / n >= 7 else '') for k, w, *_ in BUCKETS if counts[k])
    legend = ''.join('<button type="button" class="lg" data-f="%s" aria-pressed="false"><span class="bk bk-%s"></span>%s <b>%d</b></button>' % (k, k, e(w), counts[k]) for k, w, *_ in BUCKETS if counts[k])
    # what holds the most items back, for the action cards
    nc = {}
    for i in items:
        for c in i.get('no_check', []):
            nc[c] = nc.get(c, 0) + 1
    top_nc = sorted(nc.items(), key=lambda x: -x[1])[:1]
    cards = []
    cards.append(('copper', 'It stays at 0 in Planning until you sign off what 100 means: these %d things, each with its criteria.' % n,
                  '<button type="button" class="b go" data-sign="scope">Sign off the scope</button>', 'gauge-lifecycle.md §3'))
    if counts['fail']:
        cards.append(('amber', '%d thing%s failing a check. Each opens its finding with the screenshot.' % (counts['fail'], ' is' if counts['fail'] == 1 else 's are'),
                      '<button type="button" class="b go" data-f="fail">Show the %d</button>' % counts['fail'], 'findings.json; check-register.md'))
    if counts['sign']:
        cards.append(('copper', '%d live thing%s audited and waiting for your sign-off.' % (counts['sign'], ' is' if counts['sign'] == 1 else 's are'),
                      '<button type="button" class="b go" data-f="sign">Review them</button>', 'gauge-lifecycle.md §8 decided 2'))
    for cid, k in top_nc:
        reg = REG[cid]
        built = reg.get('status', '').startswith('built')
        cards.append(('river', ('%s is built but doesn\'t reach %d of these yet. %s' if built else '%s isn\'t built yet, and %d of these wait on it. %s') % (cid, k, reg['name'] + '.'),
                      '<button type="button" class="b go" data-open="%s" data-id="%s">%s</button>' % ('reach' if built else 'check', e(cid), 'Why not' if built else 'What it needs'), 'check-register.md'))
    if counts['build']:
        cards.append(('taupe', '%d thing%s decided or held, not live yet.' % (counts['build'], '' if counts['build'] == 1 else 's'),
                      '<button type="button" class="b go" data-f="build">Show them</button>', 'inventory.json'))
    cards = cards[:5]
    ch = ''.join('<div class="ac ac-%s"><p>%s</p>%s</div>' % (c, e(t), b) for c, t, b, _ in cards)
    order = [k for k, *_ in BUCKETS]
    rows = ''.join(item_row(k, k, it_, paths) for k, it_ in sorted(enumerate(items), key=lambda x: (order.index(bucket(x[1])), x[0])))   # what needs you first
    prev = g.get('preview')
    return '''<aside class="sp" aria-label="%(t)s">
<button type="button" class="spx" aria-label="Close">×</button>
<p class="crumb">Development · %(side)s</p>
<div class="top">%(gauge)s<div><h3>%(t)s</h3><p><span class="pill p-planning">Planning</span> <span class="meta">%(prev)s</span></p>
<p class="moves">What moves it: <b>%(n)d things</b></p></div></div>
<div class="bar" role="group" aria-label="Its %(n)d things by where they stand">%(bar)s</div>
<div class="legend">%(legend)s</div>
<h4>What you can do</h4><div class="acs">%(cards)s</div>
<div class="lh"><h4 id="lh-t">All %(n)d things</h4><button type="button" class="b sm" data-f="all" hidden>Show all</button></div>
<p class="key meta"><span class="dots">%(key)s</span> decided · built · live · checked · reviewed · proved</p>
<ul class="items">%(rows)s</ul>
<details class="src"><summary>Details: where these numbers come from</summary><p><a href="%(gh)ssite-audit/results/gauges.json" target="_blank" rel="noopener">gauges.json ↗</a>
<a href="%(gh)ssite-audit/results/gauges-items.json" target="_blank" rel="noopener">gauges-items.json ↗</a> made %(at)s by <a href="%(gh)stools/page-audit/gauges.py" target="_blank" rel="noopener">gauges.py ↗</a>;
the rule: <a href="%(gh)shandoffs/control-board-plan/step3/sections/gauge-lifecycle.md" target="_blank" rel="noopener">gauge-lifecycle.md ↗</a>.</p></details>
<div class="acts paths">%(paths)s</div>
</aside>''' % dict(t=e(g['title']), side='Front end' if g['side'] == 'front' else 'Back end', gauge=gauge_svg(0, 'planning', True),
                   prev=e('%d%% once you sign off the draft' % prev if prev is not None else ''), n=n, bar=bar, legend=legend, cards=ch, rows=rows,
                   key=''.join('<i class="d d-met"></i>' for _ in CRIT), gh=GH, at=e(IT['generated_at'][:16].replace('T', ' ') + ' UTC'),
                   paths=''.join('<button type="button" class="b sm" data-path="%s">%s · %d ▸</button>' % (k, w, len(paths.get(k, []))) for k, w in (('workflows', 'Workflows'), ('core', 'Core Services'), ('registered', 'Registered Services'))))


SETUP = [('probe', 'Its probe built'), ('target', 'Its target set'), ('check', 'Its check passing'), ('alarm', 'Its alarm wired to the board')]


def rt_panel(gid):
    g = next(x for x in G['runtime'] if x['id'] == gid)
    d = IT['runtime'][gid]
    su = g['setup']
    done = sum(1 for k, _ in SETUP if su.get(k))
    stage = 'planning' if done == 0 else ('phased' if not su['probe'] else ('audit' if not (su['target'] and su['check']) else ('impl' if not su['alarm'] else 'monitor')))
    sw = {'planning': 'Planning', 'phased': 'Phased Development', 'audit': 'Audit & Review', 'impl': 'Implementation', 'monitor': 'Monitoring'}[stage]
    needs = d.get('needs', [])
    steps = ''
    for k, w in SETUP:
        ok = bool(su.get(k))
        why = d['setup_why'].get(k, '')
        if ok:
            act = '<a class="b sm" href="%s" target="_blank" rel="noopener">Its evidence ↗</a>' % e(REG.get('SEARCH', {}).get('evidence', GH)) if k in ('probe',) else '<a class="b sm" href="/admin/config/alarms" data-peek-frame>Its alarms</a>'
        elif k == 'target':
            act = '<button type="button" class="b sm go" data-sign="target">Approve the proposed target</button>'
        elif k == 'check':
            act = '<button type="button" class="b sm go" data-open="check" data-id="SEARCH">See the failing cases</button>'
        else:
            act = '<button type="button" class="b sm go" data-send="alarm">Send to a build</button>'
        steps += '<li class="it"><div class="ih"><span class="bk bk-%s"></span><span class="nm2">%s</span><span class="meta">%s</span>%s</div></li>' % ('ok' if ok else ('fail' if k == 'check' else 'sign' if k == 'target' else 'build'), e(w), e(why), act)
    nrows = ''
    for nd in needs:
        srcs = ''.join('<a class="chip c-pass" href="/admin/config/register#svc-%s" data-peek-frame>%s%s</a>' % (e(s[0]), 'first: ' if j == 0 else 'backup: ', e(s[1])) for j, s in enumerate(nd['sources'])) or '<span class="chip c-none">no outside source</span>'
        nrows += '<li class="it"><div class="ih"><span class="nm2">%s</span></div><div class="chips">%s</div><p class="meta">Else: %s</p></li>' % (e(nd['label']), srcs, e(nd.get('fallback', '')))
    for u in d.get('unmapped', []):
        nrows += '<li class="it"><div class="ih"><span class="bk bk-build"></span><span class="nm2">%s</span><button type="button" class="b sm go" data-send="map">Send to a build</button></div><p class="meta">Not mapped to a need yet: a build maps it.</p></li>' % e(u)
    cards = [('copper', 'Its target isn\'t set. Proposed: %s.' % g.get('target_proposed', '').replace('target: set by Jason ', '').strip('()'), '<button type="button" class="b go" data-sign="target">Approve the target</button>'),
             ('amber', 'Its check isn\'t passing: %s.' % REG['SEARCH']['latest'], '<button type="button" class="b go" data-open="check" data-id="SEARCH">See the failing cases</button>')]
    for t, u in g.get('fix', []):
        if u:
            cards.append(('sage', t.replace('Fix it: ', '') + ' feeds it. Its card shows its state and keys.', '<a class="b go" href="%s" data-peek-frame>Open its card</a>' % e(u)))
    ch = ''.join('<div class="ac ac-%s"><p>%s</p>%s</div>' % (c, e(t), b) for c, t, b in cards[:4])
    return '''<aside class="sp" aria-label="%(t)s">
<button type="button" class="spx" aria-label="Close">×</button>
<p class="crumb">Board · %(box)s</p>
<div class="top">%(gauge)s<div><h3>%(t)s</h3><p><span class="pill p-%(stage)s">%(sw)s</span> <span class="meta">%(rd)s</span></p>
<p class="moves">What moves it: <b>%(done)d of 4 setup steps</b>, then its reading</p></div></div>
<div class="bar" role="group" aria-label="Its setup">%(bar)s</div>
<h4>What you can do</h4><div class="acs">%(cards)s</div>
<h4>Its setup</h4><ul class="items">%(steps)s</ul>
<h4>What it needs, sources in order</h4><ul class="items">%(needs)s</ul>
<details class="src"><summary>Details: what it counts</summary><p>%(rec)s. Measure: %(measure)s.</p></details>
<div class="acts paths">%(paths)s</div>
</aside>''' % dict(t=e(g['title']), box=e(g['box']), gauge=gauge_svg(done * 25, stage, True), stage=stage, sw=sw, rd=e(REG['SEARCH']['latest']), done=done,
                   bar=''.join('<button type="button" class="seg s-%s" style="flex:1" title="%s">%s</button>' % ('ok' if su.get(k) else ('fail' if k == 'check' else 'sign' if k == 'target' else 'build'), e(w), '✓' if su.get(k) else '') for k, w in SETUP),
                   cards=ch, steps=steps, needs=nrows, rec=e(d.get('records', '')), measure=e(g['measure']),
                   paths=''.join('<button type="button" class="b sm" data-path="%s">%s · %d ▸</button>' % (k, w, len(g['paths'].get(k, []))) for k, w in (('workflows', 'Workflows'), ('core', 'Core Services'), ('registered', 'Registered Services'))))


CSS = (Path(__file__).parent / 'mockup.css').read_text()
JS = (Path(__file__).parent / 'mockup.js').read_text()
dev = sys.argv[1] if len(sys.argv) > 1 else 'discovery'
rt = sys.argv[2] if len(sys.argv) > 2 else 'o-search'
REGJSON = json.dumps({k: {'name': v['name'], 'status': v['status'], 'latest': v['latest'], 'evidence': v['evidence'], 'runs': v['runs'], 'target': v['target']} for k, v in REG.items()})
FJSON = json.dumps({k: {'title': v['title'], 'detail': v['detail'], 'page': v['page'], 'shot': v['shot_url'], 'blocks': v['blocks'], 'rule': v['rule'], 'proposal': v.get('proposal_url', '')} for k, v in FIND.items()})
out = '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gauge panel mockup</title><style>%s</style></head><body>
<header class="mh"><b>Mockup: the gauge panel as a drill-down</b> <span class="meta">Real data: gauges-items.json of %s. Nothing here writes; buttons show what they'd do.</span>
<nav><button type="button" class="b sm" data-show="dev" aria-pressed="true">Development: %s</button><button type="button" class="b sm" data-show="rt" aria-pressed="false">Runtime: %s</button></nav></header>
<main><section data-view="dev">%s</section><section data-view="rt" hidden>%s</section></main>
<div class="toast" role="status" hidden></div>
<script>const REG=%s, FIND=%s;%s</script></body></html>''' % (CSS, IT['generated_at'][:10], e(next(x for x in G['development'] if x['id'] == dev)['title']), e(next(x for x in G['runtime'] if x['id'] == rt)['title']), dev_panel(dev), rt_panel(rt), REGJSON, FJSON, JS)
(Path(__file__).parent / 'mockup.html').write_text(out)
print('wrote', Path(__file__).parent / 'mockup.html', len(out))
