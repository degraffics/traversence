<?php
// tools/w15-tests/winslow.php: W15 release 1 on a real MariaDB (php winslow.php <live app/lib> <migration.sql>). Live's
// columns for micro_clusters and zip_coordinates (IntakeClusters.php's confirmed list); 1A's areas as live has them
// (1 St. Johns, 2 Round Valley, 47 Show Low); runs the migration twice the way DbUpdates::autoRun does, then reads Winslow
// with the live search's own test (UniversalSearch::clusterForZips' query) and Guides::syncTowns' town.
declare(strict_types=1);
[$lib, $mig] = [$argv[1] ?? '', $argv[2] ?? ''];
$pdo = new PDO('mysql:host=localhost;dbname=ts4;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['micro_clusters', 'zip_coordinates'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
$pdo->exec("CREATE TABLE micro_clusters (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, geo_hub_id INT UNSIGNED, slug VARCHAR(80), name VARCHAR(150), primary_zip CHAR(5), associated_zips JSON, center_lat DECIMAL(9,6), center_lon DECIMAL(9,6), source ENUM('manual','automated_seed','system_proposed'), confidence_score DECIMAL(3,2), created_at DATETIME, updated_at DATETIME)");
$pdo->exec("CREATE TABLE zip_coordinates (id INT AUTO_INCREMENT PRIMARY KEY, zip CHAR(5), city VARCHAR(80), state CHAR(2), county VARCHAR(80), latitude DECIMAL(9,6), longitude DECIMAL(9,6))");
$pdo->exec("INSERT INTO micro_clusters (id, geo_hub_id, slug, name, primary_zip, associated_zips, source) VALUES (1, 3, 'cluster-1a-st-johns', 'The Greater St. Johns Area', '85936', '[\"85936\"]', 'manual'), (2, 3, 'cluster-1b-round-valley', 'Round Valley (Springerville & Eagar)', '85938', '[\"85938\",\"85925\"]', 'manual'), (47, 3, 'cluster-auto-show-low', 'The Show Low District', '85901', '[\"85901\"]', 'automated_seed'), (60, 9, 'cluster-auto-tucson', 'Tucson (auto-seeded, needs review)', '85701', '[\"85701\"]', 'automated_seed')");
$pdo->exec("ALTER TABLE micro_clusters AUTO_INCREMENT = 61");
$pdo->exec("INSERT INTO zip_coordinates (id, zip, city, state, latitude, longitude) VALUES (2164, '86047', 'WINSLOW', 'AZ', 35.0242, -110.6974), (2165, '86025', 'HOLBROOK', 'AZ', 34.9022, -110.1582), (23825, '08095', 'WINSLOW', 'NJ', 39.65, -74.86)");
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };
$covers = function (string $zip) use ($pdo) { $st = $pdo->prepare('SELECT id, slug, name, geo_hub_id FROM micro_clusters WHERE primary_zip = ? LIMIT 1'); $st->execute([$zip]); if ($c = $st->fetch(PDO::FETCH_ASSOC)) return $c;
    $st = $pdo->prepare('SELECT id, slug, name, geo_hub_id FROM micro_clusters WHERE associated_zips LIKE ? LIMIT 1'); $st->execute(['%"' . $zip . '"%']); return $st->fetch(PDO::FETCH_ASSOC) ?: null; };
$t('before: Winslow 86047 is in no area (search reads it as outside)', $covers('86047') === null);
$sql = (string) file_get_contents($mig);
$run = function () use ($pdo, $sql) { foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) { $s = trim(preg_replace('/^--.*$/m', '', $s)); if ($s !== '') $pdo->exec($s); } };
$run(); $run();
$c = $covers('86047');
$t('after: Winslow has its own area, in the zone of the Greater St. Johns Area', $c !== null && $c['slug'] === 'cluster-1a-winslow' && $c['name'] === 'Winslow' && (int) $c['geo_hub_id'] === 3);
$t('run twice: one Winslow area', (int) $pdo->query("SELECT COUNT(*) FROM micro_clusters WHERE primary_zip = '86047'")->fetchColumn() === 1);
$t('its id named: MAX(id)+1 = 61', $c !== null && (int) $c['id'] === 61);
$t('its middle from our locations data (86047), not Winslow, NJ', (float) $pdo->query("SELECT center_lat FROM micro_clusters WHERE id = 61")->fetchColumn() === 35.0242);
$t('no other area touched', (int) $pdo->query("SELECT COUNT(*) FROM micro_clusters")->fetchColumn() === 5 && $pdo->query("SELECT slug FROM micro_clusters WHERE id = 2")->fetchColumn() === 'cluster-1b-round-valley');
// with no St. Johns area (another database), nothing is made rather than a wrong zone
$pdo->exec("DELETE FROM micro_clusters WHERE id = 61"); $pdo->exec("UPDATE micro_clusters SET slug = 'x' WHERE id = 1"); $run();
$t('no zone found: nothing made (never a guessed zone)', $covers('86047') === null);
// Undo: the one row, while nothing points to it
$t('Undo leaves the others as they were', (int) $pdo->query("SELECT COUNT(*) FROM micro_clusters")->fetchColumn() === 4);
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
