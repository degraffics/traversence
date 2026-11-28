# Release drafts for /api/trips.php: f07 (PDOException text shown to visitors) and f23 (unchecked time zone stored)

Both fixes are drafts. Nothing below has been run, linted or tested. The lane session must verify, test and zip-check each one.

- **f07 (medium), why the message leaked.** `PDOException` is a subclass of `RuntimeException`. So every database fault was caught by the `catch (RuntimeException $e)` block at line 157, and its text was sent back with a 422. This includes the guest paths `addable`, `resolve` and `journey`, which query the database before the sign-in check. A new `catch (PDOException ...)` block now comes first. It logs the detail and returns the same generic 500 that the `Throwable` block already returns.
- **f23 (low).** The `tz` value is now checked once, at line 44, against PHP's list of time zone names. Anything else becomes `''`, which is what is stored today when no `tz` is sent. The old pattern check in `from_plan` (line 127) is no longer needed and now just falls back to America/Phoenix.
- **Ship order.** f23 is built on top of f07, so its `trips.php` already contains the f07 fix. Ship f07 first, then f23. If f23 ships on its own, its file still carries the f07 fix, so f07 is not undone.

=== FILE: f07/files/api/trips.php ===
<?php
// /api/trips.php: trips you can save (decisions/0174 §1, §2, release 2; app/lib/Trips.php). POST JSON with X-CSRF-Token.
//   GET                                     -> {signed_in, csrf, trips: [{id, name, stops, url}]}   (a guest: trips [])
//   {action: add, kind, ref, trip: id|'new', tz?}  -> {trip: {id, name, url}, stop, already}   401 'account' for a guest:
//                                                the guest's trip is kept on the device (js/tv-trips.js)
//   {action: remove, stop}                  -> {undo: {trip, position, kind, ref, note}}
//   {action: restore, undo}                 -> {stop}                 Undo a removal: back in its place
//   {action: rename, trip, name}            -> {name}
//   {action: delete, trip} / {action: undelete, trip}            kept 30 days, then gone
//   {action: copy_journey, id, tz?}         -> {trip, added, left}    Make it my trip (a ready-made journey)
//   {action: claim, name, stops, tz?}       -> {trip, added, left}    the device's trip moved into the account at sign-in
//   {action: resolve, stops}                -> {stops: [...]}         a guest's trip on the device, read with today's rules
//   {action: addable, kind, ref}            -> {ok, why, name}        (guests too)
// The trip page (release 3):
//   {action: move, stop, day, position}     -> {}                     reorder, or move to another day (0-based position)
//   {action: configure, trip, starts_on?, days?, start_min?} -> {trip}  its date, days and when each day starts
//   {action: stay, stop, minutes|null}      -> {}                     how long at a stop
//   {action: use_journey, trip, id}         -> {added, left}          a ready-made journey's stops into this trip
//   {action: from_plan, where, where_slug, where_level, when, days} -> {trip}   Save as a trip (the Trip Planner)
// Release 4:
//   {action: share, trip, on}               -> {url}                  a view-only link, or off (0174 §1)
//   {action: publish_guide, trip, title, intro} -> {guide: {id, status, why, url}}   a Personal Guide (0174 §1), checked like a journey
//   {action: withdraw_guide, trip}          -> {}                     the owner takes it down (Publish again puts it back)
// Never who: nothing here is shown to anyone but the trip's owner, and nothing counts per person.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Trips.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$tripOut = fn (array $t) => ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'stops' => (int) ($t['stops'] ?? 0), 'url' => '/user/trips/trip.php?id=' . (int) $t['id']];
try {
    if (!Trips::ready($pdo)) Response::error('Trips aren\'t switched on yet: they arrive with the next site update.', 503, 'not_configured');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $trips = $uid ? array_map($tripOut, Trips::mine($pdo, (int) $uid)['trips']) : [];
        Response::json(['ok' => true, 'signed_in' => $uid !== null, 'csrf' => $_SESSION['csrf_token'], 'trips' => $trips]);
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $b = Response::jsonBody();
    $act = (string) ($b['action'] ?? '');
    $tz = (string) ($b['tz'] ?? '');
    $list = function ($stops): array {   // [{kind, ref}] from the client, checked
        $out = [];
        foreach (array_slice(is_array($stops) ? $stops : [], 0, Trips::MAX_STOPS) as $s) if (is_array($s) && ($k = Trips::key((string) ($s['kind'] ?? ''), (string) ($s['ref'] ?? '')))) $out[] = $k;
        return $out;
    };

    // guests too: what a place is today, by the rules
    if ($act === 'addable') {
        $r = Trips::resolve($pdo, (string) ($b['kind'] ?? ''), (string) ($b['ref'] ?? ''));
        Response::json(['ok' => true, 'can' => $r['ok'], 'why' => (string) ($r['why'] ?? ''), 'name' => (string) $r['name'], 'trip_name' => $r['ok'] ? Trips::nameFor($r) : '']);
    }
    if ($act === 'resolve') {
        $out = [];
        foreach ($list($b['stops'] ?? []) as [$k, $r]) { $x = Trips::resolve($pdo, $k, $r); unset($x['town']); $out[] = $x; }
        Response::json(['ok' => true, 'stops' => $out]);
    }
    if ($act === 'journey') {   // a ready-made journey's stops, for a guest's Make it my trip
        require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
        $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
        if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
        Response::json(['ok' => true, 'name' => (string) $j['title'], 'stops' => array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops']))]);
    }
    if (!$uid) Response::error('Sign in to keep trips in your account. As a guest, one trip is kept on this device.', 401, 'account');
    $uid = (int) $uid;

    switch ($act) {
        case 'add':
            $k = Trips::key((string) ($b['kind'] ?? ''), (string) ($b['ref'] ?? ''));
            if (!$k) Response::error('That can\'t go in a trip.', 400, 'bad');
            $r = Trips::resolve($pdo, $k[0], $k[1]);
            if (!$r['ok']) Response::error(($r['name'] !== '' ? $r['name'] . ': ' : '') . $r['why'], 422, 'not_addable');
            $pdo->beginTransaction();
            $tripId = ($b['trip'] ?? '') === 'new' ? Trips::create($pdo, $uid, (string) ($b['name'] ?? '') ?: Trips::nameFor($r), $tz) : (int) ($b['trip'] ?? 0);
            $stop = Trips::add($pdo, $uid, $tripId, $k[0], $k[1]);
            $pdo->commit();
            $t = Trips::own($pdo, $uid, $tripId);
            Response::json(['ok' => true, 'trip' => $tripOut($t), 'stop' => abs($stop), 'already' => $stop < 0, 'name' => $r['name']]);
        case 'remove':
            Response::json(['ok' => true, 'undo' => Trips::remove($pdo, $uid, (int) ($b['stop'] ?? 0))]);
        case 'restore':
            $u = (array) ($b['undo'] ?? []);
            $k = Trips::key((string) ($u['kind'] ?? ''), (string) ($u['ref'] ?? ''));
            if (!$k) Response::error('Nothing to put back.', 400, 'bad');
            $id = Trips::add($pdo, $uid, (int) ($u['trip'] ?? 0), $k[0], $k[1], max(0, (int) ($u['position'] ?? 0)), (string) ($u['note'] ?? ''), max(1, (int) ($u['day'] ?? 1)));
            if ($id > 0 && isset($u['stay']) && $u['stay'] !== null) Trips::setStay($pdo, $uid, $id, (int) $u['stay']);
            Response::json(['ok' => true, 'stop' => abs($id)]);
        case 'move':
            Trips::move($pdo, $uid, (int) ($b['stop'] ?? 0), (int) ($b['day'] ?? 1), (int) ($b['position'] ?? 0));
            Response::json(['ok' => true]);
        case 'configure':
            $set = array_intersect_key($b, array_flip(['starts_on', 'days', 'start_min']));
            $t = Trips::configure($pdo, $uid, (int) ($b['trip'] ?? 0), $set);
            Response::json(['ok' => true, 'trip' => $tripOut($t)]);
        case 'stay':
            Trips::setStay($pdo, $uid, (int) ($b['stop'] ?? 0), ($b['minutes'] ?? null) === null || $b['minutes'] === '' ? null : (int) $b['minutes']);
            Response::json(['ok' => true]);
        case 'use_journey':
            require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
            $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
            if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
            if (!Trips::own($pdo, $uid, (int) ($b['trip'] ?? 0))) Response::error('That trip isn\'t yours, or was deleted.', 404, 'not_found');
            $pdo->beginTransaction();
            [$added, $left] = Trips::addMany($pdo, $uid, (int) $b['trip'], array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops'])));
            $pdo->commit();
            Response::json(['ok' => true, 'added' => $added, 'left' => $left]);
        case 'share':
            Response::json(['ok' => true, 'url' => Trips::share($pdo, $uid, (int) ($b['trip'] ?? 0), !empty($b['on']))]);
        case 'publish_guide':
            $g = Trips::publishGuide($pdo, $uid, (int) ($b['trip'] ?? 0), (string) ($b['title'] ?? ''), (string) ($b['intro'] ?? ''));
            Response::json(['ok' => true, 'guide' => ['id' => (int) $g['id'], 'status' => (string) $g['status'], 'why' => preg_replace('/^Held by the automated check: /', '', (string) $g['hidden_reason']), 'url' => '/guide/personal.php?id=' . (int) $g['id']]]);
        case 'withdraw_guide':
            Trips::withdrawGuide($pdo, $uid, (int) ($b['trip'] ?? 0));
            Response::json(['ok' => true]);
        case 'from_plan':
            // Save as a trip (the Trip Planner, 0174 §3): where, when and how long become a new trip; the place's
            // ready-made journeys are offered on its page as a start
            $where = trim((string) ($b['where'] ?? ''));
            $level = (string) ($b['where_level'] ?? ''); $slug = (string) ($b['where_slug'] ?? '');
            $key = '';
            $tables = ['cluster' => 'micro_clusters', 'geo_hub' => 'geo_hubs', 'hub' => 'hubs'];
            if (isset($tables[$level]) && $slug !== '') { $q = $pdo->prepare('SELECT id FROM ' . $tables[$level] . ' WHERE slug = ? LIMIT 1'); $q->execute([$slug]); if ($pid = $q->fetchColumn()) $key = $level . ':' . (int) $pid; }
            $when = (string) ($b['when'] ?? ''); $starts = null; $label = '';
            $tzName = preg_match('#^[A-Za-z_]+(/[A-Za-z_+-]+){0,2}$#', $tz) ? $tz : 'America/Phoenix';
            $today = new DateTimeImmutable('today', new DateTimeZone($tzName));
            if ($when === 'today') $starts = $today->format('Y-m-d');
            elseif ($when === 'this weekend') $starts = $today->modify('+' . ((6 - (int) $today->format('w') + 7) % 7) . ' days')->format('Y-m-d');
            elseif (preg_match('/^m:(.+)$/', $when, $mm)) $label = ' · ' . mb_substr(trim($mm[1]), 0, 20);
            $days = ['day' => 1, 'weekend' => 2, '3-5' => 3, 'week' => 7][(string) ($b['days'] ?? '')] ?? 1;
            $name = ($where !== '' ? 'Trip to ' . trim(explode(',', $where)[0]) : 'My trip') . $label;
            $id = Trips::create($pdo, $uid, $name, $tz, ['days' => $days, 'starts_on' => $starts ?? '', 'place_key' => $key]);
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $id))]);
        case 'rename':
            Response::json(['ok' => true, 'name' => Trips::rename($pdo, $uid, (int) ($b['trip'] ?? 0), (string) ($b['name'] ?? ''))]);
        case 'delete':
        case 'undelete':
            Trips::delete($pdo, $uid, (int) ($b['trip'] ?? 0), $act === 'undelete');
            Response::json(['ok' => true]);
        case 'copy_journey':
            require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
            $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
            if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
            $pdo->beginTransaction();
            [$tripId, $added, $left] = Trips::claim($pdo, $uid, (string) $j['title'], array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops'])), $tz);
            $pdo->commit();
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $tripId) + ['stops' => $added]), 'added' => $added, 'left' => $left]);
        case 'claim':
            $pdo->beginTransaction();
            [$tripId, $added, $left] = Trips::claim($pdo, $uid, (string) ($b['name'] ?? ''), array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], $list($b['stops'] ?? [])), $tz);
            $pdo->commit();
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $tripId) + ['stops' => $added]), 'added' => $added, 'left' => $left]);
    }
    Response::error('Unknown action.', 400, 'bad');
} catch (PDOException $e) {
    // f07: a database fault never shows its text to anyone (guests included). PDOException extends RuntimeException,
    // so this catch must stay before the RuntimeException one below.
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignored) { /* the connection may be gone; nothing to show */ }
    error_log('api/trips (db): ' . $e->getMessage());
    Response::error('That didn\'t work: try again, or ask our team at support@traversence.com.', 500, 'error');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    Response::error($e->getMessage(), 422, 'refused');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('api/trips: ' . $e->getMessage());
    Response::error('That didn\'t work: try again, or ask our team at support@traversence.com.', 500, 'error');
}
=== END FILE ===

