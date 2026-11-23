#!/usr/bin/env python3
"""The gauges' configuration and evidence, for the System Control Board (decisions/0171; handoffs/control-board-build-log.md §7).

Writes site-audit/results/gauges.json, a public file the site reads (app/lib/ControlGauges.php, as SiteHealth reads
findings.json): the repo makes the configuration and the evidence, the site works out every stage from it, never stored.

  - The 16 development gauges (step3/sections/gauge-lifecycle.md §3-§6): each gauge's draft configuration (its items from
    step3/sections/inventory.json, each item's criteria met, its phase and weight 1, its checks from the check register),
    unsigned today: Planning at 0 until Jason signs off, with the draft's preview % (§6: Discovery 45% ... System
    Configuration 48%) and its three drill paths (Workflows, Core Services, Registered Services; §4).
  - The 23 runtime gauges' static parts (§7; sections/crossover.md for one measure each): box, title, measure, target or
    null ("target: set by Jason"), unit, setup (probe, target, check, alarm) as §7 states today, paths and Fix it links
    from the association map (step3/association-map.md, 03 §27). No reading: readings are the site's own.
  - W0-W14 as gauges (step3/workflows-page.md §5): W13 a list, W14 not built (Phased Development, 1 of 4).

The numbers are computed by the step-3 mockup generators (step3/mockups/src: devgauges.py, checkreg.py, assoc.py, board.py,
workflows.py), imported here so the file and the mockups Jason approved can't disagree. Those read the repo's results
(site-audit/results/*.json, scorecard.md, rules.md) and the live mirror (manifests.php, Automation.php, Preflight.php) at
/home/user/traverse-3way-sync.

A check with a result file in the one results format (check-register.md "One results format": rows with check, item,
result) counts for an item whose route is its `item`; with none, the register's own state stands, and a check that
doesn't exist yet is "no check yet", counted unmet (gauge-lifecycle.md §4).

  python3 tools/page-audit/gauges.py            write site-audit/results/gauges.json (compact, under 60 KB: the site caches
                                                it) and gauges-items.json (each gauge's detail, read when its drawer opens)
  python3 tools/page-audit/gauges.py --check    exit 1 if the committed file is stale (everything but generated_at)
  python3 tools/page-audit/gauges.py --out F    write elsewhere (tests)
"""
import argparse, glob, json, os, re, sys
from datetime import datetime, timezone

ROOT = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')) + '/'
SRC = ROOT + 'handoffs/control-board-plan/step3/mockups/src'
RESULTS = ROOT + 'site-audit/results/'
OUT = RESULTS + 'gauges.json'
MIRROR = '/home/user/traverse-3way-sync/'   # board.py's own path for the live mirror

sys.path.insert(0, SRC)


def _load():
    if not os.path.isdir(MIRROR + 'sync-app'):
        sys.exit(f'gauges.py: the live mirror isn\'t at {MIRROR} (board.py reads manifests.php, Automation.php and Preflight.php there). '
                 'Clone degraffics/traverse-3way-sync there first (CLAUDE.md: shallow and sparse).')
    import board, devgauges, assoc, checkreg, workflows
    workflows.init(board)
    return board, devgauges, assoc, checkreg, workflows


B, D, A, C, W = _load()

STAGES = D.STAGES                                     # Jason's five words (gauge-lifecycle.md §2)
CRIT = [k for k, _ in D.CRIT]                         # decided, built, checked, reviewed, live, proved (§4)
ADMIN = 'https://traversence.com'

# ------------------------------------------------------------------ where the paths land on the site
# A workflow's own page on the board (Automation::WORKFLOWS; operations-console.php: /admin/config/control/wf-W#).
WF_HREF = '/admin/config/control/wf-{}'
# A Registered Service's card in the Service register (operations-console.php: id="svc-<key>"; decisions/0173).
SVC_HREF = '/admin/config/register#svc-{}'
# The board's module ids (board.MODULES) that aren't service-defs keys: Google Places and Google Cloud are 'google'.
MOD2SVC = {'places': 'google', 'googlecloud': 'google'}
# Core Services (gauge-lifecycle.md §4; assoc.CORE_PARTS): each part's page on the site. A service's part opens its card;
# the rest their own pages (manifests.php's config sections; ControlRegister::WORKERS's process pages).
CORE_HREF = {'db': SVC_HREF.format('bluehost'), 'jobs': '/admin/config/scheduler', 'staging': '/admin/config/staging',
             'sync': SVC_HREF.format('github'), 'workers': SVC_HREF.format('railway'), 'video': '/admin/config/control/wk-video-worker',
             'dispatcher': '/admin/config/communications'}
