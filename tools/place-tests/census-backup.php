<?php
// decisions/0175's note (Jason, 2026-11-20, "Yes, build it"): the Census geocoder's backup. When the site's call to
// geocoding.geo.census.gov fails on its connection (reset or no answer), the crawler's batch goes to the worker on Railway
// to send itself, and its answers come back to the site to place by the same rules; a batch neither can send waits.
// Run on a real MySQL or MariaDB (no network: the site's call is held for its connection by an open issue):
//   php census-backup.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES), 0, 600) : '') . "\n"; } };
foreach (['entities', 'entity_metadata', 'zip_coordinates', 'service_issues'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, entity_type VARCHAR(20) NOT NULL DEFAULT 'merchant', name VARCHAR(200) NOT NULL, category_id INT NULL, primary_zip VARCHAR(10) NULL,
    latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL, is_suppressed TINYINT NOT NULL DEFAULT 0, updated_at DATETIME NULL)");
$pdo->exec("CREATE TABLE entity_metadata (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT NOT NULL, meta_key VARCHAR(60) NOT NULL, meta_value TEXT NULL, trust_score DECIMAL(5,2) NULL,
    is_locked TINYINT NOT NULL DEFAULT 0, source_type VARCHAR(40) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY ek (entity_id, meta_key))");
$pdo->exec("CREATE TABLE zip_coordinates (zip VARCHAR(10) PRIMARY KEY, latitude DECIMAL(10,7), longitude DECIMAL(10,7))");
$pdo->exec("INSERT INTO zip_coordinates VALUES ('85936', 34.4900000, -109.3600000)");
$pdo->exec("CREATE TABLE service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL,
    resolved_at DATETIME NULL, resolved_by VARCHAR(80) NULL, resolution TEXT NULL, alarm_id INT NULL)");
$e = $pdo->prepare("INSERT INTO entities (id, name, primary_zip, latitude, longitude, updated_at) VALUES (?, ?, '85936', ?, ?, NOW())");
$m = $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, updated_at) VALUES (?, ?, ?, NOW())");
foreach ([[1, 'Main Street Cafe', '120 W Main St'], [2, 'Hilltop Motel', '455 E Cleveland St'], [3, 'Box Ranch', 'PO Box 12']] as [$id, $n, $a]) {
    $e->execute([$id, $n, 34.49, -109.36]);   // the ZIP's middle: waiting for a point
    $m->execute([$id, 'address', json_encode($a)]); $m->execute([$id, 'city', json_encode('St. Johns')]); $m->execute([$id, 'state', json_encode('AZ')]);
}
$e->execute([4, 'Placed Already', 34.5012, -109.3711]); $m->execute([4, 'address', json_encode('9 Commercial Ave')]);
require_once $app . '/lib/Geocoder.php'; require_once $app . '/lib/Automation.php';

// ---- the site's call held for its connection (a 'timeout' issue): the batch goes to the worker
$pdo->exec("INSERT INTO service_issues (service, host, caller, kind, state, first_at, last_at, hold_until) VALUES ('census', 'geocoding.geo.census.gov', 'site', 'timeout', 'open', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 1 HOUR)");
$t('the hold is for its connection', Geocoder::connectionHeld($pdo));
$r = Geocoder::runOrHandOff($pdo);
$b = $r['backup'] ?? null;
$t('it waits on the site, and hands the batch to the worker: the Census\'s own batch address', !empty($r['waits']) && $b && $b['url'] === 'https://geocoding.geo.census.gov/geocoder/locations/addressbatch' && $b['benchmark'] === 'Public_AR_Current', $r);
$csv = $b ? base64_decode($b['csv']) : '';
$t('…the street addresses only (a PO box is placed another way)', $b && $b['ids'] === [1, 2] && str_contains($csv, '120 W Main St') && !str_contains($csv, 'PO Box') && ($r['mailonly'] ?? 0) === 1, [$b['ids'] ?? null, $csv]);
$t('…and nothing is placed or marked found meanwhile', (float) $pdo->query('SELECT latitude FROM entities WHERE id = 1')->fetchColumn() === 34.49 && !$pdo->query("SELECT 1 FROM entity_metadata WHERE entity_id IN (1, 2) AND meta_key = 'geocode'")->fetchColumn());

