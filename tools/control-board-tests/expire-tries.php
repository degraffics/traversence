<?php
// The system's tries applied when their time is up (0171's note of 2026-11-20, Jason: a nightly task "keeps the hour"):
// W1's alarm read "1 of 3 tries, until 09:25 UTC" hours after 09:25, because a warning became a trip only when the same
// failure came again. Since the control board's build (step 4) the tries are the one engine's own checks of what failed,
// at about 5, 15 and 35 minutes (the state model's P3, 02 §0a ruling 2), inside the hour: the watch checks when a try is
// due, and a warning still failing past the hour is a trip; a critical part's early trip is escalated the same way. Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration>
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

// a nightly task's one failure (IR-0001: a read timeout on our endpoint's reply): a warning while the system tries
require_once $app . '/lib/ControlEngine.php';
$checks = []; ControlEngine::$checker = function (string $c) use (&$checks) { return $checks[$c] ?? ['pass' => null]; };
$checks['ep:crawl/loads'] = ['pass' => false, 'proof' => '/api/crawl/loads didn\'t answer in 120,000 ms.', 'ms' => 120000];
$id = ControlAlarms::raise($pdo, 'task:failed:overture_bulk', 'wf:W1', 'trip', 'crash', 'W1: its task overture_bulk failed (run #1005)', 'Its work goes back.', 'Read its why.', "TimeoutError('The read operation timed out')");
$a = ControlAlarms::byId($pdo, $id);
$f = ControlAlarms::facts($pdo, $a);
$t('one failure: a warning on our endpoint (its component), the system trying (0 of 3, next at a time to come)', $a['level'] === 'warning' && $a['component'] === 'ep:crawl/loads' && str_contains($f['status'], '0 of 3, next at {t:') && strtotime($a['next_try_at'] . ' UTC') > time(), [$a, $f['status']]);
ControlAlarms::expireTries($pdo);
$t('inside its hour, before its first try is due, the watch leaves it a warning', ControlAlarms::byId($pdo, $id)['level'] === 'warning');

// an hour on, with no second failure (the next night's run hasn't come): its due check still fails
$pdo->exec("UPDATE control_alarms SET first_at = UTC_TIMESTAMP() - INTERVAL 61 MINUTE, last_at = first_at, next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id = $id");
$did = ControlAlarms::expireTries($pdo); Automation::forget();
$a = ControlAlarms::byId($pdo, $id); $f = ControlAlarms::facts($pdo, $a);
$t('past its hour, its check still failing: a trip (keep the hour)', $a['level'] === 'trip' && $a['tripped_at'] !== null, [$did, $a['level']]);
$t('…its words name what it holds and its next check, never "until" a time gone by', str_starts_with($f['status'], 'Paused by the system (') && str_contains($f['status'], 'next check at {t:') && !str_contains($f['status'] . $f['doing'], 'until '), $f['status']);
$t('…and the trip holds only the bulk load, never W1 whole (D1): W1\'s switch untouched', Automation::taskOff($pdo, 'overture_bulk') !== null && Automation::sysOff($pdo, 'wf', 'W1') === null && Automation::wfOff($pdo, 'W1') === null);
$t('…logged', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Trip: W1: its task overture_bulk failed%'")->fetchColumn() === 1);
$t('run again: nothing more to do (its next check is 30 min on)', ControlAlarms::expireTries($pdo) === []);

// a critical part's early trip, still trying past its hour: the Escalation Notice
require_once $app . '/lib/ControlRegister.php';
$checks['svc:github'] = ['pass' => false, 'proof' => 'GitHub didn\'t answer.', 'ms' => 15000];
$cid = ControlAlarms::raise($pdo, 'x:gh', 'svc:github', 'trip', 'crash', 'GitHub failed');
$c = ControlAlarms::byId($pdo, $cid);
$t('a critical part: a trip at once, still trying', $c['level'] === 'trip' && $c['retrying_at'] !== null, $c);
$pdo->exec("UPDATE control_alarms SET first_at = UTC_TIMESTAMP() - INTERVAL 61 MINUTE, next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id = $cid");
ControlAlarms::expireTries($pdo);
$c = ControlAlarms::byId($pdo, $cid);
$t('…past its hour with no fix: escalated (the Escalation Notice), no longer retrying', $c['retrying_at'] === null && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Escalation Notice: retries unsuccessful%'")->fetchColumn() === 1, $c);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
