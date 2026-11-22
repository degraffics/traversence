# Validates step3/board/index.html: unique ids; no interactive element nested in another (button or a inside a button or
# a; details or a control inside role=button); no aria-label holding tags; no bare class selector defined twice at the
# same level with conflicting properties. Prints PASS/FAIL lines; exit status 1 on any failure.
import re, sys
from html.parser import HTMLParser

PATH = '/home/user/traversence/handoffs/control-board-plan/step3/board/index.html'
doc = open(PATH).read()
fails = 0


def ok(c, msg):
    global fails
    print(('PASS ' if c else 'FAIL ') + msg)
    if not c:
        fails += 1


class V(HTMLParser):
    VOID = {'br', 'img', 'input', 'meta', 'link', 'hr', 'wbr', 'path', 'polyline', 'circle', 'line', 'source'}

    def __init__(s):
        super().__init__(); s.st = []; s.ids = {}; s.nest = []; s.aria = []; s.rolebtn = []

    def handle_starttag(s, t, a):
        a = dict(a)
        if 'id' in a:
            s.ids[a['id']] = s.ids.get(a['id'], 0) + 1
        if 'aria-label' in a and re.search(r'<[a-z/]', a['aria-label'] or ''):
            s.aria.append(a['aria-label'][:60])
        inter = t in ('button', 'a', 'details', 'summary', 'input', 'select', 'textarea') or a.get('role') == 'button'
        if a.get('role') == 'button' and t != 'button':
            s.rolebtn.append(t)
        if inter:
            for pt, pa in s.st:
                if pt in ('button', 'a') or pa.get('role') == 'button':
                    if not (t == 'summary' and pt == 'details'):
                        s.nest.append(f'{t} inside {pt} at {s.getpos()}')
                        break
        if t not in s.VOID:
            s.st.append((t, a))

    def handle_endtag(s, t):
        for k in range(len(s.st) - 1, -1, -1):
            if s.st[k][0] == t:
                del s.st[k:]
                break


v = V(); v.feed(doc)
dup = {k: n for k, n in v.ids.items() if n > 1}
ok(not dup, f'every id is unique ({len(v.ids)} ids)' + (f': {list(dup.items())[:5]}' if dup else ''))
ok(not v.nest, f'no interactive element nested inside another' + (f': {len(v.nest)}, e.g. {v.nest[:3]}' if v.nest else ''))
ok(not v.rolebtn, f'no role=button on a non-button element' + (f': {len(v.rolebtn)} ({set(v.rolebtn)})' if v.rolebtn else ''))
ok(not v.aria, f'no aria-label holds tags' + (f': {len(v.aria)}, e.g. {v.aria[:2]}' if v.aria else ''))

# CSS: bare class selectors defined twice at the same level with a conflicting property
css = ''.join(re.findall(r'<style>(.*?)</style>', doc, re.S))
css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)


def blocks(text):
    """Yield (media, selector, body) for top-level rules and rules one level inside @media."""
    i = 0
    while i < len(text):
        m = re.compile(r'\s*([^{}]+)\{').match(text, i)
        if not m:
            break
        sel = m.group(1).strip()
        depth, j = 1, m.end()
        while depth and j < len(text):
            depth += {'{': 1, '}': -1}.get(text[j], 0); j += 1
        body = text[m.end():j - 1]
        if sel.startswith('@media'):
            for _, s2, b2 in blocks(body):
                yield sel, s2, b2
        elif not sel.startswith('@'):
            yield '', sel, body
        i = j


props = {}
for media, sel, body in blocks(css):
    for one in sel.split(','):
        one = one.strip()
        if re.fullmatch(r'\.[\w-]+', one):
            for decl in body.split(';'):
                if ':' in decl:
                    k, val = decl.split(':', 1)
                    props.setdefault((media, one, k.strip()), set()).add(val.strip())
conf = sorted({(m, s) for (m, s, k), vals in props.items() if len(vals) > 1})
ok(not conf, 'no bare class selector is defined twice with conflicting properties' + (f': {len(conf)}: ' + ', '.join(s + (' @' + m[:30] if m else '') for m, s in conf[:12]) if conf else ''))
# each card's fault class matches its badge (the state that sets it): red → fault-r, amber → fault-a, else none
bad = []
for m in re.finditer(r'<article class="card( fault-(\w))?" aria-label="([^"]*)">', doc):
    seg = doc[m.end():m.end() + 4000]
    idl = re.search(r'<div class="idl">.*?<span class="lt (\w)">', seg, re.S)
    exp = {'r': 'r', 'a': 'a', 'h': 'a'}.get(idl.group(1) if idl else '', '')
    if (m.group(2) or '') != exp:
        bad.append(m.group(3))
n_cards = len(re.findall(r'<article class="card', doc))
ok(not bad, f'every card\'s fault class matches its badge ({n_cards} cards)' + (f': {bad[:5]}' if bad else ''))
sys.exit(1 if fails else 0)