# Each core part's processes on the board, for its live state (ControlRegister::processes: site:db, site:sched, wk:<worker>).
CORE_PROCS = {'db': ['site:db'], 'jobs': ['site:sched'], 'workers': ['wk:crawler', 'wk:w0-indexer'], 'video': ['wk:video-worker'],
              'dispatcher': ['site:pulse'], 'staging': [], 'sync': []}
NOT_MAPPED = 'association not mapped'


def mod_name(m):
    return next((x['label'] for x in B.MODULES if x['id'] == m), m)


def svc_of(m):
    return MOD2SVC.get(m, m)


def fix_links(mods):
    """Fix it: <module> (03 §27: every Fix it on a card comes from the association map). A part with no card in the Service
    register (the dispatcher, the video worker) opens its own page."""
    out = []
    for m in mods:
        if m in CORE_HREF and m not in ('db', 'jobs', 'staging', 'sync', 'workers'):
            out.append(['Fix it: ' + mod_name(m), CORE_HREF[m]])
        else:
            out.append(['Fix it: ' + mod_name(m), SVC_HREF.format(svc_of(m))])
    return out


def need_sources(key):
    """A need's Registered Services in order (Preflight::NEEDS, as board.py reads it): its 'svc' sources."""
    n = next((x for x in B.NEEDS if x['key'] == key), None)
    if not n:
        return None, []
    return n, [v for (k, v, _) in n['srcs'] if k == 'svc']


def registered(needs):
    seen, out, chain = set(), [], []
    for key in needs:
        n, svcs = need_sources(key)
        if not n:
            continue
        chain.append({'need': key, 'label': n['label'], 'sources': [[s, mod_name(B.SVC2MOD.get(s, s))] for s in svcs], 'fallback': n.get('fallback', '')})
        for s in svcs:
            if s not in seen:
                seen.add(s)
                out.append([s, mod_name(B.SVC2MOD.get(s, s)), None, None, SVC_HREF.format(s)])
    return out, chain


def core_list(keys):
    return [[k, A.CORE_PARTS[k][0], None, None, CORE_HREF[k]] for k in keys]


def wf_list(wfs):
    return [[w, w + ' ' + W.wname(w), None, None, WF_HREF.format(w)] for w in wfs]


# ------------------------------------------------------------------ check results in the one results format
def result_rows():   # noqa: the worst-first order is WORST, set below before the call
    """Rows from site-audit/results/*.json in the one results format (check-register.md): {check, item, result, at, evidence}.
    paths.json, rendered.json and the rest have no 'check' field, so they aren't read as such."""
    rows = {}
    for f in sorted(glob.glob(RESULTS + '*.json')):
        if f.endswith('/gauges.json'):
            continue
        try:
            d = json.load(open(f))
        except Exception:
            continue
        for r in (d.get('results') or []) if isinstance(d, dict) else []:
            if isinstance(r, dict) and r.get('check') and 'item' in r and r.get('result') in ('pass', 'fail', 'partial', 'no check'):
                # one item can have several rows (each account, phone and desktop): the worst stands, with its finding
                k = (r['check'], str(r['item']))
                if k not in rows or WORST.index(r['result']) < WORST.index(rows[k]['result']):
                    rows[k] = {'result': r['result'], 'at': r.get('at'), 'file': os.path.basename(f), 'finding': r.get('finding')}
    return rows


WORST = ['fail', 'partial', 'no check', 'pass']
ROWS = result_rows()

