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
for ($i = 0; $i < 25; $i++) $pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received) VALUES (UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 200, 1)");   // one record a call, as its own calls (5a: speed per record)
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
// before decisions/0175 a trip wrote into W2's switch itself; the watch puts it back to what a person set, then pauses
$pdo->exec("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP() - INTERVAL 1 DAY, 'workflow', 'W2', 0, 'On', 7, 'Jason')");
Automation::setWorkflow($pdo, 'W2', true, 'Shut down from the Control Board by the Control Board (a trip)', 0, 'the Control Board (a trip)'); Automation::forget();
$id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'ep:crawl/old:slow'")->fetchColumn();
ControlAlarms::tick($pdo);
$a = ControlAlarms::byId($pdo, $id); Automation::forget();
if ($a['cleared_at'] !== null) $t('the watch lifted the old slow trip: W2 checked and started again by the system', Automation::gathering($pdo, '', 'W2') === null && str_contains((string) $a['cleared_how'], 'Resolved by the system'), $a['cleared_how']);
else $t('W2\'s check is red here: the old slow trip is now a warning that says the system tries every 5 minutes, W2 still off', $a['level'] === 'warning' && str_contains((string) $a['means'], 'the system tries every 5 minutes') && Automation::gathering($pdo, '', 'W2') !== null, $a);   // paused by the system since decisions/0175
$t('…the watch tries it every time while it still holds what it stopped', str_contains($src, "kind = 'slow' AND (level = 'trip' OR (stopped IS NOT NULL"));

// 3. Resolve: no question; it checks and starts again
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, tripped_at, stopped) VALUES ('x:crash', 'wk:crawler', 'trip', 'crash', 'The crawler crashed', 3, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), '[]')");
$cid = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'x:crash'")->fetchColumn();
[$ok, $msg] = ControlAlarms::resolve($pdo, $cid, 7, 'Jason', '');
$t('Resolve with nothing typed: done, logged as "Checked and started again"', $ok && str_contains($msg, 'Checked and started again'), $msg);
$cs = file_get_contents($app . '/admin/control-console.php');
$t('the board\'s button is "Check and start again", with no question', str_contains($cs, "\$btn('resolve', 'Check and start again'") && !str_contains($cs, "'data-cb-ask' => 'What fixed it?'"));
$t('Pulse\'s step is a button too, not a form', str_contains($src, "['do', 'alarm_resolve', 'Check and start again']") && !str_contains($src, "['note', 'What fixed it?'"));

// 4. an old trip (raised before 3c, no tripped time) with a stale result: its words refreshed too (W0, 2026-11-19)
require_once $app . '/lib/Preflight.php';
$lines = Preflight::lines($pdo);
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at) VALUES ('pf:wf:W0', 'wf:W0', 'trip', 'check', 'W0 Indexer: Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, NOW())')->execute(['wf:W0', json_encode(['s' => 'bad', 'said' => 'What holds it: Dry run: the test batch: 3 of 5 failed.', 'fix' => [], 'at' => time(), 'extra' => ['parts' => [['Its gate: the switch-on check has no red line for it.', 'ok'], ['Dry run: the test batch: 3 of 5 failed.', 'bad']]]])]);
Preflight::alarm($pdo, 'wf:W0', $lines['wf:W0'], Preflight::results($pdo)['wf:W0'], false);
$w0 = $pdo->query("SELECT * FROM control_alarms WHERE akey = 'pf:wf:W0' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$t('an old trip (no tripped time) whose red part is now its own alarm (the test batch) is closed, saying so', $w0['cleared_at'] !== null && str_contains((string) $w0['cleared_how'], 'their own alarms'), [$w0['cleared_at'], $w0['cleared_how']]);
$t('…the watch tests a line again first when its result is over 6 hours old, words only (three a watch)', str_contains($src, "Preflight::run(\$pdo, \$lid, 'the Control Board\\'s watch', true)") && str_contains(file_get_contents($app . '/lib/Preflight.php'), 'self::alarm($pdo, $id, $lines[$id], $out, !$wordsOnly);'));

// 5. Check and start again waits for a red check, greyed with the reason (never a silent restart)
$t('the board greys Check and start again while what it would start has a red check, saying which part', str_contains($cs, "\$canBoard && \$redAt === ''") && str_contains($cs, "'s check is red: '") && str_contains($cs, 'tv_cb_alarms($alarms, $ac, $names, $pid, $canPf, (string) $whyPf, $btn, $redChecks)'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
