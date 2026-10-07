#!/usr/bin/env python3
"""
Builds api/lib/data/concepts.php from WordNet 3.1 and our categories (decisions/0067): every English word mapped to the
kinds of place that serve it, so search understands words no category or listing uses ("x-rays" → Diagnostic Imaging
Centers, "gym" → Health Clubs Studios & Gymnasiums, "mushroom" → Grocers).

Usage: python3 build_concepts.py /path/to/wordnet31 categories.json /path/to/sync-storage/api/lib/data/concepts.php

category-terms.tsv (next to this script) gives each category the everyday words people search for it by ("cardio",
"x-ray", "haircut"). Each is a direct way in, and WordNet carries it further: the kinds of it ("bread": sourdough,
bagel), its other names and forms.

categories.json is a list of {"name", "parent", "labels"}: each active category, its parent's name, and the names of
listings in it joined with " | " (the SQL to export it from phpMyAdmin is in README.md). Rebuild when categories change;
until then, a new category is still found by its own words.

How it works, with nothing written by hand:
- Each category's words are read in the sense that fits it (the medical "imaging", not imagination), chosen by how much
  each sense's WordNet neighbourhood shares with the category's other words, its parent and its listings' names.
- A category reaches DOWN: the kinds of its things (imaging → x-raying, ultrasound, MRI, radiology), and their related
  words (radiology → radiologist).
- A searched word reaches UP: what it is a kind of (mushroom → vegetable → produce), its related forms (x-ray → x-raying),
  and its definition's words.
- They meet in the middle, weighted by how rare each shared idea is across categories (TF-IDF, cosine). The best few
  categories above a floor are kept.
WordNet: Princeton University, "About WordNet", 2010 (license in word-families.LICENSE).
"""
import json, math, os, re, sys, collections

WN, CATS, OUT = sys.argv[1], sys.argv[2], sys.argv[3]
POS = {'n': 'noun', 'v': 'verb', 'a': 'adj', 'r': 'adv'}

# ---- WordNet ----------------------------------------------------------------------------------------------------
syn, idx, lexfile = {}, collections.defaultdict(list), {}
for p, name in POS.items():
    with open(os.path.join(WN, 'data.' + name), encoding='utf-8', errors='replace') as f:
        for ln in f:
            if ln.startswith('  '): continue
            head, _, gloss = ln.partition(' | ')
            t = head.split()
            off = t[0]; nw = int(t[3], 16)
            lem = [re.sub(r'\(.*\)$', '', t[4 + 2 * i].lower()) for i in range(nw)]
            k = 4 + 2 * nw; npt = int(t[k]); ptrs = []
            for i in range(npt):
                sym, o, pp = t[k + 1 + 4 * i], t[k + 2 + 4 * i], t[k + 3 + 4 * i]
                ptrs.append((sym, 'a' if pp == 's' else pp, o))
            gl = re.sub(r'"[^"]*"', ' ', gloss)   # the definition, not its example sentences
            syn[(p, off)] = (lem, ptrs, gl)
            lexfile[(p, off)] = t[1]
    with open(os.path.join(WN, 'index.' + name), encoding='utf-8', errors='replace') as f:
        for ln in f:
            if ln.startswith('  '): continue
            t = ln.split(); nsyn = int(t[2]); npt = int(t[3])
            idx[t[0]].extend((p, o) for o in t[4 + npt + 2:][:nsyn])
# how often each sense is used in real text (WordNet's tagged-sense counts): "bread" the food, far more than the money
tagcnt = {}
with open(os.path.join(WN, 'index.sense'), encoding='utf-8', errors='replace') as f:
    for ln in f:
        t = ln.split()
        if len(t) < 4: continue
        pp = {'1': 'n', '2': 'v', '3': 'a', '4': 'r', '5': 'a'}.get(t[0].split('%')[1][:1])
        if pp: tagcnt[(pp, t[1])] = max(tagcnt.get((pp, t[1]), 0), int(t[3]))

def sense_weights(senses):
    """Each sense's share: by how often it's used, else by its order (WordNet lists the commonest first)."""
    top = max([tagcnt.get(s, 0) for s in senses] + [0])
    out = []
    for k, s in enumerate(senses):
        pos_w = (1.0, 0.75, 0.6, 0.5, 0.45, 0.4, 0.35, 0.3)[min(k, 7)]
        out.append((s, max(0.1, ((tagcnt.get(s, 0) + 0.5) / (top + 0.5)) ** 1.5) if top >= 3 else pos_w))
    return out

