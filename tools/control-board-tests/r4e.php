<?php
// The Control Board's 4e (Jason, 2026-11-19: "no dead ends, system fixes first"): a slow call or job is a warning that
// says how slow, stops nothing and clears itself; a slow trip from before is lifted by the system (checked first, tried
// again every watch while a check is red); and an admin's Resolve is "Check and start again", with no question to answer.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r4e.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLedger.php'; require_once $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1)");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::forget();

// 1. slow is never a trip, wherever it's raised
for ($i = 0; $i < 25; $i++) $pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms) VALUES (UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 200)");
$ref = new ReflectionMethod('ControlLedger', 'alarms'); $ref->setAccessible(true);
foreach ([1, 2, 3, 4, 5] as $_) $ref->invoke(null, $pdo, 'crawl/results', 200, 1, ['stored' => 1, 'updated' => 0, 'review' => 0, 'refused' => 0], [], 3300);
$sl = ControlAlarms::openOf($pdo, 'ep:crawl/results:slow');
Automation::forget();
$t('the results endpoint slow five times in a row: a warning, nothing stopped, W1 still on', $sl['level'] === 'warning' && empty($sl['tripped_at']) && Automation::wfOff($pdo, 'W1') === null && str_contains((string) $sl['means'], 'Nothing is stopped'), $sl);
ControlAlarms::raise($pdo, 'x:slow', 'wf:W1', 'trip', 'slow', 'Asked for as a trip');
$t('…a slow alarm asked for as a trip by any caller is a warning', ControlAlarms::openOf($pdo, 'x:slow')['level'] === 'warning');
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('…a slow site job is a warning however many times in a row', str_contains($src, "self::raise(\$pdo, 'jobslow:' . \$job, 'job:' . \$job, 'warning', 'slow',"));

// 2. a slow trip from before 4e: the watch lifts it itself, checking first
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, tripped_at, stopped) VALUES ('ep:crawl/old:slow', 'wf:W2', 'trip', 'slow', '/api/crawl/old is slow', 3, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), '[[\"proc\",\"wf:W2\",\"W2\"]]')");
Automation::setWorkflow($pdo, 'W2', true, 'Shut down by a slow trip', 7, 'Jason'); Automation::forget();
$id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'ep:crawl/old:slow'")->fetchColumn();
ControlAlarms::tick($pdo);
$a = ControlAlarms::byId($pdo, $id); Automation::forget();
if ($a['cleared_at'] !== null) $t('the watch lifted the old slow trip: W2 checked and started again by the system', Automation::wfOff($pdo, 'W2') === null && str_contains((string) $a['cleared_how'], 'Resolved by the system'), $a['cleared_how']);
else $t('W2\'s check is red here: the old slow trip is now a warning that says the system tries every 5 minutes, W2 still off', $a['level'] === 'warning' && str_contains((string) $a['means'], 'the system tries every 5 minutes') && Automation::wfOff($pdo, 'W2') !== null, $a);
$t('…the watch tries it every time while it still holds what it stopped', str_contains($src, "kind = 'slow' AND (level = 'trip' OR (stopped IS NOT NULL"));

// 3. Resolve: no question; it checks and starts again
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, tripped_at, stopped) VALUES ('x:crash', 'wk:crawler', 'trip', 'crash', 'The crawler crashed', 3, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), '[]')");
$cid = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'x:crash'")->fetchColumn();
[$ok, $msg] = ControlAlarms::resolve($pdo, $cid, 7, 'Jason', '');
$t('Resolve with nothing typed: done, logged as "Checked and started again"', $ok && str_contains($msg, 'Checked and started again'), $msg);
$cs = file_get_contents($app . '/admin/control-console.php');
$t('the board\'s button is "Check and start again", with no question', str_contains($cs, "\$btn('resolve', 'Check and start again'") && !str_contains($cs, "'data-cb-ask' => 'What fixed it?'"));
$t('Pulse\'s step is a button too, not a form', str_contains($src, "['do', 'alarm_resolve', 'Check and start again']") && !str_contains($src, "['note', 'What fixed it?'"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
