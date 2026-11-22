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
# A4: a distance only when it says something: under half a mile from the nearest point of the place is "here", not "0 mi"
s = rep(s, "function dv_min_miles(float $lat, float $lon, array $pts): float\n",
           "/** A card's distance, or '' when it says nothing: unknown, or under half a mile from the place's nearest point (a place\n"
           " * placed only by its town is 0 mi from that town; Jason, 2026-11-22: distance only when known). */\n"
           "function dv_mi($m): string\n{\n    if ($m === null || !is_numeric($m) || !is_finite((float) $m) || (float) $m < 0.5) return '';\n"
           "    return '<b>' . number_format((float) $m, 0) . '</b> mi';\n}\n\nfunction dv_min_miles(float $lat, float $lon, array $pts): float\n")
s = rep(s, "'stats' => isset($pk['miles']) ? ['<b>' . number_format((float) $pk['miles'], 0) . '</b> mi'] : [],",
           "'stats' => array_values(array_filter([dv_mi($pk['miles'] ?? null)])),")
s = rep(s, "'stats' => isset($r['miles']) ? ['<b>' . number_format((float) $r['miles'], 0) . '</b> mi'] : [],",
           "'stats' => array_values(array_filter([dv_mi($r['miles'] ?? null)])),")
s = rep(s, "        'stats' => array_values(array_filter([isset($r['miles']) ? '<b>' . number_format((float) $r['miles'], 0) . '</b> mi' : '',\n"
           "                                              ($r['image_url'] ?? '') !== '' && ($r['image_credit'] ?? '') !== '' ? 'Photo: ' . tv_e(mb_strimwidth((string) $r['image_credit'], 0, 60, '…')) . ', Wikimedia Commons' : ''])),",
           "        'stats' => array_values(array_filter([dv_mi($r['miles'] ?? null)])),\n"
           "        'credit' => ($r['image_url'] ?? '') !== '' && ($r['image_credit'] ?? '') !== '' ? ['by' => mb_strimwidth((string) $r['image_credit'], 0, 60, '…') . ', Wikimedia Commons', 'tip' => 'Photo: ' . (string) $r['image_credit'] . ', Wikimedia Commons'] : null,")
# A5: each photo's credit on the photo with an i (Jason, 2026-11-17), not a line under every card
s = rep(s, "            if ((string) $same[0]['image_credit'] !== '') $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth((string) $same[0]['image_credit'], 0, 60, '…')) . ', Wikimedia Commons';",
           "            if ((string) $same[0]['image_credit'] !== '') $c['credit'] = ['by' => mb_strimwidth((string) $same[0]['image_credit'], 0, 60, '…') . ', Wikimedia Commons', 'tip' => 'Photo: ' . (string) $same[0]['image_credit'] . ', Wikimedia Commons'];")
s = rep(s, "        if ($fp) { $c['image'] = $fp['image']; $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth($fp['credit'] !== '' ? $fp['credit'] : 'Wikimedia Commons', 0, 60, '…')) . ($fp['credit'] !== '' ? ', Wikimedia Commons' : ''); continue; }",
           "        if ($fp) { $c['image'] = $fp['image']; $by = $fp['credit'] !== '' ? $fp['credit'] . ', Wikimedia Commons' : 'Wikimedia Commons'; $c['credit'] = ['by' => mb_strimwidth($by, 0, 72, '…'), 'tip' => 'Photo: ' . $by]; continue; }")
# B3: no stock photo on a memorial or monument (their own, Commons, else the pattern), and never one stock photo twice in a list
s = rep(s, "        try {\n            require_once __DIR__ . '/../../app/lib/StockLibrary.php';\n            $stk = StockLibrary::forSlot($pdo, 'card', 'park:'",
           "        if (preg_match('/\\b(memorial|monument)s?\\b/i', (string) $c['name'] . ' ' . (string) ($c['text'] ?? ''))) continue;   // a memorial or monument: its own photo, Commons, or the pattern, never stock (Jason, 2026-11-22)\n"
           "        try {\n            require_once __DIR__ . '/../../app/lib/StockLibrary.php';\n            $stk = StockLibrary::forSlot($pdo, 'card', 'park:'")