exc = {}
for name in ('noun', 'verb', 'adj'):
    fn = os.path.join(WN, name + '.exc')
    if os.path.exists(fn):
        for ln in open(fn, encoding='utf-8', errors='replace'):
            t = ln.split()
            if len(t) >= 2: exc.setdefault(t[0], t[1])

STOP = set('a an the and or of for to in on at by with from as is are be its it this that these those any some other '
           'such used especially usually often which who whom whose into than then also not no can may one two etc '
           'something someone thing things kind part type form use person people having being made make'.split())
ABBR = set('supls supl sply splys svc svcs svce mfrs mfg whls assn cmnty ctr ctrs dist dept sls contrs instr inds equip '
           'eqpt inc llc corp srvc mgmt mntnc prods distr pllc co ltd svcs'.split())

def words(text):
    return [w for w in re.split(r'[^a-z0-9]+', text.lower()) if len(w) >= 3 and w not in STOP and w not in ABBR]

def base(w):
    """The WordNet lemma for a word as written (plurals and irregular forms)."""
    if w in idx: return w
    if w in exc and exc[w] in idx: return exc[w]
    for a, b in (('ies', 'y'), ('ses', 's'), ('xes', 'x'), ('ches', 'ch'), ('shes', 'sh'), ('s', ''), ('es', '')):
        if w.endswith(a) and len(w) > len(a) + 2 and (w[:-len(a)] + b) in idx: return w[:-len(a)] + b
    return None

def rel(s, syms):
    return [(p, o) for sym, p, o in syn[s][1] if sym in syms]

HYPER, HYPO = ('@', '@i'), ('~', '~i')
DERIV, PERT, DOMAIN, MEMBERS = ('+',), ('\\', '&', '<', '='), (';c',), ('-c',)

def signature(s):
    """Words around a sense: its lemmas and definition, what it is a kind of (2 up), its kinds (1 down), its domain."""
    out = set()
    lem, _, gl = syn[s]
    for l in lem: out.update(l.split('_'))
    out.update(words(gl))
    frontier = [s]
    for _ in range(2):
        frontier = [h for f in frontier for h in rel(f, HYPER)]
        for h in frontier: out.update(x for l in syn[h][0] for x in l.split('_'))
    for h in rel(s, HYPO)[:40] + rel(s, DOMAIN) + rel(s, DERIV):
        out.update(x for l in syn[h][0] for x in l.split('_'))
    return out

_pref = collections.defaultdict(list)
for _l in idx:
    if len(_l) >= 4: _pref[_l[:4]].append(_l)
def idx_by_prefix(stem):
    """Nouns that start with a stem and aren't much longer: "bake" → bakery, bakeshop, bakehouse."""
    return [l for l in _pref.get(stem[:4], []) if l.startswith(stem) and len(l) <= len(stem) + 7
            and any(y[0] == 'n' for y in idx[l]) and not re.search(r'_(of|and|in)_', l)][:6]

# ---- categories: the sense that fits, then reach down ---------------------------------------------------------
cats = json.load(open(CATS))
SEEDS = collections.defaultdict(list)   # category name -> [term]
_tf = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'category-terms.tsv')
if os.path.exists(_tf):
    for ln in open(_tf, encoding='utf-8'):
        if ln.startswith('#') or '\t' not in ln: continue
        nm, terms = ln.rstrip('\n').split('\t', 1)
        SEEDS[nm] = [t.strip().lower() for t in terms.split(',') if t.strip()]
