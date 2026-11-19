<?php
// The Control Board's release 5a (decisions/0171 §10, with the night's folded fixes): the logbook (control_log and what's
// already kept, merged newest first, filtered by system or process; Log an entry; who is staff or the system, never a
// member), phase lines raising no alarm of their own, speed judged per record, and the unused check's short names.
// (#19's fix, a passing check closing its back-off issue and Check it now, is r4f.)
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r5a.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlBoard.php';
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ControlLedger.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
foreach (['control_alarms', 'control_log'] as $tb) $pdo->exec("DROP TABLE IF EXISTS $tb");
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1)");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::forget();

// 1. what writes to the log
ControlAlarms::raise($pdo, 'x:crash', 'wf:W1', 'warning', 'crash', 'W1 failed a run');
$a = ControlAlarms::openOf($pdo, 'x:crash');
ControlAlarms::ack($pdo, (int) $a['id'], 7, 'Jason');
ControlAlarms::clear($pdo, 'x:crash', 'It ran well at 10:00 UTC.');
ControlBoard::stopOne($pdo, 'wf:W1', 7, 'Jason');
$log = ControlLog::feed($pdo, 'wf:W1');
$kinds = array_column($log, 2);
$t('an alarm raised, acknowledged and cleared, and a shut-down, are in W1\'s log', in_array('alarm', $kinds, true) && in_array('ack', $kinds, true) && in_array('clear', $kinds, true) && in_array('stop', $kinds, true), $kinds);
$ack = array_values(array_filter($log, fn ($e) => $e[2] === 'ack'))[0];
$t('…with who did it (staff), and the system for what it did itself', $ack[5] === 'Jason' && array_values(array_filter($log, fn ($e) => $e[2] === 'alarm'))[0][5] === 'the system', $ack);
$t('…the switch Automation logged is there too, as W1\'s', in_array('switch', $kinds, true));
$t('newest first', array_column($log, 0) === (function ($x) { rsort($x); return $x; })(array_column($log, 0)));

// 2. Log an entry: words needed, staff only, its zone kept
$m = ''; try { ControlLog::note($pdo, 'wf:W1', 'x', 'Jason', 'America/Phoenix'); } catch (RuntimeException $x) { $m = $x->getMessage(); }
$t('Log an entry with nothing in it: refused, saying what to write', str_contains($m, 'Write the entry first'));
$m = ''; try { ControlLog::note($pdo, 'wf:W1', 'Restarted after the Bluehost outage', '', 'America/Phoenix'); } catch (RuntimeException $x) { $m = $x->getMessage(); }
$t('…and only from a staff member', str_contains($m, 'staff member'));
ControlLog::note($pdo, 'wf:W1', 'Restarted after the Bluehost outage', 'Jason', 'America/Phoenix');
$n = $pdo->query("SELECT * FROM control_log WHERE kind = 'note'")->fetch(PDO::FETCH_ASSOC);
$t('…logged as a note, by its author, in the zone where it was written', $n && $n['who'] === 'Jason' && $n['tz'] === 'America/Phoenix' && $n['proc'] === 'wf:W1', $n);
ControlLog::add($pdo, 'note', '', 'x', 'Jason', '', 'Robert\'); DROP TABLE x; --');
$t('…a zone that isn\'t one is dropped', (string) $pdo->query("SELECT tz FROM control_log ORDER BY id DESC LIMIT 1")->fetchColumn() === '');

// 3. filters: one system, one process, the whole system; Show older
$pdo->exec("INSERT INTO control_log (at, kind, proc, what, who) VALUES (UTC_TIMESTAMP() - INTERVAL 1 HOUR, 'control', 'svc:tavily', 'Run its check', 'Jason')");
$t('the Outside services system shows Tavily\'s line, not W1\'s', ($f = ControlLog::feed($pdo, 'sys:services')) && !array_filter($f, fn ($e) => $e[3] === 'wf:W1') && array_filter($f, fn ($e) => $e[3] === 'svc:tavily'));
$all = ControlLog::feed($pdo); $older = ControlLog::feed($pdo, '', $all[1][0]);
$t('Show older carries on from a moment', $older && max(array_column($older, 0)) < $all[1][0]);
require_once $app . '/admin/widgets.php'; require_once $app . '/admin/control-console.php';
$rows = tv_cb_log_rows($pdo);
$t('each row: its time labelled in the zone where it happened, what, who', str_contains($rows, 'data-tv-time data-tz="America/Phoenix"') && str_contains($rows, 'Restarted after the Bluehost outage') && str_contains($rows, 'class="cb-lg-who">Jason'));

// 4. never a member's name: who is staff or the system
$src = file_get_contents($app . '/lib/ControlLog.php') . file_get_contents($app . '/lib/ControlAlarms.php');
$t('the log is written only with a staff name or "the system"', !preg_match('/ControlLog::add\([^;]*(display_name|user_name|member)/', $src));

// 5. folded fixes
$lines = Preflight::lines($pdo);
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at) VALUES ('pf:ph:2', 'ph:2', 'trip', 'check', 'Phase 2: its workflows W1 ✗', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
Preflight::alarm($pdo, 'ph:2', $lines['ph:2'] ?? [null, 'Phase 2', true], ['s' => 'bad', 'said' => 'Its workflows: W1 ✗.', 'fix' => [], 'at' => time()]);
$t('a phase raises no alarm of its own; one open from before closes, saying why', ControlAlarms::openOf($pdo, 'pf:ph:2') === null && str_contains((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'pf:ph:2'")->fetchColumn(), 'only adds up its workflows'));
for ($i = 0; $i < 25; $i++) $pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received) VALUES (UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 200, 2)");
$ref = new ReflectionMethod('ControlLedger', 'alarms'); $ref->setAccessible(true);
$ref->invoke(null, $pdo, 'crawl/results', 200, 30, ['stored' => 30, 'updated' => 0, 'review' => 0, 'refused' => 0], [], 3300);
$t('a big batch (30 records in 3.3 s, 110 ms each against 100) isn\'t slow', ControlAlarms::openOf($pdo, 'ep:crawl/results:slow') === null);
$ref->invoke(null, $pdo, 'crawl/results', 200, 2, ['stored' => 2, 'updated' => 0, 'review' => 0, 'refused' => 0], [], 3300);
$sl = ControlAlarms::openOf($pdo, 'ep:crawl/results:slow');
$t('…2 records in 3.3 s (1,650 ms each) is: a warning in ms a record', $sl && $sl['level'] === 'warning' && str_contains((string) $sl['means'], 'ms a record'), $sl['means'] ?? null);
$su = file_get_contents($app . '/lib/SchemaUpkeep.php');
$t('the unused check no longer lists one- and two-letter columns of tables the code uses', str_contains($su, "strlen((string) \$col) > 2 && !\$used((string) \$col)"));
require_once $app . '/lib/staging-tables.php';
$t('control_log is each site\'s own on staging', in_array('control_log', StagingTables::OWN, true));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
