#!/usr/bin/env python3
"""The site audit's open findings, in one list (decision 0145 Phase 4).

    python3 tools/page-audit/findings.py [--run-url URL]

Reads what the checks wrote (results/paths.json, results/rendered.json, results/latest.json), the accepted exceptions
(exceptions.json) and the proposals (proposals.md), and writes site-audit/results/findings.json: one finding per thing
to fix, each with a stable id, the rule it breaks, the page, who saw it, the paths it gets in the way of, its screenshot
(the run that has it) and its proposal once the review has written one. Ranked by how many paths it blocks.

The site's Pulse "Site health" case reads this file from GitHub (app/lib/SiteHealth.php), so it holds only what's safe
in a public repository: pages, rules and what went wrong, never anything about a person, no money, no secrets.
The review (.claude/skills/page-review) reads it too, with page-map.json, to write the proposals.
"""
import argparse, datetime, hashlib, json, os, re

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))
RES = os.path.join(DOCS, 'site-audit', 'results')
REPO = 'https://github.com/degraffics/traversence'

STANDARD = {   # rendered.mjs's standards: [rule, what it says]
    'status': ['J01', 'The page doesn\'t open'], 'console': ['V14', 'Script errors on the page'], 'requests': ['V14', 'Its own requests fail'],
    'scroll': ['V01', 'Sideways scroll at 390px'], 'edges': ['V01', 'Cards not 3px from the screen edge at 390px'], 'dialogs': ['R02', 'A browser dialog opens'],
    'alt': ['V15', 'Images without alt text'], 'names': ['V15', 'Buttons or links without a name'],
}
KIND_WORD = {'dead_end': 'Dead end', 'steps': 'Too many steps', 'accuracy': 'Not accurate', 'surprise': 'A surprise', 'clarity': 'Not clear', 'slow': 'Slow'}
BLOCKING = {'dead_end', 'steps', 'stuck'}
CODE_BLOCKING = {'R01', 'R02', 'R03', 'R04', 'R06', 'R08'}   # the zip check blocks these (rules.md)


def load(name, default):
    try:
        return json.load(open(os.path.join(RES, name), encoding='utf-8'))
    except (OSError, ValueError):
        return default


def h(*parts, n=10):
    return hashlib.sha1('|'.join(str(p) for p in parts).encode('utf-8')).hexdigest()[:n]


def norm(s):
    """What a problem says, without the numbers that change run to run (4.2 s, 3 results)."""
    return re.sub(r'\d+(\.\d+)?', '#', str(s or '')).strip().lower()


def gh_anchor(heading):
    a = heading.strip().lower()
    a = re.sub(r'[^\w\- ]', '', a, flags=re.UNICODE)
    return a.replace(' ', '-')


def proposals():
    """[{'id': 'P4', 'title', 'anchor', 'verdict', 'decided', 'rules': {...}, 'findings': {...}}] from proposals.md."""
    try:
        src = open(os.path.join(DOCS, 'site-audit', 'proposals.md'), encoding='utf-8').read()
    except OSError:
        return []
    out = []
    parts = re.split(r'^### ', src, flags=re.M)
    for part in parts[1:]:
        head, _, body = part.partition('\n')
        m = re.match(r'(P\d+)\.\s*(.+)$', head.strip())
        if not m:
            continue
        body = body.split('\n## ')[0]
        v = re.search(r'\(verdict:\s*([a-z ,/]+)', head) or re.search(r'\*\*Verdict:?\*\*:?\s*([a-z]+)', body)
        out.append({'id': m.group(1), 'title': m.group(2).strip(), 'anchor': gh_anchor(head), 'verdict': (v.group(1).strip() if v else ''),
                    'decided': bool(re.search(r'\*\*Decided', body)), 'rules': set(re.findall(r'\b([RVTDJG]\d\d)\b', head + body)),
                    'findings': set(re.findall(r'\b(f-[0-9a-f]{10})\b', body))})
    return out