# Jason's approvals on the board (GaugeTasks), as the daily health report wrote them: gauge => when (scope) or the value (target)
def _approvals():
    try:
        d = json.load(open(RESULTS + 'gauge-approvals.json'))
    except Exception:
        return {}, {}
    sc, tg = {}, {}
    for a in d.get('approvals') or []:
        if a.get('kind') == 'scope':
            sc[a['gauge']] = a.get('at_utc')
        elif a.get('kind') == 'target' and a.get('value'):
            tg[a['gauge']] = a['value']
    return sc, tg


APPROVED_SCOPE, APPROVED_TARGET = _approvals()


PAGES_MD = open(ROOT + 'site-audit/pages.md').read().splitlines()
FINDINGS = json.load(open(RESULTS + 'findings.json')).get('findings', [])
FINDING_IDS = {f['id'] for f in FINDINGS}   # only a finding Site health has: any other id would open nothing


def route_of(it):
    """The page an item is, so its name opens it (the gauge panel, handoffs/gauge-panel.md): its admin section, a path at the
    end of its name, or the first path in its row of site-audit/pages.md (the page register). None: its source is the link."""
    if it.get('manifest'):
        return '/admin/' + it['manifest']
    m = re.search(r'\((/[^)\s]*)\)$', it['name'])
    if m:
        return m.group(1)
    m = re.search(r'site-audit/pages\.md:(\d+)', it.get('source', ''))
    if m and int(m.group(1)) <= len(PAGES_MD):
        p = re.search(r'`(/[^`\s]*)`', PAGES_MD[int(m.group(1)) - 1])
        if p:
            r = p.group(1)
            return r.split('?')[0] if re.search(r'=$', r) else r   # "/listing/view.php?id=" names a kind of page, not one
    return None


def finding_for(route, cid):
    """The open Site health finding a failing check on this page is, if the audit wrote one (findings.json), else None."""
    if not route:
        return None
    for f in FINDINGS:
        pg = (f.get('page') or '').split('?')[0]
        rule = f.get('rule') or ''
        # the check that writes a finding of this rule (findings.py): J01-J03 the paths, V06 a layout shift (LOAD's CLS),
        # the other V rules the rendered check, D the board's own status checks
        owner = 'PATHS' if rule.startswith('J') else ('LOAD' if rule == 'V06' else ('RENDER' if rule.startswith('V') else ('BOARD' if rule.startswith('D') else '')))
        if pg and pg == route.split('?')[0] and owner == cid:
            return f['id']
    return None


def item_checks(it):
    """The register's checks for an item (devgauges.item_checks), with a result file's row for its route where one exists."""
    route = route_of(it)
    out = []
    for cid, r, latest in D.item_checks(it):
        row = ROWS.get((cid, route)) if route else None
        if row:
            r, latest = row['result'], f'{row["result"]} ({row["file"]}, {row.get("at") or "no time"})'
        out.append([cid, r, latest])
    return out


def crit_met(it, checks):
    """devgauges.crit_met, with 'checked' read from the checks above (every applicable check passing; §4), and 'reviewed'
    as Jason's process has it (gauge-lifecycle §8 decided 2's note, 2026-11-23): live with no open issue, no sign-off."""
    m = D.crit_met(it)
    m['checked'] = it['status'] == 'live' and all(r == 'pass' for _, r, _ in checks)
    m['reviewed'] = it['status'] == 'live' and it['name'] not in D.OPEN and not any(r == 'fail' for _, r, _ in checks)
    return m


def review_line(it, checks=()):
    """Where an item stands on review (gauge-lifecycle §8 decided 2's note, Jason 2026-11-23: "I need to view the issue. then
    press a button to fix it. once fixed it should clear the issue"): an open issue, or none. No sign-off state."""
    if it['status'] != 'live':
        return ''
    if it['name'] in D.OPEN:
        return 'open finding: ' + '; '.join(D.OPEN[it['name']])
    fails = [c for c, r, _ in checks if r == 'fail']
    if fails:
        return 'open issue: ' + ', '.join(fails) + ' fails'
    return 'no open issue'


