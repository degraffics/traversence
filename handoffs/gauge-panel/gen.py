#!/usr/bin/env python3
"""The gauge panel redesign's mockup (handoffs/gauge-panel.md), drawn from the real gauge files.

Reads site-audit/results/gauges.json, gauges-items.json, findings.json and the check register, and writes
handoffs/gauge-panel/mockup.html: a development gauge's panel (Discovery) and a runtime gauge's (Search finds what they
came for), each a visual drill-down where every item and chip leads somewhere. A mockup only: nothing here is the site.
Revision 3 (Jason's Q2 answer: view, Fix it, cleared by its check; no sign-off). Revision 2 (the Design reviewer's 9 changes, 2026-11-23): one filled button per box (brand brown), amethyst for sign-off,
a dots legend, no colour-only bar, one place per action, one time format, plain names (ids in Details), no gauge paths in a
row, 10 rows then Show all, needs folded.
Run: python3 handoffs/gauge-panel/gen.py [dev-id] [runtime-id]
"""
import html, json, math, re, sys
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
R = ROOT / 'site-audit/results'
G = json.load(open(R / 'gauges.json'))
IT = json.load(open(R / 'gauges-items.json'))
FIND = {f['id']: f for f in json.load(open(R / 'findings.json'))['findings']}
REG = {c['id']: dict(c) for c in json.load(open(ROOT / 'handoffs/control-board-plan/step3/sections/check-register.json'))['checks']}
for _k, _v in IT.get('checks', {}).items():   # the gauges' own newest reading of each check wins over the register's
    REG.setdefault(_k, {}).update({x: _v[x] for x in ('status', 'result', 'latest', 'evidence') if x in _v})
GH = 'https://github.com/degraffics/traversence/blob/main/'
RELS = sorted(p.name for p in (ROOT / 'deliveries/releases').glob('*.json'))
DECS = {p.name[:4]: p.name for p in (ROOT / 'decisions').glob('0*.md')}
e = html.escape
FIRST = 10   # rows shown before "Show all" (table-standard §9)

# plain names for the register's ids (the Design reviewer's change 7); the ids stay in "Where this comes from"
NAMES = {'R01–R15': 'Code checks', 'RENDER': 'The page check', 'PATHS': 'The path check', 'SEARCH': 'The search check',
         'LAYOUT': 'The phone layout check', 'PHOTOS': 'The photos check', 'PHOTOS-SHOWN': 'Photos shown', 'LOAD': 'Page speed',
         'BRAND': 'Brand standards', 'TOOLS': 'The tools check', 'BOARD': "The board's readings", 'AUDIT': "The audit's own load"}


def nm(cid):
    return NAMES.get(cid, NAMES['R01–R15'] if re.match(r'R\d\d', cid) else cid)


MON = 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec'.split()


def tfmt(dt):
    h = dt.hour % 12 or 12
    return '%s %d, %d:%02d %s UTC' % (MON[dt.month - 1], dt.day, h, dt.minute, 'AM' if dt.hour < 12 else 'PM')


def times(s):
    """Every time in one format, labelled (CLAUDE.md "Times"; change 6): "Nov 23, 10:20 AM UTC"."""
    s = re.sub(r'(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d)(?::\d\d)?Z?(?: UTC)?', lambda m: tfmt(datetime(*map(int, m.groups()))), s)
    def mst(m):
        mo, d, h, mi, ap = MON.index(m.group(1)) + 1, int(m.group(2)), int(m.group(3)) % 12, int(m.group(4)), m.group(5)
        return tfmt(datetime(2026, mo, d, h + (12 if ap == 'PM' else 0), mi) + timedelta(hours=7))
    return re.sub(r'(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d+), (\d+):(\d\d) (AM|PM) MST', mst, s)


def plain(s):
    """Register ids in running text into names; "3. daily with RENDER" into words; "(W3)" dropped."""
    s = re.sub(r'^\d\.\s*', '', s)
    s = re.sub(r'\s*\((?:W\d+(?:[,–-]\s*W?\d+)*(?:,\s*W\d+)*)\)', '', s)
    for k in sorted(NAMES, key=len, reverse=True):
        s = re.sub(r'(?<![\w-])' + re.escape(k) + r'(?![\w-])', NAMES[k].lower() if not s.startswith(k) else NAMES[k], s)
    return times(s)


