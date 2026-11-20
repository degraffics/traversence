import os, shutil, sys
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel):
    src = M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1)
    return open(src, encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:80], s.count(a)); return s.replace(a, b)

put('app/ui/maps-send.php', open(S + '/maps-send.php', encoding='utf-8').read())
put('website/js/tv-maps.js', open(S + '/tv-maps.js', encoding='utf-8').read())

# suggested journeys: the route icon opens Send to Maps, with every stop
f = 'app/ui/suggested-journeys.php'; s = live(f)
s = rep(s, "/** The whole route in the visitor's own maps app (decisions/0058 §8): up to ten stops. */\nfunction tv_sj_route_url(array $stops): string\n{\n"
          "    $pts = array_map(fn ($s) => round((float) $s['lat'], 6) . ',' . round((float) $s['lon'], 6), array_slice($stops, 0, 10));\n"
          "    if (count($pts) < 2) return '';\n    $q = ['api' => 1, 'origin' => array_shift($pts), 'destination' => array_pop($pts), 'travelmode' => 'driving'];\n"
          "    if ($pts) $q['waypoints'] = implode('|', $pts);\n    return 'https://www.google.com/maps/dir/?' . http_build_query($q);\n}",
        "/** The whole route in the visitor's own maps app (decisions/0058 §8): without script, Google's first ten stops; with it,\n"
        " * Send to Maps (decisions/0174 §4, app/ui/maps-send.php) offers Google or Apple and every stop, in legs. */\n"
        "function tv_sj_route_url(array $stops): string\n{\n    require_once __DIR__ . '/maps-send.php';\n    return tv_maps_fallback(tv_maps_stops($stops));\n}")
s = rep(s, """        . ($route !== '' ? '<a class="sj-route" href="' . $e($route) . '" target="_blank" rel="noopener" title="The whole route in your maps app" aria-label="Open the whole route in your maps app" data-out="directions">'""",
           """        . ($route !== '' ? tv_maps_script() . '<a class="sj-route" ' . tv_maps_attrs($stops, (string) $j['title']) . ' title="Send to Maps: the whole route in Google Maps or Apple Maps" aria-label="Send the whole route to Google Maps or Apple Maps" aria-haspopup="dialog">'""")
s = rep(s, "require_once __DIR__ . '/../lib/Hours.php';\n", "require_once __DIR__ . '/../lib/Hours.php';\nrequire_once __DIR__ . '/maps-send.php';\n")
put(f, s)

# a landmark's Make a day of it
f = 'website/place/landmark.php'; s = live(f)
s = rep(s, """About <?= max(1, (int) round($day['miles'])) ?> mi in all. <a href="<?= tv_e($day['route']) ?>" target="_blank" rel="noopener" data-out="directions" style="color:var(--tv-link);font-weight:600;text-decoration:underline">Open the route in Maps</a></p>""",
           """About <?= max(1, (int) round($day['miles'])) ?> mi in all. <?php require_once __DIR__ . '/../../app/ui/maps-send.php'; $mAttrs = tv_maps_attrs($day['stops'], $r['name'] . ': a day out'); ?><?php if ($mAttrs !== ''): ?><?= tv_maps_script() ?><a <?= $mAttrs ?> aria-haspopup="dialog" style="color:var(--tv-link);font-weight:600;text-decoration:underline">Send to Maps</a><?php else: ?><a href="<?= tv_e($day['route']) ?>" target="_blank" rel="noopener" data-out="directions" style="color:var(--tv-link);font-weight:600;text-decoration:underline">Open the route in Maps</a><?php endif; ?></p>""")
put(f, s)

# the same rules as Directions (session manager's review): a guide's journey drops a listing whose location is kept
# private (ListingSafety) or whose address is a mailbox; a landmark's place to eat is never a private one
f = 'app/lib/JourneyPlan.php'; s = live(f)
s = rep(s, "    /** Listings left out: asked to be (decisions/0146 §6 q5), or only placed at their ZIP's middle. */",
           "    /** Listings left out: asked to be (decisions/0146 §6 q5), only placed at their ZIP's middle, a location kept private\n"
           "     *  (ListingSafety, decisions/0174 §2) or a mailbox (no way to drive to it): the same rules as Directions. */")
s = rep(s, "        foreach ($drop as $d) unset($out['entity:' . (int) $d]);\n        return $out;",
           "        try {\n"
           "            require_once __DIR__ . '/ListingSafety.php'; require_once __DIR__ . '/ListingActions.php';\n"
           "            foreach ($pdo->query('SELECT id, name, category_id FROM entities WHERE id IN (' . $in . ')')->fetchAll(PDO::FETCH_ASSOC) as $e)\n"
           "                if (ListingSafety::isConfidential($e['category_id'] !== null ? (int) $e['category_id'] : null, (string) $e['name'])) $drop[] = (int) $e['id'];\n"
           "        } catch (Throwable) {}\n"
           "        foreach ($out as $ref => $c) if (isset($c['id']) && ListingActions::isMailbox((string) ($c['address'] ?? ''))) $drop[] = (int) $c['id'];\n"
           "        foreach ($drop as $d) unset($out['entity:' . (int) $d]);\n        return $out;")
put(f, s)
f = 'app/lib/Landmarks.php'; s = live(f)
s = rep(s, "WHERE kind = 'listing' AND why LIKE '%eat%' AND lat BETWEEN ? AND ? AND lon BETWEEN ? AND ? LIMIT 300",
           "WHERE kind = 'listing' AND why LIKE '%eat%' AND private_location = 0 AND lat BETWEEN ? AND ? AND lon BETWEEN ? AND ? LIMIT 300")
put(f, s)

# FAQ
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "or the route icon to open the whole journey in your maps app.", "or the route icon to send the whole journey to Google Maps or Apple Maps (see below).")
s = rep(s, "gives the miles for each leg, and opens the whole route in Maps with one tap.", "gives the miles for each leg, and **Send to Maps** opens the whole route in Google Maps or Apple Maps.")
a = "### Why do more names in a guide link now, even in places you don't cover?"
s = rep(s, a, """### How do I send a whole journey to Google Maps or Apple Maps?
Tap the route icon on a suggested journey, or **Send to Maps** under "Make a day of it" on a place's page. Pick **Google Maps** or **Apple Maps** and where to start: **My location** or the first stop. Your choices are kept on this device for next time. Every stop goes, in order. One link holds ten points, so a longer route is split into legs ("Leg 1 of 3"): open them in order, each starting where the last one ended. Nothing is left off. Several stops in Apple Maps need iOS 18.4 or macOS 15.4 or later. It's a plain link: we send nothing to Google or Apple, and it costs nothing. Sending a trip of your own that you've saved is coming with trips.

""" + a)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
