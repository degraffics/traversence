<?php
// The Control Board's release 3a (decisions/0171 §5, §7): alarms raised at the event, one per problem (a repeat updates
// it), worse becomes a trip, cleared by itself saying how, acknowledged by a person; the ledger's server errors and wrong
// counts, a crawler run's failure, a service's issue. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3a.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms');
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1), ('second_look', 'W2', 1)");   // as live: each task with its workflow

$a = ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow', 'means', 'do', '900 ms');
$b = ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow', 'means', 'do', '950 ms');
$r = ControlAlarms::openOf($pdo, 'x:slow');
$t('a repeat updates the open alarm, never a second', $a === $b && (int) $r['n_times'] === 2 && $r['reading'] === '950 ms', $r);
ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'trip', 'crash', 'W1 stopped', '', '', '', [], true, false);   // worse: a crash (since 4e a slow alarm itself is never a trip)
$t('worse becomes a trip', ControlAlarms::openOf($pdo, 'x:slow')['level'] === 'trip');
ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'warning', 'slow', 'W1 is slow again', '', '', '');
$t('…and a lesser repeat never lowers it', ControlAlarms::openOf($pdo, 'x:slow')['level'] === 'trip');
$t('no email while the dispatcher has no alarm.trip kind (nothing sent, the alarm stands)', ControlAlarms::openOf($pdo, 'x:slow')['mailed_at'] === null);
$c = ControlAlarms::counts($pdo);
$t('the light counts it', $c['trip'] === 1 && $c['warning'] === 0, $c);
$t('acknowledged once', ControlAlarms::ack($pdo, $a, 7, 'Jason') && !ControlAlarms::ack($pdo, $a, 7, 'Jason'));
$t('a trip whose reading is good again closes itself as Recovered (decisions/0175 §3, replacing 3c\'s wait for Resolve)', ControlAlarms::clear($pdo, 'x:slow', 'Fast again at 10:00 UTC.') && ControlAlarms::openOf($pdo, 'x:slow') === null);
$w = ControlAlarms::raise($pdo, 'x:warn', 'wf:W1', 'warning', 'slow', 'W1 slow');
$t('a warning clears by itself, saying how', ControlAlarms::clear($pdo, 'x:warn', 'Fast again.') && ControlAlarms::openOf($pdo, 'x:warn') === null);
// a trip from before 3c's rule (no tripped_at) still clears as it did, and stays listed unseen
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at) VALUES ('x:crash', 'wf:W2', 'trip', 'crash', 'W2 crashed', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$k = (int) $pdo->lastInsertId(); ControlAlarms::clear($pdo, 'x:crash', 'It ran again.');
$l = ControlAlarms::list($pdo);
$t('a trip that cleared unseen stays listed (24 hours) until acknowledged', count(array_filter($l, fn ($x) => (int) $x['id'] === $k)) === 1 && ControlAlarms::counts($pdo)['unseen'] === 1);
ControlAlarms::ack($pdo, $k, 7, 'Jason');
$t('…acknowledged, it stays listed (the filter shows it under Acknowledged) and no longer counts as unseen', count(array_filter(ControlAlarms::list($pdo), fn ($x) => (int) $x['id'] === $k && $x['ack_at'] !== null)) === 1 && ControlAlarms::counts($pdo)['unseen'] === 0);
$t('Undo puts the acknowledgement back', ControlAlarms::unack($pdo, $k) && ControlAlarms::counts($pdo)['unseen'] === 1);
$n = ControlAlarms::raise($pdo, 'x:n', 'svc:osm', 'warning', 'retry', 'one'); ControlAlarms::raise($pdo, 'x:m', 'svc:osm', 'warning', 'retry', 'two');
$t('clearOthers keeps the ones still bad', ControlAlarms::clearOthers($pdo, 'x:', ['x:m'], 'gone') === 1 && ControlAlarms::openOf($pdo, 'x:m') !== null);

// the ledger: a server error trips the process the endpoint serves; the next good answer clears it
require $app . '/lib/ControlLedger.php';
$ref = new ReflectionMethod('ControlLedger', 'alarms'); $ref->setAccessible(true);
$ref->invoke(null, $pdo, 'crawl/results', 500, 3, ['stored' => 0, 'updated' => 0, 'review' => 0, 'refused' => 0], ['answered 500: boom']);
$o = ControlAlarms::openOf($pdo, 'ep:crawl/results:5xx');
$t('the ledger: a 500 from /api/crawl/results is a warning on its process while the system tries again (3c)', $o && $o['level'] === 'warning' && str_starts_with((string) $o['proc'], 'wf:') && str_contains((string) $o['reading'], 'boom') && str_contains((string) $o['means'], '1 of 3 tries'), $o);
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
$t('…W0 quiet an hour while switched on is a warning while the system tries (3c: a trip after 3 looks or an hour)', (ControlAlarms::openOf($pdo, 'silent:w0')['level'] ?? '') === 'warning', $said);
$pdo->exec("UPDATE crawl_runs SET status = 'ok' WHERE run_key = 'late'");
$pdo->exec("INSERT INTO $jt (job, started_at, finished_at, ok) VALUES ('sales_tick', NOW(), NOW(), 1)");
SearchIndex::setState($pdo, 'w0:beat', json_encode(['at' => time() - 30]));
$said = ControlAlarms::tick($pdo);
$t('…and each clears by itself when its reading is good', ControlAlarms::openOf($pdo, 'silent:run') === null && ControlAlarms::openOf($pdo, 'job:sales_tick') === null && ControlAlarms::openOf($pdo, 'silent:w0') === null, $said);

// the seed: what's already known, raised once (the queue isn't empty on day one)
$pdo->exec('DELETE FROM control_alarms'); require_once $app . '/lib/Preflight.php'; Preflight::results($pdo);
$pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, NOW())')->execute(['wf:W2', json_encode(['s' => 'bad', 'said' => 'What holds it: Its gate: 1 red line(s). Needs: Map points ✓.', 'at' => time(), 'fix' => [['tool', 'The gate', '/admin/config/board']], 'extra' => ['parts' => [['Its gate: 1 red line(s) in the switch-on check: "Google Places" (No key).', 'bad'], ['Needs: Map points ✓.', 'ok']]]])]);
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, reasons) VALUES (UTC_TIMESTAMP(), 'crawl/verify', 'GET', 500, 30, 'answered 500: Could not hand out listings to verify: PDOException: Unknown column')");
$rs = Database::t('reference_sources');
try { $pdo->exec("CREATE TABLE IF NOT EXISTS $rs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, host VARCHAR(190) NOT NULL, home_url VARCHAR(255) NOT NULL DEFAULT '', kind VARCHAR(20) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'read', last_read_at DATETIME NULL)"); } catch (Throwable $x) {}
$pdo->exec("INSERT INTO $rs (host, status) VALUES ('helsinki.fi', 'read'), ('cup.lmu.de', 'read'), ('azdot.gov', 'read'), ('navajo-nsn.gov', 'read')");
SearchIndex::setState($pdo, 'control:alarms_seeded', '');
$n = ControlAlarms::seed($pdo);
$w2 = ControlAlarms::openOf($pdo, 'pf:wf:W2'); $ep = ControlAlarms::openOf($pdo, 'ep:crawl/verify:5xx'); $off = ControlAlarms::openOf($pdo, 'w3:offregion');
$t('the seed: a red pre-flight line, named by the part that holds it, with what it means', $w2 && str_starts_with((string) $w2['title'], 'W2') && str_contains((string) $w2['title'], 'Its gate') && str_contains((string) $w2['means'], "won't start"), $w2);
$t('…W2\'s server error at /api/crawl/verify.php, in its own words', $ep && $ep['proc'] === 'wf:W2' && str_contains((string) $ep['reading'], 'Unknown column'), $ep);
// sources outside our region moved from the seed to the watch's hourly recount (control-board-offregion, 2026-11-19)
SearchIndex::setState($pdo, 'control:offregion_at', ''); try { ControlAlarms::tick($pdo); } catch (Throwable $x) {}
$off = ControlAlarms::openOf($pdo, 'w3:offregion');
$t('…sources outside our region (a country\'s address, never .gov), raised by the watch with the button that opens them', $off && str_contains((string) $off['reading'], 'helsinki.fi') && !str_contains((string) $off['reading'], 'azdot.gov') && str_contains((string) $off['fix'], 'show=offregion'), $off);
$t('…and only once', ControlAlarms::seed($pdo) === 0 && $n >= 2, $n);   // the fixture's two (W2's line, the server error); before 0175 a trip's stop also ran the pre-flight, adding more
// the dispatcher knows the kinds; the message in Pulse carries Acknowledge
require_once $app . '/lib/Notify.php';
$t('Notify::KINDS has alarm.trip (Critical, email can\'t be turned off) and alarm.warning (Urgent)', (Notify::KINDS['alarm.trip'][2] ?? '') === 'critical' && (Notify::KINDS['alarm.trip'][6] ?? true) === false && (Notify::KINDS['alarm.warning'][2] ?? '') === 'urgent');
$pb = ControlAlarms::pulseBody($pdo, ['thing' => 'alarm:' . (int) $w2['id']], 7);
$t('…its Pulse body: what happened, what to do, Acknowledge posting to the board\'s API', str_contains($pb['html'], 'data-msg-act="/admin/api/config/control"') && str_contains($pb['html'], '&quot;ack&quot;') && $pb['state'] === 'Open', $pb);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
