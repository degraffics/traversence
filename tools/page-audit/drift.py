#!/usr/bin/env python3
"""Drift: live files that changed without a release (decisions/0159 step 2, Phase 6).

    python3 tools/page-audit/drift.py --mirror <traverse-3way-sync checkout> [--write]

Since builds go through git (send.py → releases/inbox → the site), every change to the site's files should come from a
release or a logged delivery. This compares the live mirror with what it was at the last run (site-audit/results/
drift-base.json) and, for each file that changed, looks for its exact bytes (sha256) among the delivery log
(deliveries/*.json) and the site's release records (deliveries/releases/*.json, a release's files and, for a roll back,
the copies it kept). A change found nowhere is drift: edited straight on the server (cPanel, FTP) or in OneDrive. Removed
files count too.

It reports, it changes nothing. Open drift is kept in site-audit/results/drift.json and shows in Pulse's Site health
(findings.py), one finding listing the files. An entry closes by itself once a later release carries the file as it is,
or the file goes back to a released copy. Folders the site writes to itself (people's uploads) are ignored:
site-audit/drift-ignore.txt. The first run only records the base.
"""
import argparse, datetime, glob, hashlib, json, os

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))
RES = os.path.join(DOCS, 'site-audit', 'results')
TOPS = {'sync-storage': 'website', 'sync-app': 'app', 'sync-trvgo': 'trvgo'}


def ignored():
    out = ['website/uploads/']
    try:
        for line in open(os.path.join(DOCS, 'site-audit', 'drift-ignore.txt'), encoding='utf-8'):
            line = line.split('#')[0].strip()
            if line:
                out.append(line)
    except OSError:
        pass
    return out


def hashes(mirror, skip):
    out = {}
    for top, real in TOPS.items():
        root = os.path.join(mirror, top)
        for d, dirs, files in os.walk(root):
            dirs[:] = [x for x in dirs if not x.startswith('.')]
            for f in files:
                if f.startswith('.'):
                    continue
                p = os.path.join(d, f)
                rel = real + '/' + os.path.relpath(p, root).replace(os.sep, '/')
                if any(rel.startswith(s) for s in skip):
                    continue
                h = hashlib.sha256()
                with open(p, 'rb') as fh:
                    for chunk in iter(lambda: fh.read(1 << 20), b''):
                        h.update(chunk)
                out[rel] = h.hexdigest()
    return out


def released():
    """{path: set(sha256)}: every copy a delivery or a release put there (or a roll back put back)."""
    out = {}
    for f in glob.glob(os.path.join(DOCS, 'deliveries', '*.json')):
        try:
            d = json.load(open(f, encoding='utf-8'))
        except ValueError:
            continue
        for p, x in (d.get('files') or {}).items():
            if isinstance(x, dict) and x.get('sha256'):
                out.setdefault(p, set()).add(x['sha256'])
    for f in glob.glob(os.path.join(DOCS, 'deliveries', 'releases', '*.json')):
        try:
            d = json.load(open(f, encoding='utf-8'))
        except ValueError:
            continue
        for p, x in (d.get('files') or {}).items():
            if not isinstance(x, dict):
                continue
            for k in ('sha', 'old_sha'):
                if x.get(k):
                    out.setdefault(p, set()).add(x[k])
    return out


def run(mirror, write):
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    sync = os.popen(f'git -C "{mirror}" log -1 --format=%s 2>/dev/null').read().strip()
    skip = ignored()
    cur = hashes(mirror, skip)
    base_f, drift_f = os.path.join(RES, 'drift-base.json'), os.path.join(RES, 'drift.json')
    try:
        base = json.load(open(base_f, encoding='utf-8')).get('files', {})
    except (OSError, ValueError):
        base = None
    try:
        open_ = json.load(open(drift_f, encoding='utf-8')).get('open', [])
    except (OSError, ValueError):
        open_ = []
    rel = released()
    new = []
    if base is not None:
        for p in sorted(set(cur) | set(base)):
            a, b = base.get(p), cur.get(p)
            if a == b:
                continue
            if b is not None and b in rel.get(p, set()):
                continue   # a release or a logged delivery put exactly this there
            new.append({'path': p, 'what': 'removed' if b is None else ('added' if a is None else 'changed'), 'sha256': b or '', 'before': a or '', 'seen': now, 'sync': sync})
    # still open unless the file now matches a released copy, or is back to what it was before it drifted
    keep = {}
    for e in open_ + new:
        p, b = e['path'], cur.get(e['path'])
        if (b is not None and b in rel.get(p, set())) or (b or '') == e.get('before', '\0'):
            keep.pop(p, None)
            continue
        if p in keep:
            keep[p].update({'what': e['what'], 'sha256': b or '', 'sync': e.get('sync', '')})
        else:
            keep[p] = dict(e)
    data = {'version': 1, 'generated_at': now, 'sync': sync, 'files_checked': len(cur), 'first_run': base is None,
            'note': 'Generated by tools/page-audit/drift.py: live files changed without a release or a logged delivery. Read by findings.py for Pulse\'s Site health. Don\'t edit by hand.',
            'open': sorted(keep.values(), key=lambda e: (e['seen'], e['path']))}
    if write:
        os.makedirs(RES, exist_ok=True)
        json.dump({'generated_at': now, 'sync': sync, 'files': cur}, open(base_f, 'w', encoding='utf-8'), indent=0, sort_keys=True)
        json.dump(data, open(drift_f, 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    return data, new


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--write', action='store_true')
    a = ap.parse_args()
    d, new = run(a.mirror, a.write)
    if d['first_run']:
        print(f'Drift: first run, {d["files_checked"]} files recorded as the base.')
    else:
        print(f'Drift: {len(new)} new, {len(d["open"])} open ({d["files_checked"]} files checked).')
        for e in d['open'][:20]:
            print(f'  {e["what"]:8} {e["path"]}  (seen {e["seen"]}, {e["sync"]})')


if __name__ == '__main__':
    main()
