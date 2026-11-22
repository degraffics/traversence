# The 16 development gauges as a mockup (Jason, 2026-11-22): two rows, Front end (6) and Back end (10), one dial each with one
# needle, Complete (live and passing, over the in-scope items). A gauge opens a side panel of its items by status, each with its
# source, a two-faced item linking its other face. Read from step3/sections/inventory.json (sections16.py). Used by board.py for
# the Board view's "Development" rows, and written standalone as step3/board/dev-gauges.html. Design mockup, not site code.
import html, json, math, os, re

E = html.escape
HERE = os.path.dirname(os.path.abspath(__file__))
STEP3 = os.path.normpath(os.path.join(HERE, '..', '..')) + '/'
INV = json.load(open(STEP3 + 'sections/inventory.json'))

FRONT = ['Discovery', 'Directory', 'Community', 'Marketplace', 'TRVGO – Storefront', 'Accounts']
BACK = ['Dashboard & Pulse', 'Get Local 360°', 'Let\'s Explore 360°', 'Market 360°', 'Social 360°', 'Sales & Growth', 'White Glove Concierge',
        'Back-Office', 'Insights & Research', 'System Configuration']
QUESTION = 'Question (no gauge yet)'
GROUPS = [('pass', 'Live, passing', 'g'), ('fail', 'Live, not passing', 'r'), ('nochk', 'Live, not checked', 'n'), ('held', 'Built, held', 'h'),
          ('notbuilt', 'Decided, not built', 'a'), ('prop', 'Proposed, out of scope', 'p')]


def slug(g):
    return re.sub(r'[^a-z0-9]+', '-', g.lower()).strip('-')


def _group(it):
    st = it['status']
    if st == 'live':
        return {'passing': 'pass', 'not passing': 'fail'}.get(it.get('check16', it.get('check')), 'nochk')
    return {'held': 'held', 'decided': 'notbuilt', 'proposed': 'prop'}[st]


# every item once, with an id; the other face found by its name
ITEMS = []
for sec in INV['sections'].values():
    for it in sec['items']:
        if it['owner16'].startswith('replaced by'):
            continue
        it = dict(it)
        if 'check16' in it:
            it['check'], it['check_by'] = it['check16'], it['check_by16']
        ITEMS.append(it)
ITEMS += [dict(it) for it in INV['manifest_items']]
for n, it in enumerate(ITEMS, 1):
    it['id'] = f'dev-{n}'
    it['grp'] = _group(it)
BYNAME = {it['name']: it for it in ITEMS}
_names = sorted(BYNAME, key=len, reverse=True)


def _face(text):
    """'<name> (<gauge>)' -> the item it names."""
    m = re.match(r'(.*) \(([^()]*)\)$', text)
    if m and m.group(1) in BYNAME:
        return BYNAME[m.group(1)]
    return next((BYNAME[n] for n in _names if text.startswith(n + ' (')), None)


def stats(g):
    its = [i for i in ITEMS if i['owner16'] == g]
    scope = [i for i in its if i['status'] != 'proposed']
    done = sum(1 for i in scope if i['grp'] == 'pass')
    cnt = {k: sum(1 for i in its if i['grp'] == k) for k, _, _ in GROUPS}
    return dict(items=its, scope=len(scope), done=done, pct=(done / len(scope) * 100) if scope else 0.0, cnt=cnt)


BANDS = [(0, 50, 'r', 'Behind'), (50, 80, 'a', 'Under way'), (80, 100, 'g', 'Nearly there')]   # des: proposed until Jason sets them


def band(p):
    return next(b for b in BANDS if b[0] <= p < b[1] or (p >= 100 and b[1] == 100))


def _pt(f, r=46, cx=60, cy=58):
    a = math.pi * (1 - f)
    return cx + r * math.cos(a), cy - r * math.sin(a)


def _arc(f0, f1, cls, r=46):
    (x0, y0), (x1, y1) = _pt(f0, r), _pt(f1, r)
    return f'<path class="dg-band {cls}" d="M{x0:.1f} {y0:.1f} A{r} {r} 0 0 1 {x1:.1f} {y1:.1f}"/>'


def dial(p, k):
    x, y = _pt(p / 100, 38)
    bands = ''.join(_arc(a / 100, b / 100, c) for a, b, c, _ in BANDS)
    return (f'<svg class="dg-dial" viewBox="0 0 120 66" aria-hidden="true">{bands}'
            f'<line class="dg-needle t-{k}" x1="60" y1="58" x2="{x:.1f}" y2="{y:.1f}"/><circle class="dg-hub" cx="60" cy="58" r="4"/></svg>')