# ------------------------------------------------------------------ the 16 development gauges
def development():
    out = []
    for g in D.FRONT + D.BACK:
        cfg = D.config(g)
        items = []
        for it in cfg['items']:
            checks = item_checks(it)
            met = crit_met(it, checks)
            # each item's definition of done: the default list (§4), so not repeated per item; an item that drops or adds
            # criteria would carry its own 'criteria' (none does in the draft)
            items.append({'name': it['name'], 'kind': it['kind'], 'status': it['status'], 'phase': D.phase_of(it), 'weight': 1,
                          'met': [k for k in CRIT if met[k]],
                          # a failing check carries its finding's id where Site health has it (the panel opens it; else the check's report)
                          'checks': [[c, r] + ([fnd] if r == 'fail' and (fnd := ((ROWS.get((c, route_of(it))) or {}).get('finding') if (ROWS.get((c, route_of(it))) or {}).get('finding') in FINDING_IDS else None) or finding_for(route_of(it), c)) else []) for c, r, _ in checks],
                          'no_check': [c for c, r, _ in checks if r == 'no check'],
                          'review': review_line(it, checks), 'route': route_of(it), 'source': it['source']})
        total = sum(len(i.get('criteria', CRIT)) * i['weight'] for i in items)
        mt = sum(len(i['met']) * i['weight'] for i in items)
        wfs, cs, rs = D.DEVPATHS[g]
        nochk = []
        for i in items:
            for c in i['no_check']:
                if c not in nochk:
                    nochk.append(c)
        order = ['TOOLS', 'LOAD', 'PHOTOS-SHOWN', 'BRAND', 'PHOTOS', 'RENDER', 'BOARD']   # devgauges.missing_line's order
        nochk.sort(key=lambda x: order.index(x) if x in order else 99)
        out.append({
            'id': D.slug(g), 'title': g, 'side': 'front' if g in D.FRONT else 'back',
            # Planning until Jason approves the scope on the board (gauge-lifecycle §3; GaugeTasks, read from the daily health
            # report's gauge-approvals.json: the session manager's ruling on Q3, 2026-11-23)
            'signed': ('dev:' + D.slug(g)) in APPROVED_SCOPE, 'signed_at': APPROVED_SCOPE.get('dev:' + D.slug(g)),
            'items': len(items), 'criteria': total, 'met': mt, 'preview': round(mt / total * 100, 1) if total else 0.0,
            'no_check': nochk, 'list': items,
            'out_of_scope': [i['name'] for i in cfg['out']],   # Proposed: waiting on Jason's own definition (§4)
            'paths': {'workflows': wf_list([x[0].split(' ', 1)[0] for x in wfs]),
                      'core': core_list([next(k for k, v in D.CORE_L.items() if v == c) for c in cs]),
                      'registered': [[svc_of(m), mod_name(m), None, None, SVC_HREF.format(svc_of(m))] for _, m in rs]},
        })
    return out


# ------------------------------------------------------------------ the 23 runtime gauges
ONE_MEASURE = {r['id']: re.sub(r'^one measure: ', '', r['one_measure']) for r in D.INV['runtime_two_readings']}
# o-signin: crossover.md finds its one reading "acceptable as one pass/fail of the whole path" (kept as the board has it)
ONE_MEASURE.pop('o-signin', None)
# register checks a runtime gauge reads (check-register.md: LOAD's source is "the runtime gauge 'Pages load fast'"; PHOTOS is
# the photos check "Photos load" reads, whose results aren't recorded)
RT_CHECKS = {'o-speed': ['LOAD'], 'o-photos': ['PHOTOS']}


