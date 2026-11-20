"""Hex colours onto the theme tokens by what they colour (css-theme-brand §3): text, surface or line. Each value keeps
its old hex as the token's fallback. A hex it can't place stays as it is and is reported. Used by build-3d.py."""
import re
TEXT = {'3E2B1F': 'ink', '2A1B12': 'ink', '2A1D14': 'ink', '5C4632': 'meta', '8A7355': 'meta', '92400E': 'link', 'B45309': 'icon',
        'EFE3C8': 'on-umber', '991B1B': 'crimson', 'B91C1C': 'crimson'}
SURF = {'FFFBF2': 'card-a', 'FFF': 'card-a', 'FFFFFF': 'card-a', 'FFF7E6': 'card-a', 'F7EFDC': 'sec', 'E3D3AC': 'sec', 'EADFC4': 'sec',
        '3E2B1F': 'umber', '2A1B12': 'umber', '92400E': 'btn', '8FBCA8': 'sage', 'EFE3C8': 'canvas'}
LINE = {'CBB98D': 'line-strong', 'D5C39A': 'line-strong', 'E3D3AC': 'line', 'EADFC4': 'line', 'F7EFDC': 'line', '92400E': 'link', '3E2B1F': 'umber',
        '8FBCA8': 'sage'}
KIND = [(re.compile(r'^(?:color|text)$'), TEXT), (re.compile(r'^(?:background(?:-color)?|bg)$'), SURF), (re.compile(r'^(?:border(?:-[a-z]+)*|outline|divide|ring)$'), LINE),
        (re.compile(r'^(?:fill|stroke)$'), {'B45309': 'icon'})]
HEX = re.compile(r'#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})\b')
def _tok(prop, h):
    for rx, m in KIND:
        if rx.match(prop): t = m.get(h.upper()); return t
    return None
def recolor(s, report):
    def decl(m):   # prop:value in CSS or a style attribute
        prop, val = m.group(1), m.group(2)
        if 'var(--tv-' in val and not HEX.search(re.sub(r'var\([^)]*\)', '', val)): return m.group(0)
        def one(h):
            t = _tok(prop.lower(), h.group(1))
            if t: report['mapped'] += 1; return 'var(--tv-%s,#%s)' % (t, h.group(1))
            report['left'].setdefault(prop + ' #' + h.group(1).upper(), 0); report['left'][prop + ' #' + h.group(1).upper()] += 1; return h.group(0)
        # only hexes outside an existing var(...)
        parts = re.split(r'(var\([^)]*\))', val)
        return prop + ':' + ''.join(p if p.startswith('var(') else HEX.sub(one, p) for p in parts)
    s = re.sub(r'(?<![\w-])((?:background-color|background|color|border(?:-(?:top|right|bottom|left))?(?:-color)?|outline|fill|stroke))\s*:\s*([^;}"\n]*)', decl, s)
    def tw(m):   # Tailwind's text-[#…], bg-[#…], border-[#…]
        t = _tok(m.group(1), m.group(2))
        if t: report['mapped'] += 1; return '%s-[color:var(--tv-%s)]' % (m.group(1), t)
        report['left'].setdefault(m.group(1) + ' #' + m.group(2).upper(), 0); report['left'][m.group(1) + ' #' + m.group(2).upper()] += 1; return m.group(0)
    s = re.sub(r'\b(text|bg|border)-\[#([0-9A-Fa-f]{3,6})\]', tw, s)
    return s
