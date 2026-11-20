<?php
// tools/trip-tests/trip-page.php: the trip page (decisions/0174 release 3), on a real MySQL or MariaDB.
// php trip-page.php <root>: as trips.php, with app/migrations/2026-11-21_trips-page.sql run. Rows: user 990011+,
// entity 990011+, removed after.
declare(strict_types=1);
$root = $argv[1] ?? '';
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/lib/Trips.php';
$pass = 0; $fail = 0;
$ok = function (bool $c, string $m) use (&$pass, &$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; $c ? $pass++ : $fail++; };
$pdo = Database::connection();
$clean = function () use ($pdo) {
    $pdo->exec('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id BETWEEN 990011 AND 990019');
    $pdo->exec('DELETE FROM trips WHERE user_id BETWEEN 990011 AND 990019');
    $pdo->exec('DELETE FROM entity_metadata WHERE entity_id BETWEEN 990011 AND 990019');
    $pdo->exec('DELETE FROM entities WHERE id BETWEEN 990011 AND 990019');
};
$clean();
$ins = $pdo->prepare('INSERT INTO entities (id, name, category_id, latitude, longitude, is_suppressed) VALUES (?, ?, NULL, ?, ?, 0)');
$meta = $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, ?, ?)');
$ins->execute([990011, 'Early Cafe', 34.25, -110.03]);  $meta->execute([990011, 'hours', json_encode(['Daily 6am to 2pm'])]); $meta->execute([990011, 'state', json_encode('AZ')]);
$ins->execute([990012, 'Lunch Place', 34.26, -110.04]); $meta->execute([990012, 'hours', json_encode(['Daily 11am to 2pm'])]); $meta->execute([990012, 'state', json_encode('AZ')]);
$ins->execute([990013, 'No Hours Shop', 34.30, -110.10]); $meta->execute([990013, 'state', json_encode('AZ')]);
$ins->execute([990014, 'Pacific Diner', 34.05, -118.24]); $meta->execute([990014, 'state', json_encode('CA')]); $meta->execute([990014, 'hours', json_encode(['Daily 6am to 10pm'])]);
$u = 990011;
$t = Trips::create($pdo, $u, 'Test', 'Europe/London', ['starts_on' => '2026-11-21', 'days' => 2]);
$a = Trips::add($pdo, $u, $t, 'listing', '990011'); $b = Trips::add($pdo, $u, $t, 'listing', '990012'); $c = Trips::add($pdo, $u, $t, 'listing', '990013');
$trip = Trips::own($pdo, $u, $t);
$ok($trip['tz'] === 'America/Phoenix', 'the trip\'s zone is its first stop\'s place (Arizona), not the device\'s');
$p = Trips::plan($pdo, $trip);
$d1 = $p['days'][0]['stops'];
$ok(count($p['days']) === 2 && $p['days'][0]['date'] === '2026-11-21' && $p['days'][1]['date'] === '2026-11-22', 'two days, dated from the start');
$ok($d1[0]['arrive'] === 540 && $d1[0]['leg'] === null, 'the first stop at the day\'s start (9 AM), no leg before it');
$ok($d1[1]['arrive'] === 540 + 60 + $d1[1]['leg']['minutes'] && $d1[1]['leg']['about'] === true, 'the next: the stay, then the leg ("about")');
$ok($d1[1]['open'] === false && $d1[1]['hours_note'] === "Closed when you'd arrive: Opens 11 AM", 'arriving before it opens: "Closed when you\'d arrive: Opens 11 AM"');
$ok($d1[0]['open'] === true && $d1[0]['hours_note'] === 'Open then', 'open on arrival: "Open then"');
$ok($d1[2]['open'] === null && str_contains($d1[2]['hours_note'], 'not known'), 'no hours known: says so');
Trips::configure($pdo, $u, $t, ['start_min' => 660]);
$p = Trips::plan($pdo, Trips::own($pdo, $u, $t));
$ok($p['days'][0]['stops'][0]['arrive'] === 660 && $p['days'][0]['stops'][1]['open'] === true, 'a later start: 11 AM, and Lunch Place is open then');
Trips::setStay($pdo, $u, $a, 120);
$p = Trips::plan($pdo, Trips::own($pdo, $u, $t));
$ok($p['days'][0]['stops'][0]['leave'] === 660 + 120, 'a stay of 2 hours moves what follows');
$threw = false; try { Trips::setStay($pdo, $u, $a, 7); } catch (RuntimeException $e) { $threw = true; } $ok($threw, 'a stay must be one of the choices');
// no date: times, but no hours checked
Trips::configure($pdo, $u, $t, ['starts_on' => '']);
$p = Trips::plan($pdo, Trips::own($pdo, $u, $t));
$ok($p['days'][0]['date'] === null && $p['days'][0]['stops'][1]['open'] === null && $p['days'][0]['stops'][1]['hours_note'] === '', 'no date: timed, hours not judged');
Trips::configure($pdo, $u, $t, ['starts_on' => '2026-11-21']);
// moving
Trips::move($pdo, $u, $c, 1, 0);
$ids = array_column(array_filter(Trips::stops($pdo, $t), fn ($s) => $s['day'] === 1), 'id');
$ok($ids === [$c, $a, $b], 'move to the top of day 1');
Trips::move($pdo, $u, $b, 2, 0);
$p = Trips::plan($pdo, Trips::own($pdo, $u, $t));
$ok(count($p['days'][0]['stops']) === 2 && $p['days'][1]['stops'][0]['id'] === $b && $p['days'][1]['stops'][0]['arrive'] === 660, 'move to day 2: it starts that day');
Trips::move($pdo, $u, $a, 3, 0);
$ok((int) Trips::own($pdo, $u, $t)['days'] === 3, 'moving to a day past the last adds the day');
Trips::configure($pdo, $u, $t, ['days' => 1]);
$st = Trips::stops($pdo, $t);
$ok(count($st) === 3 && !array_filter($st, fn ($s) => $s['day'] !== 1), 'fewer days: their stops move to the last day, none dropped');
$threw = ''; try { Trips::move($pdo, 990012, $a, 1, 0); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok($threw !== '', 'nobody moves someone else\'s stops');
$threw = ''; try { Trips::configure($pdo, $u, $t, ['starts_on' => '2026-02-31']); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok($threw === 'Pick a date.', 'a date that doesn\'t exist is refused');
// a stop in another zone
Trips::add($pdo, $u, $t, 'listing', '990014');
$p = Trips::plan($pdo, Trips::own($pdo, $u, $t));
$last = end($p['days'][0]['stops']);
$ok($last['tz'] === 'America/Los_Angeles' && $p['tz'] === 'America/Phoenix', 'a stop in California keeps its own zone; the trip stays Arizona\'s');
// daylight: a landmark after dark
$lm = $pdo->query('SELECT gnis_id FROM landmarks WHERE lat IS NOT NULL LIMIT 1')->fetchColumn();
if ($lm) {
    $t2 = Trips::create($pdo, $u, 'Night', '', ['starts_on' => '2026-11-21', 'start_min' => 1320]);
    Trips::add($pdo, $u, $t2, 'lm', (string) $lm);
    $s = Trips::plan($pdo, Trips::own($pdo, $u, $t2))['days'][0]['stops'][0];
    $ok($s['open'] === false && str_starts_with($s['hours_note'], 'After dark'), 'a place outdoors at 10 PM: "After dark: sunset …"');
}
// a journey's stops into an existing trip
[$added, $left] = Trips::addMany($pdo, $u, $t, [['kind' => 'listing', 'ref' => '990013'], ['kind' => 'listing', 'ref' => '990011'], ['kind' => 'listing', 'ref' => '999999999']]);
$ok($added === 0 && count($left) === 1, 'Use these stops: ones already there aren\'t doubled, a missing one is named');
$undo = Trips::remove($pdo, $u, $b);
$ok($undo['day'] === 1 && array_key_exists('stay', $undo), 'Undo keeps the day and the stay');
$clean();
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
