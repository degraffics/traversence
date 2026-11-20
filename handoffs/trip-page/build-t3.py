#!/usr/bin/env python3
"""decisions/0174 release 3: the trip page. Reads the live mirror, writes out/. Replayable."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out'); SRC = os.path.join(S, 'src')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
src = lambda f: open(os.path.join(SRC, f), encoding='utf-8').read()

put('app/migrations/2026-11-21_trips-page.sql', src('trips-page.sql'))
put('app/lib/Trips.php', src('Trips.php'))
put('website/api/trips.php', src('api-trips.php'))
put('website/js/tv-trips.js', src('tv-trips.js'))
put('website/user/trips/index.php', src('user-trips.php'))
put('website/user/trips/trip.php', src('trip.php'))

LOAD = "if (!window.TvTrips && !document.querySelector('script[src*=\"/js/tv-trips.js\"]')) { var ts = document.createElement('script'); ts.src = '/js/tv-trips.js'; document.body.appendChild(ts); }"
REF = "function tripRef(l) { if (l.kind === 'outdoors') { var i = String(l.id).replace(/^rec:/, ''), g = /^g0*(\\d+)$/.exec(i); return g ? {kind: 'lm', ref: g[1], name: l.name} : {kind: 'rec', ref: i, name: l.name}; } return {kind: 'listing', ref: String(l.id), name: l.name || ''}; }"

# the map's card: Add to a trip beside Open (decisions/0174 §2, moved to release 3)
f = 'website/js/map-view.js'; s = live(f)
s = rep(s, "        + '<a class=\"go\" href=\"' + esc(l.url) + '\">Open &rsaquo;</a></div>'",
           "        + (l.private_location ? '' : '<button type=\"button\" class=\"tvm-trip\" data-trip-add=\"' + esc(JSON.stringify(tripRef(l))) + '\" aria-haspopup=\"dialog\">Add to a trip</button>')   // decisions/0174 §2\n"
           "        + '<a class=\"go\" href=\"' + esc(l.url) + '\">Open &rsaquo;</a></div>'")
s = rep(s, "      if (l.approx && !l.mailing_only) fixPin(card, p, l);   // a mailbox's pin stays the town's",
           "      if (l.approx && !l.mailing_only) fixPin(card, p, l);   // a mailbox's pin stays the town's\n      " + LOAD + "   // Add to a trip's picker")
s = rep(s, "  function fixPin(card, p, l) {", "  " + REF + "   // a card's listing as a trip stop (js/tv-trips.js)\n  function fixPin(card, p, l) {")
s = rep(s, ".tvm-acts a.go{background:#3E2B1F;color:#FFFBF2;border-color:#3E2B1F}", ".tvm-acts a.go{background:#3E2B1F;color:#FFFBF2;border-color:#3E2B1F}.tvm-acts .tvm-trip{font:600 .8rem/1.2 inherit;border:1px solid #CBB98D;border-radius:.55rem;padding:.3rem .65rem;color:#3E2B1F;background:#FFFBF2;cursor:pointer}")
put(f, s)

# search's Peek (the Trip Planner's results open there): Add to a trip
f = 'website/js/search-panel.js'; s = live(f)
s = rep(s, "        + '<a class=\"tvs-btn go\" href=\"' + esc(l.url) + '\">' + (l.kind === 'outdoors' ? 'Open' : 'Open listing') + ' &rsaquo;</a></div>'",
           "        + (l.private_location ? '' : '<button type=\"button\" class=\"tvs-btn\" style=\"cursor:pointer;font-family:inherit\" data-trip-add=\"' + esc(JSON.stringify(tripRef(l))) + '\" aria-haspopup=\"dialog\">Add to a trip</button>')   // decisions/0174 §2\n"
           "        + '<a class=\"tvs-btn go\" href=\"' + esc(l.url) + '\">' + (l.kind === 'outdoors' ? 'Open' : 'Open listing') + ' &rsaquo;</a></div>'")
s = rep(s, "      if (l.kind !== 'outdoors' && !document.querySelector('script[src*=\"/js/link-button.js\"]')) {\n        var sc = document.createElement('script'); sc.src = '/js/link-button.js'; document.body.appendChild(sc);   // it finds the buttons itself\n      }",
           "      if (l.kind !== 'outdoors' && !document.querySelector('script[src*=\"/js/link-button.js\"]')) {\n        var sc = document.createElement('script'); sc.src = '/js/link-button.js'; document.body.appendChild(sc);   // it finds the buttons itself\n      }\n      " + LOAD + "   // Add to a trip's picker")
s = rep(s, "  function row(it, kind) {", "  " + REF + "   // a Peek's listing as a trip stop (js/tv-trips.js)\n  function row(it, kind) {")
put(f, s)

# the Trip Planner: Save as a trip
f = 'website/discovery/index.php'; s = live(f)
s = rep(s, "        . '<a href=\"/user/trips/\" style=\"font-weight:600;color:#92400E;text-decoration:underline\">Your trips</a></div>'",
           "        . '<button class=\"dv-btn\" type=\"button\" id=\"dv-plan-save\" style=\"background:transparent;color:#92400E;border:1px solid #CBB98D\">Save as a trip</button>'\n"
           "        . '<a href=\"/user/trips/\" style=\"font-weight:600;color:#92400E;text-decoration:underline\">Your trips</a></div>'")
s = rep(s, "      if (window.TvSearch) TvSearch.open(q); else location.href = '/directory/?q=' + encodeURIComponent(q);\n    });",
           "      if (window.TvSearch) TvSearch.open(q); else location.href = '/directory/?q=' + encodeURIComponent(q);\n    });\n"
           "    // Save as a trip (decisions/0174 §3): where, when and how long become a trip; its page offers the place's ready-made journeys\n"
           "    var sv = document.getElementById('dv-plan-save');\n"
           "    if (sv) sv.addEventListener('click', function () {\n"
           "      var body = { action: 'from_plan', where: f.where.value.trim(), where_slug: f.where_slug.value, where_level: f.where_level.value, when: f.when.value, days: f.days.value, tz: (function () { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (x) { return ''; } })() };\n"
           "      if (window.TvAsk) TvAsk.busy(sv, true);\n"
           "      fetch('/api/trips.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {\n"
           "        if (!d.signed_in) { if (window.TvAsk) TvAsk.busy(sv, false); if (window.TvJoin) TvJoin.open({ why: 'An account keeps your trips: days, times and a map, on any device.', next: location.pathname + location.search }); return; }\n"
           "        return fetch('/api/trips.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': d.csrf || '' }, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })\n"
           "          .then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) throw j; location.href = j.trip.url; });\n"
           "      }).catch(function (e) { if (window.TvAsk) { TvAsk.busy(sv, false); TvAsk.say((e && e.message) || 'That didn\\'t work: try again.', { anchor: sv, bad: true }); } });\n"
           "    });")
put(f, s)

# FAQ
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Days, reordering, times and a map of the route are coming with the trip page; sharing a trip and publishing it as a guide come after.",
           "Each trip has its own page (see \"What's on a trip's page?\"); sharing a trip and publishing it as a guide come after.")
a = "### Who can see my trips?"
s = rep(s, a, """### What's on a trip's page?
Open a trip from **Your trips** (or **Open it** after adding a place). Pick the day it **starts**, how many **days**, and when **each day starts**. Stops are listed by day, in order: drag one to move it (or use ↑ ↓, and the day beside it, on a phone), and choose how long you'll **stay** at each. Every stop gets a time, worked out from the drive before it; with a date, each is checked against its hours that day ("Closed when you'd arrive: Opens 11 AM", with **Move to day 2**), and parks and outdoor places against daylight. Times are in the time at the trip's first stop, always labelled (MST, PDT…); a stop in another time zone shows its own. The map numbers the stops, one colour a day. The miles and times between stops are straight-line estimates, so they say "about"; driving routes on our own map are coming. **Send to Maps** sends the whole trip to Google Maps or Apple Maps for turn-by-turn directions, and **Print** prints the list.

### Can I start a trip from the Trip Planner?
Yes. On Let's Explore's **Trip Planner**, pick where, when and how long, then **Save as a trip**: it becomes a trip with those days, and its page offers the ready-made journeys for that place as a start (**Use these stops**). Searching for ideas works as before, and **Add to a trip** is on a place's **Peek** in the results and on the map's card.

""" + a)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
