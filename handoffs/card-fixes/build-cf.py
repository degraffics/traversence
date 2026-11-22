#!/usr/bin/env python3
"""Cards and photos (handoffs/screens-proposal-2026-11-22.md: A4-A6, A13, B3; Jason's approval 2026-11-22). Reads the
live mirror, writes out/. Replayable."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:100], s.count(a)); return s.replace(a, b)
put('release.json', open(os.path.join(S, 'src', 'release.json'), encoding='utf-8').read())

f = 'website/discovery/index.php'; s = live(f)
# A4 at its cause: no distance when the place's own point isn't known (none, or only its ZIP's middle: Geocoder::APPROX, the
# rule the geocoder already uses); a real point under half a mile says "under ½ mi" (0060: "near me" with miles)
s = rep(s, "function dv_min_miles(float $lat, float $lon, array $pts): float\n",
           "/**\n * A card's distance (decisions/0060: with miles), or '' when it isn't known: no point, or the place's point is only its\n"
           " * ZIP area's middle ($approx: Geocoder::APPROX), which read \"0 mi\" from its own town (Jason, 2026-11-22). A real point\n"
           " * under half a mile says so, never \"0 mi\".\n */\n"
           "function dv_mi($m, bool $approx = false): string\n{\n    if ($approx || $m === null || !is_numeric($m) || !is_finite((float) $m)) return '';\n"
           "    return (float) $m < 0.5 ? 'under <b>½</b> mi' : '<b>' . number_format((float) $m, 0) . '</b> mi';\n}\n\n"
           "/**\n * A Commons photo's credit for a card, both parts shown on the photo (decisions/0164: the licence decides the use): who\n"
           " * took it (© for a licence that keeps the author's rights) and its licence, never only in a hover. ['by', 'lic', 'tip'].\n */\n"
           "function dv_credit(string $author, string $licence): array\n{\n"
           "    $author = trim(strip_tags($author)); $lic = trim($licence);\n"
           "    if (preg_match('/^cc0\\b/i', $lic)) $lic = 'CC0';\n"
           "    elseif (preg_match('/^(public domain|pd\\b|pd-)/i', $lic)) $lic = 'Public domain';\n"
           "    elseif (preg_match('/^cc[ -]by(-sa)?(?:[ -]([0-9.]+))?/i', $lic, $m)) $lic = 'CC BY' . (!empty($m[1]) ? '-SA' : '') . (!empty($m[2]) ? ' ' . $m[2] : '');\n"
           "    $free = $lic === 'CC0' || $lic === 'Public domain';\n"
           "    $by = $author !== '' ? ($free ? '' : '© ') . mb_strimwidth($author, 0, 48, '…') : 'Wikimedia Commons';\n"
           "    return ['by' => $by, 'lic' => $lic, 'tip' => 'Photo: ' . ($author !== '' ? $author . ', ' : '') . 'Wikimedia Commons' . ($lic !== '' ? ', ' . $lic : '')];\n}\n\n"
           "function dv_min_miles(float $lat, float $lon, array $pts): float\n")
s = rep(s, "'stats' => isset($pk['miles']) ? ['<b>' . number_format((float) $pk['miles'], 0) . '</b> mi'] : [],",
           "'stats' => array_values(array_filter([dv_mi($pk['miles'] ?? null)])),")
s = rep(s, "'stats' => isset($r['miles']) ? ['<b>' . number_format((float) $r['miles'], 0) . '</b> mi'] : [],",
           "'stats' => array_values(array_filter([dv_mi($r['miles'] ?? null, !empty($r['approx']))])),")
# the listing's point: its own, or only its ZIP's middle (the geocoder's rule, one copy)
s = rep(s, "            $st = $pdo->query(\"SELECT e.id, e.name, e.category_id, c.name AS cat, e.latitude AS lat, e.longitude AS lon,\n",
           "            require_once __DIR__ . '/../../app/lib/Geocoder.php';\n"
           "            $st = $pdo->query(\"SELECT e.id, e.name, e.category_id, c.name AS cat, e.latitude AS lat, e.longitude AS lon, \" . Geocoder::APPROX . \" AS approx,\n")
s = rep(s, "        'stats' => array_values(array_filter([isset($r['miles']) ? '<b>' . number_format((float) $r['miles'], 0) . '</b> mi' : '',\n"
           "                                              ($r['image_url'] ?? '') !== '' && ($r['image_credit'] ?? '') !== '' ? 'Photo: ' . tv_e(mb_strimwidth((string) $r['image_credit'], 0, 60, '…')) . ', Wikimedia Commons' : ''])),",
           "        'stats' => array_values(array_filter([dv_mi($r['miles'] ?? null)])),\n"
           "        'credit' => ($r['image_url'] ?? '') !== '' ? dv_credit((string) ($r['image_credit'] ?? ''), (string) ($r['image_license'] ?? '')) : null,")
s = rep(s, "SELECT gnis_id, name, fclass, state, near_town, lat, lon, nation, wd_desc, image_url, image_credit, wiki_title FROM landmarks\n                                  WHERE \" . Landmarks::visibleSql() . \" AND state = ?",
           "SELECT gnis_id, name, fclass, state, near_town, lat, lon, nation, wd_desc, image_url, image_credit, image_license, wiki_title FROM landmarks\n                                  WHERE \" . Landmarks::visibleSql() . \" AND state = ?")
s = rep(s, "$st = $pdo->prepare(\"SELECT LOWER(name) AS n, state, image_url, image_credit FROM landmarks WHERE \"",
           "$st = $pdo->prepare(\"SELECT LOWER(name) AS n, state, image_url, image_credit, image_license FROM landmarks WHERE \"")
# A5: each photo's credit on the photo with an i (Jason, 2026-11-17), not a line under every card
s = rep(s, "            if ((string) $same[0]['image_credit'] !== '') $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth((string) $same[0]['image_credit'], 0, 60, '…')) . ', Wikimedia Commons';",
           "            $c['credit'] = dv_credit((string) $same[0]['image_credit'], (string) ($same[0]['image_license'] ?? ''));")
s = rep(s, "        if ($fp) { $c['image'] = $fp['image']; $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth($fp['credit'] !== '' ? $fp['credit'] : 'Wikimedia Commons', 0, 60, '…')) . ($fp['credit'] !== '' ? ', Wikimedia Commons' : ''); continue; }",
           "        if ($fp) { $c['image'] = $fp['image']; $c['credit'] = dv_credit((string) $fp['credit'], (string) ($fp['license'] ?? '')); continue; }")
# B3: no stock photo on a memorial or monument (their own, Commons, else the pattern), and never one stock photo twice in a list
s = rep(s, "        try {\n            require_once __DIR__ . '/../../app/lib/StockLibrary.php';\n            $stk = StockLibrary::forSlot($pdo, 'card', 'park:'",
           "        if (preg_match('/\\b(memorial|monument)s?\\b/i', (string) $c['name'] . ' ' . ($eid ? (string) ($c['text'] ?? '') : ''))) continue;   // a memorial or monument: its own photo, Commons, or the pattern, never stock (Jason, 2026-11-22)\n"
           "        try {\n            require_once __DIR__ . '/../../app/lib/StockLibrary.php';\n            $stk = StockLibrary::forSlot($pdo, 'card', 'park:'")
s = rep(s, "        if ($stk) {   // labelled on the card: it's a stock photo, of the kind of place, not this one (decisions/0143)\n"
           "            $c['image'] = (string) ($stk['card'] ?: $stk['url']);\n"
           "            $c['stats'][] = 'Stock photo (representative) · ' . tv_e(trim((string) $stk['author']) !== '' ? $stk['author'] . ' on ' . $stk['source_name'] : (string) $stk['source_name']);\n        }",
           "        if ($stk && isset($stockSeen[(int) ($stk['id'] ?? 0)])) $stk = null;   // never the same stock photo twice in one list: the pattern instead (Jason, 2026-11-22)\n"
           "        if ($stk) {   // labelled on the photo: it's a stock photo, of the kind of place, not this one (decisions/0143)\n"
           "            $stockSeen[(int) ($stk['id'] ?? 0)] = true;\n"
           "            $c['image'] = (string) ($stk['card'] ?: $stk['url']);\n"
           "            $by = trim((string) $stk['author']) !== '' ? $stk['author'] . ' on ' . $stk['source_name'] : (string) $stk['source_name'];\n"
           "            $c['credit'] = ['by' => 'Stock photo · ' . $by, 'lic' => '', 'tip' => 'Stock photo (representative): it shows the kind of place, not this one. Photo: ' . $by];\n        }")
s = rep(s, "    foreach ($cards as &$c) {\n        $eid = (int) ($c['eid'] ?? 0);\n        if ($eid && isset($hero[$eid]))",
           "    $stockSeen = [];\n    foreach ($cards as &$c) {\n        $eid = (int) ($c['eid'] ?? 0);\n        if ($eid && isset($hero[$eid]))")
put(f, s)

# A5, A6: the card shows its photo's credit on the photo, with an i; the corner icon on a dark disc so it reads on any sky
f = 'app/ui/explore-hero.php'; s = live(f)
s = rep(s, "    $c += ['image' => '', 'badge' => '', 'icon' => 'compass', 'kicker' => '', 'text' => '', 'stats' => [], 'chips' => [], 'url' => ''];",
           "    $c += ['image' => '', 'badge' => '', 'icon' => 'compass', 'kicker' => '', 'text' => '', 'stats' => [], 'chips' => [], 'url' => '', 'credit' => null];")
s = rep(s, "        . '<span class=\"xc-ic\">' . tv_icon((string) $c['icon']) . '</span><b>'",
           "        . (is_array($c['credit']) && $c['image'] !== '' ? '<span class=\"xc-cr\" title=\"' . tv_e((string) $c['credit']['tip']) . '\" aria-label=\"' . tv_e((string) $c['credit']['tip']) . '\"><span class=\"xc-by\" aria-hidden=\"true\">' . tv_e((string) $c['credit']['by']) . '</span>' . ((string) ($c['credit']['lic'] ?? '') !== '' ? '<span class=\"xc-lic\" aria-hidden=\"true\">' . tv_e((string) $c['credit']['lic']) . '</span>' : '') . '<i aria-hidden=\"true\">i</i></span>' : '')   // the photo's credit on it, who and the licence in words, with an i (Jason, 2026-11-17; decisions/0164)\n"
           "        . '<span class=\"xc-ic\">' . tv_icon((string) $c['icon']) . '</span><b>'")
s = rep(s, "  .xc-ic{position:absolute;top:.75rem;left:.85rem;width:34px;height:34px;border-radius:999px;background:rgba(255,251,242,.18);border:1px solid rgba(255,251,242,.45);",
           "  .xc-ic{position:absolute;top:.75rem;left:.85rem;width:34px;height:34px;border-radius:999px;background:rgba(42,27,18,.62);border:1px solid rgba(255,251,242,.55);")
s = rep(s, "  .xc.off{opacity:.78}",
           "  .xc-cr{position:absolute;top:.6rem;right:.6rem;max-width:calc(100% - 3.9rem);display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;column-gap:.35rem;row-gap:0;padding:.15rem .22rem .15rem .5rem;border-radius:.6rem;background:rgba(42,27,18,.8);color:#FFFBF2;font:500 .68rem/1.3 var(--tv-font-ui);text-align:right}\n"
           "  /* who and the licence both readable on the narrowest card: the name wraps to two lines before it's shortened, the licence never is */\n"
           "  .xc-cr .xc-by{min-width:0;overflow:hidden;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.xc-cr .xc-lic{flex:none;font-weight:700;white-space:nowrap}.xc-cr i{flex:none;width:1rem;height:1rem;border-radius:999px;border:1.5px solid #FFFBF2;font:italic 700 .62rem/1 var(--tv-font-head,Georgia,serif);display:inline-flex;align-items:center;justify-content:center}\n"
           "  .xc-badge~.xc-cr{top:2.4rem}\n"
           "  .xc.off{opacity:.78}")
put(f, s)

# A13: a public place (or one on a nation's land) isn't "Unclaimed": it shows who runs it, from its Nexus (decisions/0070)
f = 'website/listing/view.php'; s = live(f)
s = rep(s, "} else {\n    $badges[] = ['Unclaimed', 'bg-[var(--tv-card-b)] text-[color:var(--tv-ink)]'];\n}",
           "} else {\n    require_once __DIR__ . '/../../app/lib/Claims.php';\n"
           "    $ckind = Claims::kind($pdo, (int) $biz['id']);\n"
           "    if ($ckind === 'public' || $ckind === 'nation') {   // a public place shows who runs it (decisions/0070's claim-kinds note), never \"Unclaimed\"\n"
           "        $ag = Claims::agency($pdo, (int) $biz['id']);\n"
           "        if ($ag) $badges[] = ['Run by ' . $ag['name'], 'bg-[var(--tv-card-b)] text-[color:var(--tv-ink)]'];\n"
           "    } else {\n        $badges[] = ['Unclaimed', 'bg-[var(--tv-card-b)] text-[color:var(--tv-ink)]'];\n    }\n}")
put(f, s)
# the licence for the card's visible credit: PlacePhotos::for returns it, Landmarks::top selects it (both already stored)
f = 'app/lib/PlacePhotos.php'; s = live(f)
s = rep(s, "SELECT owner_ref, image_url, image_credit, image_page FROM place_photos WHERE owner_kind = ?",
           "SELECT owner_ref, image_url, image_credit, image_page, image_license FROM place_photos WHERE owner_kind = ?")
s = rep(s, "'page' => (string) $r['image_page']];", "'page' => (string) $r['image_page'], 'license' => (string) $r['image_license']];")
put(f, s)
f = 'app/lib/Landmarks.php'; s = live(f)
s = rep(s, "nation, wd_desc, image_url, image_credit, wiki_title FROM landmarks\n                              WHERE \" . self::visibleSql() . \" AND image_url <> ''",
           "nation, wd_desc, image_url, image_credit, image_license, wiki_title FROM landmarks\n                              WHERE \" . self::visibleSql() . \" AND image_url <> ''")
put(f, s)
# FAQ: Parks & Landmarks' cards, said as they now are (anchor kept)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "The parks shown are the ones near the place you're exploring (within 150 miles of its towns, nearest first, with the miles), never the whole country by name.",
           "The parks shown are the ones near the place you're exploring (within 150 miles of its towns, nearest first, with the miles; one closer than half a mile says \"under ½ mi\", and one we know only by its town or ZIP shows no distance rather than a wrong one), never the whole country by name.")
s = rep(s, "else a stock photo labelled **Stock photo (representative)** with who took it.",
           "else a stock photo of the kind of place. Each photo's credit is written on the photo itself: who took it and its licence (\"© Ann Lee · CC BY-SA 4.0\"), or **Stock photo** for a representative stock photo of the kind of place, with an **i** (point at it, or press and hold, for the full credit). The same stock photo never shows twice in one list, and a memorial or monument never gets one: it shows its own photo, a Commons photo, or the pattern. A public place, like a state park or a memorial, shows the agency that runs it (\"Run by Arizona State Parks & Trails\") rather than \"Unclaimed\".")
s = rep(s, ' A public place, like a state park or a memorial, shows the agency that runs it ("Run by Arizona State Parks & Trails") rather than "Unclaimed". Only when there\'s none of those does it show our map pattern.',
           ' Only when there\'s none of those does it show our map pattern. A public place, like a state park or a memorial, shows the agency that runs it ("Run by Arizona State Parks & Trails") rather than "Unclaimed".')
put(f, s)
print('built', sorted(os.path.relpath(os.path.join(dp, x), O) for dp, _, fs in os.walk(O) for x in fs))