CRIT = [('decided', 'Decided'), ('built', 'Built'), ('live', 'Live'), ('checked', 'Checked'), ('reviewed', 'Reviewed'), ('proved', 'Proved')]
# Jason, 2026-11-23 (Q2): "1. I need to view the issue. 2. then press a button to fix it. 3. once fixed it should clear the
# issue. So it is a process not just an accounting of someone's actions." No sign-off state: an item is an issue to fix,
# not built, without a check, or done; an issue clears by itself when its check passes again.
BUCKETS = [  # key, words
    ('fail', 'Issues to fix'), ('build', 'Not built yet'), ('check', 'No check yet'), ('ok', 'Done')]
BW = dict(BUCKETS)


def route_of(item):
    return item.get('route')   # gauges.py sets it (pages.md's row, the manifests, a path in the name)


def links(src):
    out = []
    for m in re.finditer(r'([\w./-]+\.md)(?::(\d+))?', src):
        f = m.group(1)
        if (ROOT / f).exists():
            out.append((f + (':' + m.group(2) if m.group(2) else ''), GH + f + ('#L' + m.group(2) if m.group(2) else '')))
    for m in re.finditer(r'release #(\d+)', src):
        hit = [r for r in RELS if re.search(r'-' + m.group(1) + r'-', r)]
        out.append(('release #' + m.group(1), GH + 'deliveries/releases/' + hit[0] if hit else GH + 'deliveries/releases'))
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
    if m and m.group(1) in FIND:
        return FIND[m.group(1)]
    for c in item.get('checks', []):
        if c[1] == 'fail' and len(c) > 2 and c[2] in FIND:
            return FIND[c[2]]
    return None


def bucket(item):
    if any(c[1] == 'fail' for c in item.get('checks', [])) or finding_of(item):
        return 'fail'
    if item['status'] in ('decided', 'held'):
        return 'build'
    if item.get('no_check'):
        return 'check'
    return 'ok'


def dot_state(item, crit):
    if crit in item.get('met', []) or (crit == 'reviewed' and item['status'] == 'live' and not finding_of(item)):
        return 'met'   # reviewed: no open issue (Jason's process replaces the sign-off; lifecycle §8 decided 2's note)
    if crit == 'checked':
        if any(c[1] == 'fail' for c in item.get('checks', [])):
            return 'fail'
        if item.get('no_check'):
            return 'nocheck'
    if crit == 'reviewed' and finding_of(item):
        return 'fail'
    return 'no'


DOTW = {'met': 'met', 'fail': 'an issue', 'nocheck': 'no check yet', 'no': 'not yet'}


def dots(item):
    lab = '; '.join('%s: %s' % (w.lower(), DOTW[dot_state(item, k)]) for k, w in CRIT)
    ds = ''.join('<i class="d d-%s" title="%s: %s"></i>' % (dot_state(item, k), w.lower(), DOTW[dot_state(item, k)]) for k, w in CRIT)
    # at 390 one phrase in place of the dots (change 3): where it's stuck
    first = next(((k, w) for k, w in CRIT if dot_state(item, k) != 'met'), None)
    word = 'Every step met' if not first else '%s at %s' % (DOTW[dot_state(item, first[0])].capitalize(), first[1].lower())
    return '<span class="dots" role="img" aria-label="%s">%s</span><span class="dword">%s</span>' % (e(lab), ds, e(word))


def gauge_svg(score, big=False):
    s = max(0.0, min(100.0, float(score or 0)))
    a = math.pi * (1 - s / 100)
    x, y = 60 + 50 * math.cos(a), 60 - 50 * math.sin(a)
    arc = '' if s <= 0 else '<path d="M10 60 A50 50 0 0 1 %.1f %.1f" class="g-on"/>' % (x, y)
    return ('<svg class="gs" width="%d" viewBox="0 0 120 68" role="img" aria-label="%d percent"><path d="M10 60 A50 50 0 0 1 110 60" class="g-off"/>'
            '%s<text x="60" y="58" text-anchor="middle" class="g-t">%d</text></svg>') % (120 if big else 64, int(s), arc, int(s))


