<?php
// tools/trip-tests/trips-seed.php <root>: the browser test's starting point (harness only): user 1 has no trips, and
// suggested journey 2 has three stops (two listings and a landmark).
declare(strict_types=1);
require_once ($argv[1] ?? '') . '/app/bootstrap.php';
$pdo = Database::connection();
$pdo->exec('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id = 1');
$pdo->exec('DELETE FROM trips WHERE user_id = 1');
$e = $pdo->query('SELECT id, name, latitude, longitude FROM entities WHERE id IN (3, 4) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$l = $pdo->query('SELECT gnis_id, name, lat, lon FROM landmarks ORDER BY gnis_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$stop = fn (string $ref, string $name, string $url, $lat, $lon, int $at) => ['ref' => $ref, 'name' => $name, 'sub' => 'Test', 'url' => $url, 'lat' => (float) $lat, 'lon' => (float) $lon,
    'address' => '', 'hours' => 'open', 'arrive' => $at, 'stay' => 45, 'leg' => null];
$stops = [];
foreach ($e as $i => $r) $stops[] = $stop('entity:' . $r['id'], (string) $r['name'], '/listing/view.php?id=' . $r['id'], $r['latitude'], $r['longitude'], 540 + $i * 60);
$stops[] = $stop('landmark:' . $l['gnis_id'], (string) $l['name'], '/place/landmark.php?id=' . $l['gnis_id'], $l['lat'], $l['lon'], 720);
$pdo->prepare("UPDATE suggested_journeys SET title = 'Show Low outdoors', status = 'live', stops = ? WHERE id = 2")->execute([json_encode(['stops' => $stops])]);
echo "seeded\n";
