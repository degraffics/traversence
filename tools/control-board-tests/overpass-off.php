<?php
// overpass-off (Jason, 2026-11-19, "Stop entirely", after OpenStreetMap's forum answered our statement: "Stop using the
// public OSM overpass server altogether"): no Overpass anywhere. The site reads only our copy (Geofabrik's weekly files,
// open_places); a job's kinds our copy lacks are "not in our copy", never fetched; no server is handed out, probed or
// budgeted; the pre-flight sends nothing; the crawler's report of what's missing is one quiet notice. Also Geofabrik's
// pre-flight line follows a redirect on its own host (Jason's screenshot: "Not ready: Answered 308").
// Run: mariadb < ../release-b-tests/start-schema.sql && php overpass-off.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : '') . "\n"; } };
require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/OpenPlaces.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Thresholds.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);

// 1. the site asks nothing
$src = (string) file_get_contents($app . '/lib/OpenPlaces.php');
$t('OpenPlaces never asks Overpass: no request code left, its one gate returns nothing', OpenPlaces::overpass($pdo, '[out:json];node(1);out ids;', 60, 18) === null
    && !str_contains($src, 'curl_init') && !str_contains($src, 'function ask(') && !str_contains($src, 'function fetch('));
$t('…near() reads our copy only (no ask, no last-asked lookup)', !str_contains($src, 'self::ask(') && !str_contains($src, 'open_place_asks WHERE qkey'));
$pdo->exec("CREATE TABLE IF NOT EXISTS open_places (id INT AUTO_INCREMENT PRIMARY KEY, osm_ref VARCHAR(40) UNIQUE, kind VARCHAR(20), name VARCHAR(200), what VARCHAR(120) DEFAULT '', lat DOUBLE, lon DOUBLE, phone VARCHAR(60) DEFAULT '', website VARCHAR(255) DEFAULT '', hours VARCHAR(255) DEFAULT '', street VARCHAR(160) DEFAULT '', city VARCHAR(80) DEFAULT '', state VARCHAR(2) DEFAULT '', fetched_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS open_place_asks (qkey VARCHAR(80) PRIMARY KEY, found INT DEFAULT 0, asked_at DATETIME)");
$pdo->exec("DELETE FROM open_places"); $pdo->exec("DELETE FROM open_place_asks");
$pdo->exec("INSERT INTO open_places (osm_ref, kind, name, lat, lon, city, state) VALUES ('node/1', 'food', 'Kayenta Pub', 36.725, -110.255, 'Kayenta', 'AZ')");
$near = []; try { $near = OpenPlaces::near($pdo, 'food', 36.72, -110.25, 5); } catch (Throwable $x) { $near = ['error' => $x->getMessage()]; }
$t('…a nearby search shows what our copy holds, and records no ask', ($near[0]['label'] ?? '') === 'Kayenta Pub' && (int) $pdo->query('SELECT COUNT(*) FROM open_place_asks')->fetchColumn() === 0, $near);
$job = ['looking_for' => ['osm' => ['nwr["amenity"="restaurant"]']], 'place' => ['towns' => [['town' => 'Moab', 'state' => 'UT']], 'center' => ['lat' => 38.57, 'lon' => -109.55]]];
$fj = []; try { $fj = OpenPlaces::forJob($pdo, $job); } catch (Throwable $x) { $fj = ['error' => $x->getMessage()]; }
$t('a job in a town outside our copy (Utah): not in our copy, nothing to fetch', ($fj['ours'] ?? null) === [] && ($fj['not_in_copy'] ?? []) === ['nwr["amenity"="restaurant"]'] && str_contains((string) ($fj['ours_note'] ?? ''), 'Not in our copy'), $fj);

// 2. no server, no probe, no budget
$t('no Overpass server is handed out (the crawler gets an empty list)', ServiceHealth::servers($pdo, 'osm', 'overpass') === []);
$t('the daily budget is 0 for the site and the crawler, saying why', ServiceHealth::osmBudget($pdo, 'site')['queries'] === 0 && ServiceHealth::osmBudget($pdo, 'crawler')['queries'] === 0
    && str_contains((string) ServiceHealth::osmBudget($pdo, 'site')['retired'], 'community.openstreetmap.org/t/150369') && ServiceHealth::osmRun($pdo)['queries'] === 0);
$hs = (string) file_get_contents($app . '/lib/ServiceHealth.php');
$t('no Overpass status page is probed', !str_contains($hs, "'overpass-api.de' => ['https://overpass-api.de/api/status'") && str_contains($hs, 'return null;   // Overpass retired (OSM_RETIRED): its servers are never asked, not even their status pages'));
$g = Thresholds::GATES;
$t('the settings: each Overpass gate 0 and can\'t be raised, saying why', !array_filter(['osm_daily_crawler', 'osm_daily_site', 'osm_per_minute_crawler', 'osm_per_minute_site', 'osm_site_per_hour'],
    fn ($k) => !isset($g[$k]) || $g[$k][2] !== 0 || $g[$k][4] !== 0 || !str_contains((string) $g[$k][7], 'Retired 2026-11-19')));

// 3. the pre-flight sends nothing for Overpass; Geofabrik follows its own redirect
$pf = (string) file_get_contents($app . '/lib/Preflight.php');
$t('the pre-flight\'s OpenStreetMap line sends no request and says it\'s retired', str_contains($pf, "return ['na', ServiceHealth::OSM_RETIRED") && !str_contains($pf, "node(1);out ids;"));
$t('…W1\'s places need: our copy, then Overture (no Overpass)', str_contains($pf, "[['copy', 'osm', 'our OpenStreetMap copy (Geofabrik)'], ['svc', 'overture']]"));
$t('Geofabrik\'s line follows a redirect on its own host, 3 at most', str_contains($pf, "in_array((int) \$gr[0], [301, 302, 303, 307, 308], true)") && str_contains($pf, "!== 'download.geofabrik.de') break;"));
$ops = (string) file_get_contents($app . '/lib/Operations.php');
$t('the switch-on check: our copy loaded (no Overpass server needed)', str_contains($ops, "\$line('OpenStreetMap: our copy loaded', \$copy || \$other !== ''") && !str_contains($ops, 'or a server not held'));

// 4. the crawler's report: kinds not in our copy, one quiet notice on W1
require_once $app . '/lib/crawler/CrawlService.php';
$cs = (string) file_get_contents($app . '/lib/crawler/CrawlService.php');
$t('the run\'s "not in our copy" becomes one quiet notice on W1, with Geofabrik\'s card', str_contains($cs, "ControlAlarms::raise(\$pdo, 'osm:not_in_copy', 'wf:W1', 'notice'") && str_contains($cs, "'/admin/config/register#svc-geofabrik'"));

// 5. the register's card
require_once $app . '/lib/OutsideServices.php';
$osm = OutsideServices::COMPANIES['osm'] ?? (OutsideServices::SERVICES['osm'] ?? null);
$os = (string) file_get_contents($app . '/lib/OutsideServices.php');
$t('OpenStreetMap\'s card: Overpass retired, with the forum\'s answer', str_contains($os, "['Why Overpass is retired (the forum)', 'https://community.openstreetmap.org/t/150369']") && str_contains($os, "'Overpass: retired', 'No longer used, by the crawler or the site (2026-11-19)'"));
$ui = (string) file_get_contents($app . '/ui/outside-services.php');
$t('…its servers listed as retired, nothing to pause, resume or add', str_contains($ui, "if (!empty(\$sv['retired']))") && str_contains($ui, 'if ($retiredRole) continue;'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