def chip_for(check, item):
    cid, res = check[0], check[1]
    reg = REG.get(cid) or REG.get('R01')
    n = nm(cid)
    if res == 'pass':
        ev = reg.get('evidence', '')
        return '<a class="chip c-pass" href="%s" target="_blank" rel="noopener" title="Its latest run">%s: passes ↗</a>' % (e(ev if ev.startswith('http') else GH + 'site-audit/scorecard.md'), e(n))
    if res == 'fail':
        f = finding_of(item)
        if f:
            return '<button type="button" class="chip c-fail" data-open="finding" data-id="%s">%s: fails · open the finding</button>' % (e(f['id']), e(n))
        return '<button type="button" class="chip c-fail" data-open="check" data-id="%s">%s: fails · see what it found</button>' % (e(cid), e(n))
    if res == 'partial':
        return '<button type="button" class="chip c-part" data-open="check" data-id="%s">%s: partly · what\'s missing</button>' % (e(cid), e(n))
    if reg.get('status', '').startswith('built'):
        return '<button type="button" class="chip c-none" data-open="reach" data-id="%s">%s: doesn\'t reach it yet · why</button>' % (e(cid), e(n))
    return '<button type="button" class="chip c-none" data-open="check" data-id="%s">%s: no check yet · what it needs</button>' % (e(cid), e(n))


def state_sentence(item, b):
    f = finding_of(item)
    if b == 'fail':
        if f:
            return 'The issue: %s. In the way of %d path%s. It clears by itself once its check passes again.' % (f['title'], f['blocks'], '' if f['blocks'] == 1 else 's')
        fc = [c[0] for c in item['checks'] if c[1] == 'fail']
        at = re.search(r'\(([^()]*UTC)\)', plain(REG.get(fc[0], {}).get('latest', '')))   # the run's time only: its totals are the whole site's
        return 'The issue: %s failed on this page on its last run%s. It clears by itself once it passes again.' % (nm(fc[0]), ' (' + at.group(1) + ')' if at else '')
    if b == 'build':
        return 'Built and held: it goes live with its release.' if item['status'] == 'held' else 'Decided, not built yet. It moves the gauge once a build makes it.'
    if b == 'check':
        return 'Live, and every check that reaches it passes. Not yet reached by: %s, so it isn\'t done until a check is added.' % ', '.join(nm(c).lower() for c in item['no_check'])
    return 'Done: every step met.'


def next_step(item, after):
    """Step 4: the next unmet step once this one clears, as its own button's words."""
    nc = [c for c in item.get('no_check', [])]
    if after != 'check' and nc:
        return 'Add a check: ' + nm(nc[0]).lower()
    if item['status'] != 'live':
        return 'Put it live: its release'
    if 'proved' not in item.get('met', []):
        return 'Prove it on live data: add a path check'
    return ''