def gauge(g, side):
    s = stats(g)
    lo, hi, k, word = band(s['pct'])
    return (f'<button type="button" class="dg-g" data-open-panel="p-dev-{slug(g)}" data-gauge="{E(g)}" aria-label="{E(g)}: {s["pct"]:.0f}% complete, {s["done"]} of {s["scope"]} items. Open its items">'
            f'<span class="dg-name">{E(g)}</span>{dial(s["pct"], k)}<span class="dg-word s-{k}">{word}</span>'
            f'<span class="dg-pct">{s["pct"]:.0f}%</span><span class="dg-of">{s["done"]} of {s["scope"]} items</span></button>')


def rows(heading='h2'):
    q = [i for i in ITEMS if i.get('pending')]
    return (f'<section class="dg" aria-label="Development"><div class="dg-head"><{heading} class="dg-h">Development</{heading}>'
            f'<span class="dg-draft">draft: the scope isn\'t signed off</span><span class="dg-prop">bands proposed: under 50 · 50–80 · 80 and up</span>'
            f'<button type="button" class="dg-q" data-open-panel="p-dev-questions">Questions: recommended, pending Jason <b>{len(q)}</b></button></div>'
            f'<p class="dg-sub">One needle each: Complete, live and passing its check over the gauge\'s in-scope items (Jason, 2026-11-22). Tap a gauge for its items.</p>'
            f'<p class="dg-rule"><b>Complete needs a path or function check; a page that only renders isn\'t complete.</b> Each item names its level: render (the page answers), path (the flow completes), function (it does its job on real data).</p>'
            f'<h3 class="dg-row">Front end <span>6</span></h3><div class="dg-grid">{"".join(gauge(g, "front") for g in FRONT)}</div>'
            f'<h3 class="dg-row">Back end <span>10</span> <small>the admin\'s modules, as manifests.php has them</small></h3><div class="dg-grid">{"".join(gauge(g, "back") for g in BACK)}</div></section>')


def _item(it):
    faces = []
    for f in it.get('other_face') or []:
        o = _face(f)
        if o and o['owner16'] != QUESTION:
            faces.append(f'<a href="#" class="dg-face" data-open-panel="p-dev-{slug(o["owner16"])}" data-dev-item="{o["id"]}">Other face: {E(o["name"])} · {E(o["owner16"])} ›</a>')
        elif o:
            faces.append(f'<a href="#" class="dg-face" data-open-panel="p-dev-questions" data-dev-item="{o["id"]}">Other face: {E(o["name"])} · Questions ›</a>')
    lvl = f'<span class="dg-lvl l-{it.get("level", "none")}">check level: {E(it.get("level", "none"))}</span>' if it['status'] == 'live' else ''
    chk = lvl + (f'<span class="dg-chk">{E(it.get("check_by", ""))}</span>' if it['status'] == 'live' and it.get('check_by') else '')
    if it.get('pending'):
        chk += f'<span class="dg-pend">{E(it["owner16"])}: {E(it["pending"])}</span>'
    why = f'<span class="dg-chk">{E(it["why"])}</span>' if it.get('why') else ''
    return (f'<li class="dg-it" id="{it["id"]}"><span class="dg-in">{E(it["name"])}</span><span class="dg-src">{E(it["source"])}</span>{chk}{why}'
            + ''.join(faces) + '</li>')


def _bar(cnt, total):
    seg = ''.join(f'<i class="dg-s {c}" style="width:{cnt[k] / total * 100:.2f}%" title="{E(w)}: {cnt[k]}"></i>' for k, w, c in GROUPS if cnt[k])
    key = ''.join(f'<span class="dg-k"><i class="dg-s {c}"></i>{E(w)} {cnt[k]}</span>' for k, w, c in GROUPS if cnt[k])
    return f'<div class="dg-bar" role="img" aria-label="{E(", ".join(f"{w} {cnt[k]}" for k, w, _ in GROUPS if cnt[k]))}">{seg}</div><div class="dg-keys">{key}</div>'


def panel(g):
    s = stats(g)
    body = ''
    for k, w, c in GROUPS:
        its = [i for i in s['items'] if i['grp'] == k]
        if its:
            body += f'<h3 class="dg-gh"><i class="dg-s {c}"></i>{E(w)} <b>{len(its)}</b></h3><ul class="dg-list">{"".join(_item(i) for i in its)}</ul>'
    return (f'<aside class="panel" id="p-dev-{slug(g)}" hidden role="dialog" aria-label="{E(g)}: its items"><button class="x" aria-label="Close">×</button>'
            f'<div class="crumb">Development · {"front end" if g in FRONT else "back end"}</div><h2 class="h2">{E(g)}</h2>'
            f'<div class="dg-sum"><b>{s["pct"]:.0f}%</b> complete · {s["done"]} of {s["scope"]} in-scope items live and passing · {len(s["items"])} items in all '
            f'<span class="dg-draft">draft: the scope isn\'t signed off</span></div>{_bar(s["cnt"], len(s["items"]))}{body}'
            f'<div class="btns navb"><button class="btn back" data-dev-back>← Back</button></div></aside>')


