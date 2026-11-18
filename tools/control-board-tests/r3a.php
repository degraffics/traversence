<?php
// The Control Board's release 3a (decisions/0171 §5, §7): alarms raised at the event, one per problem (a repeat updates
// it), worse becomes a trip, cleared by itself saying how, acknowledged by a person; the ledger's server errors and wrong
// counts, a crawler run's failure, a service's issue. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3a.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms');

$a = ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow', 'means', 'do', '900 ms');
$b = ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow', 'means', 'do', '950 ms');
$r = ControlAlarms::openOf($pdo, 'x:slow');
$t('a repeat updates the open alarm, never a second', $a === $b && (int) $r['n_times'] === 2 && $r['reading'] === '950 ms', $r);
ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'trip', 'slow', 'W1 stopped', '', '', '');
$t('worse becomes a trip', ControlAlarms::openOf($pdo, 'x:slow')['level'] === 'trip');
ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow again', '', '', '');
$t('…and a lesser repeat never lowers it', ControlAlarms::openOf($pdo, 'x:slow')['level'] === 'trip');
$t('no email while the dispatcher has no alarm.trip kind (nothing sent, the alarm stands)', ControlAlarms::openOf($pdo, 'x:slow')['mailed_at'] === null);
$c = ControlAlarms::counts($pdo);
$t('the light counts it', $c['trip'] === 1 && $c['warning'] === 0, $c);
$t('acknowledged once', ControlAlarms::ack($pdo, $a, 7, 'Jason') && !ControlAlarms::ack($pdo, $a, 7, 'Jason'));
$t('it clears by itself, saying how', ControlAlarms::clear($pdo, 'x:slow', 'Fast again at 10:00 UTC.') && ControlAlarms::openOf($pdo, 'x:slow') === null);
$k = ControlAlarms::raise($pdo, 'x:crash', 'wf:W2', 'trip', 'crash', 'W2 crashed'); ControlAlarms::clear($pdo, 'x:crash', 'It ran again.');
$l = ControlAlarms::list($pdo);
$t('a trip that cleared unseen stays listed (24 hours) until acknowledged', count(array_filter($l, fn ($x) => (int) $x['id'] === $k)) === 1 && ControlAlarms::counts($pdo)['unseen'] === 1);
ControlAlarms::ack($pdo, $k, 7, 'Jason');
$t('…acknowledged, it leaves the list', !array_filter(ControlAlarms::list($pdo), fn ($x) => (int) $x['id'] === $k));
$n = ControlAlarms::raise($pdo, 'x:n', 'svc:osm', 'warning', 'retry', 'one'); ControlAlarms::raise($pdo, 'x:m', 'svc:osm', 'warning', 'retry', 'two');
$t('clearOthers keeps the ones still bad', ControlAlarms::clearOthers($pdo, 'x:', ['x:m'], 'gone') === 1 && ControlAlarms::openOf($pdo, 'x:m') !== null);

// the ledger: a server error trips the process the endpoint serves; the next good answer clears it
require $app . '/lib/ControlLedger.php';
$ref = new ReflectionMethod('ControlLedger', 'alarms'); $ref->setAccessible(true);
$ref->invoke(null, $pdo, 'crawl/results', 500, 3, ['stored' => 0, 'updated' => 0, 'review' => 0, 'refused' => 0], ['answered 500: boom']);
$o = ControlAlarms::openOf($pdo, 'ep:crawl/results:5xx');
$t('the ledger: a 500 from /api/crawl/results is a trip on its process', $o && $o['level'] === 'trip' && str_starts_with((string) $o['proc'], 'wf:') && str_contains((string) $o['reading'], 'boom'), $o);
$ref->invoke(null, $pdo, 'crawl/results', 200, 3, ['stored' => 2, 'updated' => 0, 'review' => 1, 'refused' => 1], []);
$t('…a good answer clears it; more outcomes than received is a wrong count', ControlAlarms::openOf($pdo, 'ep:crawl/results:5xx') === null && ControlAlarms::openOf($pdo, 'ep:crawl/results:count')['level'] === 'warning');
$ref->invoke(null, $pdo, 'crawl/results', 200, 3, ['stored' => 2, 'updated' => 0, 'review' => 1, 'refused' => 0], []);
$t('…and counts that add up clear that', ControlAlarms::openOf($pdo, 'ep:crawl/results:count') === null);

// the watch (control_tick): a run that never reported, a job that stopped, W0 quiet while on
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/SearchIndex.php';
$pdo->exec("INSERT INTO crawl_runs (run_key, started_at, status) VALUES ('late', NOW() - INTERVAL 50 MINUTE, 'running')");
$jt = Database::t('job_runs');
$pdo->exec("CREATE TABLE IF NOT EXISTS $jt (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, ok TINYINT(1) DEFAULT NULL, note VARCHAR(255) NOT NULL DEFAULT '', KEY idx_job_runs (job, id))");
$pdo->exec("INSERT INTO $jt (job, started_at, finished_at, ok, note) VALUES ('sales_tick', NOW() - INTERVAL 20 MINUTE, NOW() - INTERVAL 19 MINUTE, 0, 'Table missing: leads')");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::forget();
SearchIndex::setState($pdo, 'w0:beat', json_encode(['at' => time() - 3600]));
$said = ControlAlarms::tick($pdo);
$t('the watch: a run past its time plus 5 is a trip on the crawler', (ControlAlarms::openOf($pdo, 'silent:run')['proc'] ?? '') === 'wk:crawler', $said);
$t('…a job whose last run stopped is a trip, its note the reading', (ControlAlarms::openOf($pdo, 'job:sales_tick')['reading'] ?? '') === 'Table missing: leads', $said);
$t('…W0 quiet an hour while switched on is a trip', (ControlAlarms::openOf($pdo, 'silent:w0')['level'] ?? '') === 'trip', $said);
$pdo->exec("UPDATE crawl_runs SET status = 'ok' WHERE run_key = 'late'");
$pdo->exec("INSERT INTO $jt (job, started_at, finished_at, ok) VALUES ('sales_tick', NOW(), NOW(), 1)");
SearchIndex::setState($pdo, 'w0:beat', json_encode(['at' => time() - 30]));
$said = ControlAlarms::tick($pdo);
$t('…and each clears by itself when its reading is good', ControlAlarms::openOf($pdo, 'silent:run') === null && ControlAlarms::openOf($pdo, 'job:sales_tick') === null && ControlAlarms::openOf($pdo, 'silent:w0') === null, $said);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