def item_row(n, item):
    b = bucket(item)
    rt = route_of(item)
    name = e(item['name'])
    lk = links(item['source'])
    nml = ('<a class="nm" href="%s" data-peek-frame title="Open the page beside the board">%s</a>' % (e(rt), name)) if rt else (
        '<a class="nm" href="%s" target="_blank" rel="noopener" title="Where it\'s decided">%s</a>' % (e(lk[0][1]) if lk else GH, name))
    f = finding_of(item)
    acts = []   # one filled button per box: the first; the rest outlined (change 1)
    if b == 'fail':
        fc = [c[0] for c in item['checks'] if c[1] == 'fail']
        what = (f['title'] if f else nm(fc[0]) + ' failed').rstrip('.')
        acts.append('<button type="button" class="b go" data-send="%d" data-what="%s" data-next="%s">Fix it</button>' % (n, e(what), e(next_step(item, 'fail'))))
        if f:
            acts.append('<button type="button" class="b ol" data-open="finding" data-id="%s">See the evidence</button>' % e(f['id']))
        else:
            acts.append('<button type="button" class="b ol" data-open="check" data-id="%s">See what %s found</button>' % (e(fc[0]), e(nm(fc[0]).lower())))
    elif b == 'build':
        acts.append('<button type="button" class="b go" data-send="%d" data-what="building it" data-next="%s">Fix it: build it</button>' % (n, e(next_step(item, 'build'))))
    elif b == 'check':
        c0 = item['no_check'][0]
        acts.append('<button type="button" class="b go" data-send="%d" data-what="adding %s" data-next="%s">Add a check</button>' % (n, e(nm(c0).lower()), e(next_step(item, 'check'))))
        acts.append('<button type="button" class="b ol" data-open="%s" data-id="%s">Why it isn\'t checked</button>' % ('reach' if REG.get(c0, {}).get('status', '').startswith('built') else 'check', e(c0)))
    if rt:
        acts.append('<a class="b ol" href="%s" data-peek-frame>Open the page</a>' % e(rt))
    if f:
        acts.append('<a class="b ol" href="#site-health-%s">Its row in Site health</a>' % e(f['id']))
    chips = ''.join(chip_for(c, item) for c in item.get('checks', [])) or '<button type="button" class="chip c-none" data-open="nocheck">No check yet: it isn\'t built · what will check it</button>'
    ids = ', '.join(c[0] for c in item.get('checks', []))
    src = ''.join('<a href="%s" target="_blank" rel="noopener">%s ↗</a>' % (e(u), e(t)) for t, u in lk)
    after = 'check' if (b == 'fail' and item.get('no_check')) else 'ok'
    return ('<li class="it" data-b="%s" data-after="%s"%s><div class="ih"><span class="bk bk-%s" title="%s"></span>%s%s'
            '<button type="button" class="ex" aria-expanded="false" aria-label="Open %s">▾</button></div>'
            '<div class="ib" hidden><p class="st">%s</p><div class="chips">%s</div><div class="acts">%s</div>'
            '<details class="src"><summary>Where this comes from</summary><p>%s</p><p class="meta">%s%s</p></details></div></li>') % (
        b, after, ' data-more' if n >= FIRST else '', b, e(BW[b]), nml, dots(item), name,
        e(state_sentence(item, b)), chips, ''.join(acts), src or 'its source line names no file', e(item['source']),
        e(' · check ids: ' + ids) if ids else '')


LEGEND = ('<button type="button" class="info" aria-expanded="false" aria-controls="dl-%s" title="What the dots mean">ⓘ</button>'
          '<div class="dleg" id="dl-%s" hidden><button type="button" class="dlx" data-dlx aria-label="Close">×</button><p><b>Each dot is one step</b>: decided · built · live · checked · reviewed · proved.</p><ul>'
          '<li><i class="d d-met"></i> met</li><li><i class="d d-fail"></i> an issue</li><li><i class="d d-nocheck"></i> no check yet</li>'
          '<li><i class="d d-no"></i> not yet</li></ul></div>')


def card(primary, colour, text, button):
    if not primary:
        button = button.replace('class="b go"', 'class="b ol"')
    return '<div class="ac ac-%s"><p>%s</p>%s</div>' % (colour, e(text), button)