def build(run_url=''):
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    paths = load('paths.json', {})
    rendered = load('rendered.json', {})
    code = load('latest.json', {})
    before = {f['id']: f for f in load('findings.json', {}).get('findings', [])}
    try:
        accepted = json.load(open(os.path.join(DOCS, 'site-audit', 'exceptions.json'), encoding='utf-8'))
    except (OSError, ValueError):
        accepted = []
    found = {}

    def add(fid, **f):
        cur = found.get(fid)
        if cur:
            cur['who'] = sorted(set(cur['who']) | set(f.get('who', [])))
            cur['paths'] = sorted(set(cur['paths']) | set(f.get('paths', [])))
            cur['_blocking'] |= f.get('_blocking', set())
            cur['_ev'].extend(f.get('_ev', []))
            return
        found[fid] = {'id': fid, 'who': [], 'paths': [], 'page': '', 'shot_url': '', '_blocking': set(), '_ev': [], **f}

    # the paths: every problem on the way, and the paths a runner couldn't follow
    p_run = paths.get('run_url') or run_url
    page_paths = {}
    for r in paths.get('results', []):
        if r.get('status') in ('waits', 'skipped'):
            continue
        pid = r['id']
        for pg in r.get('pages', []):
            page_paths.setdefault(pg.split('#')[0], set()).add(pid)
        if r.get('stuck'):
            last = (r.get('pages') or [''])[-1]
            add('f-' + h('stuck', pid), kind='path', rule='J01', title=f'A path couldn\'t be followed: {r["title"]}', detail=r['stuck'] + '.', page=last,
                who=[r['who']], paths=[pid], _blocking={pid}, severity='alert', shot_url=(p_run + '#artifacts') if p_run else '', _ev=[r['stuck']])
        for p in r.get('problems', []):
            what = p['what']
            fid = 'f-' + h('path', p['rule'], p['page'], norm(what))
            add(fid, kind='path' if p['kind'] != 'slow' else 'cost', rule=p['rule'], title=f'{KIND_WORD.get(p["kind"], p["kind"])}: {what}'[:160],
                detail=f'On the path "{r["title"]}".' if p['kind'] != 'slow' else 'Pages that take this long lose people; look at its queries and cache (decisions/0145 §1).',
                page=p['page'], who=[r['who']], paths=[pid], _blocking={pid} if (p['kind'] in BLOCKING or r.get('status') == 'failed' and p['kind'] == 'dead_end') else set(),
                severity='alert' if p['kind'] in BLOCKING else 'update', shot_url=(p_run + '#artifacts') if p_run and r.get('shots') else '', _ev=[what])

    # the browser checks: a finding per page and standard, whoever saw it
    r_run = rendered.get('run_url') or run_url
    for r in rendered.get('results', []):
        for k, d in r.get('why', []):
            rule, word = STANDARD.get(k, ['V14', k])
            url = r.get('url', '')
            fid = 'f-' + h('page', k, url)
            through = sorted(page_paths.get(url.split('#')[0], set()))
            add(fid, kind='page', rule=rule, title=f'{word}: {url}'[:160], detail=(d or '')[:400], page=url if url.startswith('/') else '',
                who=[r.get('who', '')], paths=through, _blocking=set(through) if k == 'status' else set(), severity='alert' if k == 'status' else 'update',
                shot_url=(r_run + '#artifacts') if r_run else '', _ev=[k, d or ''])

    # the code checks: a finding per rule, with examples (each rule has its decided proposal)
    exc = {(e.get('rule'), e.get('file')) for e in accepted if isinstance(e, dict)}
    by_rule = {}
    for f in code.get('findings', []):
        if (f.get('rule'), f.get('file')) in exc:
            continue
        by_rule.setdefault(f['rule'], []).append(f)
    for rule, fs in by_rule.items():
        eg = '; '.join(f'{f["file"]}:{f["line"]} {f["msg"]}' for f in fs[:3])
        files = sorted({f['file'] for f in fs})
        add('f-' + h('code', rule), kind='code', rule=rule, title=f'{len(fs)} place{"s" if len(fs) != 1 else ""} in the code break {rule}'[:160],
            detail=f'In {len(files)} file{"s" if len(files) != 1 else ""}. For example: {eg}'[:600], severity='alert' if rule in CODE_BLOCKING else 'update',
            _ev=[f'{f["file"]}:{f["msg"]}' for f in fs])

    # cost and effectiveness: what the health report's totals show
    hl = paths.get('health') or {}
    for j in (hl.get('jobs') or []):
        add('f-' + h('job', j['job']), kind='cost', rule='D03', title=f'A scheduled job isn\'t running cleanly: {j["job"]}',
            detail=(f'{j["fails_today"]} failed today. ' if j.get('fails_today') else '') + (f'Last ran {j["last"]} (site time).' if j.get('last') else 'It has never run.'),
            severity='update', _ev=[j['job'], str(j.get('fails_today')), str(j.get('last'))])
    c = hl.get('cache') or {}
    if c.get('entries') and c.get('expired_share', 0) >= 30:
        add('f-' + h('cache', 'expired'), kind='cost', rule='D05', title=f'The page cache holds {c["expired_share"]}% expired entries',
            detail='Expired entries are kept but never served: clearing them, or keeping the busiest longer, makes pages faster for nothing. A hit rate in the health report would show which.',
            severity='update', _ev=[str(c['expired_share'] // 10)])
    for s in (hl.get('status') or []):
        add('f-' + h('status', s['name']), kind='cost', rule='D03', title=f'An admin status check isn\'t OK: {s["name"]}', detail=f'It says: {s["state"]}.', severity='update', _ev=[s['state']])
    if (hl.get('ai') or {}).get('over_80_pct_of_cap'):
        add('f-' + h('ai', 'cap'), kind='cost', rule='D05', title='AI this month is over 80% of its cap', detail='See Services & usage in the admin for which feature spends it.', severity='update', _ev=['ai'])

    # the proposals the review wrote, by the finding or (for the code checks) the rule
    props = proposals()
    out = []
    for f in found.values():
        p = next((x for x in props if f['id'] in x['findings']), None) or (next((x for x in props if f['rule'] in x['rules']), None) if f['kind'] == 'code' else None)
        old = before.get(f['id'], {})
        sig = h(*sorted(set(map(str, f.pop('_ev')))), n=16)
        blocking = f.pop('_blocking')
        out.append({**f, 'sig': sig, 'blocks': len(blocking), 'proposal': p['id'] if p else '', 'proposal_url': f'{REPO}/blob/main/site-audit/proposals.md#{p["anchor"]}' if p else '',
                    'verdict': (p['verdict'] if p else ''), 'decided': bool(p and p['decided']), 'first_seen': old.get('first_seen') or now, 'last_seen': now})
    rank = {'critical': 0, 'alert': 1, 'update': 2}
    out.sort(key=lambda f: (-f['blocks'], rank.get(f['severity'], 2), -len(f['paths']), f['kind'] != 'path', f['first_seen'], f['id']))
    data = {'version': 1, 'generated_at': now, 'run_url': p_run or r_run or run_url, 'site': paths.get('site') or rendered.get('site') or 'https://traversence.com',
            'counts': {'open': len(out), 'blocking': sum(1 for f in out if f['blocks']), 'by_kind': {k: sum(1 for f in out if f['kind'] == k) for k in ('path', 'page', 'code', 'cost')}},
            'note': 'Generated by tools/page-audit/findings.py from the site audit\'s results. Read by the site\'s Pulse "Site health" case and the page-review skill. Don\'t edit by hand.',
            'findings': out}
    os.makedirs(RES, exist_ok=True)
    json.dump(data, open(os.path.join(RES, 'findings.json'), 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    return data


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--run-url', default='')
    a = ap.parse_args()
    d = build(a.run_url)
    print(f'Findings: {d["counts"]["open"]} open, {d["counts"]["blocking"]} in the way of a path ({d["counts"]["by_kind"]}).')


if __name__ == '__main__':
    main()