cat_senses = collections.Counter()   # which of WordNet's topics (food, artifact, person…) our categories are about
def cat_vector(c):
    name_words = words(c['name'])
    label_counts = collections.Counter(w for w in words(c.get('labels', '')) if w not in name_words)
    context = set(name_words) | set(words(c.get('parent', ''))) | {w for w, n in label_counts.most_common(25)}
    v = collections.defaultdict(float)
    used = set()
    toks = name_words
    lemmas = []
    i = 0
    while i < len(toks):   # two words that are one thing in WordNet ("health club", "self storage") first
        two = toks[i] + '_' + (base(toks[i + 1]) or toks[i + 1]) if i + 1 < len(toks) else None
        if two and two in idx: lemmas.append(two); i += 2; continue
        b = base(toks[i])
        if b: lemmas.append(b)
        i += 1
    for w in name_words: put_w(v, w, 1.0)
    # the everyday words for it, and through WordNet what they cover ("bread" → sourdough, bagel)
    for term in SEEDS.get(c['name'], []):
        for x in words(term): put_w(v, x, 0.9)
        lm = term.replace(' ', '_')
        lm = lm if lm in idx else (base(term) if ' ' not in term else None)
        for sn in [y for y in idx.get(lm or '', []) if y[0] == 'n'][:1]:
            add_down(v, sn, 0.8, used)
    # the parent's ideas ("Food & Dining", "Health & Medical"), lightly: what the category is part of
    for pw in words(c.get('parent', '')):
        put_w(v, pw, 0.35)
        b = base(pw)
        for s in [x for x in idx.get(b or '', []) if x[0] == 'n'][:1] + [t for x in idx.get(b or '', []) if x[0] == 'a' for t in rel(x, PERT)][:1]:
            add_tok(v, s, 0.35, 0)
    for lm in lemmas:
        senses = [s for s in idx[lm] if s[0] == 'n'][:6] or [s for s in idx[lm] if s[0] in 'av'][:3]
        if not senses: continue
        ctx = context - set(lm.split('_'))
        prior = dict(sense_weights(senses))
        scored = [(len(signature(s) & ctx) + prior[s], -k, s) for k, s in enumerate(senses)]
        best = max(scored)[0]
        best = 0 if best < 1 else best
        # nothing around it picks a sense: its two commonest, the second weaker
        keep = [(s, 1.0) for s in senses[:1]] + [(s, 0.6) for s in senses[1:2]] if best == 0 else \
               [(s, 1.0) for sc, k, s in sorted(scored, reverse=True) if sc >= max(1, best * 0.6)][:2]
        # the trade's place, which WordNet doesn't link: baker → bakery, barber → barbershop, grocer → grocery store.
        # Its definition says what's there ("breads and cakes and pastries").
        stem = re.sub(r'(er|ers|ist|ists|ian|ians|or|ors)$', '', lm) if '_' not in lm else ''
        if len(stem) >= 4:
            for other in idx_by_prefix(stem):
                if other == lm: continue
                for t in [y for y in idx[other] if y[0] == 'n'][:1]: add_down(v, t, 0.6, used)
        for s, sw in keep:
            cat_senses[lexfile[s]] += sw
            if s[0] == 'a':   # "dental": the noun it's about (teeth)
                for t in rel(s, PERT): add_down(v, t, 0.9 * sw, used)
            add_down(v, s, sw, used)
    return v

def W(x): return 'W:' + (base(x) or x)

def put_w(v, x, w):
    k = W(x)
    if v[k] < w: v[k] = w

def add_tok(v, s, w, wf=0.6):
    if w <= 0: return
    k = 'S:%s%s' % s
    if v[k] < w: v[k] = w
    if wf:
        for l in syn[s][0]:
            for x in [l] + (l.split('_') if '_' in l else []): put_w(v, x, w * wf)

_desc = {}
def descendants(s):
    """How many kinds are below a sense (3 steps): a broad class (a facility, a person) has thousands."""
    if s not in _desc:
        n, frontier = 0, [s]
        for _ in range(3):
            frontier = [h for f in frontier for h in rel(f, HYPO)]; n += len(frontier)
            if n > 2000: break
        _desc[s] = n
    return _desc[s]

def add_down(v, s, w, used):
    add_tok(v, s, w)
    # its definition's words, and the ideas they name ("a grocer sells foodstuffs": foodstuff)
    for x in words(syn[s][2]):
        put_w(v, x, w * 0.3)
        b = base(x)
        for t in [y for y in idx.get(b or '', []) if y[0] == 'n'][:1]: add_tok(v, t, w * 0.3, 0)
    # its related forms, two steps (baker → bake → bakery), and what THEIR definitions name ("a bakery: where breads and
    # cakes are produced or sold"): what a business is about, which the hierarchy alone doesn't say
    hop, seen_d = [s], {s}
    for step in range(2):
        hop = [d for h in hop for d in rel(h, DERIV) if d not in seen_d]
        for d in hop:
            seen_d.add(d); dw = w * (0.8, 0.6)[step]
            add_tok(v, d, dw)
            for x in words(syn[d][2]):
                put_w(v, x, dw * 0.3)
                for t in [y for y in idx.get(base(x) or '', []) if y[0] == 'n'][:1]: add_tok(v, t, dw * 0.3, 0)
    for d in rel(s, MEMBERS)[:200]: add_tok(v, d, w * 0.45)
    if descendants(s) > 400: w *= 0.25   # a broad class reaches down only faintly
    frontier, ww, seen = [s], w, {s}
    for depth in range(3):
        ww *= (0.8, 0.75, 0.65)[depth]
        nxt = []
        for f in frontier:
            for h in rel(f, HYPO):
                if h in seen: continue
                seen.add(h); nxt.append(h); add_tok(v, h, ww)
                for d in rel(h, DERIV): add_tok(v, d, ww * 0.8)
        frontier = nxt[:400]

