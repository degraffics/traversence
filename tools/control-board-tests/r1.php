<?php
// The System Control Board's Release 1 tests (decisions/0171 §15.1): the unit register, the pre-flight's Routes lines,
// Start re-testing what each workflow depends on, and the four query timings once a Start.
// Run: mariadb < ../release-b-tests/start-schema.sql && php r1.php <root>/app   (a root holding app/ and website/api/,
// the mirror with the release laid over it; .env four folders up from lib/, as the other tests).
declare(strict_types=1);
$app = $argv[1];
final class Access { public static $ok = true; public static function can($p, $o = []) { return [self::$ok, 'Executives only']; } public static function staff() { return ['name' => 'Jason']; } }
require_once $app . '/lib/Database.php'; require $app . '/lib/SystemStart.php'; require $app . '/lib/Preflight.php'; require $app . '/lib/ControlRegister.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$root = dirname($app);

// the register
$p = ControlRegister::processes($pdo);
$sys = array_count_values(array_column($p, 0));
$t('four systems, each with processes', count($sys) === 4 && min($sys) > 0, $sys);
$t('every workflow W0–W14 is a process of the Knowledge Engine', count(array_filter(array_keys($p), fn ($k) => str_starts_with($k, 'wf:'))) === count(Automation::WORKFLOWS));
$t('the three workers', isset($p['wk:w0-indexer'], $p['wk:crawler'], $p['wk:video-worker']));
$t('a site job under a workflow isn\'t listed twice', !isset($p['job:listings_tick']) && isset($p['job:billing_tick']));
$w1 = ControlRegister::of($pdo, 'W1');
$t('W1: its job, its needs, its services', in_array('listings_tick', $w1['jobs'], true) && in_array('places', $w1['needs'], true) && in_array('overture', $w1['services'], true) && !in_array('osm', $w1['services'], true), $w1);   // Overpass retired 2026-11-19: W1 doesn't read OpenStreetMap's servers
$t('W1 reads our copies (open_places, npi_orgs)', in_array('open_places', $w1['reads'], true) && in_array('npi_orgs', $w1['reads'], true));
$w0 = ControlRegister::of($pdo, 'W0');
$t('W0: carried by W0 Indexer, through /api/crawl/index', $w0['workers'] === ['w0-indexer'] && $w0['endpoints'] === ['crawl/index'], $w0);
// a crawler task brings in its endpoint and the crawler
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1)");
ControlRegister::forget();
$w1 = ControlRegister::of($pdo, 'W1');
$t('a crawler task of W1 brings its endpoints and the crawler', in_array('crawl/jobs', $w1['endpoints'], true) && in_array('crawl/results', $w1['endpoints'], true) && in_array('crawl/service', $w1['endpoints'], true) && in_array('crawler', $w1['workers'], true), $w1);
$t('…and the tables they write', in_array('crawl_jobs', $w1['writes'], true) && in_array('crawler_raw_index', $w1['writes'], true));
$d = ControlRegister::dependsOn($pdo, 'wf:W0');
$t('W0 depends on its worker\'s line, then its need', $d === ['rw:beat', 'nd:pages'], $d);
$d = ControlRegister::dependsOn($pdo, 'wf:W1');
$firstNd = min(array_keys(array_filter($d, fn ($x) => str_starts_with($x, 'nd:'))));
$lastSvc = max(array_keys(array_filter($d, fn ($x) => !str_starts_with($x, 'nd:'))));
$t('W1: its services first, then its needs', $lastSvc < $firstNd && in_array('svc:overture', $d, true) && !in_array('svc:osm', $d, true) && in_array('nd:places', $d, true), $d);
$t('a task depends on its services', ControlRegister::dependsOn($pdo, 't:overture') === ['svc:overture']);