def _qitem(it):
    return (f'<li class="dg-it"><span class="dg-in">{E(it["name"])}</span><span class="dg-pend">Recommended: {E(it["owner16"])} · {E(it["pending"])}</span>'
            f'<span class="dg-chk">{E(it.get("why", ""))}</span><a href="#" class="dg-face" data-open-panel="p-dev-{slug(it["owner16"])}" data-dev-item="{it["id"]}">Open it on {E(it["owner16"])} ›</a></li>')


def panels():
    q = [i for i in ITEMS if i.get('pending')]
    qp = (f'<aside class="panel" id="p-dev-questions" hidden role="dialog" aria-label="Questions: recommended, pending Jason"><button class="x" aria-label="Close">×</button>'
          f'<div class="crumb">Development</div><h2 class="h2">Questions: recommended, pending Jason</h2><p class="sub">Items with no manifest home and no clear front-end gauge, placed where the session manager recommends until Jason confirms (development-gauges.md). Each is counted on the gauge named.</p>'
          f'<ul class="dg-list">{"".join(_qitem(i) for i in q)}</ul><div class="btns navb"><button class="btn back" data-dev-back>← Back</button></div></aside>')
    return ''.join(panel(g) for g in FRONT + BACK) + qp


CSS = r"""
.dg{margin:0 0 1.2rem}.dg-head{display:flex;flex-wrap:wrap;gap:.4rem .7rem;align-items:baseline}.dg-h{font:700 20px/1.2 var(--serif);margin:0;color:var(--ink)}
.dg-draft{font-size:11.5px;border:1px dotted var(--copper);color:var(--copper);border-radius:3px;padding:0 5px}.dg-prop{font-size:11.5px;color:var(--meta)}
.dg-q{margin-left:auto;border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.2rem .7rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.dg-q b{background:var(--amber);color:#fff;border-radius:999px;padding:0 .4rem;margin-left:.3rem;font-size:11px}
.dg-sub{font-size:12.5px;color:var(--meta);margin:.25rem 0 .4rem}.dg-row{font:600 13px var(--sans);color:var(--meta);text-transform:uppercase;letter-spacing:.04em;margin:.8rem 0 .4rem}
.dg-row span{background:var(--sec);border-radius:999px;padding:0 .45rem;margin-left:.2rem}.dg-row small{text-transform:none;letter-spacing:0;font-weight:400}
.dg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem}
.dg-g{display:flex;flex-direction:column;align-items:center;gap:.1rem;background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.5rem .4rem .55rem;font:inherit;color:var(--ink);cursor:pointer;min-width:0}
.dg-g:hover,.dg-g:focus-visible{border-color:var(--copper);outline:none;box-shadow:0 0 0 2px #C85A1733}
.dg-name{font-weight:600;font-size:13px;text-align:center;line-height:1.2;min-height:2.4em;display:flex;align-items:center}
.dg-dial{width:100%;max-width:130px;height:auto}.dg-band{fill:none;stroke-width:9}.dg-band.r{stroke:#E7B9AC}.dg-band.a{stroke:#EBCF9B}.dg-band.g{stroke:#BFD8C8}
.dg-needle{stroke-width:3.5;stroke-linecap:round}.dg-needle.t-r{stroke:var(--red)}.dg-needle.t-a{stroke:var(--amber)}.dg-needle.t-g{stroke:#3d6b52}.dg-hub{fill:var(--ink)}
.dg-word{font-size:11.5px;font-weight:600;border-radius:999px;padding:0 .5rem;color:#fff}.dg-word.s-r{background:var(--red)}.dg-word.s-a{background:var(--amber)}.dg-word.s-g{background:#3d6b52}
.dg-pct{font:700 20px/1.1 var(--sans)}.dg-of{font-size:11.5px;color:var(--meta)}
.dg-sum{font-size:13px;margin:.2rem 0 .5rem}.dg-bar{display:flex;height:12px;border-radius:999px;overflow:hidden;background:var(--sec)}
.dg-s{display:inline-block}.dg-bar .dg-s{height:100%}.dg-s.g{background:#3d6b52}.dg-s.r{background:var(--red)}.dg-s.n{background:#B7AC97}.dg-s.h{background:var(--copper)}.dg-s.a{background:var(--amber)}.dg-s.p{background:#DDD3BF}
.dg-keys{display:flex;flex-wrap:wrap;gap:.2rem .7rem;font-size:11.5px;margin:.35rem 0 .2rem}.dg-k .dg-s,.dg-gh .dg-s{width:10px;height:10px;border-radius:2px;margin-right:.3rem}
.dg-gh{font:600 14px var(--sans);margin:.9rem 0 .3rem;display:flex;align-items:center}.dg-gh b{margin-left:.35rem;font-size:12px;color:var(--meta)}
.dg-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.3rem}
.dg-it{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.4rem .55rem;display:flex;flex-direction:column;gap:.15rem;font-size:13px;overflow-wrap:anywhere}
.dg-it.hit{border-color:var(--copper);box-shadow:0 0 0 2px #C85A1744}.dg-in{font-weight:600}.dg-src,.dg-chk{font-size:11.5px;color:var(--meta)}.dg-face{font-size:12px;color:var(--brown)}
.dg-rule{font-size:12.5px;margin:.1rem 0 .4rem;border-left:3px solid var(--copper);padding-left:.5rem}.dg-lvl{font-size:11px;border:1px solid var(--card-edge);border-radius:999px;padding:0 .45rem;align-self:flex-start;background:var(--sec)}
.dg-lvl.l-path,.dg-lvl.l-function{border-color:#3d6b52;color:#3d6b52}.dg-pend{font-size:11.5px;color:var(--copper)}
@media (max-width:767px){.dg-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}.dg-q{margin-left:0}}
"""

