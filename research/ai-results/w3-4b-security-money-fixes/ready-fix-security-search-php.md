=== FILE: f05/files/api/search.php ===
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
=== END FILE ===

=== FILE: f05/files/app/lib/SuggestionRemoveGuard.php ===
<?php
// app/lib/SuggestionRemoveGuard.php — f05: who may turn a removed suggestion into a staff block.
// A GET on /api/search.php?removed= is only ever counted (no one's id). A block needs:
//   a POST, carrying this session's CSRF token (header X-CSRF-Token or body field csrf_token),
//   not marked cross-site by the browser (Sec-Fetch-Site), and a person who can use the Workbench ($canTeach).
// Fails closed: no session, no token, or a wrong token means nothing is recorded.
// SESSION_KEY is the safest guess at the site's token; the lane confirms it (release.json verify).

declare(strict_types=1);

final class SuggestionRemoveGuard
{
    public const SESSION_KEY = 'csrf_token';
    public const BODY_KEY = 'csrf_token';
    public const HEADER = 'HTTP_X_CSRF_TOKEN';
    public const MAX_BODY = 65536;

    /** The POST body: form fields, or a small JSON object. Anything else is empty. */
    public static function body(array $post, string $contentType, ?string $raw = null): array
    {
        if ($post) return $post;
        if (stripos($contentType, 'application/json') === false) return [];
        if ($raw === null) {
            $read = @file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
            $raw = is_string($read) ? $read : '';
        }
        if ($raw === '' || strlen($raw) > self::MAX_BODY) return [];
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    /** A string field, or null when missing or not a string. */
    public static function str(array $a, string $key): ?string
    {
        return isset($a[$key]) && is_string($a[$key]) ? $a[$key] : null;
    }

    /** The token this session was given, or null (no session, or none issued). */
    public static function sessionToken(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return null;
        $t = $_SESSION[self::SESSION_KEY] ?? null;
        return is_string($t) && $t !== '' ? $t : null;
    }

    /** The token the request carries: the header first, then the body field. */
    public static function supplied(array $server, array $body): ?string
    {
        $h = $server[self::HEADER] ?? null;
        if (is_string($h) && $h !== '') return $h;
        return self::str($body, self::BODY_KEY);
    }

    public static function tokenOk(?string $expected, ?string $supplied): bool
    {
        if ($expected === null || $supplied === null || $expected === '' || $supplied === '') return false;
        return hash_equals($expected, $supplied);
    }

    /** The browser says another site sent this request. */
    public static function crossSite(array $server): bool
    {
        return strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? ''))) === 'cross-site';
    }

    public static function requestOk(array $server, array $body, ?string $expected): bool
    {
        if (self::crossSite($server)) return false;
        return self::tokenOk($expected, self::supplied($server, $body));
    }

    /** Only a checked POST from someone who can teach search records who blocked it. */
    public static function mayBlock(bool $isPost, bool $requestOk, bool $canTeach): bool
    {
        return $isPost && $requestOk && $canTeach;
    }
}
=== END FILE ===

=== FILE: f05/tests/suggestion-remove-guard-test.php ===
<?php
// tools/search-tests/suggestion-remove-guard-test.php — f05: unit checks for SuggestionRemoveGuard (no database).
// Run: php tools/search-tests/suggestion-remove-guard-test.php   (or set ROOT=/path/to/repo)

declare(strict_types=1);

$root = getenv('ROOT') ?: dirname(__DIR__, 2);
$lib = $root . '/app/lib/SuggestionRemoveGuard.php';
if (!is_file($lib)) { echo "FAIL cannot find $lib (set ROOT)\n"; exit(1); }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) $fails++;
}

$tok = 'abc123def456abc123def456';

// tokens
check('no expected token fails closed', !SuggestionRemoveGuard::tokenOk(null, $tok));
check('no supplied token fails', !SuggestionRemoveGuard::tokenOk($tok, null));
check('empty tokens fail', !SuggestionRemoveGuard::tokenOk('', ''));
check('wrong token fails', !SuggestionRemoveGuard::tokenOk($tok, $tok . 'x'));
check('matching token passes', SuggestionRemoveGuard::tokenOk($tok, $tok));

// where the token comes from
check('header token is read', SuggestionRemoveGuard::supplied(['HTTP_X_CSRF_TOKEN' => $tok], []) === $tok);
check('body token is read', SuggestionRemoveGuard::supplied([], ['csrf_token' => $tok]) === $tok);
check('array body token ignored', SuggestionRemoveGuard::supplied([], ['csrf_token' => [$tok]]) === null);

