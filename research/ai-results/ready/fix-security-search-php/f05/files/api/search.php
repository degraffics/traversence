<?php
// /api/search.php — universal search, answered in place (decisions/0053). Works signed in or out; read-only.
//   GET ?q=greer arizona[&level=cluster&slug=round-valley][&drop=place,category]
//     -> {chips, text, top, groups: [{kind, title, items: [{label, sub, url, id?, peek?}], more?}], people, did_you_mean}
//     level/slug: the page's or visitor's current place (the default area); drop: chip types the person removed;
//     radius: miles around the place instead of its boundary. The reply's `map` is the query string for the full-screen
//     map (/api/search_listings.php?…&view=map).
//     at=DOW,MIN: the visitor's day and time, for "open now". With the 5W index built, `results` holds everything that
//     matches in one ordered list ({items: [{kind, id, label, sub, url, open, peek}], kinds, total, more, note}).
//   GET ?presets=1[&level&slug | &near=Show Low][&at][&mine=["your phrase",…]] -> {place, presets: [{label, q, count}],
//                                          mine: [{q, count}]}: your own quick searches counted here (SearchPresets::mine)
//   ?count=1&q=…[&level&slug] (a beacon when someone acts on a search) -> counts the reading only, per place and day
//   GET ?suggest=1&q=[&level&slug] -> {items: [{label, crumb, url|q, kind, here, peek?, id?}], here, place}: as you type
//   GET ?peek=LISTING_ID -> {listing}: the peek card's public details (a confidential location shows its phone only).
//   GET ?removed=…&q=…[&rk=] -> a visitor's × on a suggestion, counted only (never who, never a block).
//   POST removed=…&q=…[&rk=]&csrf_token=… (or header X-CSRF-Token; form or JSON body) -> staff block for these words
//     (f05: a block changes what everyone sees, so it needs a POST with this site's CSRF token; a GET never blocks).
// Nothing about the query is stored.

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/UniversalSearch.php';
if (is_file(__DIR__ . '/../../app/lib/SearchPresets.php')) require_once __DIR__ . '/../../app/lib/SearchPresets.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
try {
    if (isset($_GET['peek'])) {
        $pk = (string) $_GET['peek'];
        $l = str_starts_with($pk, 'rec:') ? UniversalSearch::peekOutdoors($pdo, mb_substr($pk, 4, 40)) : UniversalSearch::peek($pdo, (int) $pk);
        if (!$l) Response::error('That listing isn\'t available.', 404, 'not_found');
        Response::json(['ok' => true, 'listing' => $l]);
    }
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $ctx = ['level' => (string) ($_GET['level'] ?? ''), 'slug' => (string) ($_GET['slug'] ?? ''),
            'town' => mb_substr(trim((string) ($_GET['town'] ?? '')), 0, 80),
            // a place outside our areas (set by state, city or ZIP, or Auto-detect): still the person's place
            'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 60), 'state' => mb_substr(trim((string) ($_GET['state'] ?? '')), 0, 2),
            'zip' => mb_substr(trim((string) ($_GET['zip'] ?? '')), 0, 5),
            'lat' => is_numeric($_GET['plat'] ?? null) ? (float) $_GET['plat'] : null, 'lon' => is_numeric($_GET['plon'] ?? null) ? (float) $_GET['plon'] : null,
            'reach' => preg_match('/^(place|all|\d{1,3})$/', (string) ($_GET['reach'] ?? ''), $rm) ? $rm[1] : 'all'];   // how far: the place, miles, or everywhere   // the town someone chose in its town area ("Saint Johns, AZ")
    $at = preg_match('/^([0-6]),(\d{1,4})$/', (string) ($_GET['at'] ?? ''), $am) && (int) $am[2] < 1440 ? [(int) $am[1], (int) $am[2]] : [(int) date('w'), (int) date('G') * 60 + (int) date('i')];
    // Map pins from the 5W index (Discover's map): listings and outdoors places with a point, by Why, in a place or a distance
    if (isset($_GET['pins'])) {
        if (!class_exists('SearchIndex') || !SearchIndex::ready($pdo)) Response::json(['success' => true, 'pins' => [], 'total' => 0, 'center' => null]);
        $place = UniversalSearch::scopePlace($pdo, $ctx);
        $area = null;
        if (is_numeric($_GET['lat'] ?? null) && is_numeric($_GET['lon'] ?? null)) $area = ['lat' => (float) $_GET['lat'], 'lon' => (float) $_GET['lon'], 'radius' => max(1, min(250, (int) ($_GET['radius'] ?? 25)))];
        elseif ($place && !empty($place['zips']) && (int) ($_GET['radius'] ?? 0) > 0) {
            $zc = $pdo->prepare('SELECT latitude, longitude FROM zip_coordinates WHERE zip = ? LIMIT 1');
            $zc->execute([(string) $place['zips'][0]]);
            if ($z = $zc->fetch(PDO::FETCH_ASSOC)) $area = ['lat' => (float) $z['latitude'], 'lon' => (float) $z['longitude'], 'radius' => max(1, min(250, (int) $_GET['radius']))];
        }
        $kinds = array_values(array_intersect(explode(',', (string) ($_GET['kinds'] ?? 'listing,outdoors')), ['listing', 'outdoors', 'story', 'group']));
        $why = preg_match('/^[a-z]{2,10}$/', (string) ($_GET['why'] ?? '')) ? (string) $_GET['why'] : '';
        // words typed in the header search while the map is open narrow the pins (js/map-view.js)
        $words = $q !== '' ? array_slice(array_values(array_filter(preg_split('/\s+/u', mb_strtolower($q)) ?: [], fn ($w) => mb_strlen($w) >= 2 && !in_array($w, ['to', 'in', 'the', 'and', 'of', 'near', 'for', 'at', 'with', 'on', 'places', 'place', 'things', 'thing', 'me'], true))), 0, 5) : [];
        $rows = SearchIndex::rows($pdo, $area ? null : $place, $area, ['kinds' => $kinds, 'why' => $why, 'geo' => true, 'limit' => 3000] + ($words ? ['words' => $words] : []));
        $pins = array_map(fn ($r) => ['id' => $r['kind'] === 'outdoors' ? 'rec:' . $r['item_id'] : (int) $r['item_id'], 'lat' => (float) $r['lat'], 'lon' => (float) $r['lon'],
                                     'name' => $r['label'], 'category' => $r['sub'], 'city' => $r['city'], 'state' => $r['state']], $rows);
        Response::json(['success' => true, 'pins' => $pins, 'total' => count($pins), 'truncated' => count($pins) >= 3000,
                        'center' => $area ?: null]);
    }
    // Tier 1 (decisions/0060): direct hits as you type, local first, each with its place › kind
    // staff can teach search a word from the box itself (decisions/0063): their "Add it" opens the Workbench
    $canTeach = (function () use ($pdo): bool {
        try { $me = Auth::currentUser(); if (!$me) return false; require_once __DIR__ . '/../../app/lib/crawler/IntakeStager.php'; return IntakeStager::canUseIntake($pdo, $me); } catch (Throwable) { return false; }
    })();
    // a suggestion removed (decisions/0063 §3): staff block it for these words; a visitor's × is counted, never who.
    // f05: any page can make a visitor's browser send a GET, so a GET is only ever a count (no user id, never a block);
    // a staff block needs a POST carrying this site's CSRF token (app/lib/SuggestionRemoveGuard.php)
    $rmBody = [];
    $rmPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    if ($rmPost) {
        require_once __DIR__ . '/../../app/lib/SuggestionRemoveGuard.php';
        $rmBody = SuggestionRemoveGuard::body($_POST, (string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    }
    $rmPosted = $rmPost && SuggestionRemoveGuard::str($rmBody, 'removed') !== null;
    if ($rmPosted || isset($_GET['removed'])) {
        require_once __DIR__ . '/../../app/lib/SuggestionFlags.php';
        if ($rmPosted) {
            // no valid token (or a cross-site request): nothing is recorded at all
            if (!SuggestionRemoveGuard::requestOk($_SERVER, $rmBody, SuggestionRemoveGuard::sessionToken())) Response::error('Please refresh the page and try again.', 403, 'csrf_failed');
            $rmPq = SuggestionRemoveGuard::str($rmBody, 'q');
            $rmQ = $rmPq !== null ? mb_substr(trim($rmPq), 0, 80) : $q;
            $rmWhat = (string) SuggestionRemoveGuard::str($rmBody, 'removed');
            $rmKind = SuggestionRemoveGuard::str($rmBody, 'rk') ?? 'pred';
            $rmBy = SuggestionRemoveGuard::mayBlock(true, true, $canTeach) ? Auth::currentUserId() : null;
        } else {
            // a GET: counted only, with no one's id, so a forged link can't block anything (f05)
            $rmQ = $q;
            $rmWhat = (string) $_GET['removed'];
            $rmKind = (string) ($_GET['rk'] ?? 'pred');
            $rmBy = null;
        }
        if (SuggestionFlags::ready($pdo)) { try { SuggestionFlags::remove($pdo, $rmQ, $rmWhat, $rmKind, $rmBy); } catch (Throwable) {} }
        Response::json(['ok' => true]);
    }
    // suggestions only read the session: let it go now, so the next keystroke's request doesn't wait for this one to
    // finish (PHP holds a visitor's session one request at a time) (2026-10-14: suggestions were slow)
    if (isset($_GET['suggest']) && session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (isset($_GET['suggest'])) Response::json(['ok' => true, 'can_teach' => $canTeach] + UniversalSearch::suggest($pdo, $q, $ctx + ['end' => !empty($_GET['end']), 'track' => in_array($_GET['track'] ?? '', ['explore', 'local', 'social', 'market', 'home', 'member'], true) ? (string) $_GET['track'] : '']));
    // Presets for the place (decisions/0053 §5): its scope (level/slug), or a place named in `near`
    if (isset($_GET['presets'])) {
        if (!class_exists('SearchPresets')) Response::json(['ok' => true, 'presets' => [], 'place' => null]);
        $near = mb_substr(trim((string) ($_GET['near'] ?? '')), 0, 60);
        $place = UniversalSearch::scopePlace($pdo, $ctx);
        if (!$place && $near !== '') { foreach (UniversalSearch::understand($pdo, $near)['chips'] as $c) if ($c['type'] === 'place') { $place = $c; break; } }
        $mine = json_decode((string) ($_GET['mine'] ?? ''), true);   // your own quick searches, from this device (Jason, 2026-11-22: "Count them")
        $mine = is_array($mine) ? SearchPresets::mine($pdo, $ctx + ['track' => in_array($_GET['track'] ?? '', ['explore', 'local', 'social', 'market', 'home', 'member'], true) ? (string) $_GET['track'] : ''], array_values(array_filter((array) Response::unwrap($mine), 'is_string'))) : [];
        Response::json(['ok' => true, 'place' => $place['label'] ?? null, 'presets' => SearchPresets::forPlace($pdo, $place, $at, $place && empty($place['scope']) && $near !== '' ? $near : ''), 'mine' => $mine]);
    }
    // Someone acted on a search: count its reading for this place and day (never the words, never who)
    // "near me" found too little within 5 mi (Jason, 2026-11-10): recorded for the team as search demand ("results lacking",
    // the words and the place, never who) and the town goes to the crawler
    if (isset($_GET['lacking'])) {
        if (is_file(__DIR__ . '/../../app/lib/SearchDemand.php') && mb_strlen($q) >= 2) {
            require_once __DIR__ . '/../../app/lib/SearchDemand.php';
            $lp = SearchDemand::fromSearch($pdo, $q, $ctx, 'lacking');
            if ($lp && !empty($lp['city']) && !empty($lp['state']) && is_file(__DIR__ . '/../../app/lib/crawler/TownCrawl.php')) {
                require_once __DIR__ . '/../../app/lib/crawler/TownCrawl.php';
                try { TownCrawl::fromSearch($pdo, (string) $lp['city'], (string) $lp['state']); } catch (Throwable $e) { error_log('lacking crawl: ' . $e->getMessage()); }
            }
        }
        Response::json(['ok' => true]);
    }
    if (isset($_GET['count'])) {
        if (class_exists('SearchPresets') && mb_strlen($q) >= 2) SearchPresets::record($pdo, $q, $ctx);
        // the words searched, by area and day, and whether anything was found: never who (decisions/0061)
        require_once __DIR__ . '/../../app/lib/Situations.php';
        $cp = UniversalSearch::scopePlace($pdo, $ctx);
        try { Situations::logTerm($pdo, $q, (string) ($cp['key'] ?? ''), isset($_GET['r']) && (int) $_GET['r'] === 0); } catch (Throwable) {}
        // search demand (decisions/0066): the place, what for, by day; never who
        if (is_file(__DIR__ . '/../../app/lib/SearchDemand.php')) { require_once __DIR__ . '/../../app/lib/SearchDemand.php'; SearchDemand::fromSearch($pdo, $q, $ctx); }
        // nothing found (gap review P1-3): demand for the place searched or the searcher's area, named or not; a kind of place
        // becomes that kind's job in the town (from our own kinds, never the words), a topic waits as one of W6's gaps
        if (isset($_GET['r']) && (int) $_GET['r'] === 0 && class_exists('SearchDemand')) {
            try {
                $lp = SearchDemand::fromSearch($pdo, $q, $ctx, 'lacking');   // "results lacking": the column's existing value, no change to its type
                if ($lp && !empty($lp['city']) && !empty($lp['state']) && is_file(__DIR__ . '/../../app/lib/crawler/TownCrawl.php')) {
                    require_once __DIR__ . '/../../app/lib/OpenPlaces.php'; require_once __DIR__ . '/../../app/lib/crawler/TownCrawl.php';
                    $kind = OpenPlaces::kindFor(mb_strtolower((string) $lp['anchor']));
                    if ($kind !== '') TownCrawl::fromSearch($pdo, (string) $lp['city'], (string) $lp['state'], $kind);
                }
            } catch (Throwable $e) { error_log('empty search demand: ' . $e->getMessage()); }
        }
        Response::json(['ok' => true]);
    }
    // what was opened after a search (decisions/0061, batch 3): the words, area, day and the kind of thing; never who
    if (isset($_GET['opened'])) {
        if (is_file(__DIR__ . '/../../app/lib/Learning.php')) {
            require_once __DIR__ . '/../../app/lib/Learning.php';
            $cp = UniversalSearch::scopePlace($pdo, array_diff_key($ctx, ['town' => 1]));
            try { Learning::logOpen($pdo, $q, (string) ($cp['key'] ?? ''), (string) $_GET['opened']); } catch (Throwable) {}
        }
        Response::json(['ok' => true]);
    }
    // a kind of place tapped while typing (decisions/0067, 2026-10-07): the words, area, day and the kind; never who
    if (isset($_GET['opened_kind'])) {
        if (is_file(__DIR__ . '/../../app/lib/Learning.php')) {
            require_once __DIR__ . '/../../app/lib/Learning.php';
            $cp = UniversalSearch::scopePlace($pdo, array_diff_key($ctx, ['town' => 1]));
            try { Learning::logOpenKind($pdo, $q, (string) ($cp['key'] ?? ''), mb_substr((string) $_GET['opened_kind'], 0, 120)); } catch (Throwable) {}
        }
        Response::json(['ok' => true]);
    }
    // how a search turned out (the Missed searches report, decisions/0061): words, area, day, outcome; never who
    if (isset($_GET['outcome'])) {
        require_once __DIR__ . '/../../app/lib/Situations.php';
        // by area: a town chosen in its town area counts under the area (a town has no place key of its own)
        $cp = UniversalSearch::scopePlace($pdo, array_diff_key($ctx, ['town' => 1]));
        try { Situations::logOutcome($pdo, $q, (string) ($cp['key'] ?? ''), explode(',', (string) $_GET['outcome'])); } catch (Throwable) {}
        Response::json(['ok' => true]);
    }
    $drop = array_values(array_intersect(explode(',', (string) ($_GET['drop'] ?? '')), ['place', 'scope', 'category', 'kind', 'cond', 'facts', 'when', 'why', 'who', 'funnel', 'outing']));
    // a scope chosen in the panel ("Search in: Stories") narrows to one kind (decisions/0053, the search panel)
    $sc = (string) ($_GET['scope'] ?? '');
    if (in_array($sc, ['places', 'listings', 'stories', 'groups', 'topics'], true)) $ctx['kind'] = $sc;
    // or to one of the brand sections: Let's Explore, Get Local, Social, Marketplace
    elseif (preg_match('/^f:(explore|local|social|market)$/', $sc, $m)) $ctx['funnel'] = $m[1];
    // the section the search was opened in (the Chameleon Filter): explore puts the place's stories and outdoors first
    if (in_array($_GET['track'] ?? '', ['explore', 'local', 'social', 'market', 'home', 'member'], true)) $ctx['track'] = (string) $_GET['track'];
    // the visitor's own day and time ("DOW,MIN"), for "open now"
    if (preg_match('/^([0-6]),(\d{1,4})$/', (string) ($_GET['at'] ?? ''), $m) && (int) $m[2] < 1440) $ctx['at'] = [(int) $m[1], (int) $m[2]];
    if (class_exists('SearchIndex')) SearchIndex::refresh($pdo);   // at most every 15 minutes: picks up changed listings
    if (mb_strlen($q) < 2 && $ctx['slug'] === '') Response::json(['ok' => true, 'chips' => [], 'text' => '', 'top' => null, 'groups' => [], 'people' => null, 'did_you_mean' => null]);
    Response::json(['ok' => true, 'can_teach' => $canTeach] + UniversalSearch::search($pdo, $q, $ctx, $drop, Auth::currentUserId(), max(0, min(250, (int) ($_GET['radius'] ?? 0)))));
} catch (Throwable $e) {
    error_log('search.php: ' . $e->getMessage());
    Response::error('Search is unavailable right now.', 500, 'search_failed');
}