JS = r"""<script>
(function(){var trail=[];
document.addEventListener('click',function(e){var a=e.target.closest('[data-dev-item]');if(a){var cur=document.querySelector('aside.panel:not([hidden])');if(cur)trail.push(cur.id);var id=a.getAttribute('data-dev-item');
  setTimeout(function(){var it=document.getElementById(id);if(it){document.querySelectorAll('.dg-it.hit').forEach(function(x){x.classList.remove('hit');});it.classList.add('hit');it.scrollIntoView({block:'center'});}},30);return;}
 var b=e.target.closest('[data-dev-back]');if(b){e.preventDefault();var p=trail.pop();if(p){var t=document.createElement('a');t.setAttribute('data-open-panel',p);t.hidden=true;document.body.appendChild(t);t.click();t.remove();}else{var x=b.closest('aside.panel').querySelector('.x');if(x)x.click();}}
 if(e.target.closest('.dg-g'))trail=[];},true);
})();
</script>"""

# the standalone page's own panel handling (the board has its own)
JS_PAGE = r"""<script>
(function(){var b=document.body;function close(){b.classList.remove('open');document.querySelectorAll('aside.panel').forEach(function(p){p.hidden=true;});}
function open(id){document.querySelectorAll('aside.panel').forEach(function(p){p.hidden=p.id!==id;});b.classList.add('open');var p=document.getElementById(id);if(p){p.scrollTop=0;var x=p.querySelector('.x');if(x)x.focus({preventScroll:true});}}
document.addEventListener('click',function(e){var t=e.target.closest('[data-open-panel]');if(t){e.preventDefault();open(t.getAttribute('data-open-panel'));return;}
 if(e.target.closest('aside.panel .x')){close();return;}if(e.target.classList.contains('scrim'))close();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});})();
</script>"""



# ================================================================ the gauge lifecycle (Jason, 2026-11-22; sections/gauge-lifecycle.md)
# "initially 0 and determine what is needed to make the gauge reach 100": each gauge's measure is configured. Today every gauge is
# in Planning (0), its configuration drafted from the inventory; "Preview with the draft configuration" shows what it would read.
STAGES = ['Planning', 'Phased Development', 'Audit & Review', 'Implementation', 'Monitoring']
CRIT = [('decided', 'Decided'), ('built', 'Built'), ('checked', 'Checked (path or function)'), ('reviewed', 'Reviewed'), ('live', 'Live'), ('proved', 'Proved on real data')]
PHASES = [('p1', 'Phase 1: what\'s live, checked, reviewed and proved'), ('p2', 'Phase 2: what\'s built and held'), ('p3', 'Phase 3: what\'s decided and not built')]
RUNTIME_WORDS = ['Working', 'Less than optimal', 'Trip', 'Paused', 'Failing', 'Warning', 'Held', 'not checked']