=== FILE: f07/tests/trips-pdo-error-hidden.php ===
<?php
// tools/trips-tests style (f07): a database fault on /api/trips.php never shows its text; generic 500 instead.
// Run: php tests/trips-pdo-error-hidden.php [path/to/api/trips.php]
declare(strict_types=1);

$file = $argv[1] ?? __DIR__ . '/../files/api/trips.php';
$fail = 0;
$check = function (string $what, bool $ok) use (&$fail): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$ok) $fail++; };

$src = @file_get_contents($file);
$check('the file can be read: ' . $file, is_string($src) && $src !== '');
if (!is_string($src) || $src === '') exit(1);

$check('why the order matters: PDOException is a RuntimeException', is_subclass_of(PDOException::class, RuntimeException::class));

$out = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
$check('php -l passes', $code === 0);

preg_match_all('/\}\s*catch\s*\(\s*\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)\s+\$/', $src, $m, PREG_OFFSET_CAPTURE);
$names = array_map(fn ($x) => ltrim((string) $x[0], '\\'), $m[1]);
$iP = array_search('PDOException', $names, true);
$iR = array_search('RuntimeException', $names, true);
$check('there is a catch (PDOException ...)', $iP !== false);
$check('there is still a catch (RuntimeException ...)', $iR !== false);
$check('catch (PDOException) comes before catch (RuntimeException)', $iP !== false && $iR !== false && $iP < $iR);

