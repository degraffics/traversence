<?php
// control-board-legacy (the session manager, 2026-11-19), with the control board's build (step 4): a workflow's line is a
// roll-up and raises nothing (sections 1–4). Before that build, a pre-flight trip from before 3c (no trip time) was settled by
// the watch to what a fresh raise would be today: closed if its check passes, its notice or warning if amber, still a trip
// timed from now if red, each logged by the system; an alarm's title is its red part, else its amber part, never a
// passing one (W0 read "the switch-on check has no red line for it"); a trip with its time keeps its level (words only);
// W0's test batch is asked again when its answer waits or it was short, and a short one names the indexer's version.
// Run: mariadb < ../release-b-tests/start-schema.sql && php legacy.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
Preflight::results($pdo); $lines = Preflight::lines($pdo);
$old = function (string $key, string $proc, string $title, bool $timed = false) use ($pdo) { $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, reading, n_times, first_at, last_at, tripped_at) VALUES (?, ?, 'trip', 'check', ?, 'old words', 1, UTC_TIMESTAMP() - INTERVAL 8 HOUR, UTC_TIMESTAMP() - INTERVAL 8 HOUR, " . ($timed ? 'UTC_TIMESTAMP() - INTERVAL 8 HOUR' : 'NULL') . ")")->execute([$key, $proc, $title]); };
$gate = ['Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).', 'ok'];
$o = fn (string $s, array $parts) => ['s' => $s, 'said' => implode(' ', array_column($parts, 0)), 'fix' => [], 'at' => time(), 'extra' => ['parts' => $parts]];

// 1–4. a workflow's own line is a roll-up, never a fault (the control board's build, step 4: the one engine, 02 §2.2;
// Lift (a)): an old trip on a workflow's line, timed or not, red, amber or passing today, closes as a roll-up when its
// line is read, and lifts whatever it held; its parts' own alarms say what holds the workflow
foreach ([['pf:wf:W0', 'wf:W0', 'warn', [$gate, ['Dry run: the test batch: asked just now.', 'warn']], false], ['pf:wf:W3', 'wf:W3', 'bad', [['Needs: Pages ✓.', 'ok'], ['Its gate: "Tavily key" (missing).', 'bad']], false], ['pf:wf:W5', 'wf:W5', 'warn', [['Dry run: amber.', 'warn']], true]] as [$k, $pr, $st, $parts, $timed]) {
    $old($k, $pr, 'old words', $timed);
    $id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = '$k' AND cleared_at IS NULL")->fetchColumn();
    Automation::sysPause($pdo, 'wf', substr($pr, 3), 'old trip', $id); Automation::forget();
    Preflight::alarm($pdo, substr($k, 3), $lines[substr($k, 3)], $o($st, $parts), false, true); Automation::forget();
    $c = (string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE id = $id")->fetchColumn();
    $t($k . ($timed ? ' (timed)' : ' (before 3c)') . ', ' . $st . ' today: closed as a roll-up, its pause lifted', ControlAlarms::openOf($pdo, $k) === null && str_contains($c, 'only adds up its parts') && Automation::sysOff($pdo, 'wf', substr($pr, 3)) === null, $c);
}
Preflight::alarm($pdo, 'wf:W3', $lines['wf:W3'], $o('bad', [['Its gate: "Tavily key" (missing).', 'bad']]), true);
$t('a red workflow line raises nothing of its own now', ControlAlarms::openOf($pdo, 'pf:wf:W3') === null);

// 5. the watch: old trips first, a waiting answer read back, W0's short batch asked again
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch tests old trips first and settles them', str_contains($src, "ORDER BY (level = 'trip' AND tripped_at IS NULL) DESC") && str_contains($src, 'Preflight::alarm($pdo, $lid, $pl[$lid], $pr[$lid], false, $legacy)'));
$t('…reads back a line left waiting over 2 minutes, and asks W0\'s test batch again after half an hour red', str_contains($src, "(\$st === 'wait' && \$at < time() - 120)") && str_contains($src, "(\$lid === 'rw:batch' && \$st === 'bad' && \$at < time() - 1800)"));
$pf = file_get_contents($app . '/lib/Preflight.php');
$t('a short test batch names the indexer\'s version; under 1.1.1 it says to redeploy, with Railway\'s link', str_contains($pf, "Railway still runs indexer ' . \$gv") && str_contains($pf, "version_compare(\$gv, '1.1.1', '<') ? [self::fixUrl('Railway: the w0-indexer service → Deployments'") && !str_contains($pf, "the worker's settings didn't reach it"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