// whole request
check('POST with token passes', SuggestionRemoveGuard::requestOk([], ['csrf_token' => $tok], $tok));
check('POST without token fails', !SuggestionRemoveGuard::requestOk([], ['removed' => 'x'], $tok));
check('cross-site with right token fails', !SuggestionRemoveGuard::requestOk(['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_X_CSRF_TOKEN' => $tok], [], $tok));
check('same-origin with token passes', SuggestionRemoveGuard::requestOk(['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_X_CSRF_TOKEN' => $tok], [], $tok));
check('no session token fails closed', !SuggestionRemoveGuard::requestOk([], ['csrf_token' => $tok], null));

// who may block
check('GET never blocks, even for staff', !SuggestionRemoveGuard::mayBlock(false, true, true));
check('POST without token never blocks', !SuggestionRemoveGuard::mayBlock(true, false, true));
check('visitor POST never blocks', !SuggestionRemoveGuard::mayBlock(true, true, false));
check('staff POST with token blocks', SuggestionRemoveGuard::mayBlock(true, true, true));

// body parsing
check('form body used as is', SuggestionRemoveGuard::body(['removed' => 'a'], 'application/x-www-form-urlencoded', '') === ['removed' => 'a']);
check('JSON body parsed', SuggestionRemoveGuard::body([], 'application/json; charset=utf-8', '{"removed":"a","q":"b"}') === ['removed' => 'a', 'q' => 'b']);
check('bad JSON is empty', SuggestionRemoveGuard::body([], 'application/json', '{nope') === []);
check('non-JSON raw body ignored', SuggestionRemoveGuard::body([], 'text/plain', 'removed=a') === []);
check('oversize JSON ignored', SuggestionRemoveGuard::body([], 'application/json', '{"a":"' . str_repeat('x', 70000) . '"}') === []);
check('str() rejects arrays', SuggestionRemoveGuard::str(['removed' => ['x']], 'removed') === null);

// session token (only when sessions can start in CLI)
if (session_status() === PHP_SESSION_NONE && @session_start()) {
    unset($_SESSION['csrf_token']);
    check('session without token gives null', SuggestionRemoveGuard::sessionToken() === null);
    $_SESSION['csrf_token'] = $tok;
    check('session token read', SuggestionRemoveGuard::sessionToken() === $tok);
    session_destroy();
} else {
    echo "PASS session checks skipped (sessions unavailable in this CLI)\n";
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f05/tests/search-removed-get-test.php ===
<?php
// tools/search-tests/search-removed-get-test.php — f05: source checks on api/search.php (no database, no requests).
// A GET ?removed= must pass no user id; the block path must be a checked POST.
// Run: php tools/search-tests/search-removed-get-test.php   (or set SEARCH_PHP=/path/to/api/search.php, ROOT=/path/to/repo)

declare(strict_types=1);

$root = getenv('ROOT') ?: dirname(__DIR__, 2);
$cands = array_filter(array_merge([getenv('SEARCH_PHP') ?: '', $root . '/api/search.php'], glob($root . '/*/api/search.php') ?: []));
$file = '';
foreach ($cands as $c) if (is_file($c)) { $file = $c; break; }
if ($file === '') { echo "FAIL cannot find api/search.php (set SEARCH_PHP)\n"; exit(1); }
$src = (string) file_get_contents($file);

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) $fails++;
}

check('old GET block call is gone',
    !str_contains($src, "(string) (\$_GET['rk'] ?? 'pred'), \$canTeach ? Auth::currentUserId() : null)"));

$get = strpos($src, "\$rmWhat = (string) \$_GET['removed'];");
$getNull = $get !== false ? strpos($src, '$rmBy = null;', $get) : false;
$getEnd = $get !== false ? strpos($src, '}', $get) : false;
check('GET branch sets no user id', $get !== false && $getNull !== false && $getEnd !== false && $getNull < $getEnd);

$guard = strpos($src, 'SuggestionRemoveGuard::requestOk(');
$block = strpos($src, 'SuggestionRemoveGuard::mayBlock(');
$remove = strpos($src, 'SuggestionFlags::remove($pdo, $rmQ, $rmWhat, $rmKind, $rmBy)');
check('CSRF check exists', $guard !== false);
check('CSRF check comes before the block decision', $guard !== false && $block !== false && $guard < $block);
check('the block decision comes before SuggestionFlags::remove', $block !== false && $remove !== false && $block < $remove);
check('failed check answers 403', str_contains($src, "Response::error('Please refresh the page and try again.', 403, 'csrf_failed')"));
check('POST path only on POST', str_contains($src, "strtoupper((string) (\$_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'"));

