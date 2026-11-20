<?php
// tools/trip-tests/trips.php: trips you can save (decisions/0174 release 2), on a real MySQL or MariaDB.
// php trips.php <root>: <root> holds app/ (the release's files over the live mirror), and its database has entities,
// entity_metadata, categories, landmarks and the trips tables (app/migrations/2026-11-20_trips.sql). Its rows use
// user 990001+, entity 990001+ and are removed after.
declare(strict_types=1);
$root = $argv[1] ?? '';
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/lib/Trips.php';
require_once $root . '/app/lib/AccountDeletion.php';
require_once $root . '/app/lib/staging-tables.php';
$pass = 0; $fail = 0;
$ok = function (bool $c, string $m) use (&$pass, &$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; $c ? $pass++ : $fail++; };
$pdo = Database::connection();
$clean = function () use ($pdo) {
    $pdo->exec('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id BETWEEN 990001 AND 990009');
    $pdo->exec('DELETE FROM trips WHERE user_id BETWEEN 990001 AND 990009');
    $pdo->exec('DELETE FROM entity_metadata WHERE entity_id BETWEEN 990001 AND 990009');
    $pdo->exec('DELETE FROM entities WHERE id BETWEEN 990001 AND 990009');
};
$clean();
$ok(Trips::ready($pdo), 'the tables are there');
$ok(in_array('trips', StagingTables::RECORDS, true) && in_array('trip_stops', StagingTables::RECORDS, true), 'both tables are named in staging-tables.php (records)');

// the listings the rules are about
$ins = $pdo->prepare('INSERT INTO entities (id, name, category_id, latitude, longitude, is_suppressed) VALUES (?, ?, NULL, ?, ?, ?)');
$ins->execute([990001, 'Pine Cafe', 34.25, -110.03, 0]);
$ins->execute([990002, "Mountain Women's Shelter", 34.26, -110.04, 0]);
$ins->execute([990003, 'Box Diner', 34.27, -110.05, 0]);
$ins->execute([990004, 'Gone Grill', 34.28, -110.06, 0]);
$ins->execute([990005, 'Hidden Shop', 34.29, -110.07, 1]);
$ins->execute([990006, 'No Point Cafe', null, null, 0]);
$meta = $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, ?, ?)');
$meta->execute([990001, 'address', json_encode('12 Main St')]); $meta->execute([990001, 'city', json_encode('Show Low')]);
$meta->execute([990003, 'address', json_encode('PO Box 77')]);
$meta->execute([990004, 'closed', json_encode(['when' => '2026-11-01', 'note' => 'gone'])]);

$r = Trips::resolve($pdo, 'listing', '990001');
$ok($r['ok'] && $r['name'] === 'Pine Cafe' && $r['town'] === 'Show Low' && abs($r['lat'] - 34.25) < 1e-6, 'a listing resolves: its name, its town (JSON decoded), its point');
$ok(Trips::nameFor($r) === 'Trip to Show Low', 'a new trip is named for the place: Trip to Show Low');
$ok(str_contains(Trips::addable($pdo, 'listing', '990002'), 'kept private'), 'a shelter: "Its location is kept private: call them"');
$ok(str_contains(Trips::addable($pdo, 'listing', '990003'), 'mailbox'), 'a mailbox address: no place to drive to');
$ok(str_contains(Trips::addable($pdo, 'listing', '990004'), 'closed'), 'a listing marked closed (its mark holds when and why)');
$ok(Trips::addable($pdo, 'listing', '990005') === 'No longer listed.', 'a hidden listing: no longer listed');
$ok(str_contains(Trips::addable($pdo, 'listing', '990006'), 'no point'), 'no point: it can\'t be a stop yet');
$ok(Trips::key('listing', '1 OR 1') === null && Trips::key('place', 'drop:1') === null && Trips::key('osm', 'node/12') !== null && Trips::key('nope', '1') === null, 'kinds and refs are checked');
$lm = $pdo->query('SELECT gnis_id FROM landmarks LIMIT 1')->fetchColumn();
if ($lm) $ok(Trips::resolve($pdo, 'lm', (string) $lm)['ok'], 'a landmark shown on the site resolves');

