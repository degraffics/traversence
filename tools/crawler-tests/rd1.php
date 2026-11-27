<?php
// tools/crawler-tests/rd1.php: R-D1, the launch area's load order, on a real MariaDB (php rd1.php <lib dir> <US.txt>).
// <lib dir>: a copy of the live sync-app/lib with the build's crawler/OpenLoads.php laid over it. Loads the real US.txt
// for AZ, NM, CO and UT into zip_coordinates, a few clusters as live has them, and checks the order and its guards.
declare(strict_types=1);
[$lib, $us] = [$argv[1] ?? '', $argv[2] ?? ''];
require_once $lib . '/crawler/OpenLoads.php';
$pdo = new PDO('mysql:host=localhost;dbname=rd1;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['zip_coordinates', 'micro_clusters', 'entities', 'open_loads', 'anchors'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
$pdo->exec('CREATE TABLE zip_coordinates (id INT AUTO_INCREMENT PRIMARY KEY, zip CHAR(5), city VARCHAR(80), state CHAR(2), latitude DECIMAL(9,6) NULL, longitude DECIMAL(9,6) NULL)');
$pdo->exec('CREATE TABLE micro_clusters (id INT PRIMARY KEY, slug VARCHAR(120), name VARCHAR(120), primary_zip CHAR(5), associated_zips JSON NULL)');
$pdo->exec('CREATE TABLE entities (id INT AUTO_INCREMENT PRIMARY KEY, primary_zip CHAR(5), is_suppressed TINYINT DEFAULT 0)');
$pdo->exec('CREATE TABLE open_loads (id INT AUTO_INCREMENT PRIMARY KEY, dataset VARCHAR(20), place_key VARCHAR(40), release_id VARCHAR(40), started_at DATETIME, finished_at DATETIME NULL, seen INT DEFAULT 0, staged INT DEFAULT 0, listed INT DEFAULT 0, closed INT DEFAULT 0)');
$pdo->exec('CREATE TABLE anchors (id INT AUTO_INCREMENT PRIMARY KEY, cluster_id INT NULL, slug VARCHAR(120) UNIQUE, name VARCHAR(120), zips TEXT, state CHAR(2), lat DECIMAL(9,6) NULL, lon DECIMAL(9,6) NULL)');
$ins = $pdo->prepare('INSERT INTO zip_coordinates (zip, city, state, latitude, longitude) VALUES (?, ?, ?, ?, ?)');
$pdo->beginTransaction();
foreach (file($us, FILE_IGNORE_NEW_LINES) as $l) { $r = explode("\t", $l); if (in_array($r[4] ?? '', ['AZ', 'NM', 'CO', 'UT'], true) && ($r[9] ?? '') !== '') $ins->execute([$r[1], $r[2], $r[4], $r[9], $r[10]]); }
$pdo->commit();
// as live: St. Johns, Round Valley still on its old slug (85938, 85925), Show Low, Holbrook, Winslow (W15 r1), and Phoenix outside
$c = $pdo->prepare('INSERT INTO micro_clusters (id, slug, name, primary_zip, associated_zips) VALUES (?, ?, ?, ?, ?)');
foreach ([[1, 'cluster-1a-st-johns', 'St. Johns', '85936', '["85936"]'], [2, 'cluster-1b-round-valley', 'Springerville & Eagar', '85938', '["85938","85925"]'],
          [47, 'cluster-1a-show-low', 'Show Low', '85901', '["85901","85902"]'], [55, 'cluster-1a-holbrook', 'Holbrook', '86025', '["86025"]'],
          [60, 'cluster-1a-winslow', 'Winslow', '86047', '["86047"]'], [90, 'cluster-9z-phoenix', 'Phoenix', '85001', '["85001"]']] as $r) $c->execute($r);
$pdo->exec("INSERT INTO anchors (cluster_id, slug, name, zips, state, lat, lon) VALUES (1, 'st-johns-az', 'St. Johns', '85936', 'AZ', 34.5, -109.37)");
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };

$area = OpenLoads::area($pdo);
$t('the area is AZ and NM towns only (CO and UT wait for G1)', !array_filter($area, fn ($x) => !in_array($x['state'], ['AZ', 'NM'], true)));
$far = array_filter($area, fn ($x) => $x['miles'] > 200 && !$x['is1a']);
$t('nothing outside 200 miles but 1A', !$far);
$one = array_values(array_filter($area, fn ($x) => $x['is1a']));
$names = array_map(fn ($x) => $x['city'], $one);
$t('1A first: its six towns lead the list', array_slice(array_map(fn ($x) => $x['city'], $area), 0, count($one)) === $names && count(array_intersect(['Saint Johns', 'Springerville', 'Eagar', 'Show Low', 'Holbrook', 'Winslow'], $names)) === 6);
$t('Eagar is in (200.3 mi, a 1A town)', in_array('Eagar', $names, true));
$t('Phoenix is out (its cluster is not 1A, and it is past 200 mi)', !in_array('Phoenix', array_column($area, 'city'), true));
$rest = array_values(array_filter($area, fn ($x) => !$x['is1a']));
$m = array_column($rest, 'miles'); $s = $m; sort($s);
$t('after 1A, nearest the Four Corners first', $m === $s && count($rest) > 100);

$d = OpenLoads::due($pdo, 'overture', 'r1', 3);
$t('due: three 1A towns, keyed town:<id>', count($d) === 3 && !array_filter($d, fn ($x) => !preg_match('/^town:\d+$/', $x['key'])));
$t('St. Johns reuses its cluster anchor (no second anchor)', (int) $pdo->query("SELECT COUNT(*) FROM anchors WHERE name = 'St. Johns' AND state = 'AZ'")->fetchColumn() === 1 && in_array('town:1', array_column(OpenLoads::due($pdo, 'overture', 'r1', 10), 'key'), true));
$before = (int) $pdo->query('SELECT COUNT(*) FROM anchors')->fetchColumn();
OpenLoads::due($pdo, 'overture', 'r1', 3);
$t('asking again makes no new anchors', $before === (int) $pdo->query('SELECT COUNT(*) FROM anchors')->fetchColumn());
foreach ($d as $x) $pdo->prepare("INSERT INTO open_loads (dataset, place_key, release_id, started_at, finished_at) VALUES ('overture', ?, 'r1', NOW(), NOW())")->execute([$x['key']]);
$d2 = OpenLoads::due($pdo, 'overture', 'r1', 3);
$t('a finished town is skipped; the next ones come', !array_intersect(array_column($d, 'key'), array_column($d2, 'key')) && count($d2) === 3);
$t('another dataset starts again from 1A', OpenLoads::due($pdo, 'foursquare', 'r1', 1)[0]['key'] === $d[0]['key']);
$t('a box is about 7 km each way around the town', abs(($d[0]['box'][3] - $d[0]['box'][1]) - 0.13) < 0.001);

// the fallback: once every area town is done, the clusters by fewest listings, as before
foreach (OpenLoads::area($pdo) as $tw) { $k = OnDemand::townFor($pdo, $tw['city'], $tw['state']); if ($k) $pdo->prepare("INSERT INTO open_loads (dataset, place_key, release_id, started_at, finished_at) VALUES ('overture', ?, 'r2', NOW(), NOW())")->execute([$k]); }
$fb = OpenLoads::due($pdo, 'overture', 'r2', 2);
$t('after the area: clusters, as before (cluster:<id>)', count($fb) === 2 && str_starts_with($fb[0]['key'], 'cluster:'));
$t('n = 0 asks for nothing', OpenLoads::due($pdo, 'overture', 'r3', 0) === []);

// W15 r2's exception can't outlive r2
$t('Round Valley counts as 1A while its slug is still 1b', in_array('85925', OpenLoads::zips1A($pdo), true));
$pdo->exec("INSERT INTO micro_clusters (id, slug, name, primary_zip, associated_zips) VALUES (91, 'cluster-1a-round-valley', 'Springerville & Eagar', '85938', '[\"85938\",\"85925\"]')");
$pdo->exec("UPDATE micro_clusters SET slug = 'cluster-1b-old', primary_zip = '00000', associated_zips = '[]' WHERE id = 2");
$t('after r2 renames it, the new slug carries it as 1A by itself', in_array('85925', OpenLoads::zips1A($pdo), true));
$live = getenv('LIVE_SLUGS');   // the live clusters' slugs, one per line, when the mirror can give them
$t('R2_PENDING is removed once r2 is live (fails while a cluster-1a-round-valley is live and the constant stays)', $live === false || !str_contains((string) $live, 'cluster-1a-round-valley') || OpenLoads::R2_PENDING === []);
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