if ($iP !== false) {
    $start = $m[0][$iP][1];
    $end = isset($m[0][$iP + 1]) ? $m[0][$iP + 1][1] : strlen($src);
    $block = substr($src, $start, $end - $start);
    $has = preg_match('/Response::error\((.*?)\);/s', $block, $em) === 1;
    $check('the PDOException block answers with Response::error', $has);
    $check('the PDOException answer does not use the exception text', $has && strpos($em[1], 'getMessage') === false && strpos($em[1], '$e') === false);
    $check('the PDOException answer is a 500', $has && preg_match('/,\s*500\s*,/', $em[1]) === 1);
    $check('the PDOException detail is logged for staff', strpos($block, 'error_log(') !== false);
    $check('the PDOException block rolls back an open transaction', strpos($block, 'rollBack()') !== false);
}

// the only place an exception's own text may reach the visitor is the RuntimeException (Trips' refusals) block
$check('exactly one Response::error($e->getMessage() remains', substr_count($src, 'Response::error($e->getMessage()') === 1);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f07/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f07/release.json ===
{
  "name": "f07-trips-db-error-hidden",
  "gate": "lane session: verify, run tests, zip-check before ship (draft, nothing run)",
  "what": "api/trips.php: PDOException extends RuntimeException, so database faults were caught by the RuntimeException block (was :157-159) and their SQL text echoed with a 422, including on the guest actions addable/resolve/journey that query before the sign-in check. A catch (PDOException) now comes first: rollback (guarded), error_log the detail, and answer with the same generic 500 'error' the Throwable block already uses.",
  "files": ["files/api/trips.php"],
  "decisions": [
    "The status and wording copy the existing Throwable block (500, 'error', support@traversence.com), so the client sees nothing new.",
    "The rollBack in the new block is wrapped in try/catch, so a lost connection cannot raise a second, uncaught PDOException carrying the same text.",
    "Scope is limited to the catch chain. Trips' own RuntimeException refusals still show their text with a 422, as before."
  ],
  "tests": ["tests/trips-pdo-error-hidden.php"],
  "risks": [
    "Nothing here has been run, linted or tested. It is a draft.",
    "A database fault (for example a duplicate key) used to answer 422 'refused'. It now answers 500 'error'. Any client code in js/tv-trips.js or the trip page that branches on 422 for these cases will now take the 500 branch.",
    "If Trips.php or JourneyPlan.php throws its own RuntimeException subclasses that wrap SQL text (other than PDOException), that text still goes out through the 422 block."
  ],
  "verify": [
    "Live path: the attached file was a scratch copy. Its header says /api/trips.php and it requires __DIR__/../../app, so it sits one folder below the site root (for example <webroot>/api/trips.php). Confirm the real path before zipping; files/api/trips.php is assumed.",
    "app/bootstrap.php, the Response class, Database and app/lib/Trips.php: file not shown. Confirm that Response::error() sends and exits (the code relies on that), and that Trips.php never deliberately throws a PDOException meant for the visitor.",
    "Decision for a person: $pdo = Database::connection() runs outside the try block. A connection failure there is handled by whatever bootstrap does (file not shown). Check that display_errors is off in production, or decide whether to move the connection inside the try. That is not changed here.",
    "Manual check: point a staging copy at a DB user without SELECT on the trips tables, POST {action: addable} as a guest, and expect a 500 with the generic text and the detail in the error log.",
    "Run: php tests/trips-pdo-error-hidden.php files/api/trips.php"
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f23/files/api/trips.php ===
<?php
// /api/trips.php: trips you can save (decisions/0174 §1, §2, release 2; app/lib/Trips.php). POST JSON with X-CSRF-Token.
//   GET                                     -> {signed_in, csrf, trips: [{id, name, stops, url}]}   (a guest: trips [])
//   {action: add, kind, ref, trip: id|'new', tz?}  -> {trip: {id, name, url}, stop, already}   401 'account' for a guest:
//                                                the guest's trip is kept on the device (js/tv-trips.js)
//   {action: remove, stop}                  -> {undo: {trip, position, kind, ref, note}}
//   {action: restore, undo}                 -> {stop}                 Undo a removal: back in its place
//   {action: rename, trip, name}            -> {name}
//   {action: delete, trip} / {action: undelete, trip}            kept 30 days, then gone
//   {action: copy_journey, id, tz?}         -> {trip, added, left}    Make it my trip (a ready-made journey)
//   {action: claim, name, stops, tz?}       -> {trip, added, left}    the device's trip moved into the account at sign-in
//   {action: resolve, stops}                -> {stops: [...]}         a guest's trip on the device, read with today's rules
//   {action: addable, kind, ref}            -> {ok, why, name}        (guests too)
// The trip page (release 3):
//   {action: move, stop, day, position}     -> {}                     reorder, or move to another day (0-based position)
//   {action: configure, trip, starts_on?, days?, start_min?} -> {trip}  its date, days and when each day starts
//   {action: stay, stop, minutes|null}      -> {}                     how long at a stop
//   {action: use_journey, trip, id}         -> {added, left}          a ready-made journey's stops into this trip
//   {action: from_plan, where, where_slug, where_level, when, days} -> {trip}   Save as a trip (the Trip Planner)
// Release 4:
//   {action: share, trip, on}               -> {url}                  a view-only link, or off (0174 §1)
//   {action: publish_guide, trip, title, intro} -> {guide: {id, status, why, url}}   a Personal Guide (0174 §1), checked like a journey
//   {action: withdraw_guide, trip}          -> {}                     the owner takes it down (Publish again puts it back)
// Never who: nothing here is shown to anyone but the trip's owner, and nothing counts per person.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Trips.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$tripOut = fn (array $t) => ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'stops' => (int) ($t['stops'] ?? 0), 'url' => '/user/trips/trip.php?id=' . (int) $t['id']];
try {
    if (!Trips::ready($pdo)) Response::error('Trips aren\'t switched on yet: they arrive with the next site update.', 503, 'not_configured');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $trips = $uid ? array_map($tripOut, Trips::mine($pdo, (int) $uid)['trips']) : [];
        Response::json(['ok' => true, 'signed_in' => $uid !== null, 'csrf' => $_SESSION['csrf_token'], 'trips' => $trips]);
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $b = Response::jsonBody();
    $act = (string) ($b['action'] ?? '');
    $tz = is_string($b['tz'] ?? null) && strlen($b['tz']) <= 64 && in_array($b['tz'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true) ? $b['tz'] : '';   // f23: one checked time zone for every action below; anything else is '' (as if none was sent)
    $list = function ($stops): array {   // [{kind, ref}] from the client, checked
        $out = [];
        foreach (array_slice(is_array($stops) ? $stops : [], 0, Trips::MAX_STOPS) as $s) if (is_array($s) && ($k = Trips::key((string) ($s['kind'] ?? ''), (string) ($s['ref'] ?? '')))) $out[] = $k;
        return $out;
    };

    // guests too: what a place is today, by the rules
    if ($act === 'addable') {
        $r = Trips::resolve($pdo, (string) ($b['kind'] ?? ''), (string) ($b['ref'] ?? ''));
        Response::json(['ok' => true, 'can' => $r['ok'], 'why' => (string) ($r['why'] ?? ''), 'name' => (string) $r['name'], 'trip_name' => $r['ok'] ? Trips::nameFor($r) : '']);
    }
    if ($act === 'resolve') {
        $out = [];
        foreach ($list($b['stops'] ?? []) as [$k, $r]) { $x = Trips::resolve($pdo, $k, $r); unset($x['town']); $out[] = $x; }
        Response::json(['ok' => true, 'stops' => $out]);
    }
    if ($act === 'journey') {   // a ready-made journey's stops, for a guest's Make it my trip
        require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
        $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
        if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
        Response::json(['ok' => true, 'name' => (string) $j['title'], 'stops' => array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops']))]);
    }
    if (!$uid) Response::error('Sign in to keep trips in your account. As a guest, one trip is kept on this device.', 401, 'account');
    $uid = (int) $uid;

    switch ($act) {
        case 'add':
            $k = Trips::key((string) ($b['kind'] ?? ''), (string) ($b['ref'] ?? ''));
            if (!$k) Response::error('That can\'t go in a trip.', 400, 'bad');
            $r = Trips::resolve($pdo, $k[0], $k[1]);
            if (!$r['ok']) Response::error(($r['name'] !== '' ? $r['name'] . ': ' : '') . $r['why'], 422, 'not_addable');
            $pdo->beginTransaction();
            $tripId = ($b['trip'] ?? '') === 'new' ? Trips::create($pdo, $uid, (string) ($b['name'] ?? '') ?: Trips::nameFor($r), $tz) : (int) ($b['trip'] ?? 0);
            $stop = Trips::add($pdo, $uid, $tripId, $k[0], $k[1]);
            $pdo->commit();
            $t = Trips::own($pdo, $uid, $tripId);
            Response::json(['ok' => true, 'trip' => $tripOut($t), 'stop' => abs($stop), 'already' => $stop < 0, 'name' => $r['name']]);
        case 'remove':
            Response::json(['ok' => true, 'undo' => Trips::remove($pdo, $uid, (int) ($b['stop'] ?? 0))]);
        case 'restore':
            $u = (array) ($b['undo'] ?? []);
            $k = Trips::key((string) ($u['kind'] ?? ''), (string) ($u['ref'] ?? ''));
            if (!$k) Response::error('Nothing to put back.', 400, 'bad');
            $id = Trips::add($pdo, $uid, (int) ($u['trip'] ?? 0), $k[0], $k[1], max(0, (int) ($u['position'] ?? 0)), (string) ($u['note'] ?? ''), max(1, (int) ($u['day'] ?? 1)));
            if ($id > 0 && isset($u['stay']) && $u['stay'] !== null) Trips::setStay($pdo, $uid, $id, (int) $u['stay']);
            Response::json(['ok' => true, 'stop' => abs($id)]);
        case 'move':
            Trips::move($pdo, $uid, (int) ($b['stop'] ?? 0), (int) ($b['day'] ?? 1), (int) ($b['position'] ?? 0));
            Response::json(['ok' => true]);
        case 'configure':
            $set = array_intersect_key($b, array_flip(['starts_on', 'days', 'start_min']));
            $t = Trips::configure($pdo, $uid, (int) ($b['trip'] ?? 0), $set);
            Response::json(['ok' => true, 'trip' => $tripOut($t)]);
        case 'stay':
            Trips::setStay($pdo, $uid, (int) ($b['stop'] ?? 0), ($b['minutes'] ?? null) === null || $b['minutes'] === '' ? null : (int) $b['minutes']);
            Response::json(['ok' => true]);
        case 'use_journey':
            require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
            $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
            if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
            if (!Trips::own($pdo, $uid, (int) ($b['trip'] ?? 0))) Response::error('That trip isn\'t yours, or was deleted.', 404, 'not_found');
            $pdo->beginTransaction();
            [$added, $left] = Trips::addMany($pdo, $uid, (int) $b['trip'], array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops'])));
            $pdo->commit();
            Response::json(['ok' => true, 'added' => $added, 'left' => $left]);
        case 'share':
            Response::json(['ok' => true, 'url' => Trips::share($pdo, $uid, (int) ($b['trip'] ?? 0), !empty($b['on']))]);
        case 'publish_guide':
            $g = Trips::publishGuide($pdo, $uid, (int) ($b['trip'] ?? 0), (string) ($b['title'] ?? ''), (string) ($b['intro'] ?? ''));
            Response::json(['ok' => true, 'guide' => ['id' => (int) $g['id'], 'status' => (string) $g['status'], 'why' => preg_replace('/^Held by the automated check: /', '', (string) $g['hidden_reason']), 'url' => '/guide/personal.php?id=' . (int) $g['id']]]);
        case 'withdraw_guide':
            Trips::withdrawGuide($pdo, $uid, (int) ($b['trip'] ?? 0));
            Response::json(['ok' => true]);
        case 'from_plan':
            // Save as a trip (the Trip Planner, 0174 §3): where, when and how long become a new trip; the place's
            // ready-made journeys are offered on its page as a start
            $where = trim((string) ($b['where'] ?? ''));
            $level = (string) ($b['where_level'] ?? ''); $slug = (string) ($b['where_slug'] ?? '');
            $key = '';
            $tables = ['cluster' => 'micro_clusters', 'geo_hub' => 'geo_hubs', 'hub' => 'hubs'];
            if (isset($tables[$level]) && $slug !== '') { $q = $pdo->prepare('SELECT id FROM ' . $tables[$level] . ' WHERE slug = ? LIMIT 1'); $q->execute([$slug]); if ($pid = $q->fetchColumn()) $key = $level . ':' . (int) $pid; }
            $when = (string) ($b['when'] ?? ''); $starts = null; $label = '';
            $tzName = $tz !== '' ? $tz : 'America/Phoenix';   // $tz was checked at the top (f23)
            $today = new DateTimeImmutable('today', new DateTimeZone($tzName));
            if ($when === 'today') $starts = $today->format('Y-m-d');
            elseif ($when === 'this weekend') $starts = $today->modify('+' . ((6 - (int) $today->format('w') + 7) % 7) . ' days')->format('Y-m-d');
            elseif (preg_match('/^m:(.+)$/', $when, $mm)) $label = ' · ' . mb_substr(trim($mm[1]), 0, 20);
            $days = ['day' => 1, 'weekend' => 2, '3-5' => 3, 'week' => 7][(string) ($b['days'] ?? '')] ?? 1;
            $name = ($where !== '' ? 'Trip to ' . trim(explode(',', $where)[0]) : 'My trip') . $label;
            $id = Trips::create($pdo, $uid, $name, $tz, ['days' => $days, 'starts_on' => $starts ?? '', 'place_key' => $key]);
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $id))]);
        case 'rename':
            Response::json(['ok' => true, 'name' => Trips::rename($pdo, $uid, (int) ($b['trip'] ?? 0), (string) ($b['name'] ?? ''))]);
        case 'delete':
        case 'undelete':
            Trips::delete($pdo, $uid, (int) ($b['trip'] ?? 0), $act === 'undelete');
            Response::json(['ok' => true]);
        case 'copy_journey':
            require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
            $j = JourneyPlan::get($pdo, (int) ($b['id'] ?? 0));
            if (!$j) Response::error('That journey was rebuilt or taken down.', 404, 'not_found');
            $pdo->beginTransaction();
            [$tripId, $added, $left] = Trips::claim($pdo, $uid, (string) $j['title'], array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], Trips::fromJourney((array) $j['stops'])), $tz);
            $pdo->commit();
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $tripId) + ['stops' => $added]), 'added' => $added, 'left' => $left]);
        case 'claim':
            $pdo->beginTransaction();
            [$tripId, $added, $left] = Trips::claim($pdo, $uid, (string) ($b['name'] ?? ''), array_map(fn ($k) => ['kind' => $k[0], 'ref' => $k[1]], $list($b['stops'] ?? [])), $tz);
            $pdo->commit();
            Response::json(['ok' => true, 'trip' => $tripOut(Trips::own($pdo, $uid, $tripId) + ['stops' => $added]), 'added' => $added, 'left' => $left]);
    }
    Response::error('Unknown action.', 400, 'bad');
} catch (PDOException $e) {
    // f07: a database fault never shows its text to anyone (guests included). PDOException extends RuntimeException,
    // so this catch must stay before the RuntimeException one below.
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignored) { /* the connection may be gone; nothing to show */ }
    error_log('api/trips (db): ' . $e->getMessage());
    Response::error('That didn\'t work: try again, or ask our team at support@traversence.com.', 500, 'error');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    Response::error($e->getMessage(), 422, 'refused');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('api/trips: ' . $e->getMessage());
    Response::error('That didn\'t work: try again, or ask our team at support@traversence.com.', 500, 'error');
}
=== END FILE ===