CV = {c['name']: cat_vector(c) for c in cats}
N = len(CV)
df = collections.Counter(t for v in CV.values() for t in v)
idf = {t: math.log(1 + N / n) for t, n in df.items()}
post = collections.defaultdict(list)
norm = {}
for name, v in CV.items():
    norm[name] = math.sqrt(sum((w * idf[t]) ** 2 for t, w in v.items())) or 1.0
    for t, w in v.items(): post[t].append((name, w))

# ---- a searched word: reach up, meet the categories ----------------------------------------------------------
def word_vector(lm, only=None):
    v = collections.defaultdict(float)
    senses = idx.get(lm, [])
    # nouns first (things people look for), then verbs and describing words; the most common senses count most
    order = [s for s in senses if s[0] == 'n'][:4] + [s for s in senses if s[0] == 'v'][:2] + [s for s in senses if s[0] in 'ar'][:2]
    for s, a in sense_weights(order):
        if any(sym == '@i' for sym, _, _ in syn[s][1]): continue   # a person or a place by name (George Washington, John Ford): not a kind of thing
        if only is not None:
            if s != only: continue
            a = 1.0
        add_tok(v, s, a)
        for x in words(syn[s][2]): put_w(v, x, a * 0.3)
        for t in rel(s, DOMAIN): add_tok(v, t, a * 0.5, 0.3)
        for t in rel(s, PERT): add_tok(v, t, a * 0.7)
        roots = [(s, a)] + [(d, a * 0.8) for d in rel(s, DERIV)]
        for r, ra in roots:
            add_tok(v, r, ra)
            # what it is a kind of, up to five steps: the idea itself counts, its words only one step up
            frontier, ww = [r], ra
            for depth in range(5):
                ww *= 0.7
                frontier = [h for f in frontier for h in rel(f, HYPER)]
                for h in frontier: add_tok(v, h, ww, 0.4 if depth == 0 else 0)
    return v

NORM_EXP = float(os.environ.get('CONCEPTS_NORM', '1'))
FLOOR = float(os.environ.get('CONCEPTS_FLOOR', '0.12'))
SENSE_FLOOR = float(os.environ.get('CONCEPTS_SENSE', '0.5'))

def cosines(q):
    qn = math.sqrt(sum((w * idf.get(t, 0)) ** 2 for t, w in q.items())) or 1.0
    acc = collections.defaultdict(float)
    for t, w in q.items():
        if t not in idf: continue
        f = w * idf[t] * idf[t]
        for name, cw in post[t]: acc[name] += f * cw
    return {n: s / (qn * norm[n] ** NORM_EXP) for n, s in acc.items()}

def match(lm, top=4, floor=None):
    """Each sense of the word meets the categories on its own; a rarely used sense counts for less, never for nothing
    ("x-ray" the radiation is commonest, the scan is what a directory search means)."""
    floor = FLOOR if floor is None else floor
    senses = idx.get(lm, [])
    order = [s for s in senses if s[0] == 'n'][:4] + [s for s in senses if s[0] == 'v'][:2] + [s for s in senses if s[0] in 'ar'][:2]
    best = collections.defaultdict(float)
    for s, a in sense_weights(order):
        g = SENSE_FLOOR + (1 - SENSE_FLOOR) * a
        for n, c in cosines(word_vector(lm, s)).items():
            if c * g > best[n]: best[n] = c * g
    res = sorted(((sc, n) for n, sc in best.items()), reverse=True)
    if not res: return []
    b0 = res[0][0]
    return [(n, round(sc, 3)) for sc, n in res[:top] if sc >= floor and sc >= b0 * 0.45]

if __name__ == '__main__' and os.environ.get('CONCEPTS_TRY'):
    for w in os.environ['CONCEPTS_TRY'].split(','):
        print(w, match(base(w) or w))
    sys.exit(0)

out, squash = {}, {}
# a seed is a direct way in: full strength, and a word with several categories keeps them all
direct = collections.defaultdict(dict)
for nm, terms in SEEDS.items():
    if nm not in CV: continue
    for t in terms:
        direct[t][nm] = 1.0
wn = {}
for lm in idx:
    if not re.match(r"^[a-z][a-z0-9_'\-]*$", lm) or len(lm) < 3: continue
    m = match(lm)
    if m: wn[lm.replace('_', ' ')] = m