def dev_panel(gid):
    g = next(x for x in G['development'] if x['id'] == gid)
    items = IT['development'][gid]['list']
    paths = g['paths']
    counts = {k: sum(1 for i in items if bucket(i) == k) for k, _ in BUCKETS}
    n = len(items)
    bar = ''.join('<button type="button" class="seg s-%s" style="flex:%d" data-f="%s" aria-pressed="false" aria-label="%s: %d of %d" title="%s: %d"%s>%s</button>' % (
        k, counts[k], k, e(w), counts[k], n, e(w), counts[k], '' if counts[k] else ' hidden', counts[k] if counts[k] * 100 / n >= 7 else '') for k, w in BUCKETS)
    legend = ''.join('<button type="button" class="lg" data-f="%s" aria-pressed="false"%s><span class="bk bk-%s"></span>%s <b data-count="%s">%d</b></button>' % (k, '' if counts[k] else ' hidden', k, e(w), k, counts[k]) for k, w in BUCKETS)
    nc = {}
    for i in items:
        for c in i.get('no_check', []):
            nc[c] = nc.get(c, 0) + 1
    cards = [('sign', 'It stays at 0 in Planning until you approve what 100 means: these %d things, each with its six steps.' % n,
              '<button type="button" class="b go" data-sign="scope">Approve the scope</button>')]
    if counts['fail']:
        cards.append(('fail', '%d issue%s to fix. Each opens its evidence and its Fix it; it clears itself once its check passes.' % (counts['fail'], '' if counts['fail'] == 1 else 's'),
                      '<button type="button" class="b go" data-f="fail">Show the <span data-count="fail">%d</span></button>' % counts['fail']))
    for cid, k in sorted(nc.items(), key=lambda x: -x[1])[:1]:
        built = REG[cid].get('status', '').startswith('built')
        cards.append(('check', ('%s runs, but doesn\'t reach %d of these yet.' if built else '%s isn\'t built yet, and %d of these wait on it.') % (nm(cid), k),
                      '<button type="button" class="b go" data-send="all" data-what="%s reaching these %d">Add the check</button>' % (e(nm(cid).lower()), k)))
    if counts['build']:
        cards.append(('build', '%d thing%s decided or held, not live yet.' % (counts['build'], '' if counts['build'] == 1 else 's'),
                      '<button type="button" class="b go" data-f="build">Show them</button>'))
    ch = ''.join(card(j == 0, c, t, b) for j, (c, t, b) in enumerate(cards[:5]))
    ch = ch.replace('<p>%d issue' % counts['fail'], '<p><span data-count="fail">%d</span> issue' % counts['fail'], 1)
    order = [k for k, _ in BUCKETS]
    rows = ''.join(item_row(j, it_) for j, it_ in enumerate(sorted(items, key=lambda x: order.index(bucket(x)))))
    prev = g.get('preview')
    if prev is None:   # the site computes the preview (ControlGauges::devScore); the draft's share of steps met, as §6
        prev = round(100 * sum(len(i.get('met', [])) for i in items) / (6 * n))
    return '''<aside class="sp" aria-label="%(t)s" data-gauge="%(t)s">
<button type="button" class="spx" aria-label="Close">×</button>
<p class="crumb">Development · %(side)s</p>
<div class="top">%(gauge)s<div><h3>%(t)s</h3><p><span class="pill">Planning</span> <span class="meta">%(prev)d%% once you approve the draft</span></p>
<p class="moves">What moves it: <b>%(n)d things</b></p></div></div>
<div class="bar" role="group" aria-label="Its %(n)d things by where they stand">%(bar)s</div>
<div class="legend">%(legend)s</div>
<h4>What you can do</h4><div class="acs">%(cards)s</div>
<div class="lh"><h4 id="lh-t">What needs you first</h4><button type="button" class="b sm ol" data-f="all" hidden>Show all</button></div>
<div class="key meta"><span>Steps: decided · built · live · checked · reviewed · proved</span>%(leg)s</div>
<ul class="items">%(rows)s</ul>
<button type="button" class="b sm ol more" data-more>Show all %(n)d</button>
<details class="src"><summary>Details: where these numbers come from</summary><p><a href="%(gh)ssite-audit/results/gauges.json" target="_blank" rel="noopener">gauges.json ↗</a>
<a href="%(gh)ssite-audit/results/gauges-items.json" target="_blank" rel="noopener">gauges-items.json ↗</a> made %(at)s by <a href="%(gh)stools/page-audit/gauges.py" target="_blank" rel="noopener">gauges.py ↗</a>;
the rule: <a href="%(gh)shandoffs/control-board-plan/step3/sections/gauge-lifecycle.md" target="_blank" rel="noopener">gauge-lifecycle.md ↗</a>.</p></details>
<div class="acts paths">%(paths)s</div>
</aside>''' % dict(t=e(g['title']), side='Front end' if g['side'] == 'front' else 'Back end', gauge=gauge_svg(0, True), prev=prev, n=n, bar=bar,
                   legend=legend, cards=ch, rows=rows, leg=LEGEND % (gid, gid), gh=GH, at=e(times(IT['generated_at'])),
                   paths=''.join('<button type="button" class="b sm ol" data-path="%s">%s · %d ▸</button>' % (k, w, len(paths.get(k, []))) for k, w in (('workflows', 'Workflows'), ('core', 'Core Services'), ('registered', 'Registered Services'))))


