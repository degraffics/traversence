#!/usr/bin/env python3
"""Hero tidy (Jason, 2026-11-20): ⋯ starts Link to, Claim, Add to a trip; the page's Link icon hides while ⋯ is open; the
stock photo's details see-through; Explore's default-place notice slim; the hub's stray text; same-named areas once."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)

# ---- ⋯: Link to, Claim, Add to a trip first (Jason, 2026-11-20) ----
f = 'app/ui/engage-ui.php'; s = live(f)
s = rep(s, " * add ([kind, ref, name, why]: Add to a trip, decisions/0174 §2; why '' when it can go in one),",
           " * add ([kind, ref, name, why]: Add to a trip, decisions/0174 §2; why '' when it can go in one), link (the page's name: Link to,\n"
           " * which runs the page's own Link button), claim (a URL, or ['', why]). Order (Jason, 2026-11-20: \"should start with Link\n"
           " * to, then Claim, Add to Trip, then the rest\"): Link to, Claim, Add to a trip, then Share, QR and the page's tools.")
s = rep(s, "    $add = $t['add'] ?? null;\n    $m .= is_array($add) && ($add[3] ?? '') === '' ? '<button type=\"button\" role=\"menuitem\" data-trip-add=\"' . tv_e(json_encode(['kind' => $add[0], 'ref' => (string) $add[1], 'name' => (string) $add[2]], JSON_UNESCAPED_UNICODE)) . '\" aria-haspopup=\"dialog\">' . $ic($ADD) . 'Add to a trip</button>'\n        : $off($ADD, 'Add to a trip', is_array($add) ? (string) $add[3] : 'This page isn\\'t a place you can visit.');\n", "")
s = rep(s, "    $m .= $first . '<!--eg-more-->';",
           "    $LINK = '<path d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\"/><path d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\"/>';\n"
           "    $CLAIM = '<path d=\"M3 9l1.5-5h15L21 9\"/><path d=\"M4 9v11h16V9\"/><path d=\"M9 20v-6h6v6\"/>';\n"
           "    if (!empty($t['link'])) $m .= '<button type=\"button\" role=\"menuitem\" data-eg-linkto>' . $ic($LINK) . 'Link to ' . tv_e((string) $t['link']) . '</button>';\n"
           "    if (array_key_exists('claim', $t)) { $c = $t['claim'];\n"
           "        $m .= is_string($c) && $c !== '' ? '<a role=\"menuitem\" href=\"' . tv_e($c) . '\">' . $ic($CLAIM) . 'Claim</a>' : $off($CLAIM, 'Claim', is_array($c) ? (string) ($c[1] ?? '') : 'Only a business\\'s or an organization\\'s listing can be claimed.'); }\n"
           "    $add = $t['add'] ?? null;\n"
           "    $m .= is_array($add) && ($add[3] ?? '') === '' ? '<button type=\"button\" role=\"menuitem\" data-trip-add=\"' . tv_e(json_encode(['kind' => $add[0], 'ref' => (string) $add[1], 'name' => (string) $add[2]], JSON_UNESCAPED_UNICODE)) . '\" aria-haspopup=\"dialog\">' . $ic($ADD) . 'Add to a trip</button>'\n"
           "        : $off($ADD, 'Add to a trip', is_array($add) ? (string) $add[3] : 'This page isn\\'t a place you can visit.');\n"
           "    $m .= $first . '<!--eg-more-->';")
s = rep(s, "            $tt['add'] = [$kind, $ref, (string) ($o['title'] ?? ''),",
           "            if (!empty($o['fold']) && !isset($tt['link']) && (string) ($o['title'] ?? '') !== '') $tt['link'] = (string) $o['title'];   // Link to (the hero's ⋯)\n"
           "            if (!array_key_exists('claim', $tt)) $tt['claim'] = ['', $kind === 'listing' ? 'Already claimed by its business.' : 'Only a business\\'s or an organization\\'s listing can be claimed.'];\n"
           "            $tt['add'] = [$kind, $ref, (string) ($o['title'] ?? ''),")
s = rep(s, "  .eg-ml[hidden]{display:none}", "  .eg-ml[hidden]{display:none}.eg-hid{visibility:hidden!important}   /* the page's Link icon, while ⋯ is open (Jason, 2026-11-20) */")
put(f, s)

f = 'website/js/engage.js'; s = live(f)
s = rep(s, "  document.addEventListener('click', function (e) {\n    var t = e.target; if (!t.closest || !t.isConnected) return;\n    var more = t.closest('.eg-more');",
           "  // the page's own Link button for this bar (link-button.js draws it from data-link): Link to runs it, and it hides while\n"
           "  // ⋯ is open so the two never sit on each other (Jason, 2026-11-20)\n"
           "  function pageLink(bar) {\n"
           "    if (!bar) return null;\n"
           "    var k = bar.getAttribute('data-eg-kind'), r = bar.getAttribute('data-eg-ref'),\n"
           "        key = { listing: 'business:' + r, lm: 'place:lm:' + r, rec: 'place:rec:' + r, place: 'place:' + r }[k];\n"
           "    if (!key) return null;\n"
           "    var slot = document.querySelector('[data-link=\"' + key.replace(/\"/g, '') + '\"]');\n"
           "    return slot ? (slot.querySelector('.tv-link') || (slot.classList.contains('tv-link') ? slot : null) || slot) : null;\n"
           "  }\n"
           "  function barOf(m) { var h = m && m._home; return h ? h.closest('.eg-bar') : null; }\n"
           "  document.addEventListener('click', function (e) {\n    var t = e.target; if (!t.closest || !t.isConnected) return;\n"
           "    var lt = t.closest('[data-eg-linkto]');\n"
           "    if (lt) { var lb = pageLink(barOf(lt.closest('.eg-ml'))); closeMenus(); var btn = lb && (lb.matches('button') ? lb : lb.querySelector('button'));\n"
           "      if (btn) btn.click(); else if (window.TvAsk) TvAsk.say('Linking isn\\'t on this page yet.', { anchor: lt }); return; }\n"
           "    var more = t.closest('.eg-more');")
s = rep(s, "      document.body.appendChild(m); m.hidden = false; more.setAttribute('aria-expanded', 'true');",
           "      document.body.appendChild(m); m.hidden = false; more.setAttribute('aria-expanded', 'true');\n"
           "      var pl = pageLink(more.closest('.eg-bar')); if (pl) { pl.classList.add('eg-hid'); m._link = pl; }")
s = rep(s, "  function closeMenus(but) { document.querySelectorAll('.eg-ml:not([hidden])').forEach(function (m) { if (m === but) return; m.hidden = true;",
           "  function closeMenus(but) { document.querySelectorAll('.eg-ml:not([hidden])').forEach(function (m) { if (m === but) return; m.hidden = true; if (m._link) { m._link.classList.remove('eg-hid'); m._link = null; }")
put(f, s)

# a listing's Claim in ⋯: where the corner's Claim goes; greyed with why where there's none
f = 'website/listing/view.php'; s = live(f)
s = rep(s, "        'tools' => ['trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : []]]),",
           "        'tools' => ['trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : [],\n"
           "                    'claim' => $isOwner ? ['', 'It\\'s yours: Manage is in the corner.'] : ($biz['status'] === 'unclaimed' && $notBiz === '' ? '/claim.php?listing=' . $eid : ['', $notBiz !== '' ? 'Not a business: nothing to claim.' : 'Already claimed by its business.'])]]),   // ⋯: Link to, Claim, Add to a trip first (Jason, 2026-11-20)")
put(f, s)

# ---- the stock photo's details: see the photo through it, still readable; its words said once (Jason, 2026-11-20) ----
f = 'app/ui/stock-ui.php'; s = live(f)
s = rep(s, "    $what = !empty($s['representative']) ? 'Representative photo: it shows the kind of place or thing, not this one itself.' : 'Stock photo of this place.';",
           "    $what = !empty($s['representative']) ? 'It shows the kind of place or thing, not this one itself.' : 'Stock photo of this place.';   // the heading already says Representative")
s = rep(s, ".tv-stk-d{display:grid;gap:.3rem;margin-top:.3rem;background:var(--tv-card-a,#FFFBF2);",
           ".tv-stk-d{display:grid;gap:.3rem;margin-top:.3rem;background:color-mix(in srgb,var(--tv-card-a,#FFFBF2) 80%,transparent);-webkit-backdrop-filter:blur(10px) saturate(1.15);backdrop-filter:blur(10px) saturate(1.15);")
put(f, s)

# ---- Explore: the default place offered in one slim line (Jason, 2026-11-20: "isnt handled well visually") ----
f = 'website/discovery/index.php'; s = live(f)
s = rep(s, "<div class=\"dv-card dv-ask tv-bleed\" id=\"dv-ask\" hidden role=\"region\" aria-label=\"Your default place\">\n"
           "  <?= tv_icon('compass') ?><span class=\"dv-note\" id=\"dv-ask-t\"></span>\n"
           "  <button type=\"button\" class=\"dv-btn\" id=\"dv-ask-go\"></button>\n"
           "  <button type=\"button\" class=\"dv-btn ghost\" id=\"dv-ask-other\">Choose another place</button>\n"
           "  <button type=\"button\" class=\"dv-btn ghost\" id=\"dv-ask-no\"><?= $place ? 'Stay here' : 'Stay with North America' ?></button>\n"
           "</div>",
           "<div class=\"dv-ask tv-bleed\" id=\"dv-ask\" hidden role=\"region\" aria-label=\"Your default place\">\n"
           "  <?= tv_icon('pin') ?><span class=\"dv-ask-t\" id=\"dv-ask-t\"></span>\n"
           "  <button type=\"button\" class=\"dv-ask-go\" id=\"dv-ask-go\"></button>\n"
           "  <button type=\"button\" class=\"dv-ask-l\" id=\"dv-ask-other\">Another place</button>\n"
           "  <button type=\"button\" class=\"dv-ask-x\" id=\"dv-ask-no\" aria-label=\"<?= $place ? 'No thanks: stay here' : 'No thanks: stay with North America' ?>\" title=\"<?= $place ? 'No thanks: stay here' : 'No thanks: stay with North America' ?>\">&times;</button>\n"
           "</div>")
s = rep(s, "  .dv-ask{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem .9rem;margin-bottom:.9rem}.dv-ask[hidden]{display:none}.dv-ask .dv-note{flex:1 1 18rem}",
           "  /* the default place, offered in one slim line (Jason, 2026-11-20) */\n"
           "  .dv-ask{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .6rem;margin:0 0 .7rem;padding:.4rem .45rem .4rem .7rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-left:3px solid var(--tv-icon,#B45309);border-radius:.6rem;font-size:.88rem;color:var(--tv-ink)}\n"
           "  .dv-ask[hidden]{display:none}.dv-ask>svg{width:16px;height:16px;flex:none;color:var(--tv-icon,#B45309)}.dv-ask-t{flex:1 1 14rem;min-width:0}.dv-ask-t b{font-weight:700}\n"
           "  .dv-ask-go{border:0;border-radius:999px;background:var(--tv-btn);color:var(--tv-btn-text);font:700 .82rem/1.2 inherit;padding:.32rem .8rem;cursor:pointer}.dv-ask-go:hover{background:var(--tv-btn-hover)}\n"
           "  .dv-ask-l{border:0;background:none;color:var(--tv-link,#92400E);text-decoration:underline;font:600 .82rem/1.2 inherit;cursor:pointer;padding:.3rem .2rem}\n"
           "  .dv-ask-x{width:28px;height:28px;flex:none;border-radius:999px;border:1px solid var(--tv-line-strong);background:transparent;color:var(--tv-meta);font-size:1rem;line-height:1;cursor:pointer}")
s = rep(s, "      document.getElementById('dv-ask-t').textContent = 'Your default place' + (l.zip ? ' (ZIP ' + l.zip + ')' : '') + ' is in ' + name + '. Explore it?';\n"
           "      var go = document.getElementById('dv-ask-go'); go.textContent = 'Explore ' + name;",
           "      var t = document.getElementById('dv-ask-t'); t.textContent = 'Near your default place' + (l.zip ? ' (ZIP ' + l.zip + ')' : '') + ': ';\n"
           "      var bn = document.createElement('b'); bn.textContent = name; t.appendChild(bn);\n"
           "      var go = document.getElementById('dv-ask-go'); go.textContent = 'Explore it';")
put(f, s)

# ---- the hub: a note printed as text (#211's slip), and same-named areas once ----
f = 'website/hub/index.php'; s = live(f)
s = rep(s, "'suggest' => $sg + ['kind' => 'story']]])]) ?>   // ⋯ (2026-11-19)", "'suggest' => $sg + ['kind' => 'story']]])]) /* ⋯ (2026-11-19) */ ?>")
s = rep(s, "    $nh = '<div class=\"gd-next\">';\n    foreach ($kids as [$kk, $kn, $ku, $kl, $kc, $planned])",
           "    $nh = '<div class=\"gd-next\">';\n"
           "    // areas with the same name show once (Jason, 2026-11-20: \"Albuquerque\" ten times): the biggest opens, the rest are counted\n"
           "    $byName = [];\n"
           "    foreach ($kids as $k) { $nk = mb_strtolower(trim((string) $k[1])); if (!isset($byName[$nk])) { $byName[$nk] = $k + [6 => 1]; continue; }\n"
           "        $b = $byName[$nk]; $n = $b[6] + 1; if ((int) $k[4] > (int) $b[4]) { $b = $k + [6 => 0]; } $b[4] = (int) $byName[$nk][4] + (int) $k[4]; $b[6] = $n; $byName[$nk] = $b; }\n"
           "    $kids2 = array_values($byName);\n"
           "    foreach ($kids2 as $k2) { [$kk, $kn, $ku, $kl, $kc, $planned] = $k2; $areas = (int) ($k2[6] ?? 1); if ($areas > 1) $kl = $areas . ' areas';")
s = rep(s, "            . '<span>' . ($planned ? 'Coming soon' : ($kc ? number_format($kc) . ' listing' . ($kc === 1 ? '' : 's') : Guides::TIERS[Guides::place($pdo, $kk)['level'] ?? 'sub'][1])) . '</span></a>';\n    $blocks['next'] = $nh . '</div>';",
           "            . '<span>' . ($planned ? 'Coming soon' : ($kc ? number_format($kc) . ' listing' . ($kc === 1 ? '' : 's') : Guides::TIERS[Guides::place($pdo, $kk)['level'] ?? 'sub'][1])) . '</span></a>';\n    }\n    $blocks['next'] = $nh . '</div>';")
put(f, s)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Everything else is under **⋯**, in words: **Share** and **QR code**,", "Everything else is under **⋯**, in words: first **Link to** (the page's own Link: follow it, and what's new shows in your Pulse), **Claim** (a business's or an organization's listing; greyed elsewhere, with why) and **Add to a trip**, then **Share** and **QR code**,")
s = rep(s, "then **See on the map**, **Plan a trip here**, **Add to a trip** and **Report** where the page has them.", "then **See on the map**, **Plan a trip here** and **Report** where the page has them. While ⋯ is open, the page's Link icon steps aside so the two never sit on each other.")
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