def crit_met(it):
    """The draft's criteria met, read from the inventory: status -> decided, built, live; check level -> checked, proved."""
    st, lvl, ok = it['status'], it.get('level'), it.get('check') == 'passing'
    m = {'decided': True, 'built': st in ('live', 'held'), 'live': st == 'live', 'reviewed': False,
         'checked': st == 'live' and ok and lvl in ('path', 'function'), 'proved': st == 'live' and ok and lvl == 'function'}
    return m


def phase_of(it):
    return 'p1' if it['status'] == 'live' else ('p2' if it['status'] == 'held' else 'p3')


def config(g):
    its = [i for i in ITEMS if i['owner16'] == g and i['status'] != 'proposed']   # proposed items wait for Jason's definition
    met = sum(sum(crit_met(i).values()) for i in its)
    total = len(its) * len(CRIT)   # weights 1 by default
    pct = met / total * 100 if total else 0.0
    return dict(items=its, met=met, total=total, pct=pct, stage=stage_of(its), out=[i for i in ITEMS if i['owner16'] == g and i['status'] == 'proposed'])


def stage_of(its, signed_off=True):
    """The stage comes from the process state, not from % bands."""
    if not signed_off:
        return 'Planning'
    cm = [crit_met(i) for i in its]
    if any(not c['built'] for c in cm):
        return 'Phased Development'
    if any(not (c['checked'] and c['reviewed']) for c in cm):
        return 'Audit & Review'
    if any(not (c['live'] and c['proved']) for c in cm):
        return 'Implementation'
    return 'Monitoring'


BANDS5 = [(0, 25, 'Planning'), (25, 50, 'Phased Development'), (50, 75, 'Audit & Review'), (75, 100, 'Implementation'), (100, 101, 'Monitoring')]   # reading (b), des


def band5(p):
    return next(w for a, b, w in BANDS5 if a <= p < b)


SK = {'Planning': 'pl', 'Phased Development': 'pd', 'Audit & Review': 'ar', 'Implementation': 'im', 'Monitoring': 'mo'}


def dial2(p):
    x0, y0 = _pt(0, 38)
    x1, y1 = _pt(p / 100, 38)
    track = '<path class="dg-track" d="M14 58 A46 46 0 0 1 106 58"/>'
    return (f'<svg class="dg-dial" viewBox="0 0 120 66" aria-hidden="true">{track}<path class="dg-fill" data-len="{p:.1f}" d="M14 58 A46 46 0 0 1 106 58" pathLength="100" stroke-dasharray="0 100"/>'
            f'<line class="dg-needle" x1="60" y1="58" x2="{x0:.1f}" y2="{y0:.1f}" data-now="{x0:.1f},{y0:.1f}" data-prev="{x1:.1f},{y1:.1f}"/><circle class="dg-hub" cx="60" cy="58" r="4"/></svg>')


def gauge(g, side):
    c = config(g)
    st = c['stage']
    bw = band5(round(c['pct']))
    return (f'<div class="dg-gw"><button type="button" class="dg-g" data-open-panel="p-dev-{slug(g)}" data-gauge="{E(g)}" data-prev-pct="{c["pct"]:.0f}" data-prev-stage="{E(st)}" data-prev-k="{SK[st]}" '
            f'data-prev-band="{E(bw)}" data-prev-bk="{SK[bw]}" '
            f'aria-label="{E(g)}: Planning, 0%. Configuration drafted: {len(c["items"])} items, {c["total"]} criteria, awaiting sign-off. Open its configuration">'
            f'<span class="dg-name">{E(g)}</span>{dial2(c["pct"])}<span class="dg-word s-pl" data-now="Planning">Planning</span>'
            f'<span class="dg-pct" data-now="0%">0%</span><span class="dg-of" data-now="configuration drafted: {len(c["items"])} items, {c["total"]} criteria, awaiting sign-off">'
            f'configuration drafted: {len(c["items"])} items, {c["total"]} criteria, awaiting sign-off</span></button>'
            f'<button type="button" class="dg-cfg" data-open-panel="p-dev-{slug(g)}" data-dev-open-tab="conf" aria-label="Configure {E(g)}">Configure</button></div>')