SETUP = [('probe', 'Its probe built'), ('target', 'Its target set'), ('check', 'Its check passing'), ('alarm', 'Its alarm wired to the board')]


def rt_panel(gid):
    g = next(x for x in G['runtime'] if x['id'] == gid)
    d = IT['runtime'][gid]
    su = g['setup']
    done = sum(1 for k, _ in SETUP if su.get(k))
    stage = 'Planning' if done == 0 else ('Phased Development' if not su['probe'] else ('Audit & Review' if not (su['target'] and su['check']) else ('Implementation' if not su['alarm'] else 'Monitoring')))
    sc = REG['SEARCH']
    # the actions live only in the cards (change 5); the setup rows say their state, with a small link to the card
    cards = []
    if not su.get('target'):
        cards.append(('tgt', 'sign', 'Its target isn\'t set. Proposed: every search case passes. Today: %s.' % plain(sc['latest']), '<button type="button" class="b go" data-sign="target">Approve the target</button>'))
    if not su.get('check'):
        cards.append(('chk', 'fail', 'Its check isn\'t passing: 12 search cases fail. It clears itself once they pass.', '<button type="button" class="b go" data-open="check" data-id="SEARCH">See the failing cases and fix them</button>'))
    for t, u in g.get('fix', []):
        if u:
            cards.append(('', 'ok', t.replace('Fix it: ', '') + ' feeds it. Its card shows its state and keys.', '<a class="b go" href="%s" data-peek-frame>Open its card</a>' % e(u)))
    ch = ''.join('<div class="ac ac-%s"%s><p>%s</p>%s</div>' % (c, ' id="ac-%s"' % a if a else '', e(t), b if j == 0 else b.replace('class="b go"', 'class="b ol"')) for j, (a, c, t, b) in enumerate(cards[:4]))
    steps = ''
    for k, w in SETUP:
        ok = bool(su.get(k))
        if ok:
            st, link = '✓ done', ('<a class="sl" href="%s" target="_blank" rel="noopener">its evidence ↗</a>' % e(sc.get('evidence', GH)) if k == 'probe' else '<a class="sl" href="/admin/config/alarms" data-peek-frame>its alarms</a>')
        elif k == 'target':
            st, link = 'needs you', '<a class="sl" href="#ac-tgt">Approve the target ↑</a>'
        elif k == 'check':
            st, link = 'an issue', '<a class="sl" href="#ac-chk">Fix it ↑</a>'
        else:
            st, link = 'not wired yet', '<button type="button" class="sl" data-send="alarm">Send to a build</button>'
        b = 'ok' if ok else ('fail' if k == 'check' else 'sign' if k == 'target' else 'build')
        steps += '<li class="it"><div class="ih"><span class="bk bk-%s"></span><span class="nm2">%s</span><span class="sw sw-%s">%s</span>%s</div><p class="meta">%s</p></li>' % (
            b, e(w), b, e(st), link, e(plain(d['setup_why'].get(k, ''))))
    needs = d.get('needs', [])
    nsrc = sum(len(x['sources']) for x in needs)
    nrows = ''
    for nd in needs:
        srcs = ''.join('<a class="chip c-pass" href="/admin/config/register#svc-%s" data-peek-frame>%s%s</a>' % (e(s[0]), 'first: ' if j == 0 else 'backup: ', e(s[1])) for j, s in enumerate(nd['sources'])) or '<span class="chip c-none">no outside source</span>'
        nrows += '<li class="it"><div class="ih"><span class="nm2">%s</span></div><div class="chips">%s</div><p class="meta">Else: %s</p></li>' % (e(plain(nd['label'])), srcs, e(plain(nd.get('fallback', ''))))
    for u in d.get('unmapped', []):
        nrows += '<li class="it"><div class="ih"><span class="bk bk-build"></span><span class="nm2">%s</span><button type="button" class="b sm go" data-send="map">Send to a build</button></div><p class="meta">Not mapped to a need yet: a build maps it.</p></li>' % e(u)
    return '''<aside class="sp" aria-label="%(t)s" data-gauge="%(t)s">
<button type="button" class="spx" aria-label="Close">×</button>
<p class="crumb">Board · %(box)s</p>
<div class="top">%(gauge)s<div><h3>%(t)s</h3><p><span class="pill">%(stage)s</span></p>
<p class="moves">What moves it: <b>%(done)d of 4 setup steps done</b>, then its reading</p></div></div>
<h4>What you can do</h4><div class="acs">%(cards)s</div>
<h4>Its setup</h4><ul class="items">%(steps)s</ul>
<details class="nd"><summary><b>What it needs:</b> %(nn)d needs, %(ns)d sources ▸</summary><ul class="items">%(needs)s</ul></details>
<details class="src"><summary>Details: what it counts</summary><p>%(rec)s. Measure: %(measure)s. Check id: SEARCH.</p></details>
<div class="acts paths">%(paths)s</div>
</aside>''' % dict(t=e(g['title']), box=e(g['box']), gauge=gauge_svg(done * 25, True), stage=stage, done=done, cards=ch, steps=steps,
                   nn=len(needs) + len(d.get('unmapped', [])), ns=nsrc, needs=nrows, rec=e(d.get('records', '')), measure=e(g['measure']),
                   paths=''.join('<button type="button" class="b sm ol" data-path="%s">%s · %d ▸</button>' % (k, w, len(g['paths'].get(k, []))) for k, w in (('workflows', 'Workflows'), ('core', 'Core Services'), ('registered', 'Registered Services'))))