// a person's trips
$u = 990001;
$t1 = Trips::create($pdo, $u, 'Trip to Show Low', 'America/Phoenix');
$s1 = Trips::add($pdo, $u, $t1, 'listing', '990001');
$ok($s1 > 0, 'a stop is added');
$ok(Trips::add($pdo, $u, $t1, 'listing', '990001') === -$s1, 'the same place again: already there, not twice');
$threw = ''; try { Trips::add($pdo, $u, $t1, 'listing', '990002'); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok(str_contains($threw, 'kept private'), 'adding a shelter is refused with why, even by a direct call');
$threw = ''; try { Trips::add($pdo, 990002, $t1, 'listing', '990001'); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok($threw !== '', 'nobody adds to someone else\'s trip');
$ok(Trips::mine($pdo, 990002)['trips'] === [], 'and nobody else sees it');
if ($lm) Trips::add($pdo, $u, $t1, 'lm', (string) $lm);
$st = Trips::stops($pdo, $t1);
$ok(count($st) === ($lm ? 2 : 1) && $st[0]['name'] === 'Pine Cafe', 'the stops in order');
$undo = Trips::remove($pdo, $u, $s1);
$ok(count(Trips::stops($pdo, $t1)) === ($lm ? 1 : 0), 'a stop taken out');
Trips::add($pdo, $u, $t1, $undo['kind'], $undo['ref'], $undo['position'], $undo['note']);
$st = Trips::stops($pdo, $t1);
$ok($st[0]['name'] === 'Pine Cafe', 'Undo puts it back in its place');
// a stop that later becomes private stays, greyed with why
$pdo->exec("UPDATE entities SET name = 'Pine Cafe safe house' WHERE id = 990001");
$st = Trips::stops($pdo, $t1);
$ok(!$st[0]['ok'] && str_contains($st[0]['why'], 'kept private') && !isset($st[0]['lat']), 'a stop that becomes private keeps its place, greyed, with no point');
$pdo->exec("UPDATE entities SET name = 'Pine Cafe' WHERE id = 990001");
$ok(Trips::rename($pdo, $u, $t1, '  Weekend <b>away</b> ') === 'Weekend away', 'rename: tags and spaces cleaned');
// delete keeps it 30 days
Trips::delete($pdo, $u, $t1);
$m = Trips::mine($pdo, $u);
$ok(!$m['trips'] && count($m['deleted']) === 1, 'Delete moves it to Deleted trips');
Trips::delete($pdo, $u, $t1, true);
$ok(count(Trips::mine($pdo, $u)['trips']) === 1, 'Put back');
$t2 = Trips::create($pdo, $u, 'Old', '');
Trips::delete($pdo, $u, $t2);
$pdo->exec('UPDATE trips SET deleted_at = NOW() - INTERVAL 31 DAY WHERE id = ' . $t2);
$ok(!array_filter(Trips::mine($pdo, $u)['deleted'], fn ($t) => $t['id'] === $t2), 'after 30 days a deleted trip is gone');

// the device's trip moved in at sign-in; a ready-made journey's stops
[$t3, $added, $left] = Trips::claim($pdo, $u, 'Trip to Pinetop', [['kind' => 'listing', 'ref' => '990001'], ['kind' => 'listing', 'ref' => '990002'], ['kind' => 'bad', 'ref' => 'x'], ['kind' => 'listing', 'ref' => '990003']]);
$ok($added === 1 && count($left) === 2 && str_contains(implode(' ', $left), 'kept private'), 'claim: what can go in moves in; the rest is named with why');
$ok(Trips::fromJourney([['ref' => 'entity:5'], ['ref' => 'landmark:7'], ['ref' => 'osm:node/9'], ['ref' => 'rec:R1'], ['ref' => 'place:cluster:1']]) === [['listing', '5'], ['lm', '7'], ['osm', 'node/9'], ['rec', 'R1']], 'a journey\'s stops map onto trip stops');

// account deletion deletes their trips
$ok((AccountDeletion::summary($pdo, $u)['trips'] ?? 0) >= 2, 'Delete account\'s summary counts their trips');
$src = file_get_contents($root . '/app/lib/AccountDeletion.php');
$ok(str_contains($src, "DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = ?") && str_contains($src, "DELETE FROM trips WHERE user_id = ?"), 'AccountDeletion::run deletes the person\'s trips and their stops');
$clean();
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
