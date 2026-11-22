#!/usr/bin/env python3
"""AUDIT, the check register's "The audit's own load: what each run asks of the site" (the 20% sample).

    python3 tools/page-audit/audit-sample.py [--results site-audit/results]

The register (handoffs/control-board-plan/step3/sections/check-register.md, row AUDIT and "How often, and what each run
asks of the site"; Jason, 2026-11-22: "wont bog our platform or resources down creating slowness issues"). Target (ours,
approved with the schedule, Jason, 2026-11-22): a light run asks at most about 20 requests, one at a time with a 1 s gap;
the probe pages' response times stay within 20% of their 7-day median, or the next run backs off.

It asks nothing of the site itself. It reads what the runs wrote:
  - each check's own load (the "run" blocks in results/load.json, tools.json, photos-shown.json, brand.json): requests
    (our page views and calls), what the browser fetched for them, the time, and the smallest gap between our requests;
    a light run passes at 20 requests or fewer, one at a time, at least 1 s apart; any other run passes one at a time,
    1 s apart (the register numbers only the light run);
  - the probe pages' response times: the hourly light run's document response time for each key page (load.json, mode
    light: the server's time to its first byte, the median of the run's sizes), against that page's median over the last
    7 days of samples kept here. More than 20% over it fails, and the next run backs off: "an hour later, then two" (the
    register), so audit.json's backoff holds the next light run one hour beyond its turn, then two; load-check.mjs and
    tools-check.mjs read it and ask nothing while it holds. A page with no earlier sample is "no check".
Writes results/audit.json in the register's one results format, with the samples (7 days) and the back-off.
"""
import argparse, datetime, hashlib, json, os, statistics

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))
TARGET = ('a light run asks at most about 20 requests, one at a time with a 1 s gap; the probe pages\' response times stay '
          'within 20% of their 7-day median, or the next run backs off')
TARGET_SOURCE = 'Jason, 2026-11-22 (the schedule below); the 20% is ours, approved with the schedule'
LIGHT_MAX = 20        # "at most about 20 requests"
GAP_MS = 1000         # "one at a time with a 1 s gap"
SLOWER = 1.20         # "within 20% of their 7-day median"
DAYS = 7
FILES = ['load.json', 'tools.json', 'photos-shown.json', 'brand.json']


def iso(t):
    return t.strftime('%Y-%m-%dT%H:%M:%SZ')


def parse(s):
    try:
        return datetime.datetime.fromisoformat(str(s).replace('Z', '+00:00'))
    except ValueError:
        return None


def load(path, default):
    try:
        return json.load(open(path, encoding='utf-8'))
    except (OSError, ValueError):
        return default


def row(item, result, measured, now, evidence=None, **extra):
    fid = 'f-' + hashlib.sha1(f'audit|{item}'.encode('utf-8')).hexdigest()[:10]
    return {'check': 'AUDIT', 'item': item, 'who': None, 'target': TARGET, 'result': result, 'measured': measured, 'at': iso(now),
            'evidence': evidence, 'finding': fid if result == 'fail' else None, **extra}


def judge_run(run):
    """A run's own load against the target."""
    if run.get('skipped'):
        return 'pass', 'asked nothing: ' + run['skipped']
    gap = run.get('min_gap_ms')
    paced = run.get('one_at_a_time', False) and (gap is None or gap >= GAP_MS - 10)   # a timer's few ms of slack
    if run.get('mode') == 'light':
        n = run.get('requests', 0)
        if n > LIGHT_MAX:
            return 'fail', f'{n} requests, over the light run\'s {LIGHT_MAX}'
        return ('pass', f'{n} requests, one at a time, {gap if gap is not None else "—"} ms apart at the least') if paced else ('fail', f'not one at a time with a 1 s gap (smallest gap {gap} ms)')
    return ('pass', f'{run.get("requests", 0)} requests one at a time, {gap if gap is not None else "—"} ms apart at the least') if paced else ('fail', f'not one at a time with a 1 s gap (smallest gap {gap} ms)')