// the pre-flight's Routes lines
$L = Preflight::lines($pdo);
$t('a Routes group, after the tasks', isset(Preflight::GROUPS['routes']) && array_search('routes', array_keys(Preflight::GROUPS)) === array_search('tasks', array_keys(Preflight::GROUPS)) + 1);
$t('a route line per workflow in Start\'s order and W14, per worker, and the claimed line', isset($L['rt:wf:W0'], $L['rt:wf:W12'], $L['rt:wf:W14'], $L['rt:wk:crawler'], $L['rt:claimed']) && !isset($L['rt:wf:W13']));
$t('route lines aren\'t needed for launch (they hold nothing yet)', !array_filter($L, fn ($l, $k) => str_starts_with($k, 'rt:') && $l[2], ARRAY_FILTER_USE_BOTH));
$r = Preflight::run($pdo, 'rt:claimed', 'test');
$t('every endpoint on the server is claimed', $r['s'] === 'ok', $r['said']);
file_put_contents($root . '/website/api/crawl/zzz.php', '<?php');
$r = Preflight::run($pdo, 'rt:claimed', 'test');
@unlink($root . '/website/api/crawl/zzz.php');
$t('a new endpoint no process names turns it red, named', $r['s'] === 'bad' && str_contains($r['said'], '/api/crawl/zzz.php'), $r['said']);
$r = Preflight::run($pdo, 'rt:wf:W1', 'test');
$t('W1\'s route: red, naming the tables that aren\'t there', $r['s'] === 'bad' && str_contains($r['said'], 'the table crawl_jobs') && str_contains($r['said'], 'Endpoints: /api/crawl/jobs.php ✓'), $r['said']);
$t('…with Database updates as its fix', ($r['fix'][0][1] ?? '') === 'Database updates');
foreach (['crawl_jobs', 'crawler_raw_index', 'crawler_sources', 'crawl_run_items', 'crawl_service_calls', 'crawl_costs', 'crawl_requests', 'open_places', 'npi_orgs', 'irs_orgs', 'rec_places', 'landmarks', 'entities', 'zip_coordinates'] as $tb) $pdo->exec("CREATE TABLE IF NOT EXISTS $tb (id INT)");
$r = Preflight::run($pdo, 'rt:wf:W1', 'test');
$t('W1\'s route with its tables: every part there, waiting for the crawler\'s check-in', $r['s'] === 'wait' && str_contains($r['said'], 'the crawler (no check-in read yet)'), $r['said']);
$r = ControlRegister::route($pdo, 'rt:wf:W13');
$t('W13 (people\'s own) has no route: not needed, said so', $r[0] === 'na' && str_contains($r[1], 'People write stories'), $r);
$r = Preflight::run($pdo, 'rt:wk:video-worker', 'test');
$t('the video worker\'s route: its two endpoints there, its table missing', $r['s'] === 'bad' && str_contains($r['said'], '/api/video/jobs.php ✓') && str_contains($r['said'], 'video_jobs ✗'), $r['said']);

// Start: what a workflow depends on, tested fresh first
$pdo->exec("DELETE FROM preflight_results");
$pdo->exec("INSERT INTO preflight_results (id, v, at) VALUES ('rw:beat', '{\"s\":\"ok\",\"said\":\"old\",\"at\":" . (time() - 7200) . "}', UTC_TIMESTAMP())");
$ok = Preflight::refreshFor($pdo, 'wf:W0', time() + 30, 'test');
$beat = Preflight::result($pdo, 'rw:beat'); $nd = Preflight::result($pdo, 'nd:pages');
$t('a worker line two hours old is tested again', $ok && $beat['said'] !== 'old' && $beat['by'] === 'test', $beat);
$t('its need is tested after it', $nd !== null && (int) $nd['at'] >= (int) $beat['at']);
$pdo->exec("DELETE FROM preflight_results");
$pdo->exec("INSERT INTO preflight_results (id, v, at) VALUES ('rw:beat', '{\"s\":\"ok\",\"said\":\"fresh\",\"at\":" . (time() - 60) . "}', UTC_TIMESTAMP())");
// a fresh request: the static memo is per request, so a new process is simulated by reflection
$rp = new ReflectionProperty('Preflight', 'fresh'); $rp->setAccessible(true); $rp->setValue(null, []);
Preflight::refreshFor($pdo, 'wf:W0', time() + 30, 'test');
$t('a worker line a minute old is kept', Preflight::result($pdo, 'rw:beat')['said'] === 'fresh');
$t('the need is tested always', Preflight::result($pdo, 'nd:pages') !== null);
$rp->setValue(null, []);
$pdo->exec("DELETE FROM preflight_results");
$t('out of time: it says so, nothing run', Preflight::refreshFor($pdo, 'wf:W0', time() - 1, 'test') === false && Preflight::result($pdo, 'rw:beat') === null);

// Start: the confirm step runs it, and the four timings once
$rp->setValue(null, []);
$pdo->exec("DELETE FROM preflight_results");
$st = SystemStart::start($pdo, 7, 'Jason');
SystemStart::$budget = 120;
for ($i = 0; $i < 4 && ($st['items']['verify']['s'] ?? '') !== 'done'; $i++) $st = SystemStart::tick($pdo);
$t('the confirm step tested W1\'s needs before W1', Preflight::result($pdo, 'nd:places') !== null && Preflight::result($pdo, 'wf:W1') !== null);
$t('the four timings ran once, kept with the session', count($st['timings']['lines'] ?? []) === 4, array_keys($st['timings']['lines'] ?? []));
$at = (int) ($st['timings']['at'] ?? 0); $st = SystemStart::tick($pdo);
$t('…not again at the next look', (int) ($st['timings']['at'] ?? 0) === $at);
$t('the confirm step says how the timings went', str_contains((string) $st['items']['verify']['why'], 'query timings:'), $st['items']['verify']['why']);
$failedAsk = array_filter($st['asks'] ?? [], fn ($a) => str_contains((string) $a[0], 'the query failed'));
$failed = array_filter($st['timings']['lines'], fn ($x) => !empty($x['failed']));
$t('a failed query is a step on the card, holding nothing', count($failedAsk) === count($failed) && !array_filter($failedAsk, fn ($a) => in_array('master', (array) $a[4], true)), [count($failedAsk), count($failed)]);
$src = file_get_contents($app . '/lib/SystemStart.php');
$t('the header no longer describes one-at-a-time pacing', !str_contains($src, 'each starting only when the one') && str_contains($src, 'without waiting on runs'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