def runtime():
    out = []
    for _, box, objs in B.PROMISES:
        for o in objs:
            su = B.rt_setup(o)
            target_set = su[1][1]
            snap = A.SNAP[o['id']]
            wfs = list(snap['wf'] or {})
            regs, chain = registered(list(snap['needs']) + [n for w, ts in (snap['wf'] or {}).items() for t in ts for n in A.TASK_NEEDS.get(t, [])])
            fx = fix_links(o['fix']) + [[NOT_MAPPED + ': ' + u, None] for u in snap['unmapped']]
            no_check = [c for c in RT_CHECKS.get(o['id'], []) if C.BY[c]['result'] in ('no check', 'not recorded')]
            out.append({
                'id': o['id'], 'box': box, 'title': o['name'],
                'measure': ONE_MEASURE.get(o['id'], o['measure']),
                # a target not set (or only proposed) is null: "target: set by Jason" (board.rt_setup's rule; 03 §27)
                'target': o['target'] if target_set else (APPROVED_TARGET.get('rt:' + o['id']) or None),
                'target_proposed': None if target_set else (None if 'set by Jason' in o['target'] and o['target'].strip() == B.SET else o['target']),
                'unit': '%',
                'setup': {'probe': su[0][1], 'target': su[1][1] or ('rt:' + o['id']) in APPROVED_TARGET, 'check': su[2][1], 'alarm': su[3][1]},
                'setup_why': {'probe': su[0][2], 'target': su[1][2] if not (not su[1][1] and ('rt:' + o['id']) in APPROVED_TARGET) else 'approved on the board', 'check': su[2][2], 'alarm': su[3][2]},
                'needs_probe': B.NEEDS_PROBE.get(o['id']),
                'records': snap['records'],
                'paths': {'workflows': wf_list(wfs), 'tasks': {w: ts for w, ts in (snap['wf'] or {}).items()},
                          'core': core_list(A.CORE[o['id']]), 'registered': regs, 'needs': chain},
                'procs': sorted({'wf:' + w for w in wfs} | {'svc:' + r[0] for r in regs} | {p for k in A.CORE[o['id']] for p in CORE_PROCS[k]}),
                'fix': fx, 'unmapped': snap['unmapped'], 'no_check': no_check,
            })
    return out


# ------------------------------------------------------------------ W0-W14 (workflows-page.md §5)
def workflows():
    out = []
    for w, name, what, where in B.WORKFLOWS:
        m, t, probe = W.MEASURE[w]
        kind = 'list' if w in W.LIST_ONLY else ('not_built' if w == 'W14' else 'gauge')
        row = {'wf': w, 'name': name.replace('\\', ''), 'kind': kind, 'what': what.replace('\\', ''), 'href': WF_HREF.format(w)}
        if kind == 'list':
            row.update({'measure': W.LIST_ONLY[w], 'target': None, 'setup': None, 'setup_why': None, 'probe': None})
        else:
            su = W.setup(w)
            row.update({'measure': m, 'target': t, 'target_approved': 'Jason, 2026-11-22: "Approve as proposed"', 'probe': probe,
                        'setup': {'probe': su[0][1], 'target': su[1][1], 'check': su[2][1], 'alarm': su[3][1]},
                        'setup_why': {'probe': su[0][2], 'target': su[1][2], 'check': su[2][2], 'alarm': su[3][2]}})
        row['tasks'] = list(W.TASKS.get(w, []))   # its tasks (workflows.py TASKS: assoc.WF_TASKS + SystemStart's), for its holds
        row['feeds'] = [[o, n] for o, n, _ in W.feeds(w)]
        row['core'] = core_list([c for c in W.core(w) if c in A.CORE_PARTS])
        regs, chain = registered([n['key'] for n in W.needs_of(w)])
        row['registered'], row['needs'] = regs, chain
        out.append(row)
    return out


CACHE_MAX = 60000   # the site keeps gauges.json in page_cache, whose column size on live isn't known: SiteHealth's ~15 KB is proven


def pack(met, crit):
    """An item's criteria as six characters in CRIT's order: 1 met, 0 unmet, - not one of its criteria (§4)."""
    return ''.join(('1' if k in met else '0') if k in crit else '-' for k in CRIT)


PARTS = {'workflows': {}, 'core': {}, 'registered': {}}   # each part's [name, href] once; the paths name parts by id


def short(rows, kind):
    for r in rows:
        PARTS[kind][r[0]] = [r[1], r[4]]
    return [r[0] for r in rows]   # the state is the site's, read live