CSS = (Path(__file__).parent / 'mockup.css').read_text()
JS = (Path(__file__).parent / 'mockup.js').read_text()
dev = sys.argv[1] if len(sys.argv) > 1 else 'discovery'
rt = sys.argv[2] if len(sys.argv) > 2 else 'o-search'
REGJSON = json.dumps({k: {'name': nm(k) + ': ' + v.get('name', ''), 'status': v.get('status', ''), 'latest': plain(v.get('latest', '')), 'evidence': v.get('evidence', ''),
                          'runs': plain(v.get('runs', '')), 'target': v.get('target', '')} for k, v in REG.items()})
FJSON = json.dumps({k: {'title': v['title'], 'detail': v['detail'], 'page': v['page'], 'shot': v['shot_url'], 'blocks': v['blocks'], 'rule': v['rule'], 'proposal': v.get('proposal_url', '')} for k, v in FIND.items()})
out = '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gauge panel mockup</title><style>%s</style></head><body>
<header class="mh"><b>Mockup: the gauge panel as a drill-down (revision 4)</b> <span class="meta">Real data: gauges-items.json of %s. Nothing here writes; buttons show what they'd do.</span>
<nav><button type="button" class="b sm" data-show="dev" aria-pressed="true">Development: %s</button><button type="button" class="b sm" data-show="rt" aria-pressed="false">Runtime: %s</button></nav></header>
<main><section data-view="dev">%s</section><section data-view="rt" hidden>%s</section></main>
<div class="toast" role="status" hidden></div>
<script>const REG=%s, FIND=%s;%s</script></body></html>''' % (CSS, e(times(IT['generated_at'])), e(next(x for x in G['development'] if x['id'] == dev)['title']),
                                                               e(next(x for x in G['runtime'] if x['id'] == rt)['title']), dev_panel(dev), rt_panel(rt), REGJSON, FJSON, JS)
(Path(__file__).parent / 'mockup.html').write_text(out)
print('wrote', Path(__file__).parent / 'mockup.html', len(out))