def rows(heading='h2'):
    q = [i for i in ITEMS if i.get('pending')]
    return (f'<section class="dg" aria-label="Development"><div class="dg-head"><{heading} class="dg-h">Development</{heading}>'
            f'<span class="dg-draft">configurations drafted, awaiting Jason\'s sign-off</span>'
            f'<button type="button" class="dg-pv" data-dev-preview aria-pressed="false">Preview with the draft configuration</button>'
            f'<span class="dg-rd" role="group" aria-label="The word under the needle"><span class="dg-rdl">The word under the needle:</span>'
            f'<button type="button" data-dev-reading="stage" aria-pressed="true">(a) stage of the process</button><button type="button" data-dev-reading="band" aria-pressed="false">(b) % band</button></span>'
            f'<button type="button" class="dg-q" data-open-panel="p-dev-questions">Questions: pending Jason <b>{len(q)}</b></button></div>'
            f'<p class="dg-sub">Each gauge starts at 0 in Planning and is configured to say what 100 means (Jason, 2026-11-22). The word under the needle is its stage: '
            f'{" → ".join(STAGES)}. One needle: criteria met over criteria configured; the word is a label, never a second needle. '
            f'Reading the five words as process stages is our inference from Jason\'s band answer, so both readings are shown: (a) the stage of the process, '
            f'(b) % bands (0–25 Planning, 25–50 Phased Development, 50–75 Audit & Review, 75–99 Implementation, 100 Monitoring; proposed).</p>'
            f'<p class="dg-rule"><b>Complete needs a path or function check; a page that only renders isn\'t complete.</b> The preview fills each item\'s criteria from the inventory: '
            f'decided, built and live from its status; checked from a path or function check; proved from a function check; reviewed is never met until a review is recorded.</p>'
            f'<h3 class="dg-row">Front end <span>6</span></h3><div class="dg-grid">{"".join(gauge(g, "front") for g in FRONT)}</div>'
            f'<h3 class="dg-row">Back end <span>10</span> <small>the admin\'s modules, as manifests.php has them</small></h3><div class="dg-grid">{"".join(gauge(g, "back") for g in BACK)}</div></section>')


def _citem(it):
    faces = []
    for f in it.get('other_face') or []:
        o = _face(f)
        if o:
            faces.append(f'<a href="#" class="dg-face" data-open-panel="p-dev-{slug(o["owner16"])}" data-dev-item="{o["id"]}">Other face: {E(o["name"])} · {E(o["owner16"])} ›</a>')
    m = crit_met(it)
    cr = ''.join(f'<i{" class=ok" if m[k] else ""}>{k}</i>' for k, w in CRIT)
    lvl = f' · check level: {E(it.get("level", "none"))}' if it['status'] == 'live' else ''
    pend = f'<span class="dg-pend">{E(it["pending"])}</span>' if it.get('pending') else ''
    return (f'<li class="dg-it" id="{it["id"]}"><span class="dg-in">{E(it["name"])}</span><span class="dg-crs">{cr}</span>'
            f'<span class="dg-src">{E(it["source"])}{lvl} · weight 1</span>{pend}' + ''.join(faces) + '</li>')


def panel(g):
    c = config(g)
    pid = slug(g)
    conf = ''
    prog = ''
    for k, w in PHASES:
        its = [i for i in c['items'] if phase_of(i) == k]
        if not its:
            continue
        conf += f'<h3 class="dg-gh">{E(w)} <b>{len(its)} items</b></h3><ul class="dg-list">{"".join(_citem(i) for i in its)}</ul>'
        met = sum(sum(crit_met(i).values()) for i in its)
        tot = len(its) * len(CRIT)
        per = ''.join(f'<li>{E(cw)}: {sum(1 for i in its if crit_met(i)[ck])} of {len(its)}</li>' for ck, cw in CRIT)
        prog += (f'<h3 class="dg-gh">{E(w)}</h3><div class="dg-bar" role="img" aria-label="{met} of {tot} criteria met"><i class="dg-s g" style="width:{met / tot * 100:.1f}%"></i></div>'
                 f'<p class="dg-sum">{met} of {tot} criteria met ({met / tot * 100:.0f}%)</p><ul class="dg-plist">{per}</ul>')
    if c['out']:
        conf += (f'<h3 class="dg-gh">Out of scope until Jason defines them <b>{len(c["out"])}</b></h3><ul class="dg-list">'
                 + ''.join(f'<li class="dg-it" id="{i["id"]}"><span class="dg-in">{E(i["name"])}</span><span class="dg-src">{E(i["source"])}</span></li>' for i in c['out']) + '</ul>')
    tabs = ''.join(f'<button type="button" role="tab" class="dg-tab" id="t-{pid}-{t}" aria-controls="tp-{pid}-{t}" aria-selected="{"true" if t == "conf" else "false"}" data-dev-tab="{t}">{w}</button>'
                   for t, w in (('conf', 'Configuration'), ('prog', 'Progress'), ('mon', 'Monitoring')))
    return (f'<aside class="panel" id="p-dev-{pid}" hidden role="dialog" aria-label="{E(g)}: its configuration"><button class="x" aria-label="Close">×</button>'
            f'<div class="crumb">Development · {"front end" if g in FRONT else "back end"}</div><h2 class="h2">{E(g)}</h2>'
            f'<div class="dg-sum"><b>Planning · 0%</b>: configuration drafted, {len(c["items"])} items, {c["total"]} criteria, awaiting sign-off. '
            f'With the draft signed off: <b>{c["pct"]:.0f}%</b>, {E(c["stage"])} ({c["met"]} of {c["total"]} criteria met).</div>'
            f'<div class="dg-tabs" role="tablist" aria-label="{E(g)}">{tabs}</div>'
            f'<div class="dg-tp" role="tabpanel" id="tp-{pid}-conf" aria-labelledby="t-{pid}-conf"><p class="dg-sub">✓ met · not met. Each item\'s definition of done: {" · ".join(w.split(" (")[0].lower() for _, w in CRIT)}. Phases and weights (1 each) are drafted from the inventory.</p>'
            f'<div class="btns"><button type="button" class="btn f" disabled title="Drawn: inert in the mockup">Sign off (Jason)</button></div>{conf}</div>'
            f'<div class="dg-tp" role="tabpanel" id="tp-{pid}-prog" aria-labelledby="t-{pid}-prog" hidden><p class="dg-sub">Criteria met, by phase, if the draft were signed off.</p>{prog}</div>'
            f'<div class="dg-tp" role="tabpanel" id="tp-{pid}-mon" aria-labelledby="t-{pid}-mon" hidden><p class="dg-sub">Once at 100, every criterion is checked again on its own clock; a regression drops the needle, moves the stage back with its reason, and is logged here.</p>'
            f'<p class="nohist">No history yet</p><p class="dg-sub">Sign-off: none yet. Jason\'s sign-off of the scope is written to the Control Board\'s logbook with its date. '
            f'A scope change after sign-off needs his OK again and shows on the gauge ("scope changed: +3 items, awaiting OK"), so the denominator never moves quietly.</p></div>'
            f'<div class="btns navb"><button class="btn back" data-dev-back>← Back</button></div></aside>')