=== FILE: f23/tests/trips-tz-checked.php ===
<?php
// tools/trips-tests style (f23): api/trips.php checks tz once, at the top; only real time zone names are kept.
// Runs the file's own $tz line (not a copy) against sample bodies.
// Run: php tests/trips-tz-checked.php [path/to/api/trips.php]
declare(strict_types=1);

$file = $argv[1] ?? __DIR__ . '/../files/api/trips.php';
$fail = 0;
$check = function (string $what, bool $ok) use (&$fail): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$ok) $fail++; };

$src = @file_get_contents($file);
$check('the file can be read: ' . $file, is_string($src) && $src !== '');
if (!is_string($src) || $src === '') exit(1);

$out = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
$check('php -l passes', $code === 0);

$found = preg_match('/^[ \t]*(\$tz = [^\n]*)$/m', $src, $lm) === 1;
$check('there is one $tz = ... line', $found && preg_match_all('/^[ \t]*\$tz = /m', $src) === 1);
if (!$found) { echo "$fail FAIL\n"; exit(1); }
$line = $lm[1];
$check('the $tz line uses the time zone list', strpos($line, 'DateTimeZone::listIdentifiers') !== false);
$check("\$b['tz'] is read only on the \$tz line", substr_count($src, "\$b['tz']") === substr_count($line, "\$b['tz']"));
$check('the old pattern check on $tz is gone', strpos($src, "preg_match('#^[A-Za-z_]+") === false);
$check('from_plan falls back to America/Phoenix', strpos($src, "\$tzName = \$tz !== '' ? \$tz : 'America/Phoenix';") !== false);

