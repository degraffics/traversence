<?php
// The System Control Board's release 2b tests (decisions/0171 §1, §2): the board's stage, a process's readings from the
// ledger, the interlock on Start it, Shut it down (tasks first, then the workflow), and the whole system shut down in
// order, ending "stopped, safe" once no crawler run is going.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < 2026-11-18_control_ledger.sql (from release 2a)
//      && php r2b.php <root>/app
declare(strict_types=1);
$app = $argv[1];
final class Access { public static function can($p, $o = []) { return [true, '']; } public static function staff() { return ['name' => 'Jason']; } }
require_once $app . '/lib/Database.php'; require $app . '/lib/ControlBoard.php'; require $app . '/lib/SystemStart.php'; require $app . '/lib/Preflight.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };

// the stage
$t('the master off: the board says stopped', ControlBoard::stage($pdo)[0] === 'shutdown', ControlBoard::stage($pdo));
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::forget();
$t('the master on: observation', ControlBoard::stage($pdo)[0] === 'observe', ControlBoard::stage($pdo));

// a process's readings from the ledger
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1)");
ControlRegister::forget();
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received, n_stored, n_review, n_refused) VALUES (UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 120, 5, 1, 3, 1), (UTC_TIMESTAMP(), 'crawl/results', 'POST', 500, 80, 2, 0, 0, 0)");
$lid = (int) $pdo->query('SELECT MIN(id) FROM control_ledger')->fetchColumn();
$pdo->exec("INSERT INTO control_records (ledger_id, at, kind, ref_id, what) VALUES ($lid, UTC_TIMESTAMP(), 'listing', 501, 'stored'), ($lid, UTC_TIMESTAMP(), 'review', 77, 'review')");
$p = ControlBoard::process($pdo, 'wf:W1', ['engine', 'W1 Listings'], []);
$t('W1: its worker calls in the last day, counted', $p['calls']['calls'] === 2 && $p['calls']['received'] === 7 && $p['calls']['review'] === 3 && $p['calls']['errors'] === 1, $p['calls']);
$t('…its latest call failed: red, and its words start with why', $p['light'] === 'bad' && str_starts_with($p['words'], 'The site\'s /api/crawl/results answers a server error'), [$p['light'], $p['words']]);
$t('…its switch is on', $p['switch'] === 'on');
$r = ControlBoard::records($pdo, ['crawl/results']);
$t('the records it touched, newest first', count($r) === 2 && $r[0][0] === 'review' && (int) $r[1][1] === 501, $r);

// Shut it down / Start it
$m = ControlBoard::stopOne($pdo, 'wf:W1', 7, 'Jason'); Automation::forget();
$t('Shut it down: W1 paused, its crawler task off first', Automation::wfOff($pdo, 'W1') !== null && (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'coverage'")->fetchColumn() === 0 && str_contains($m, 'tasks first'), $m);
$p = ControlBoard::process($pdo, 'wf:W1', ['engine', 'W1 Listings'], []);
$t('…the board shows it off', $p['light'] === 'off' && $p['switch'] === 'off');
$no = '';
try { ControlBoard::startOne($pdo, 'wf:W1', 7, 'Jason'); } catch (RuntimeException $x) { $no = $x->getMessage(); }
Automation::forget();
$t('Start it: refused while its check is red (the interlock), saying why', str_starts_with($no, 'Not started: its check is red.') && Automation::wfOff($pdo, 'W1') !== null, $no);

// the whole system, in order
$pdo->exec("INSERT INTO crawl_runs (run_key, started_at, status) VALUES ('going', NOW(), 'running')");
$m = ControlBoard::shutDown($pdo, 7, 'Jason'); Automation::forget();
$log = $pdo->query("SELECT ref FROM automation_log WHERE why LIKE 'Shut down in order%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$wfs = array_values(array_filter($log, fn ($x) => preg_match('/^W\d+$/', $x)));
$nums = array_map(fn ($x) => (int) substr($x, 1), $wfs); $desc = $nums; rsort($desc);
$t('Shut down in order: the workflows in reverse start-up order (those on; W1 and W3 were off already)', count($wfs) >= 9 && $nums === $desc && !in_array('W1', $wfs, true), $wfs);
$t('…then the master switch off', Automation::master($pdo) !== null);
$t('…stopping while a crawler run is still going', ControlBoard::stage($pdo)[0] === 'shutdown' && ControlBoard::settle($pdo)['state'] === 'stopping', ControlBoard::settle($pdo));
$pdo->exec("UPDATE crawl_runs SET status = 'ok' WHERE run_key = 'going'");
$sd = ControlBoard::settle($pdo);
$t('…then "stopped, safe", saying what it left', $sd['state'] === 'safe' && str_contains((string) $sd['left'], 'nothing half-written') && str_contains(ControlBoard::stage($pdo)[1], 'Stopped, safe'), $sd);
ControlBoard::cleared($pdo);
$t('Start the system clears the shut-down\'s record', ControlBoard::shutdownState($pdo) === []);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
