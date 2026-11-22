#!/usr/bin/env python3
"""The map release (brand.md §4's note, Jason 2026-11-19: routes and trails are Electric Lime #B6F22E over a Deep Umber
#3E2B1F casing, --tv-route and --tv-route-casing; admin-design.md: "the first map that draws routes uses both (casing ~2px
wider under the lime)"; css-theme-brand §4 Release 3).
1. js/map-view.js onto the tokens, colours only (recolor.py, 3f's). Leaflet's path options are SVG attributes, where var()
   doesn't resolve, so the radius ring reads its token's value at run time (getComputedStyle), the hex as the fallback.
2. user/trips/trip.php: the day lines on --tv-route over --tv-route-casing. Option B (the session manager's review,
   2026-11-22; Jason picks in the release's pop-up): the chosen day lime over its casing, the other days casing only,
   faded; a day is chosen by the Route row over the map (Day 1 first) or by tapping one of its pins. Pins keep their day
   colours (markers, not routes). `python3 build.py A|C` writes the other options to variants/ for the pop-up's images.
Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import recolor as R
M = '/home/user/traverse-3way-sync/sync-storage/'; S = os.path.dirname(os.path.abspath(__file__))
MODE = (sys.argv[1] if len(sys.argv) > 1 else 'B').upper()
O = os.path.join(S, 'out' if MODE == 'B' else 'variants/' + MODE)
shutil.rmtree(O, ignore_errors=True)
R.TEXT.update({'FFFBF2': 'on-umber', '7A6348': 'meta'}); R.SURF.update({'F7F1E3': 'card-a', '1E140C': 'void'}); R.LINE.update({'B91C1C': 'fail'})
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)

# ---- 1. the shared map ----
a = open(M + 'js/map-view.js', encoding='utf-8').read()
ring = "L.circle([c.lat, c.lon], { radius: c.radius * 1609.34, color: '#B45309', weight: 2,"
a = rep(a, ring, "L.circle([c.lat, c.lon], { radius: c.radius * 1609.34, color: @@RING@@, weight: 2,")   # kept out of recolor's reach
r = {'mapped': 0, 'left': {}}; s = R.recolor(a, r)
s = rep(s, '@@RING@@', "tvTok('--tv-icon', '#B45309')")
s = rep(s, "// js/map-view.js", "// Leaflet's path options are SVG attributes, where var() doesn't resolve: they read the token's value here (the map release)\n"
           "function tvTok(n, d) { try { return getComputedStyle(document.documentElement).getPropertyValue(n).trim() || d; } catch (e) { return d; } }\n// js/map-view.js")
print('js/map-view.js mapped', r['mapped'], 'left', dict(sorted(r['left'].items(), key=lambda x: -x[1])))
put('website/js/map-view.js', s)

# ---- 2. the trip page's day lines ----
t = open(M + 'user/trips/trip.php', encoding='utf-8').read()
t = rep(t, "  // the map: numbered pins, straight lines between, one colour a day",
           "  // the map: numbered pins in their day's colour, straight lines between on the route colour (brand.md §4's note, 2026-11-19:\n"
           "  // Electric Lime over a Deep Umber casing, --tv-route / --tv-route-casing; " + {
             'B': "the chosen day lime, the others casing only, faded)",
             'A': "every day lime over its casing)",
             'C': "kept one colour a day; lime only for trails and routes that aren't a trip's days)"}[MODE])
old_lines = "    Object.keys(byDay).forEach(function (d) { if (byDay[d].length > 1) L.polyline(byDay[d], {color: COL[(d - 1) % COL.length], weight: 3, dashArray: '6 6', opacity: .85}).addTo(map); });"
tok = ("    function tok(n, d) { try { return getComputedStyle(document.documentElement).getPropertyValue(n).trim() || d; } catch (e) { return d; } }   // SVG options take no var()\n"
       "    var LIME = tok('--tv-route', '#B6F22E'), CASE = tok('--tv-route-casing', '#3E2B1F');\n")
if MODE == 'A':
    t = rep(t, old_lines, tok +
        "    Object.keys(byDay).forEach(function (d) { if (byDay[d].length < 2) return;\n"
        "      L.polyline(byDay[d], {color: CASE, weight: 6, opacity: .9, interactive: false}).addTo(map);   // the casing, 2px wider under the lime\n"
        "      L.polyline(byDay[d], {color: LIME, weight: 4, dashArray: '8 6', interactive: false}).addTo(map); });")
elif MODE == 'B':
    t = rep(t, old_lines, tok +
        "    var lines = {}, days = Object.keys(byDay).filter(function (d) { return byDay[d].length > 1; });\n"
        "    days.forEach(function (d) { lines[d] = [L.polyline(byDay[d], {color: CASE, weight: 6, interactive: false}).addTo(map), L.polyline(byDay[d], {color: LIME, weight: 4, dashArray: '8 6', interactive: false}).addTo(map)]; });\n"
        "    var bar = document.getElementById('tp-route');\n"
        "    function choose(day) {   // the chosen day's route lime over its casing; the others casing only, faded\n"
        "      days.forEach(function (d) { var on = String(d) === String(day); lines[d][0].setStyle({opacity: on ? .95 : .35}); lines[d][1].setStyle({opacity: on ? 1 : 0}); if (on) { lines[d][0].bringToFront(); lines[d][1].bringToFront(); } });\n"
        "      if (bar) [].forEach.call(bar.querySelectorAll('[data-route-day]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-route-day') === String(day))); });\n"
        "    }\n"
        "    if (bar && days.length > 1) { bar.insertAdjacentHTML('beforeend', days.map(function (d) { return '<button type=\"button\" data-route-day=\"' + d + '\" aria-pressed=\"false\">Day ' + d + '</button>'; }).join('')); bar.hidden = false; bar.addEventListener('click', function (e) { var b = e.target.closest('[data-route-day]'); if (b) choose(b.getAttribute('data-route-day')); }); }\n"
        "")
    t = rep(t, "    if (all.length === 1) map.setView(all[0], 12); else map.fitBounds(all, {padding: [28, 28]});\n",
               "    if (all.length === 1) map.setView(all[0], 12); else map.fitBounds(all, {padding: [28, 28]});\n    if (typeof choose === 'function' && Object.keys(byDay).some(function (d) { return byDay[d].length > 1; })) choose(Object.keys(byDay).filter(function (d) { return byDay[d].length > 1; })[0]);   // once the map has its view, so the lines are drawn\n")
    # a pin chooses its day's route too
    t = rep(t, "      m.bindPopup(pop);\n", "      m.bindPopup(pop); m.on('click', function () { if (typeof choose === 'function') choose(p.day); });\n")
    # choose is defined after the pins; hoist with a forward declaration
    t = rep(t, "    var COL = ['#B45309', '#2F6F57', '#6B4E9B', '#9B2C2C', '#1F5F8B'], byDay = {};",
               "    var COL = ['#B45309', '#2F6F57', '#6B4E9B', '#9B2C2C', '#1F5F8B'], byDay = {}, choose;")
    t = rep(t, "    function choose(day) {", "    choose = function (day) {")
    t = rep(t, "      if (bar) [].forEach.call(bar.querySelectorAll('[data-route-day]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-route-day') === String(day))); });\n    }\n",
               "      if (bar) [].forEach.call(bar.querySelectorAll('[data-route-day]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-route-day') === String(day))); });\n    };\n")
    # the Route row over the map (filled by the script, shown only with two days or more that have lines) and its words
    t = rep(t, '<div class="tp-mapw tv-bleed"><div id="tp-map"', '<div class="tp-mapw tv-bleed"><div id="tp-route" class="tp-route" role="group" aria-label="Show one day\'s route" hidden><span>Route:</span></div><div id="tp-map"')
    t = rep(t, '<p class="tp-note">Numbered in order; lines are straight, not roads.</p>', '<p class="tp-note">Numbered in order; lines are straight, not roads. The chosen day\'s route is in lime; tap a day or one of its stops to see another.</p>')
    t = rep(t, "  @media (max-width:767px){.tp-day,.tp-set{", "  .tp-route{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;margin:0 0 .45rem;font-size:.85rem;color:var(--tv-meta)}.tp-route[hidden]{display:none}\n"
               "  .tp-route button{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.2rem .75rem;font:inherit;cursor:pointer}\n"
               "  .tp-route button[aria-pressed=\"true\"]{background:var(--tv-umber);border-color:var(--tv-umber);color:var(--tv-on-umber)}\n"
               "  @media (max-width:767px){.tp-day,.tp-set{")
    # the FAQ's trip entry (decisions/0057): words stay true (playbook §2)
    f = open('/home/user/traverse-3way-sync/sync-app/ui/faq.md', encoding='utf-8').read()
    f = rep(f, " The map numbers the stops, one colour a day.", " The map numbers the stops, one colour a day, and draws the chosen day's route in lime; tap **Day 1**, **Day 2**… over the map, or one of a day's stops, to see another.")
    put('app/ui/faq.md', f)
# C keeps the day lines as they are
r2 = {'mapped': 0, 'left': {}}
put('website/user/trips/trip.php', t)
print('trip.php mode', MODE)