$run = function (array $b) use ($line) { $tz = null; eval($line); return $tz; };
$missing = new stdClass();
$cases = [
    ['America/Phoenix', 'America/Phoenix'],
    ['Europe/London', 'Europe/London'],
    ['UTC', 'UTC'],
    ['Asia/Calcutta', 'Asia/Calcutta'],                  // older name some browsers still send
    ['America/Argentina/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    ['', ''],
    [$missing, ''],
    ['Foo/Bar', ''],                                     // passed the old pattern, then threw in new DateTimeZone
    ['america/phoenix', ''],
    ['<script>alert(1)</script>', ''],
    ["America/Phoenix\n", ''],
    [str_repeat('A/', 100), ''],
    ['+05:00', ''],
    [['America/Phoenix'], ''],
    [7, ''],
    [null, ''],
];
foreach ($cases as [$in, $want]) {
    $b = $in === $missing ? ['action' => 'claim'] : ['action' => 'claim', 'tz' => $in];
    $got = $run($b);
    $check('tz ' . ($in === $missing ? '(not sent)' : json_encode($in)) . ' -> ' . json_encode($want), $got === $want);
    if (is_string($got) && $got !== '') {
        $ok = true; try { new DateTimeZone($got); } catch (Throwable $e) { $ok = false; }
        $check('  ' . $got . ' opens as a DateTimeZone', $ok);
    }
}

// f07 carried in this file: the PDOException catch must still come before the RuntimeException catch
$p = strpos($src, 'catch (PDOException'); $r = strpos($src, 'catch (RuntimeException');
$check('f07 kept: catch (PDOException) before catch (RuntimeException)', $p !== false && $r !== false && $p < $r);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f23/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f23/release.json ===
{
  "name": "f23-trips-tz-checked",
  "gate": "lane session: verify, run tests, zip-check before ship (draft, nothing run). Ship after f07; this file already contains f07's change.",
  "what": "api/trips.php: tz from the client was stored unchecked by add (:77), from_plan (:134), copy_journey (:147) and claim (:152). Only from_plan pattern-checked it, and that check let names like 'Foo/Bar' through, which then threw in new DateTimeZone. tz is now checked once at :44 against DateTimeZone::listIdentifiers(ALL_WITH_BC), at most 64 bytes and a string only. Anything else becomes '', the same as when no tz is sent. from_plan now just falls back to America/Phoenix.",
  "files": ["files/api/trips.php"],
  "decisions": [
    "Safest default for a bad tz: store '' (today's behaviour when tz is absent), not a guessed zone. A person should confirm '' is what Trips::create/claim expect for 'no zone'.",
    "ALL_WITH_BC is used so older names that browsers still send (for example Asia/Calcutta) are kept. Raw offsets ('+05:00') and wrong-case names are refused rather than normalised.",
    "This file is built on f07's version so shipping f23 cannot undo f07. f07's catch change is included unchanged."
  ],
  "tests": ["tests/trips-tz-checked.php"],
  "risks": [
    "Nothing here has been run, linted or tested. It is a draft.",
    "A client that sends an offset or an unusual zone string now gets the trip saved with no zone instead of that string. The from_plan dates then use America/Phoenix, as they already did for odd values.",
    "Rows already stored with bad tz values are not touched. No data change is made, so no data_reason is needed. Whether to clean old rows is a separate decision."
  ],
  "verify": [
    "Live path: the attached file was a scratch copy. files/api/trips.php is assumed (see f07). Confirm before zipping.",
    "app/lib/Trips.php: file not shown. Confirm how create()/claim() store and later use tz, the column length, and whether '' means 'no zone'.",
    "Decision for a person: should old rows with a tz outside the list be cleared? Any such migration needs its own UPDATE with a data_reason; none is included here.",
    "js/tv-trips.js and the trip page: file not shown. Confirm they send Intl.DateTimeFormat().resolvedOptions().timeZone (an IANA name).",
    "Run: php tests/trips-tz-checked.php files/api/trips.php and php ../f07/tests/trips-pdo-error-hidden.php files/api/trips.php"
  ],
  "regions": false
}
=== END FILE ===