#!/usr/bin/env python3
"""What the page review reads (decision 0145 §2, Phase 4; .claude/skills/page-review/SKILL.md).

    python3 tools/page-audit/review.py inputs [--mirror ../traverse-3way-sync] [--pages /a,/b] [--top 15] > review-inputs.md
    python3 tools/page-audit/review.py next-id          # the next proposal number (P7, P8…)
    python3 tools/page-audit/review.py done [--mirror ../traverse-3way-sync]   # remember this review (results/review-state.json)

inputs: the pages to review, most paths blocked first: those with open findings (results/findings.json), and those
whose files changed on the live mirror since the last review (with --mirror). For each page: its findings and their
ids, what the browser checks saw (who, size, standard), the paths through it and what got in their way, its screenshots
(the run that has them), the decisions and docs that govern it with the sections that name it (results/page-map.json),
the standards for its kind of page, and the proposals already written about it. Then the cost and effectiveness
inputs (the health report's totals, the slowest pages) and the decided proposals, so a review doesn't propose again
what Jason already decided. Only reads; prints markdown.
"""
import argparse, datetime, json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))
RES = os.path.join(DOCS, 'site-audit', 'results')
sys.path.insert(0, HERE)
from findings import proposals  # noqa: E402  (the same reading of proposals.md)


def load(name, default):
    try:
        return json.load(open(os.path.join(RES, name), encoding='utf-8'))
    except (OSError, ValueError):
        return default


def changed_pages(mirror, since):
    """Pages (addresses) whose files changed on the mirror since the last review's sync."""
    if not mirror or not since:
        return set()
    try:
        out = subprocess.run(['git', '-C', mirror, 'diff', '--name-only', since, 'HEAD', '--', 'sync-storage'], capture_output=True, text=True, timeout=60).stdout
    except (OSError, subprocess.SubprocessError):
        return set()
    pages = set()
    for f in out.split('\n'):
        if f.startswith('sync-storage/') and f.endswith('.php'):
            p = '/' + f[len('sync-storage/'):]
            pages.add(p[:-len('index.php')] if p.endswith('/index.php') else p)
    return pages