// syntax check when a php binary is at hand
$php = PHP_BINARY;
if ($php !== '' && function_exists('shell_exec')) {
    $out = (string) shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1');
    check('php -l search.php', str_contains($out, 'No syntax errors'));
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f05/faq/faq-entries.md ===
no faq change

(Visitors see no difference: their × on a suggestion is still counted the same way. Only staff blocks change, and those happen in the Workbench flow, not in a visitor FAQ.)
=== END FILE ===

=== FILE: f05/release.json ===
{
  "name": "f05-search-removed-csrf",
  "gate": "Ship only after the lane confirms the site's CSRF token (session key or helper) and updates the client script that sends ?removed= to send a POST with the token. Until then, staff ×'s are counted like a visitor's and do not block, which is the safe side.",
  "what": "api/search.php: GET ?removed= now always passes a null user id to SuggestionFlags::remove, so it only counts and never makes a staff block. A staff block now needs a POST (form or JSON) with removed/q/rk plus this session's CSRF token (X-CSRF-Token header or csrf_token field). The request must not be marked Sec-Fetch-Site: cross-site, and the person must pass the existing $canTeach check. A POST with a missing or wrong token gets 403 csrf_failed and records nothing. A new app/lib/SuggestionRemoveGuard.php holds the checks so they can be tested without a database.",
  "files": [
    "files/api/search.php",
    "files/app/lib/SuggestionRemoveGuard.php"
  ],
  "decisions": [
    "Safest default: GET never blocks. Even a signed-in staff member's GET ?removed= is only counted, with user id null.",
    "Safest default: the CSRF token is checked against $_SESSION['csrf_token'] with hash_equals, and the check fails closed. With no session, no token or a wrong token, nothing is recorded and the reply is 403. NEEDS A PERSON: confirm the site's real token key or helper and change SuggestionRemoveGuard::SESSION_KEY or sessionToken() to match.",
    "A POST from a visitor with a valid token is counted with a null id, the same as a GET.",
    "No Origin/Host comparison, because proxies or a CDN could break real staff requests. The token is the control; Sec-Fetch-Site: cross-site is refused as an extra layer.",
    "GET visitor counts remain forgeable (anyone can inflate a count). That is unchanged and out of this finding's scope. NEEDS A PERSON: decide whether visitor ×'s should also move to POST."
  ],
  "tests": [
    "tests/suggestion-remove-guard-test.php -> tools/search-tests/suggestion-remove-guard-test.php",
    "tests/search-removed-get-test.php -> tools/search-tests/search-removed-get-test.php"
  ],
  "risks": [
    "Nothing here has been run or verified: no tests executed, no php -l, no zip check, no live request tried.",
    "Until the client JS is changed to POST with a token, staff can no longer block suggestions from the search box. Their × is only counted.",
    "If the session key guess ('csrf_token') is wrong, every staff POST gets 403. This fails closed, but staff will see 'Please refresh the page and try again.'",
    "The 403 relies on Response::error ending the request, as the existing peek branch already assumes. If it throws instead, the outer catch would answer 500 search_failed. Either way, no block is recorded.",
    "The file's live location is inferred from its require paths (<web root>/api/search.php, two levels below app/). The release uses files/api/search.php."
  ],
  "verify": [
    "file not shown: the site's CSRF token source (bootstrap / Auth / any Csrf helper). Confirm the session key or helper and the name the pages expose the token under (meta tag or JS variable). Adjust SuggestionRemoveGuard::SESSION_KEY or sessionToken().",
    "file not shown: the front-end script that calls /api/search.php?removed=…&rk=… (suggestion ×). Change it to fetch POST with body {removed, q, rk} and header X-CSRF-Token. Ship it together with this release, or accept that staff blocks pause.",
    "file not shown: app/lib/SuggestionFlags.php. Confirm remove($pdo, $q, $removed, $kind, $userId|null) treats a null id as a visitor count, as the original code already relied on.",
    "file not shown: app/bootstrap.php. Confirm the session is active on API requests, so sessionToken() can read it.",
    "Confirm the real live path of api/search.php under the web root, and place files/api/search.php there. Place app/lib/SuggestionRemoveGuard.php under the repo's app/lib/.",
    "Manually: as staff, GET /api/search.php?removed=x&q=test returns ok and no block appears (only a count). POST without a token returns 403. POST with the token blocks. As a visitor, POST with the token only counts.",
    "Run both tests from tools/search-tests/ and php -l both PHP files. Zip-check that the folder holds only the two files listed."
  ],
  "regions": false
}
=== END FILE ===