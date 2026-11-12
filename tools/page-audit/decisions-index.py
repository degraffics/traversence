#!/usr/bin/env python3
"""The decisions for the admin's search (Help) and its Decisions page: app/data/decisions-index.php.

    python3 tools/page-audit/decisions-index.py <out.php>

One entry per decision, [number, title, its decision in a line, file], then one per note added to it ("**Note (date …):**",
"**Update …**", "**Amendment …**"), [number, the note's heading, its first line, file, 'note'], so a search finds what a
note changed (Jason, 2026-11-12). Regenerate when decisions or notes are added; the site reads only this file.
"""
import glob, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))

def line(s, n=160):
    s = re.sub(r'\s+', ' ', re.sub(r'[*`]', '', s)).strip()
    s = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', s)
    return s if len(s) <= n else s[:n - 1].rstrip(' ,;:') + '…'

def php(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

out = []
for p in sorted(glob.glob(os.path.join(DOCS, 'decisions', '[0-9][0-9][0-9][0-9]-*.md'))):
    f = 'decisions/' + os.path.basename(p)
    num = os.path.basename(p)[:4]
    t = open(p, encoding='utf-8').read()
    m = re.search(r'^#\s+(?:ADR[- ]?\d+\s*[:.\-—–]\s*)?(.+)$', t, re.M)
    title = line(m.group(1), 200) if m else num
    d = re.search(r'^##\s+Decision\b.*?\n(.*?)(?=^##\s|\Z)', t, re.M | re.S)
    para = ''
    if d:
        for chunk in re.split(r'\n\s*\n', d.group(1)):
            c = chunk.strip()
            if c and not c.startswith('|') and not c.startswith('#'):
                para = c.splitlines()[0] if c.startswith(('-', '*', '1.')) else c
                break
    out.append(f"    [{php(num)}, {php(title)}, {php(line(para))}, {php(f)}],")
    for nm in re.finditer(r'^\*\*((?:Note|Update|Amendment)\b[^*]*)\*\*:?\s*(.*)$', t, re.M | re.I):
        head = line(nm.group(1).rstrip(': '), 120)
        body = nm.group(2)
        if not body.strip():
            rest = t[nm.end():].lstrip('\n')
            body = rest.split('\n\n', 1)[0]
        out.append(f"    [{php(num)}, {php(head)}, {php(line(body))}, {php(f)}, 'note'],")

hdr = """<?php
// app/data/decisions-index.php — the decision records (degraffics/traversence decisions/) for the admin search's Help and
// System Configuration → Decisions: [number, title, its decision in a line, file], and each note added to one
// [number, the note's heading, its first line, file, 'note']. Made by tools/page-audit/decisions-index.py in the docs repo;
// don't edit by hand.
declare(strict_types=1);

return [
"""
open(sys.argv[1], 'w', encoding='utf-8').write(hdr + '\n'.join(out) + '\n];\n')
print(len(out), 'entries')