s = rep(s, "        if ($stk) {   // labelled on the card: it's a stock photo, of the kind of place, not this one (decisions/0143)\n"
           "            $c['image'] = (string) ($stk['card'] ?: $stk['url']);\n"
           "            $c['stats'][] = 'Stock photo (representative) · ' . tv_e(trim((string) $stk['author']) !== '' ? $stk['author'] . ' on ' . $stk['source_name'] : (string) $stk['source_name']);\n        }",
           "        if ($stk && isset($stockSeen[(int) ($stk['id'] ?? 0)])) $stk = null;   // never the same stock photo twice in one list: the pattern instead (Jason, 2026-11-22)\n"
           "        if ($stk) {   // labelled on the photo: it's a stock photo, of the kind of place, not this one (decisions/0143)\n"
           "            $stockSeen[(int) ($stk['id'] ?? 0)] = true;\n"
           "            $c['image'] = (string) ($stk['card'] ?: $stk['url']);\n"
           "            $by = trim((string) $stk['author']) !== '' ? $stk['author'] . ' on ' . $stk['source_name'] : (string) $stk['source_name'];\n"
           "            $c['credit'] = ['by' => $by, 'tip' => 'Stock photo (representative): it shows the kind of place, not this one. Photo: ' . $by];\n        }")
s = rep(s, "    foreach ($cards as &$c) {\n        $eid = (int) ($c['eid'] ?? 0);\n        if ($eid && isset($hero[$eid]))",
           "    $stockSeen = [];\n    foreach ($cards as &$c) {\n        $eid = (int) ($c['eid'] ?? 0);\n        if ($eid && isset($hero[$eid]))")
put(f, s)

# A5, A6: the card shows its photo's credit on the photo, with an i; the corner icon on a dark disc so it reads on any sky
f = 'app/ui/explore-hero.php'; s = live(f)
s = rep(s, "    $c += ['image' => '', 'badge' => '', 'icon' => 'compass', 'kicker' => '', 'text' => '', 'stats' => [], 'chips' => [], 'url' => ''];",
           "    $c += ['image' => '', 'badge' => '', 'icon' => 'compass', 'kicker' => '', 'text' => '', 'stats' => [], 'chips' => [], 'url' => '', 'credit' => null];")
s = rep(s, "        . '<span class=\"xc-ic\">' . tv_icon((string) $c['icon']) . '</span><b>'",
           "        . (is_array($c['credit']) && $c['image'] !== '' ? '<span class=\"xc-cr\" title=\"' . tv_e((string) $c['credit']['tip']) . '\" aria-label=\"' . tv_e((string) $c['credit']['tip']) . '\"><span>Photo: ' . tv_e((string) $c['credit']['by']) . '</span><i aria-hidden=\"true\">i</i></span>' : '')   // the photo's credit on it, with an i (Jason, 2026-11-17)\n"
           "        . '<span class=\"xc-ic\">' . tv_icon((string) $c['icon']) . '</span><b>'")
s = rep(s, "  .xc-ic{position:absolute;top:.75rem;left:.85rem;width:34px;height:34px;border-radius:999px;background:rgba(255,251,242,.18);border:1px solid rgba(255,251,242,.45);",
           "  .xc-ic{position:absolute;top:.75rem;left:.85rem;width:34px;height:34px;border-radius:999px;background:rgba(42,27,18,.62);border:1px solid rgba(255,251,242,.55);")
s = rep(s, "  .xc.off{opacity:.78}",
           "  .xc-cr{position:absolute;top:.6rem;right:.6rem;max-width:calc(100% - 4.5rem);display:inline-flex;align-items:center;gap:.3rem;padding:.12rem .2rem .12rem .5rem;border-radius:999px;background:rgba(42,27,18,.8);color:#FFFBF2;font:500 .68rem/1.3 var(--tv-font-ui)}\n"
           "  .xc-cr span{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.xc-cr i{flex:none;width:1rem;height:1rem;border-radius:999px;border:1.5px solid #FFFBF2;font:italic 700 .62rem/1 var(--tv-font-head,Georgia,serif);display:inline-flex;align-items:center;justify-content:center}\n"
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
# FAQ: Parks & Landmarks' cards, said as they now are (anchor kept)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "The parks shown are the ones near the place you're exploring (within 150 miles of its towns, nearest first, with the miles), never the whole country by name.",
           "The parks shown are the ones near the place you're exploring (within 150 miles of its towns, nearest first, with the miles; a park in a town shows no distance rather than \"0 mi\"), never the whole country by name.")
s = rep(s, "else a stock photo labelled **Stock photo (representative)** with who took it.",
           "else a stock photo of the kind of place. Each photo's credit sits on the photo with an **i** (point at it, or press and hold, for whose photo it is and whether it's a representative stock photo). The same stock photo never shows twice in one list, and a memorial or monument never gets one: it shows its own photo, a Commons photo, or the pattern. A public place, like a state park or a memorial, shows the agency that runs it (\"Run by Arizona State Parks & Trails\") rather than \"Unclaimed\".")
put(f, s)
print('built', sorted(os.path.relpath(os.path.join(dp, x), O) for dp, _, fs in os.walk(O) for x in fs))