def build():
    """Two files: gauges.json, the compact configuration the site keeps an hour (under CACHE_MAX), and gauges-items.json,
    each gauge's detail (its items by name, setup's words, needs and sources), read only when a gauge's drawer opens."""
    reg = {c['id']: {'name': c['name'], 'status': c['status'], 'result': c['result'], 'latest': c['latest'], 'target': c['target'],
                     'evidence': c['evidence'] if c['evidence'].startswith('http') else None} for c in C.CHECKS}
    dev, rt, wfs = development(), runtime(), workflows()
    head = {'version': 2, 'generated_by': 'tools/page-audit/gauges.py'}
    summary = dict(head, note='The gauges\' configuration (handoffs/control-board-plan/step3/sections/gauge-lifecycle.md), compact: the site '
                   'computes every stage from it (app/lib/ControlGauges.php); each gauge\'s detail is in gauges-items.json.',
                   stages=STAGES, criteria=CRIT,
                   counts={'scope': sum(g['items'] for g in dev), 'no_check': sum(1 for g in dev for i in g['list'] if i['no_check'])},   # §4: 236 of 292
                   development=[{'id': g['id'], 'title': g['title'], 'side': g['side'], 'signed': g['signed'], 'signed_at': g['signed_at'],
                                 'm': [[pack(i['met'], i.get('criteria', CRIT)), i['weight'], len(i['no_check'])] for i in g['list']],
                                 'no_check': g['no_check'], 'out': len(g['out_of_scope']),
                                 'paths': {k: short(v, k) for k, v in g['paths'].items()}} for g in dev],
                   runtime=[{k: g[k] for k in ('id', 'box', 'title', 'measure', 'target', 'target_proposed', 'unit', 'setup', 'procs', 'fix', 'no_check', 'needs_probe')}
                            | {'paths': {k: short(g['paths'][k], k) for k in ('workflows', 'core', 'registered')}} for g in rt],
                   workflows=[{k: w[k] for k in ('wf', 'name', 'kind', 'measure', 'target', 'setup', 'tasks')}
                              | {'core': short(w['core'], 'core'), 'registered': short(w['registered'], 'registered')} for w in wfs])
    summary['parts'] = {k: dict(sorted(v.items())) for k, v in PARTS.items()}
    detail = dict(head, note='Each gauge\'s detail for its drawer (ControlGauges::detail): read when the drawer opens.',
                  checks=reg, results_read=sorted({r['file'] for r in ROWS.values()}),
                  development={g['id']: {'list': g['list'], 'out_of_scope': g['out_of_scope']} for g in dev},
                  runtime={g['id']: {'setup_why': g['setup_why'], 'records': g['records'], 'tasks': g['paths']['tasks'], 'needs': g['paths']['needs'],
                                     'unmapped': g['unmapped']} for g in rt},
                  workflows={w['wf']: {k: w.get(k) for k in ('what', 'probe', 'target_approved', 'setup_why', 'feeds', 'needs')} for w in wfs})
    return summary, detail


def body_of(d):
    return json.dumps(d, indent=1, ensure_ascii=False, sort_keys=False) + '\n'


def stale(path, d):
    try:
        old = json.load(open(path))
    except Exception as e:
        return f'can\'t read {path} ({e})'
    old.pop('generated_at', None)
    return None if old == json.loads(body_of(d)) else f'{os.path.basename(path)} is stale: its inputs changed since it was made'


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--check', action='store_true', help='fail if the committed gauges.json or gauges-items.json is stale')
    ap.add_argument('--out', default=OUT, help='gauges.json; gauges-items.json is written beside it')
    a = ap.parse_args()
    items_out = os.path.join(os.path.dirname(a.out), 'gauges-items.json')
    summary, detail = build()
    compact = len(json.dumps(summary, ensure_ascii=False, separators=(',', ':')).encode())
    if compact > CACHE_MAX:
        print(f'gauges.json would be {compact} bytes compact, over the site\'s {CACHE_MAX}: move detail to gauges-items.json')
        return 1
    if a.check:
        bad = [m for m in (stale(a.out, summary), stale(items_out, detail)) if m]
        if bad:
            print('; '.join(bad) + '; run python3 tools/page-audit/gauges.py')
            return 1
        print('gauges.json and gauges-items.json are current')
        return 0
    at = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    open(a.out, 'w').write(body_of({'generated_at': at, **summary}))
    open(items_out, 'w').write(body_of({'generated_at': at, **detail}))
    print(f'wrote {a.out} ({compact} bytes compact): {len(summary["development"])} development, {len(summary["runtime"])} runtime, '
          f'{len(summary["workflows"])} workflows; and {items_out}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