VECTORS = os.environ.get('CONCEPTS_VECTORS', '')
if VECTORS:
    # word embeddings (decisions/0067, 2026-10-07): spaCy's en_core_web_lg vectors (Explosion Vectors, CC0: 514,000
    # words, each its own vector), read only here. A word meets each category's everyday words and name by meaning
    # ("burrito" sits near "tacos", "sedan" near "suv"), blended 60/40 with WordNet's link, which keeps the two honest
    # about each other. Measured on 110 words: right first for 43 of the 50 no seed has; sure (>= 0.60) and wrong for none.
    import numpy as np, spacy
    nlp = spacy.load(VECTORS, exclude=['tagger', 'parser', 'ner', 'lemmatizer', 'attribute_ruler', 'senter', 'tok2vec'])
    def evec(text):
        ws = [w for w in re.split(r'[^a-z0-9]+', text.lower()) if w and w not in STOP and w not in ABBR]
        vs = [nlp.vocab[w].vector for w in ws if nlp.vocab[w].has_vector]
        if not vs: return None
        v = np.mean(vs, axis=0); n = np.linalg.norm(v)
        return v / n if n else None
    T, owner = [], []
    for c in cats:
        if c['name'] not in CV: continue
        for t in SEEDS.get(c['name'], []) + [re.sub(r'\(.*?\)', '', c['name'])]:
            v = evec(t)
            if v is not None: T.append(v); owner.append(c['name'])
    T = np.array(T, dtype=np.float32); owner = np.array(owner)
    CN = sorted(set(owner)); CM = np.array([T[owner == n].mean(0) / np.linalg.norm(T[owner == n].mean(0)) for n in CN], dtype=np.float32)
    cidx = {n: i for i, n in enumerate(CN)}
    def emb(w):
        v = evec(w)
        if v is None: return {}
        sv = T @ v; cs = CM @ v; best = {}
        for i in np.argsort(-sv)[:60]:
            n = owner[i]
            if sv[i] > best.get(n, 0): best[n] = float(sv[i])
        return {n: 0.75 * b + 0.25 * float(cs[cidx[n]]) for n, b in best.items()}
    words = set(wn) | set(direct)
    for k in nlp.vocab.vectors.keys():
        st = nlp.vocab.strings[k] if k in nlp.vocab.strings else ''
        if re.match(r'^[a-z][a-z\-]{2,19}$', st): words.add(st)
    SURE, MIN = 0.60, 0.30
    for w in words:
        e = emb(w)
        n = {c: min(1.0, sc / 0.25) for c, sc in wn.get(w, [])}
        sc = sorted(((c, round(0.6 * e.get(c, 0) + 0.4 * n.get(c, 0), 3)) for c in set(e) | set(n)), key=lambda x: -x[1])
        sc = [(c, x) for c, x in sc if x >= MIN][:3]
        if sc: out[w] = sc
else:
    SURE, MIN = 0.08, 0.04   # WordNet's own scale
    out = dict(wn)
for t, cs in direct.items():   # seeds first, then the others only when they're sure ("ford": the dealers, not John Ford's films)
    have = dict(out.get(t, []))
    merged = sorted(cs.items(), key=lambda x: -have.get(x[0], 0)) + [(n, x) for n, x in sorted(have.items(), key=lambda x: -x[1]) if n not in cs and x >= SURE]
    out[t] = merged[:6]
for key in out:
    sq = re.sub(r'[^a-z0-9]', '', key)
    if sq != key: squash.setdefault(sq, key)

def php(s): return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
with open(OUT, 'w', encoding='utf-8') as f:
    f.write("<?php\n// Generated by workers/lexicon/build_concepts.py from WordNet 3.1 and the categories (decisions/0067). Do not edit: rebuild.\n")
    f.write("// 'sure' and 'min': the score at which a kind is what the words mean, and the least offered as a maybe. 'w': word => [[category, score]], best first. 's': a word written without its spaces or hyphens => the word.\n")
    names = sorted({n for m in out.values() for n, _ in m})
    nid = {n: i for i, n in enumerate(names)}
    f.write("return ['sure'=>%s,'min'=>%s,'c'=>[" % (SURE, MIN) + ','.join(php(n) for n in names) + "],\n'w'=>[")
    f.write(','.join(php(k) + '=>[' + ','.join('[%d,%s]' % (nid[n], s) for n, s in m) + ']' for k, m in out.items()))
    f.write("],\n's'=>[" + ','.join(php(k) + '=>' + php(v) for k, v in squash.items()) + "]];\n")
print('words', len(out), 'squash', len(squash), 'categories reached', len({n for m in out.values() for n, _ in m}))