def build(res, now=None):
    now = now or datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
    old = load(os.path.join(res, 'audit.json'), {})
    history = [h for h in old.get('history', []) if (parse(h.get('at')) or now) >= now - datetime.timedelta(days=DAYS)]
    sampled = list(old.get('sampled_runs', []))[-200:]
    rows, runs_seen = [], set()
    run_url = os.environ.get('GITHUB_RUN_ID') and f'{os.environ.get("GITHUB_SERVER_URL", "https://github.com")}/{os.environ.get("GITHUB_REPOSITORY")}/actions/runs/{os.environ["GITHUB_RUN_ID"]}'

    # 1. each run's own load
    load_json = None
    for name in FILES:
        d = load(os.path.join(res, name), None)
        if not d:
            continue
        if name == 'load.json':
            load_json = d
        for mode, run in sorted((d.get('runs') or {}).items()):
            if not isinstance(run, dict) or run.get('id') in runs_seen:
                continue   # one page pass shared by LOAD, PHOTOS-SHOWN and BRAND is one run
            runs_seen.add(run.get('id'))
            result, why = judge_run(run)
            rows.append(row(f'{run.get("check", d.get("check"))} {mode} run ({run.get("started_at", "")[:16].replace("T", " ")} UTC)', result,
                            {k: run.get(k) for k in ('requests', 'page_views', 'calls', 'subrequests', 'seconds', 'gap_ms', 'min_gap_ms', 'one_at_a_time', 'skipped', 'shared_by')},
                            now, d.get('run_url') or None, why=why))

    # 2. the probe pages' response times: this light run's sample against each page's 7-day median
    light = (load_json or {}).get('runs', {}).get('light') or {}
    slow, judged = [], 0
    if light and not light.get('skipped'):
        per_page = {}
        for r in (load_json or {}).get('results', []):
            if r.get('mode') != 'light' or not r.get('item', '').startswith('/'):
                continue
            ms = (r.get('measured') or {}).get('ttfb_ms')
            if isinstance(ms, (int, float)):
                per_page.setdefault(r['item'].split(' @')[0], []).append(ms)
        fresh = light.get('id') not in sampled
        for page, xs in sorted(per_page.items()):
            ms = statistics.median(xs)
            before = [h['ms'] for h in history if h.get('page') == page]
            if not before:
                rows.append(row(f'{page} response time', 'no check', {'ms': ms, 'median_7d_ms': None, 'samples': 0}, now, load_json.get('run_url') or None,
                                why='no earlier sample to compare with yet'))
            else:
                med = statistics.median(before)
                ratio = round(ms / med, 2) if med else None
                ok = med == 0 or ms <= med * SLOWER
                judged += 1
                if not ok:
                    slow.append(f'{page} {ms:.0f} ms against a 7-day median of {med:.0f} ms')
                rows.append(row(f'{page} response time', 'pass' if ok else 'fail', {'ms': ms, 'median_7d_ms': med, 'ratio': ratio, 'samples': len(before)}, now,
                                load_json.get('run_url') or None))
            if fresh:
                history.append({'at': light.get('started_at') or iso(now), 'page': page, 'ms': ms})
        if fresh and light.get('id'):
            sampled.append(light['id'])

        # 3. the back-off: "the next run backs off (an hour later, then two)"
        b = old.get('backoff') or {}
        if not fresh:
            backoff = b   # the same sample judged again: nothing new to decide
        elif slow:
            level = min(int(b.get('level') or 0) + 1, 2)
            backoff = {'level': level, 'until': iso(now + datetime.timedelta(hours=1 + level, minutes=-10)), 'why': 'the probe pages were slow: ' + '; '.join(slow)[:300],
                       'since': b.get('since') or iso(now)}
        else:
            backoff = {'level': 0, 'until': None, 'why': '', 'since': None}
    else:
        backoff = old.get('backoff') or {'level': 0, 'until': None, 'why': '', 'since': None}

    counts = {}
    for r in rows:
        counts[r['result']] = counts.get(r['result'], 0) + 1
    data = {'version': 1, 'check': 'AUDIT', 'name': 'The audit\'s own load: what each run asks of the site', 'generated_at': iso(now),
            'site': (load_json or {}).get('site', 'https://traversence.com'), 'run_url': run_url or '', 'target': TARGET, 'target_source': TARGET_SOURCE,
            'source': 'handoffs/control-board-plan/step3/sections/check-register.md',
            'runs': {'sample': {'id': f'audit-{iso(now)}', 'check': 'AUDIT', 'mode': 'sample', 'requests': 0, 'note': 'reads the runs\' own results: asks nothing of the site'}},
            'counts': counts, 'backoff': backoff, 'history': history, 'sampled_runs': sampled,
            'note': 'Generated by tools/page-audit/audit-sample.py for the check register\'s AUDIT row. Read by the gauges, load-check.mjs and tools-check.mjs (the back-off). Don\'t edit by hand.',
            'results': rows}
    os.makedirs(res, exist_ok=True)
    json.dump(data, open(os.path.join(res, 'audit.json'), 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    return data


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--results', default=os.environ.get('RESULTS_DIR') or os.path.join(DOCS, 'site-audit', 'results'))
    a = ap.parse_args()
    d = build(a.results)
    b = d['backoff']
    print(f'AUDIT: {d["counts"]}; ' + (f'backing off until {b["until"]} ({b["why"]})' if b.get('until') else 'no back-off'))


if __name__ == '__main__':
    main()
