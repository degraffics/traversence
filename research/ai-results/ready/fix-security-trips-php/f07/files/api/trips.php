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