def _qitem(it):
    return (f'<li class="dg-it"><span class="dg-in">{E(it["name"])}</span><span class="dg-pend">Recommended: {E(it["owner16"])} · pending Jason ("Needs changes", 2026-11-22; no detail yet)</span>'
            f'<span class="dg-chk">{E(it.get("why", ""))}</span><a href="#" class="dg-face" data-open-panel="p-dev-{slug(it["owner16"])}" data-dev-item="{it["id"]}">Open it on {E(it["owner16"])} ›</a></li>')


CSS += r"""
.dg-pv{border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.2rem .7rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.dg-pv[aria-pressed="true"]{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}
.dg-track{fill:none;stroke:#E6DDC8;stroke-width:9}.dg-fill{fill:none;stroke:var(--sage);stroke-width:9;transition:stroke-dasharray 150ms ease-out}.dg-needle{stroke:var(--ink)}
.dg-word.s-pl{background:#7A6A52}.dg-word.s-pd{background:var(--amber)}.dg-word.s-ar{background:var(--copper)}.dg-word.s-im{background:#3B5A6B}.dg-word.s-mo{background:#3d6b52}
.dg-of{text-align:center;line-height:1.25}.dg-tabs{display:flex;gap:.3rem;margin:.6rem 0 .3rem;flex-wrap:wrap}
.dg-tab{border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.2rem .7rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.dg-tab[aria-selected="true"]{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}
.dg-crs{display:flex;flex-wrap:wrap;gap:.2rem}.dg-c{font-size:11px;border:1px solid var(--card-edge);border-radius:999px;padding:0 .4rem;color:var(--meta);background:var(--sec)}
.dg-gw{display:flex;flex-direction:column;gap:3px;min-width:0}.dg-gw .dg-g{flex:1}.dg-cfg{border:1px solid var(--field-edge);background:var(--card);border-radius:.4rem;padding:.15rem .5rem;font:inherit;font-size:12px;cursor:pointer;color:var(--brown)}
.dg-rd{display:inline-flex;flex-wrap:wrap;gap:.25rem;align-items:center}.dg-rdl{font-size:12px;color:var(--meta)}.dg-rd button{border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.15rem .6rem;font:inherit;font-size:12px;cursor:pointer;color:var(--ink)}
.dg-rd button[aria-pressed="true"]{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}
.dg-c.ok{border-color:#3d6b52;color:#3d6b52;background:#EEF4EF}.dg-plist{margin:.1rem 0 .4rem 1rem;padding:0;font-size:12.5px}
"""

