#!/usr/bin/env python3
"""A listing's page sees who's signed in again; Back when there's no route.
1. listing/view.php read its own session from PHP's default folder, not the site's (app/storage/sessions, bootstrap.php,
   2026-10-10), so since then every listing page treated a signed-in person as a guest: no account bar (decisions/0055),
   no Back in it, no admin Edit, Manage or team checks. Jason, 2026-11-20: signed in, no bar ("Yes, signed in").
2. Back where there's no route (Jason, 2026-11-20 pop-up: "back to where they came from"): on the pages that carry the
   route (Let's Explore's and the pages reached from it, decisions/0058's note of 2026-11-12) opened with none in this
   tab, "‹ Back" goes to the page on our site they came from (Pulse, the admin, a link inside the site). Elsewhere it's
   unchanged, as Jason set on 2026-11-12. Reads the live mirror."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)

f = 'website/listing/view.php'; s = live(f)
s = rep(s, "    session_name('traversence_session');\n    @session_start(['read_and_close' => true]);",
           "    session_name('traversence_session');\n"
           "    // the site's own session folder (app/bootstrap.php, 2026-10-10): without it this page read PHP's default folder, found no\n"
           "    // session and showed everyone as a guest (no account bar, decisions/0055; Jason, 2026-11-20: \"Yes, signed in\")\n"
           "    $tvSd = __DIR__ . '/../../app/storage/sessions'; if (is_dir($tvSd)) session_save_path($tvSd);\n"
           "    @session_start(['read_and_close' => true]);")
put(f, s)

f = 'website/js/tv-trail.js'; s = live(f)
# where a route-carrying page has no route in this tab: one step, the page on our site they came from
s = rep(s, "  if (!fam && !(trail.length && trail[0].f === 'explore')) { try { sessionStorage.setItem(KEY, '[]'); } catch (e) {} return; }",
           "  // A page that carries the route, opened with none in this tab (a new tab, a link from Pulse, the admin or a mail): one step back\n"
           "  // to the page on our site it came from (Jason, 2026-11-20: \"back to where they came from\"), never a dead end. Not a route.\n"
           "  var from = null;\n"
           "  try { var rf = document.referrer ? new URL(document.referrer) : null; if (rf && rf.origin === location.origin && rf.pathname + rf.search !== path + location.search && !/^\\/(login|logout|register|verify|reset|api)\\b/.test(rf.pathname)) from = rf; } catch (e) {}\n"
           "  if (!fam && !(trail.length && trail[0].f === 'explore')) { try { sessionStorage.setItem(KEY, '[]'); } catch (e) {} if (from) return backTo(from); return; }")
s = rep(s, "  window.tvTrail = trail;\n  if (trail.length < 2) return;",
           "  window.tvTrail = trail;\n  if (trail.length < 2) { if (from && fam === 'explore' && trail.length === 1 && trail[0].u === here.u) return backTo(from); return; }")
# backTo(): the same "‹ Back to …" look, named by the page's section where we know it, placed like the route
s = rep(s, "(function () {\n  'use strict';\n  if (window.tvTrail || window.top !== window.self) return;",
           "(function () {\n  'use strict';\n  if (window.tvTrail || window.top !== window.self) return;\n"
           "  var NAMES = { admin: 'the admin', user: 'your account', directory: 'Get Local', community: 'Groups', market: 'the Marketplace', shop: 'the Marketplace', discovery: 'Let\\'s Explore', hub: 'the area guide', listing: 'the listing', journeys: 'Journeys', faq: 'the Help Center' };\n"
           "  function backTo(u) {\n"
           "    var seg = u.pathname.split('/')[1] || '', name = u.pathname === '/' ? 'Home' : (NAMES[seg.replace(/\\.php$/, '')] || '');\n"
           "    var w = document.createElement('span'); w.className = 'tv-tr';\n"
           "    w.innerHTML = '<a href=\"' + String(u.pathname + u.search).replace(/\"/g, '&quot;') + '\" title=\"Back to where you came from\"><b aria-hidden=\"true\">&lsaquo;</b><span>' + (name ? 'Back to ' + name : 'Back') + '</span></a>';\n"
           "    function put() { trailCss(); var as = document.querySelector('.tv-as'); if (as) { var sp = as.querySelector('.tv-as-sp'); if (sp) as.insertBefore(w, sp); else as.appendChild(w); return; }\n"
           "      var m = document.getElementById('ta-work') || document.getElementById('tv-main') || document.querySelector('main'); if (!m) return;\n"
           "      var line = document.createElement('div'); line.className = 'tv-trl'; line.appendChild(w); m.insertBefore(line, m.firstChild); }\n"
           "    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', put); else put();\n"
           "  }")
# the route's CSS, shared with backTo()
s = rep(s, "  var css = document.createElement('style');\n  css.textContent = ", "  trailCss();\n  function trailCss() { if (document.getElementById('tv-trail-css')) return; var css = document.createElement('style'); css.id = 'tv-trail-css';\n  css.textContent = ")
s = rep(s, "  document.head.appendChild(css);", "  document.head.appendChild(css); }")
put(f, s)

f = 'app/ui/faq.md'; s = live(f)
k = "### Can I take a message back?\n"
assert s.count(k) == 1
s = s.replace(k, """### How do I get back to where I was?
Pages you reach from **Let's Explore** (a region, a place, a listing, a guide, a journey) show **‹ Back to …** at the top: in your account bar when you're signed in, otherwise in a slim line atop the page. **▾** beside it shows the whole route you took, and opening a page already on it takes you back up. A listing, place or guide opened some other way (from Pulse, the admin, or a link elsewhere on the site) shows **‹ Back** to the page you came from (2026-11-20).

""" + k, 1)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
