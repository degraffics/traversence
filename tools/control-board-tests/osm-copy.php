<?php
// osm-copy (Jason, 2026-11-19: "Yes, add them" and "Yes, widen it"): our OpenStreetMap copy holds Utah, Colorado and
// Nevada too, and carries health care and guides (worker 2.14.0's OSM_TAG_FILTER), so W1's health kinds come from it; an
// office kind other than guides still isn't in our copy. Run: mariadb < ../release-b-tests/start-schema.sql && php osm-copy.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : '') . "\n"; } };
require_once $app . '/lib/OpenPlaces.php'; require_once $app . '/lib/crawler/OpenLoads.php'; require_once $app . '/lib/SearchIndex.php';
$t('five states in our copy: Arizona, New Mexico, Utah, Colorado, Nevada', array_keys(OpenPlaces::COPY_STATES) === ['AZ', 'NM', 'UT', 'CO', 'NV'] && array_values(OpenPlaces::COPY_STATES) === array_keys(OpenLoads::GEOFABRIK_STATES));
$care = 'nwr["healthcare"~"^(hospital|clinic|urgent_care)$"]';
$t('health care is answerable from our copy now', OpenPlaces::filterKind($care) !== '', OpenPlaces::filterKind($care));
$t('…and guides (office=guide)', OpenPlaces::filterKind('nwr["office"="guide"]') !== '');
$t('…but no other office kind (the extract keeps guides only)', OpenPlaces::filterKind('nwr["office"="lawyer"]') === '');
$t('the kinds kept by our copy include them, so each state loads again at once with them', count(array_filter(OpenPlaces::copyKinds(), fn ($k) => in_array($k, array_keys(OpenPlaces::leadKinds()), true))) >= 2);
// a Utah job, once Utah's copy is loaded with today's kinds
$pdo->exec("CREATE TABLE IF NOT EXISTS open_loads (id INT AUTO_INCREMENT PRIMARY KEY, dataset VARCHAR(20), place_key VARCHAR(40), release_id VARCHAR(40), started_at DATETIME, finished_at DATETIME NULL, seen INT DEFAULT 0, listed INT DEFAULT 0, UNIQUE KEY d (dataset, place_key, release_id))");
$pdo->exec("CREATE TABLE IF NOT EXISTS open_places (id INT AUTO_INCREMENT PRIMARY KEY, osm_ref VARCHAR(40) UNIQUE, kind VARCHAR(20), name VARCHAR(200), what VARCHAR(120) DEFAULT '', lat DOUBLE, lon DOUBLE, phone VARCHAR(60) DEFAULT '', website VARCHAR(255) DEFAULT '', hours VARCHAR(255) DEFAULT '', street VARCHAR(160) DEFAULT '', city VARCHAR(80) DEFAULT '', state VARCHAR(2) DEFAULT '', fetched_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS open_place_asks (qkey VARCHAR(80) PRIMARY KEY, found INT DEFAULT 0, asked_at DATETIME)");   // OpenPlaces::ready() looks for it
$pdo->exec("DELETE FROM open_loads"); $pdo->exec("DELETE FROM open_places");
$pdo->exec("INSERT INTO open_loads (dataset, place_key, release_id, started_at, finished_at, listed) VALUES ('geofabrik', 'utah', '2026-11-19', UTC_TIMESTAMP(), UTC_TIMESTAMP(), 10)");
SearchIndex::setState($pdo, 'open:geofabrik_sig:utah', OpenPlaces::copySignature());
$kind = OpenPlaces::filterKind($care);
$pdo->prepare("INSERT INTO open_places (osm_ref, kind, name, lat, lon, city, state) VALUES ('node/9', ?, 'Moab Regional Hospital', 38.575, -109.55, 'Moab', 'UT')")->execute([$kind]);
$job = ['looking_for' => ['osm' => [$care, 'nwr["office"="lawyer"]']], 'place' => ['towns' => [['town' => 'Moab', 'state' => 'UT']], 'zip_points' => [['zip' => '84532', 'lat' => 38.573, 'lon' => -109.549]]]];
$fj = OpenPlaces::forJob($pdo, $job);
$t('a Utah town: its health care read from our copy, the office kind not in our copy', count($fj['ours'][$care] ?? []) === 1 && ($fj['not_in_copy'] ?? []) === ['nwr["office"="lawyer"]'], $fj);
$due = array_column(OpenLoads::geofabrikDue($pdo), 'key');
$t('the states not loaded yet are due (Utah, loaded with today\'s kinds, isn\'t)', !in_array('utah', $due, true) && in_array('colorado', $due, true) && in_array('nevada', $due, true), $due);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
