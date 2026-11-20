<?php
// The system's tries applied when their time is up (0171's note of 2026-11-20, Jason: a nightly task "keeps the hour"):
// W1's alarm read "1 of 3 tries, until 09:25 UTC" hours after 09:25, because a warning became a trip only when the same
// failure came again. The watch now trips a warning still trying past control_fix_minutes, and escalates a critical part's
// early trip the same way. Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration>
//   && php expire-tries.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS system_pauses');
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('overture_bulk', 'W1', 1)");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::forget();

// a nightly task's one failure: a warning while the system tries
$id = ControlAlarms::raise($pdo, 'task:failed:overture_bulk', 'wf:W1', 'trip', 'crash', 'W1: its task overture_bulk failed (run #1005)', 'Its work goes back.', 'Read its why.', "TimeoutError('The read operation timed out')");
$a = ControlAlarms::byId($pdo, $id);
$t('one failure: a warning, the system trying (1 of 3)', $a['level'] === 'warning' && str_contains((string) $a['means'], '1 of 3 tries, until '), $a['means']);
ControlAlarms::expireTries($pdo);
$t('inside its hour, the watch leaves it a warning', ControlAlarms::byId($pdo, $id)['level'] === 'warning');

// an hour on, with no second failure (the next night's run hasn't come)
$pdo->exec("UPDATE control_alarms SET first_at = UTC_TIMESTAMP() - INTERVAL 61 MINUTE, last_at = first_at WHERE id = $id");
$did = ControlAlarms::expireTries($pdo); Automation::forget();
$a = ControlAlarms::byId($pdo, $id);
$t('past its hour, the watch makes it a trip (0171 3c as set)', $a['level'] === 'trip' && $a['tripped_at'] !== null, [$did, $a['level']]);
$t('…its words say the tries ran out, never "until" a time gone by', str_contains((string) $a['means'], 'Its tries ran out at ') && !str_contains((string) $a['means'], 'trying again by itself'), $a['means']);
$t('…and the trip places its pause (decisions/0175): W1 paused by the system, its switch untouched', Automation::gathering($pdo, '', 'W1') !== null && Automation::wfOff($pdo, 'W1') === null);
$t('…logged', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Trip: W1: its task overture_bulk failed%'")->fetchColumn() === 1);
$t('run again: nothing more to do', ControlAlarms::expireTries($pdo) === []);

// a critical part's early trip, still trying past its hour: the Escalation Notice
require_once $app . '/lib/ControlRegister.php';
$cid = ControlAlarms::raise($pdo, 'x:gh', 'svc:github', 'trip', 'crash', 'GitHub failed');
$c = ControlAlarms::byId($pdo, $cid);
$t('a critical part: a trip at once, still trying', $c['level'] === 'trip' && $c['retrying_at'] !== null, $c);
$pdo->exec("UPDATE control_alarms SET first_at = UTC_TIMESTAMP() - INTERVAL 61 MINUTE WHERE id = $cid");
ControlAlarms::expireTries($pdo);
$c = ControlAlarms::byId($pdo, $cid);
$t('…past its hour with no fix: escalated (the Escalation Notice), no longer retrying', $c['retrying_at'] === null && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Escalation Notice: retries unsuccessful%'")->fetchColumn() === 1, $c);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
