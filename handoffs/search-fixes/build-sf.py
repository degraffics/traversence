#!/usr/bin/env python3
"""Search fixes (handoffs/screens-proposal-2026-11-22.md: A1-A3, B1, B2; Jason's approval 2026-11-22). Reads the live
mirror, writes out/. Replayable."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:100], s.count(a)); return s.replace(a, b)

put('release.json', open(os.path.join(S, 'src', 'release.json'), encoding='utf-8').read())

# A1: a section's count is what's listed under it (categories are a way in, in Suggestions: 0058 §38)
f = 'app/lib/SearchIndex.php'; s = live(f)
s = rep(s, "foreach ($out as $x) { $kinds[$x['kind']] = ($kinds[$x['kind']] ?? 0) + 1; $funnels[$x['funnel']] = ($funnels[$x['funnel']] ?? 0) + 1; }",
           "foreach ($out as $x) { $kinds[$x['kind']] = ($kinds[$x['kind']] ?? 0) + 1; if ($x['kind'] !== 'topic') $funnels[$x['funnel']] = ($funnels[$x['funnel']] ?? 0) + 1; }   // a section counts what's listed under it, never the categories (2026-11-22)")
# A2: outdoor places peek too (the peek card already reads them: UniversalSearch::peekOutdoors)
s = rep(s, "'peek' => $r['kind'] === 'listing',", "'peek' => $r['kind'] === 'listing' || $r['kind'] === 'outdoors',")
put(f, s)

f = 'website/js/search-panel.js'; s = live(f)
# A2: the tabs take Search Directory's names, as the rows' tags do (decisions/0058 §38)
s = rep(s, "return f.count ? '<button type=\"button\" role=\"tab\" class=\"tvs-tab' + (funnel === f.key ? ' on' : '') + '\" data-funnel=\"' + f.key + '\">' + esc(f.label) + ' <small>' + f.count + '</small></button>' : '';",
           "return f.count ? '<button type=\"button\" role=\"tab\" class=\"tvs-tab' + (funnel === f.key ? ' on' : '') + '\" data-funnel=\"' + f.key + '\">' + esc(SECTION[f.key] || f.label) + ' <small>' + f.count + '</small></button>' : '';   // named as in Search Directory, like the rows' tags (0058 §38)")
s = rep(s, "h += '<p class=\"tvs-lbl tvs-sub\">' + esc(f.label) + ' <span class=\"tvs-muted\">(' + f.count + ')</span></p>'",
           "h += '<p class=\"tvs-lbl tvs-sub\">' + esc(SECTION[f.key] || f.label) + ' <span class=\"tvs-muted\">(' + f.count + ')</span></p>'")
s = rep(s, "      if (fx && fx.url) h += '<a class=\"tvs-more\" href=\"' + esc(fx.url) + '\">Open ' + esc(fx.label) + ' &rarr;</a>';",
           "      if (fx && fx.url) h += '<a class=\"tvs-more\" href=\"' + esc(fx.url) + '\">Open ' + esc(fx.label) + ' &rarr;</a>';   // the section's own page keeps its own name")
# A2: Peek on every row with a page: listings and outdoor places in the peek card, the rest in the page peek (tv-ask.js)
s = rep(s, "(it.peek ? peekBtn(it) : '<span aria-hidden=\"true\">&rsaquo;<",
           "(it.peek || (it.url && it.kind !== 'topic') ? peekBtn(it) : '<span aria-hidden=\"true\">&rsaquo;<", 2)
s = rep(s, "  function peekBtn(it) {\n    return '<span class=\"tvs-peekb\" role=\"button\" tabindex=\"0\" data-peek=\"' + esc(String(it.id)) + '\"",
           "  function peekBtn(it) {   // a listing by its number, an outdoor place as rec:<id>, any other row by its page (2026-11-22)\n"
           "    var pid = it.kind === 'outdoors' ? 'rec:' + it.id : (it.peek ? String(it.id) : 'url:' + it.url);\n"
           "    return '<span class=\"tvs-peekb\" role=\"button\" tabindex=\"0\" data-peek=\"' + esc(pid) + '\"")
s = rep(s, "  function peek(id) {\n", "  function peekGo(el) {   // what a Peek names: a page opens in the page peek, a listing or outdoor place in the card\n"
           "    var v = el.getAttribute('data-peek') || '';\n"
           "    if (v.indexOf('url:') === 0) { if (window.TvAsk) TvAsk.peek(v.slice(4), el.getAttribute('aria-label').replace(/^Peek at /, '')); return; }\n"
           "    peek(/^\\d+$/.test(v) ? +v : v);\n  }\n  function peek(id) {\n")
s = rep(s, "    if (pk) { e.preventDefault(); remember(input.value); peek(+pk.getAttribute('data-peek')); return; }",
           "    if (pk) { e.preventDefault(); remember(input.value); peekGo(pk); return; }")
s = rep(s, "    if (pb) { e.preventDefault(); e.stopPropagation(); peek(+pb.getAttribute('data-peek')); return; }",
           "    if (pb) { e.preventDefault(); e.stopPropagation(); peekGo(pb); return; }")
s = rep(s, "      var ou = oa.hasAttribute('data-peek') ? '/listing/view.php?id=' + oa.getAttribute('data-peek') : oa.getAttribute('href');",
           "      var pv = oa.getAttribute('data-peek') || '', ou = oa.hasAttribute('data-peek') ? (/^\\d+$/.test(pv) ? '/listing/view.php?id=' + pv : (pv.indexOf('url:') === 0 ? pv.slice(4) : (oa.closest('a[href]') || oa).getAttribute('href'))) : oa.getAttribute('href');")
# A3: "results are lacking" (decisions/0060) above the rows, where it's read, not under them
s = rep(s, "    if (R.note) h += '<p class=\"tvs-note\">' + esc(R.note) + '</p>';\n    h += meantHtml(R);",
           "    if (R.note) h += '<p class=\"tvs-note\">' + esc(R.note) + '</p>';\n    if (R.near) h += nearFoot(R.near, d);   // fewer than enough near: said above the rows (2026-11-22)\n    h += meantHtml(R);")
s = rep(s, "    if (R.near) h += (R.osm ? osmSlot(R.osm.kind, R.osm.lat, R.osm.lon, R.osm.place, R.osm.mi) : '') + nearFoot(R.near, d);",
           "    if (R.near && R.osm) h += osmSlot(R.osm.kind, R.osm.lat, R.osm.lon, R.osm.place, R.osm.mi);")
# B1: the guide offer under the results, so what was asked for comes first (Jason, 2026-11-22)
s = rep(s, "    if (d.guide) h += guideOffer(d.guide);   // a place with no guide yet: we can build one now (decisions/0147)",
           "    var guideH = d.guide ? guideOffer(d.guide) : '';   // a place with no guide yet: we can build one now (decisions/0147); under the results (Jason, 2026-11-22)")
s = rep(s, "    if (R) { h += resultsHtml(d, R) + (nearBare() ? nearChooser() : '') + mayH;",
           "    if (R) { h += resultsHtml(d, R) + guideH + (nearBare() ? nearChooser() : '') + mayH;")
s = rep(s, "    b.innerHTML = fold(h, cut, q) + tellHtml(q, found || !!sitH);",
           "    b.innerHTML = fold(h + guideH, cut, q) + tellHtml(q, found || !!sitH);")
# Esc in a pop-up over the panel (a Peek card, the page peek, a confirm) closes only that pop-up; a second Esc the panel
# (0058's note: Esc in an inner pop-up closes only it, as the place picker does; the session manager's review, 2026-11-22)
s = rep(s, "    if (e.key === 'Escape' && panel && !(e.target.classList && (e.target.classList.contains('tvs-in') || e.target.classList.contains('tvs-in2')))) close();",
           "    if (e.key === 'Escape' && panel && !e.tvInner && !(e.target.classList && (e.target.classList.contains('tvs-in') || e.target.classList.contains('tvs-in2')))) close();")
s = rep(s, "  document.addEventListener('keydown', function (e) {\n    var mb = (e.key === 'Enter'",
           "  // first, before any pop-up's own Esc: is one open over the panel? Then this Esc is that pop-up's alone\n"
           "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.querySelector('.tvs-peek, .tv-ask-lb')) e.tvInner = true; }, true);\n"
           "  document.addEventListener('keydown', function (e) {\n    var mb = (e.key === 'Enter'")
put(f, s)

# B2: Quick Search counts what each finds; a category people ask about too; Open now none (it changes by the minute)
f = 'app/lib/SearchPresets.php'; s = live(f)
s = rep(s, "            if ($name = $st->fetchColumn()) $out[] = ['label' => (string) $name, 'q' => mb_strtolower((string) $name), 'count' => null, 'score' => 28 + (int) round(min(30, 8 * log(1 + $n)))];",
           "            if ($name = $st->fetchColumn()) $out[] = ['label' => (string) $name, 'q' => mb_strtolower((string) $name), 'count' => self::inCategory($pdo, $place, substr($k, 4)), 'score' => 28 + (int) round(min(30, 8 * log(1 + $n)))];")
s = rep(s, "    private static function have(PDO $pdo, ?array $place): array\n",
           "    /** How many listings here are in a category (its own and its sub-categories), for its Quick Search line (Jason,\n"
           "     * 2026-11-22: counts on the rest); null when it can't be counted, so no number shows rather than a wrong one. */\n"
           "    private static function inCategory(PDO $pdo, ?array $place, string $slug): ?int\n    {\n"
           "        try {\n            $p = [':slug' => $slug, ':slug2' => $slug];\n            $where = SearchIndex::whereSql($pdo, $place, null, $p);\n"
           "            $st = $pdo->prepare(\"SELECT COUNT(*) FROM search_index si WHERE si.kind = 'listing' AND si.category_id IN (SELECT c.id FROM categories c LEFT JOIN categories pc ON pc.id = c.parent_id WHERE c.slug = :slug OR pc.slug = :slug2)\" . ($where !== '' ? \" AND $where\" : ''));\n"
           "            $st->execute($p);\n            $n = (int) $st->fetchColumn();\n            return $n > 0 ? $n : null;\n"
           "        } catch (Throwable $e) { return null; }\n    }\n\n    private static function have(PDO $pdo, ?array $place): array\n")
put(f, s)

# FAQ: the entries that describe what changed, updated in place (anchors kept)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "- **Search Results** on the left. Each one says which part of the site it's in: **Directory**, **Discovery** or **Community**.",
           "- **Search Results** on the left. Each one says which part of the site it's in: **Directory**, **Discovery** or **Community**, and the tabs over them use the same names (\"Discovery 1\", \"Directory 4\"). The tabs count only what's listed under them, so they add up to the Results count; the categories your words might mean are in Suggestions.")
s = rep(s, "Tapping a result opens its page. A listing also has a **Peek** button: tap it for a quick card with the address, Call and Directions, without leaving the page you're on.",
           "Tapping a result opens its page. Every result also has a **Peek** button: on a listing or an outdoor place it opens a quick card with the address, Call and Directions; on anything else (a story, a journey) it opens the page in a window over the one you're on. Either way you don't leave the page you're on.")
s = rep(s, "If fewer than 3 are that near, it says so (\"Our results for coffee near Phoenix are lacking: the nearest of ours is 12 mi away\")",
           "If fewer than 3 are that near, it says so above the results (\"Our results for coffee near Phoenix are lacking: the nearest of ours is 12 mi away\")")
s = rep(s, "When you search somewhere we don't have a guide for yet (Dana Point, California, say), search offers to build one.",
           "When you search somewhere we don't have a guide for yet (Dana Point, California, say), search offers to build one, under the results.")
s = rep(s, "- **Quick Search** on the right: one-tap searches for the place, with how many each finds, your own,",
           "- **Quick Search** on the right: one-tap searches for the place, with how many each finds (**Open now** has no number: it changes by the minute), your own,")
put(f, s)
print('built', sorted(os.path.relpath(os.path.join(dp, x), O) for dp, _, fs in os.walk(O) for x in fs))
