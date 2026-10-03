#!/usr/bin/env python3
"""
Builds api/lib/data/word-families.php from WordNet 3.1 (decisions/0064): each common noun mapped to the family of things
search acts on (belongings, documents, pets, vehicles…), and every irregular verb form to its base form (found -> find).

Usage: python3 build_families.py /path/to/wordnet31 /path/to/sync-storage/api/lib/data/word-families.php

Only a noun's most common sense counts ("key" the metal one, not the musical one). WordNet: Princeton University,
"About WordNet", 2010 (license in word-families.LICENSE). Run again to rebuild; nothing else reads WordNet.
"""
import os, re, sys

WN, OUT = sys.argv[1], sys.argv[2]

# families, most specific first: (class, [root senses as lemma.n.N])
FAMILIES = [
    ("tire", ["tire.n.01"]),
    ("keys", ["key.n.01"]),
    ("eye", ["spectacles.n.01", "contact_lens.n.01"]),
    ("documents", ["document.n.01", "document.n.02", "document.n.03", "identification.n.02", "passport.n.01", "license.n.01", "certificate.n.01", "card.n.01", "card.n.02"]),
    ("money", ["money.n.01", "money.n.03", "currency.n.01", "credit_card.n.01", "check.n.01"]),
    ("pet", ["dog.n.01", "domestic_cat.n.01", "domestic_animal.n.01", "pony.n.01", "horse.n.01"]),
    ("creature", ["animal.n.01"]),
    ("device", ["telephone.n.01", "computer.n.01", "electronic_device.n.01", "electronic_equipment.n.01", "home_appliance.n.01", "camera.n.01"]),
    ("vehicle", ["motor_vehicle.n.01", "bicycle.n.01", "boat.n.01", "trailer.n.01", "wheeled_vehicle.n.01"]),
    ("power-tool", ["power_tool.n.01"]),
    ("hand-tool", ["hand_tool.n.01"]),
    ("plumbing", ["plumbing_fixture.n.01"]),
    ("meds", ["medicine.n.02", "drug.n.01"]),
    ("food", ["food.n.01", "food.n.02"]),
    ("belongings", ["bag.n.01", "bag.n.04", "bag.n.06", "case.n.05", "luggage.n.01", "jewelry.n.01", "clothing.n.01", "watch.n.01", "umbrella.n.01",
                    "personal_property.n.01", "toy.n.01", "sports_equipment.n.01"]),
]

# corrections where the most common WordNet sense isn't the one people mean in a search, and everyday words it lacks
OVERRIDES = {
    "cat": "pet", "cats": "pet", "kitten": "pet", "kitty": "pet", "puppy": "pet", "bunny": "pet", "rabbit": "pet", "hamster": "pet", "parrot": "pet",
    "drone": "device", "iphone": "device", "smartphone": "device", "cell phone": "device", "tablet": "device", "ipad": "device", "headphones": "device",
    "earbuds": "device", "airpods": "device", "charger": "device", "id": "documents", "id card": "documents", "drivers license": "documents",
    "social security card": "documents", "birth certificate": "documents", "green card": "documents", "ring": "belongings", "earrings": "belongings",
    "bracelet": "belongings", "purse": "belongings", "wallet": "belongings", "keychain": "keys", "key fob": "keys", "fob": "keys",
    "drill": "power-tool", "saw": "power-tool", "grinder": "power-tool", "hammer": "hand-tool", "wrench": "hand-tool", "screwdriver": "hand-tool",
    "toilet": "plumbing", "insulin": "meds", "inhaler": "meds", "epipen": "meds", "jack": None, "bug": "creature",
}

def lines(name):
    with open(os.path.join(WN, name), encoding="utf-8", errors="replace") as f:
        for ln in f:
            if not ln.startswith("  "):
                yield ln.rstrip("\n")

# index.noun: lemma -> synset offsets, most common sense first
index = {}
for ln in lines("index.noun"):
    p = ln.split()
    lemma, n_syn, n_ptr = p[0], int(p[2]), int(p[3])
    offs = p[4 + n_ptr + 2:]
    index[lemma] = offs[:n_syn]