JS += r"""<script>
(function(){
function tab(b){var pid=b.id.replace(/^t-/,'').replace(/-(conf|prog|mon)$/,''),t=b.getAttribute('data-dev-tab');['conf','prog','mon'].forEach(function(k){var x=document.getElementById('t-'+pid+'-'+k),p=document.getElementById('tp-'+pid+'-'+k);if(x)x.setAttribute('aria-selected',k===t?'true':'false');if(p)p.hidden=k!==t;});}
document.addEventListener('click',function(e){var b=e.target.closest('[data-dev-tab]');if(b){tab(b);return;}
 var a=e.target.closest('[data-dev-item]');if(a){var id=a.getAttribute('data-dev-item');setTimeout(function(){var it=document.getElementById(id),tp=it&&it.closest('.dg-tp');if(tp&&tp.hidden){var t=document.getElementById(tp.id.replace(/^tp-/,'t-'));if(t)tab(t);it.scrollIntoView({block:'center'});}},10);}
 var v=e.target.closest('[data-dev-preview]');if(v){var on=v.getAttribute('aria-pressed')!=='true';v.setAttribute('aria-pressed',on?'true':'false');
  document.querySelectorAll('.dg-g').forEach(function(g){var n=g.querySelector('.dg-needle'),w=g.querySelector('.dg-word'),pc=g.querySelector('.dg-pct'),f=g.querySelector('.dg-fill');
   var xy=(on?n.getAttribute('data-prev'):n.getAttribute('data-now')).split(',');n.setAttribute('x2',xy[0]);n.setAttribute('y2',xy[1]);
   f.setAttribute('stroke-dasharray',on?(f.getAttribute('data-len')+' 100'):'0 100');
   w.textContent=on?g.getAttribute('data-prev-stage'):w.getAttribute('data-now');w.className='dg-word s-'+(on?g.getAttribute('data-prev-k'):'pl');
   pc.textContent=on?g.getAttribute('data-prev-pct')+'%':pc.getAttribute('data-now');
   var of=g.querySelector('.dg-of');of.textContent=on?'if the draft were signed off':of.getAttribute('data-now');});word();}
 var r=e.target.closest('[data-dev-reading]');if(r){document.querySelectorAll('[data-dev-reading]').forEach(function(x){x.setAttribute('aria-pressed',x===r?'true':'false');});word();}
 var o=e.target.closest('[data-dev-open-tab]');if(o){setTimeout(function(){var t=document.getElementById('t-'+o.getAttribute('data-open-panel').replace(/^p-dev-/,'')+'-'+o.getAttribute('data-dev-open-tab'));if(t)tab(t);},10);}
 var gg=e.target.closest('.dg-g');if(gg){setTimeout(function(){var t=document.getElementById('t-'+gg.getAttribute('data-open-panel').replace(/^p-dev-/,'')+'-conf');if(t)tab(t);},10);}});
function word(){var on=(document.querySelector('[data-dev-preview]')||{}).getAttribute&&document.querySelector('[data-dev-preview]').getAttribute('aria-pressed')==='true';
 var band=(document.querySelector('[data-dev-reading][aria-pressed="true"]')||{}).getAttribute&&document.querySelector('[data-dev-reading][aria-pressed="true"]').getAttribute('data-dev-reading')==='band';
 document.querySelectorAll('.dg-g').forEach(function(g){var w=g.querySelector('.dg-word');if(!on){w.textContent='Planning';w.className='dg-word s-pl';return;}
  w.textContent=band?g.getAttribute('data-prev-band'):g.getAttribute('data-prev-stage');w.className='dg-word s-'+(band?g.getAttribute('data-prev-bk'):g.getAttribute('data-prev-k'));});}
})();
</script>"""


def page(base_css):
    q = sum(1 for i in ITEMS if i.get('pending'))
    body = (f'<header class="bhead"><b>System Control Board</b><span class="chip">Development gauges · design mockup · step 3 · not site code</span></header>'
            f'<main class="dg-main">{rows("h1")}<p class="dg-sub">From <code>step3/sections/inventory.json</code> ({len(ITEMS)} items, each owned by one of the 16 gauges; {q} of them recommended, pending Jason). '
            f'Live means on the live site (mirror Sync e229587 or a live release record). The lifecycle: sections/gauge-lifecycle.md.</p></main>'
            f'<div class="scrim"></div>{panels()}{JS}{JS_PAGE}')
    return (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            f'<title>Development Gauges</title><meta name="description" content="Step 3 mockup: the 16 development gauges, each starting at 0 in Planning, configured to say what 100 means.">'
            f'<style>{base_css}{CSS}.dg-main{{padding:1rem 16px 3rem;max-width:1280px;margin:0 auto}}@media (max-width:767px){{.dg-main{{padding:.6rem 3px 3rem}}}}</style></head><body>{body}</body></html>')