// ---- the worker's answers: placed by the same rules
$reply = "\"1\",\"120 W Main St, St. Johns, AZ, 85936\",\"Match\",\"Exact\",\"120 W MAIN ST, SAINT JOHNS, AZ, 85936\",\"-109.3712345,34.5054321\",\"1\",\"L\"\n"
       . "\"2\",\"455 E Cleveland St, St. Johns, AZ, 85936\",\"No_Match\"\n"
       . "\"4\",\"9 Commercial Ave\",\"Match\",\"Exact\",\"9 COMMERCIAL AVE\",\"-100.0,40.0\",\"1\",\"L\"\n"
       . "\"3\",\"PO Box 12\",\"Match\",\"Exact\",\"X\",\"-101.0,41.0\",\"1\",\"L\"\n";
$a = Geocoder::takeAnswers($pdo, [1, 2, 4], $reply);
$pt = $pdo->query('SELECT latitude, longitude FROM entities WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$t('a match is placed on its street, marked found, and says it came by Railway', abs((float) $pt['latitude'] - 34.5054321) < 1e-6 && abs((float) $pt['longitude'] + 109.3712345) < 1e-6
    && str_contains((string) $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = 1 AND meta_key = 'geocode'")->fetchColumn(), '"via":"railway"'), $pt);
$t('no match is marked as the site\'s own call would (tried again after 90 days)', str_contains((string) $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = 2 AND meta_key = 'geocode'")->fetchColumn(), '"s":"nomatch"'));
$t('a listing with a real point isn\'t changed, even if named', (float) $pdo->query('SELECT latitude FROM entities WHERE id = 4')->fetchColumn() === 34.5012);
$t('an answer for a listing it wasn\'t handed is never placed', (float) $pdo->query('SELECT latitude FROM entities WHERE id = 3')->fetchColumn() === 34.49);
$t('the counts say so', $a['sent'] === 2 && $a['found'] === 1 && $a['not_found'] === 1, $a);

// ---- not for another kind of hold: the batch waits, as before
$e->execute([5, 'Corner Store', 34.49, -109.36]); $m->execute([5, 'address', json_encode('7 S 1st W')]); $m->execute([5, 'city', json_encode('St. Johns')]); $m->execute([5, 'state', json_encode('AZ')]);
$pdo->exec("UPDATE service_issues SET kind = 'ban'");
$t('a hold of another kind isn\'t the connection', !Geocoder::connectionHeld($pdo));
$w = null; try { Geocoder::runOrHandOff($pdo); } catch (RuntimeException $x) { $w = $x; }
$t('…the batch waits on the site (no backup)', $w !== null && !($w instanceof GeocoderUnreachable), $w ? get_class($w) : 'no throw');
$pdo->exec("UPDATE service_issues SET kind = 'timeout'");
$t('a connection hold again: the waiting listing goes to the worker', Geocoder::connectionHeld($pdo) && (Geocoder::runOrHandOff($pdo)['backup']['ids'] ?? []) === [5]);
$t('the Census isn\'t paused by a person (its calls are business operations); were it, the backup would stand down', isset(Automation::NOT_PAUSABLE['census']) && str_contains((string) file_get_contents($app . '/lib/Geocoder.php'), "Automation::hostOff(\$pdo, \$host) !== null) return false"));

// ---- the endpoint reads the answers and hands the batch on
$src = (string) file_get_contents(dirname($app) . '/website/api/crawl/geocode.php');
$t('the endpoint: answers_b64 is placed by takeAnswers, the batch by runOrHandOff', str_contains($src, "Geocoder::takeAnswers(\$pdo, (array) (\$b['ids'] ?? []), \$raw)") && str_contains($src, 'Geocoder::runOrHandOff($pdo, Geocoder::BATCH)'));
$t('Find points by hand still uses the site\'s own call', method_exists('Geocoder', 'run'));
foreach (['entities', 'entity_metadata', 'zip_coordinates', 'service_issues'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