def inputs(a):
    fnd = load('findings.json', {'findings': []})
    pm = {r['page']: r for r in load('page-map.json', {'pages': []})['pages']}
    rendered = load('rendered.json', {'results': []})
    paths = load('paths.json', {'results': []})
    state = load('review-state.json', {})
    props = proposals()
    want = set(p.strip() for p in (a.pages or '').split(',') if p.strip())
    changed = changed_pages(a.mirror, state.get('mirror'))
    by_page = {}
    for f in fnd['findings']:
        key = (f.get('page') or '').split('?')[0] or ('(code: ' + f['rule'] + ')' if f['kind'] == 'code' else '(site-wide: ' + f['kind'] + ')')
        by_page.setdefault(key, []).append(f)
    for p in changed:
        by_page.setdefault(p, [])
    pages = sorted(by_page, key=lambda p: (-max([f['blocks'] for f in by_page[p]] or [0]), p.startswith('('), -len(by_page[p]), p))   # most paths blocked, pages before site-wide
    if want:
        pages = [p for p in pages if p in want] + [p for p in want if p not in by_page]
    pages = pages[:a.top]
    run = fnd.get('run_url') or paths.get('run_url') or rendered.get('run_url') or ''
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M UTC')
    L = [f'# Review inputs ({now})', '', f'Findings of {fnd.get("generated_at", "?")} ({fnd.get("counts", {}).get("open", 0)} open, {fnd.get("counts", {}).get("blocking", 0)} in a path\'s way). '
         f'Screenshots: {run + "#artifacts" if run else "the latest Site checks run (Actions → Site checks → Artifacts)"}. '
         f'Last review: {state.get("at", "never")}' + (f'; {len(changed)} page(s) changed on the mirror since' if changed else '') + '.', '']
    for p in pages:
        fs = by_page.get(p, [])
        m = pm.get(p, {})
        L += [f'## `{p}`' + (' (changed since the last review)' if p in changed else ''), '']
        if m:
            L.append(f'- **Kind:** {", ".join(m.get("kinds") or ["page"])}; **file:** `{m.get("file") or "?"}`' + (f'; **routes.md:** {m["purpose"]} ({m["status"]})' if m.get('purpose') else ''))
            gov = [f'{n["doc"]} ({", ".join(n["sections"]) or "named"})' + (' [replaced]' if n['replaced'] else '') for n in m.get('named_in', [])]
            L.append(f'- **Governed by:** {"; ".join(gov) or "no decision names it (G02)"}' + (f'; routes.md gives {", ".join(m["decisions"])}' if m.get('decisions') else ''))
            L.append(f'- **Standards for its kind:** {"; ".join(m.get("standards", []))}')
        for f in fs:
            L.append(f'- **Finding {f["id"]}** ({f["kind"]}, {f["rule"]}, {f["severity"]}, blocks {f["blocks"]} path(s){", seen as " + ", ".join(f["who"]) if f["who"] else ""}): {f["title"]}. {f.get("detail", "")}'
                     + (f' Proposal: {f["proposal"]}{" (decided)" if f.get("decided") else ""}.' if f.get('proposal') else '') + f' Since {f["first_seen"][:10]}.')
        for r in rendered.get('results', []):
            if r.get('url', '').split('?')[0] == p and r.get('why'):
                L.append(f'- **Browser check** as {r["who"]} at {r["size"]}: ' + '; '.join(f'{k}: {d}'[:200] for k, d in r['why']) + (f' (screenshot `{r["who"]}-{re.sub(r"[^a-z0-9]+", "_", r["url"], flags=re.I)[:80]}.png`)' if r['size'] == 'phone' else ''))
        for r in paths.get('results', []):
            if any(pg.split('?')[0] == p for pg in r.get('pages', [])):
                probs = [x for x in r.get('problems', []) if x['page'].split('?')[0] == p]
                L.append(f'- **Path** "{r["title"]}" as {r["who"]}: {r["status"]}, {r.get("steps", {}).get("taps")} taps (measure {r.get("steps", {}).get("limit")})'
                         + (': ' + '; '.join(f'{x["kind"]}: {x["what"]}' for x in probs) if probs else '') + (f'. Screenshots: {", ".join(r.get("shots", [])[:4])}' if r.get('shots') else ''))
        about = [x for x in props if p.startswith('/') and re.search(r'(?<![\w.-])' + re.escape(p) + r'(?![\w-])', x['body'])]
        if about:
            L.append('- **Proposals that name it:** ' + ', '.join(f'{x["id"]} ({"decided" if x["decided"] else "open"})' for x in about))
        L.append('')
    hl = paths.get('health')
    L += ['## Cost and effectiveness', '']
    if hl and not hl.get('error'):
        L.append(f'- Scheduled jobs not running cleanly: {", ".join(j["job"] + (" (" + str(j["fails_today"]) + " failed today)" if j.get("fails_today") else "") for j in hl.get("jobs", [])) or "none"}')
        if hl.get('cache'):
            L.append(f'- Page cache: {hl["cache"]["entries"]} entries, {hl["cache"]["expired_share"]}% expired; no hit rate in the health report yet')
        if hl.get('ai'):
            L.append(f'- AI this month: {"over" if hl["ai"]["over_80_pct_of_cap"] else "under"} 80% of the cap; features spending: {", ".join(hl["ai"]["features"]) or "none"}')
        if hl.get('biggest_tables'):
            L.append('- Largest tables: ' + ', '.join(f'{t["name"]} {t["mb"]} MB' for t in hl['biggest_tables']))
        if hl.get('status'):
            L.append('- Admin status checks not OK: ' + '; '.join(f'{s["name"]}: {s["state"]}' for s in hl['status']))
    else:
        L.append('- The health report wasn\'t read on the last paths run' + (f' ({hl["error"]})' if hl and hl.get('error') else '') + '.')
    slow = sorted((t | {'path': r['title']} for r in paths.get('results', []) for t in r.get('timings', [])), key=lambda t: -t['ms'])[:6]
    if slow:
        L.append('- Slowest pages on the paths: ' + '; '.join(f'`{t["page"]}` {t["ms"] / 1000:.1f} s' for t in slow))
    L.append('- Not measured yet (say so in a "missing" finding rather than guess): calls, failures and cost per useful result for each outside service other than AI; cache hit rates; query timings (staging).')
    L += ['', '## Proposals already written', '']
    L += [f'- {x["id"]}. {x["title"]}: {"decided" if x["decided"] else "open"}' + (f' (verdict: {x["verdict"]})' if x['verdict'] else '') for x in props]
    L.append(f'\nThe next proposal is P{next_id()}.')
    print('\n'.join(L))


def next_id():
    return max([int(x['id'][1:]) for x in proposals()] or [0]) + 1


def done(a):
    sha = ''
    if a.mirror:
        try:
            sha = subprocess.run(['git', '-C', a.mirror, 'rev-parse', '--short', 'HEAD'], capture_output=True, text=True, timeout=30).stdout.strip()
        except (OSError, subprocess.SubprocessError):
            pass
    st = {'at': datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M UTC'), 'mirror': sha or load('review-state.json', {}).get('mirror', ''),
          'findings_of': load('findings.json', {}).get('generated_at', ''), 'proposals': f'P1–P{next_id() - 1}'}
    json.dump(st, open(os.path.join(RES, 'review-state.json'), 'w', encoding='utf-8'), indent=1)
    print(f'Review recorded: {st}')


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    sub = ap.add_subparsers(dest='cmd', required=True)
    i = sub.add_parser('inputs'); i.add_argument('--mirror', default=''); i.add_argument('--pages', default=''); i.add_argument('--top', type=int, default=15)
    sub.add_parser('next-id')
    d = sub.add_parser('done'); d.add_argument('--mirror', default='')
    a = ap.parse_args()
    if a.cmd == 'inputs':
        inputs(a)
    elif a.cmd == 'next-id':
        print(f'P{next_id()}')
    else:
        done(a)


if __name__ == '__main__':
    main()