# data.noun: offset -> hypernyms (and instance hypernyms)
hyper, words = {}, {}
for ln in lines("data.noun"):
    p = ln.split(" | ")[0].split()
    off, w_cnt = p[0], int(p[3], 16)
    ws = [p[4 + 2 * i].lower() for i in range(w_cnt)]
    i = 4 + 2 * w_cnt
    n_ptr = int(p[i]); i += 1
    hs = []
    for _ in range(n_ptr):
        sym, target, pos = p[i], p[i + 1], p[i + 2]
        if pos == "n" and sym in ("@", "@i"):
            hs.append(target)
        i += 4
    hyper[off], words[off] = hs, ws

def sense(name):
    lemma, _, n = name.rsplit(".", 2)
    offs = index.get(lemma)
    return offs[int(n) - 1] if offs and int(n) <= len(offs) else None

roots = []
for cls, names in FAMILIES:
    for nm in names:
        off = sense(nm)
        if off:
            roots.append((off, cls))
        else:
            print("no such sense:", nm, file=sys.stderr)
rootcls = {}
for off, cls in roots:
    rootcls.setdefault(off, cls)
order = {cls: i for i, (cls, _) in enumerate(FAMILIES)}

def family(off):
    best, seen, todo, depth = None, set(), [off], 0
    while todo and depth < 14:
        nxt = []
        for o in todo:
            if o in seen:
                continue
            seen.add(o)
            c = rootcls.get(o)
            if c and (best is None or order[c] < order[best]):
                best = c
            nxt += hyper.get(o, [])
        todo, depth = nxt, depth + 1
    return best

nouns = {}
for lemma, offs in index.items():
    if not offs or not re.match(r"^[a-z][a-z'_-]{1,30}$", lemma) or lemma.count("_") > 2:
        continue
    c = family(offs[0])            # its most common sense only
    if c:
        nouns[lemma.replace("_", " ")] = c

for w, c in OVERRIDES.items():
    if c is None:
        nouns.pop(w, None)
    else:
        nouns[w] = c

verbs = {}
for ln in lines("verb.exc"):
    p = ln.split()
    if len(p) >= 2 and re.match(r"^[a-z]+$", p[0]) and re.match(r"^[a-z_]+$", p[1]):
        verbs.setdefault(p[0], p[1].replace("_", " "))
plurals = {}
for ln in lines("noun.exc"):
    p = ln.split()
    if len(p) >= 2 and re.match(r"^[a-z]+$", p[0]):
        plurals.setdefault(p[0], p[1].replace("_", " "))

# every single-word English lemma with its parts of speech (n, v, a, r), so any real word is known (decisions/0064)
allwords = {}
for pos, fname in (("n", "index.noun"), ("v", "index.verb"), ("a", "index.adj"), ("r", "index.adv")):
    for ln in lines(fname):
        w = ln.split(" ", 1)[0]
        if re.match(r"^[a-z]{2,20}$", w):
            allwords[w] = allwords.get(w, "") + pos

def php(d):
    return "[" + ",".join("'%s'=>'%s'" % (k.replace("'", "\\'"), v.replace("'", "\\'")) for k, v in sorted(d.items())) + "]"

with open(OUT, "w", encoding="utf-8") as f:
    f.write("<?php\n// Generated by workers/lexicon/build_families.py from WordNet 3.1 (decisions/0064). Do not edit: rebuild.\n")
    f.write("// n: noun => family; v: irregular verb form => base form; p: irregular plural => singular.\n")
    f.write("return ['n'=>" + php(nouns) + ",\n'v'=>" + php(verbs) + ",\n'p'=>" + php(plurals) + "];\n")
# the whole vocabulary on its own: read only when a word isn't otherwise known
with open(os.path.join(os.path.dirname(OUT), "word-list.php"), "w", encoding="utf-8") as f:
    f.write("<?php\n// Generated by workers/lexicon/build_families.py from WordNet 3.1 (decisions/0064). Do not edit: rebuild.\n")
    f.write("// Every single-word English lemma => its parts of speech (n noun, v verb, a adjective, r adverb).\n")
    f.write("return " + php(allwords) + ";\n")
counts = {}
for c in nouns.values():
    counts[c] = counts.get(c, 0) + 1
print(len(allwords), "words;", len(nouns), "nouns;", len(verbs), "verb forms;", len(plurals), "plurals;", counts)
