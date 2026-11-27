#!/usr/bin/env python3
"""tools/stylesheet/dynamic-classes.py: the class names the Tailwind CLI can't see (the built stylesheet's fix 4; the session
manager, 2026-11-27: "every name built in code is either written out in full in the source or put on the safelist").

The CLI reads every file as text and keeps any whole class name it finds in it, wherever it sits (a PHP array, a JS
string). What it can't read is a name glued from pieces: 'bg-' . $tone, "text-" + c, `w-${n}`, 'sm:' . $x. This lists each
such place with file:line, the fragment and the line, from the live mirror.
Run: python3 dynamic-classes.py <mirror checkout> [--json out.json]"""
import json, os, re, sys

UTIL = (r'(?:bg|text|border(?:-[trblxy])?|ring|ring-offset|outline|divide(?:-[xy])?|fill|stroke|from|via|to|shadow|accent|caret|decoration|placeholder'
        r'|w|h|min-w|max-w|min-h|max-h|size|p[xytrbl]?|m[xytrbl]?|space-[xy]|gap(?:-[xy])?|inset(?:-[xy])?|top|right|bottom|left|z|order'
        r'|grid-cols|grid-rows|col-span|row-span|col-start|col-end|basis|flex|grow|shrink|rounded(?:-[trbl]{1,2})?|opacity|font|leading|tracking'
        r'|line-clamp|aspect|columns|translate-[xy]|rotate|scale(?:-[xy])?|skew-[xy]|duration|delay|ease|justify|items|content|self|place-(?:items|content|self)|object|overflow|cursor)')
VARIANT = r'(?:sm|md|lg|xl|2xl|hover|focus|active|group-hover|dark|disabled|first|last|odd|even|peer-checked|aria-[a-z]+)'
# a quoted string that ends in "<utility>-" or "<variant>:" and is then joined to something
PHP = re.compile(r"""(['"])((?:[^'"\\]|\\.)*?(?:\b""" + UTIL + r"-|\b" + VARIANT + r":))\1\s*\.")
JSPLUS = re.compile(r"""(['"])((?:[^'"\\]|\\.)*?(?:\b""" + UTIL + r"-|\b" + VARIANT + r":))\1\s*\+")
TEMPLATE = re.compile(r"""\b(""" + UTIL + r")-\$\{|\b" + VARIANT + r":\$\{")
# PHP interpolation inside a double-quoted class string: "bg-$tone", "text-{$c}"
INTERP = re.compile(r"""\b(""" + UTIL + r")-\{?\$[a-zA-Z_]")

def scan(root):
    out = []
    for base in ('sync-app', 'sync-storage', 'sync-trvgo'):
        for d, _, fs in os.walk(os.path.join(root, base)):
            if '/vendor' in d or '/node_modules' in d or '/uploads' in d: continue
            for f in fs:
                if not f.endswith(('.php', '.js', '.html')) or f.endswith('.min.js'): continue
                p = os.path.join(d, f)
                try: lines = open(p, encoding='utf-8', errors='replace').read().split('\n')
                except OSError: continue
                for i, ln in enumerate(lines, 1):
                    if len(ln) > 4000: ln = ln[:4000]
                    hits = []
                    for rx in (PHP, JSPLUS): hits += [m.group(2)[-40:] for m in rx.finditer(ln)]
                    hits += [m.group(0) for m in TEMPLATE.finditer(ln)]
                    if f.endswith('.php'):
                        for m in re.finditer(r'"([^"\\]*(?:\\.[^"\\]*)*)"', ln):
                            hits += [x.group(0) for x in INTERP.finditer(m.group(1))]
                    # a CSS custom property, a URL, an id or a data attribute isn't a class name
                    hits = [h for h in hits if not re.search(r'(--|https?:|#|data-|aria-label|\?|/)\S*$', h)]
                    if hits: out.append({'file': os.path.relpath(p, root), 'line': i, 'fragments': sorted(set(hits)), 'text': ln.strip()[:220]})
    return out

if __name__ == '__main__':
    if len(sys.argv) < 2: sys.exit(__doc__)
    rows = scan(sys.argv[1])
    if '--json' in sys.argv: json.dump(rows, open(sys.argv[sys.argv.index('--json') + 1], 'w'), indent=1)
    for r in rows: print('%s:%d  %s' % (r['file'], r['line'], ' | '.join(r['fragments'])))
    print('%d places in %d files' % (len(rows), len({r['file'] for r in rows})), file=sys.stderr)
