#!/usr/bin/env python3
"""decisions/0174 release 2: trips and Add to a trip. Reads the live mirror, writes out/. Replayable."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out'); SRC = os.path.join(S, 'src')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
src = lambda f: open(os.path.join(SRC, f), encoding='utf-8').read()

put('app/migrations/2026-11-20_trips.sql', src('trips.sql'))
put('app/lib/Trips.php', src('Trips.php'))
put('website/api/trips.php', src('api-trips.php'))
put('website/js/tv-trips.js', src('tv-trips.js'))
put('website/user/trips/index.php', src('user-trips.php'))

# ⋯ on content pages: Add to a trip, switched on (greyed with why when the place can't go in one)
f = 'app/ui/engage-ui.php'; s = live(f)
s = rep(s, "    if (!empty($o['tools'])) $h .= tv_engage_tools((array) $o['tools'], (string) ($o['report'] ?? ''), $ic, $first);",
           "    if (!empty($o['tools'])) {\n"
           "        $tt = (array) $o['tools'];\n"
           "        if (in_array($kind, ['listing', 'lm', 'rec', 'place'], true)) {   // Add to a trip (decisions/0174 §2): the rules say whether, never hidden\n"
           "            require_once __DIR__ . '/../lib/Trips.php';\n"
           "            $tt['add'] = [$kind, $ref, (string) ($o['title'] ?? ''), Trips::ready($pdo) ? Trips::addable($pdo, $kind, $ref) : 'Coming with the next site update.'];\n"
           "            $h .= tv_trips_script();\n"
           "        }\n"
           "        $h .= tv_engage_tools($tt, (string) ($o['report'] ?? ''), $ic, $first);   // ⋯ (Report moves into it)\n"
           "    }")
s = rep(s, " * page's own button (one way to do a thing); one it can't offer here is greyed with why, never hidden. $t: map (the page's\n"
           " * See on map button, '#id', or ''), dir (the page's directions URL, or ['', why]), trip (['key' => place key] or ['lat', 'lon']),",
           " * page's own button (one way to do a thing); one it can't offer here is greyed with why, never hidden. $t: map (the page's\n"
           " * See on map button, '#id', or ''), dir (the page's directions URL, or ['', why]), trip (['key' => place key] or ['lat', 'lon']),\n"
           " * add ([kind, ref, name, why]: Add to a trip, decisions/0174 §2; why '' when it can go in one),")
s = rep(s, "    $m .= $off($ADD, 'Add to a trip', 'Coming: trips you can save and add places to.');",
           "    $add = $t['add'] ?? null;\n"
           "    $m .= is_array($add) && ($add[3] ?? '') === '' ? '<button type=\"button\" role=\"menuitem\" data-trip-add=\"' . tv_e(json_encode(['kind' => $add[0], 'ref' => (string) $add[1], 'name' => (string) $add[2]], JSON_UNESCAPED_UNICODE)) . '\" aria-haspopup=\"dialog\">' . $ic($ADD) . 'Add to a trip</button>'\n"
           "        : $off($ADD, 'Add to a trip', is_array($add) ? (string) $add[3] : 'This page isn\\'t a place you can visit.');")
s = rep(s, "/** One comment's card:",
           "/** The trips script, once per page (js/tv-trips.js: the picker, the guest's trip on the device, moving it in at sign-in). */\n"
           "function tv_trips_script(): string\n{\n    static $done = false;\n    if ($done) return '';\n    $done = true;\n"
           "    return '<script src=\"/js/tv-trips.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-trips.js') . '\" defer></script>';\n}\n\n/** One comment's card:")
put(f, s)

# a ready-made journey: Make it my trip
f = 'app/ui/suggested-journeys.php'; s = live(f)
s = rep(s, "        . '<p class=\"sj-meta\">' . $e($meta) . '</p>'",
           "        . '<p class=\"sj-meta\">' . $e($meta) . '</p>'\n"
           "        . tv_trips_script() . '<p class=\"sj-mine\"><button type=\"button\" data-trip-journey=\"' . (int) $j['id'] . '\" aria-haspopup=\"dialog\">Make it my trip</button></p>'   // decisions/0174 §1")
s = rep(s, "  .sj-note{", "  .sj-mine{margin:.1rem 0 .3rem}.sj-mine button{font:600 .82rem/1.2 inherit;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer}\n  .sj-note{")
s = rep(s, "require_once __DIR__ . '/../lib/Hours.php';\nrequire_once __DIR__ . '/maps-send.php';\n", "require_once __DIR__ . '/../lib/Hours.php';\nrequire_once __DIR__ . '/maps-send.php';\nrequire_once __DIR__ . '/engage-ui.php';   // tv_trips_script()\n")
put(f, s)

# Get Local's cards: ⋯ → Add to a trip
f = 'website/directory/index.php'; s = live(f)
s = rep(s, "        + '<button type=\"button\" role=\"menuitem\" data-tv-peek=\"' + l.id + '\">' + I.peek + 'Peek</button></span></span>'",
           "        + '<button type=\"button\" role=\"menuitem\" data-tv-peek=\"' + l.id + '\">' + I.peek + 'Peek</button>'\n"
           "        // Add to a trip (decisions/0174 §2): a place kept private is greyed with why; the server checks the rest\n"
           "        + (l.private_location ? '<span class=\"dr-mo\">' + I.add + 'Add to a trip<small>Its location is kept private: call them</small></span>'\n"
           "          : '<button type=\"button\" role=\"menuitem\" data-trip-add=\"' + esc(JSON.stringify({kind: 'listing', ref: String(l.id), name: l.business_name})) + '\" aria-haspopup=\"dialog\">' + I.add + 'Add to a trip</button>')\n"
           "        + '</span></span>'")
s = rep(s, "    peek: '<svg", "    add: '<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M12 5v14M5 12h14\"/></svg>',\n    peek: '<svg")
s = rep(s, "<script src=\"/js/link-button.js?v=<?= (int) @filemtime(__DIR__ . '/../js/link-button.js') ?>\" defer></script>",
           "<script src=\"/js/link-button.js?v=<?= (int) @filemtime(__DIR__ . '/../js/link-button.js') ?>\" defer></script>\n"
           "<script src=\"/js/tv-trips.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-trips.js') ?>\" defer></script>")
put(f, s)

# Your trips in the account's menu, and from the Trip Planner
f = 'website/user/dashboard.php'; s = live(f)
s = rep(s, "$R('/user/orders.php', 'Your orders', 'inbox')", "$R('/user/orders.php', 'Your orders', 'inbox'), $R('/user/trips/', 'Your trips', 'map')", 2)
put(f, s)
f = 'website/discovery/index.php'; s = live(f)
s = rep(s, "Where, when and what search now. Length, budget and features stay with your plan for when trip plans open.</span></div>'",
           "Where, when and what search now. Length, budget and features stay with your plan; save places with ⋯ → Add to a trip.</span>'\n"
           "        . '<a href=\"/user/trips/\" style=\"font-weight:600;color:#92400E;text-decoration:underline\">Your trips</a></div>'")
put(f, s)

# account deletion deletes the person's trips (decisions/0174 §6)
f = 'app/lib/AccountDeletion.php'; s = live(f)
s = rep(s, "            'saved places' => $n('SELECT COUNT(*) n FROM user_places WHERE user_id = ?'),",
           "            'saved places' => $n('SELECT COUNT(*) n FROM user_places WHERE user_id = ?'),\n"
           "            'trips' => $n('SELECT COUNT(*) n FROM trips WHERE user_id = ?'),")
s = rep(s, "            $do('DELETE m FROM people_group_members m JOIN people_groups g ON g.id = m.group_id WHERE g.owner_id = ?', [$uid]);",
           "            $do('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = ?', [$uid]);   // their trips (decisions/0174 §6)\n"
           "            $do('DELETE FROM trips WHERE user_id = ?', [$uid]);\n"
           "            $do('DELETE m FROM people_group_members m JOIN people_groups g ON g.id = m.group_id WHERE g.owner_id = ?', [$uid]);")
put(f, s)

f = 'app/lib/staging-tables.php'; s = live(f)
s = rep(s, "        'address_contacts', 'business_broadcasts',", "        'trips', 'trip_stops' /* a member's trips (decisions/0174) */,\n        'address_contacts', 'business_broadcasts',")
put(f, s)

# FAQ
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Sending a trip of your own that you've saved is coming with trips.", "A trip you've saved goes the same way, from **Your trips**.")
s = rep(s, "Saving a journey to your trips and changing it is coming with the Trip Planner.", "Tap **Make it my trip** to copy it into a trip of your own and change it (see \"How do I save places to a trip?\").")
a = "### How do I send a whole journey to Google Maps or Apple Maps?"
s = rep(s, a, """### How do I save places to a trip?
On a place, a business or an outdoor place, tap ⋯ then **Add to a trip**, and pick one of your trips or **New trip** (named for the place, like "Trip to Show Low"). On **Get Local** it's in each card's ⋯. On a ready-made journey, **Make it my trip** copies its stops into a trip of your own. It says "Added to Trip to Show Low" with **Open it** and **Undo**. Your trips are under **Your trips** in your account's menu (and beside the Trip Planner on Let's Explore): take a stop out (with Undo), rename a trip, send it to Google Maps or Apple Maps, or delete it (it's kept 30 days, so you can put it back). Days, reordering, times and a map of the route are coming with the trip page; sharing a trip and publishing it as a guide come after.

### Who can see my trips?
Only you. A trip is private: nobody else sees it, and nobody sees who added a place to a trip. Sharing a trip by link, and publishing one as a Personal Guide, are coming, and only when you choose. Deleting your account deletes your trips.

### Why can't I add some places to a trip?
**Add to a trip** is greyed, with the reason, when a place can't go in one: a place whose location is kept private (a shelter: call them instead), a place we tell about but don't put on the map, a business closed for good, or one with only a mailbox address. A stop that becomes one of these later stays in your trip, greyed with the reason, and is left out when you send the trip to Maps.

### Can I make a trip without an account?
Yes: as a guest, one trip is kept on this device (in this browser only). Make an account to keep it and see it anywhere: when you sign in, the trip on this device moves into your account. Private browsing may not keep it.

""" + a)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
