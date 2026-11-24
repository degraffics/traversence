<?php
// A task's own-code trip pressed (2026-11-24, W1 live: overture_bulk #1290 and foursquare_bulk #1288 each paused wf:W1; Check and
// start again only noted it, the planner skipped the paused workflow, so no next run could ever clear it: a dead end).
// php task-trip.php <app dir>   (after the start schema)
// - the press lifts what this trip paused; another trip's pause on the same workflow is named, with its own button
// - pressed on both, W1 runs: the planner takes the task again; the trip stays open until that run does its work
// - "Try it first on the next run" never claims a run that won't happen: held, it says why
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
foreach (['Database', 'ControlAlarms', 'Automation', 'ControlEngine'] as $c) require_once $app . '/lib/' . $c . '.php';
require_once $app . '/lib/crawler/CrawlService.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got), 0, 400) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS system_pauses'); Automation::ensurePauses($pdo);
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES ('overture_bulk', 'W1', 1, 'Overture'), ('foursquare_bulk', 'W1', 1, 'Foursquare')");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1, off_why = NULL WHERE task IN ('overture_bulk', 'foursquare_bulk')");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::forget();
ControlEngine::$checker = fn (string $c) => ['pass' => null, 'proof' => 'its next run is its check'];
// W1's live state as read (2026-11-23): two trips on the task's own code, each holding W1 with its own pause
$trip = function (string $task, int $run) use ($pdo): int {
    ControlAlarms::raise($pdo, 'task:failed:' . $task, 'wf:W1', 'trip', 'crash', $task . ' failed in its own code (run #' . $run . ')', 'Its work goes back.', 'Read its why.', "TimeoutError('The read operation timed out')", [], true, false, 'task:' . $task);
    $id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'task:failed:" . $task . "'")->fetchColumn();
    $pdo->prepare("UPDATE control_alarms SET level = 'trip', tripped_at = UTC_TIMESTAMP(), stopped = ? WHERE id = ?")->execute([json_encode([['pause', 'wf:W1', 'W1 (its tasks wait with it)']]), $id]);
    Automation::sysPause($pdo, 'wf', 'W1', $task . ' failed in its own code', $id);
    return $id;
};
$ov = $trip('overture_bulk', 1290); $fs = $trip('foursquare_bulk', 1288); Automation::forget();
$inPlan = fn (string $task) => Automation::sysAny($pdo, ['wf:W1', 'task:' . $task]) === null;
$t('as live: W1 paused by the system, so the planner skips overture_bulk', !$inPlan('overture_bulk'));
$held = CrawlService::runNext($pdo, 'overture_bulk');
$t('"Try it first on the next run" while W1 is paused: says it won\'t run and what lifts it (never "It runs first")', is_string($held) && str_contains($held, 'won\'t run while') && str_contains($held, 'Check and start again'), $held);

[$ok, $msg] = ControlAlarms::resolve($pdo, $ov, 7, 'Jason', ''); Automation::forget();
$t('pressed on overture_bulk: its own pause lifted', (int) $pdo->query("SELECT COUNT(*) FROM system_pauses WHERE alarm_id = $ov AND cleared_at IS NULL")->fetchColumn() === 0);
$t('…W1 still held by foursquare_bulk\'s trip, named, with its own button, never just "acknowledged"', $ok && str_contains($msg, 'still paused by "foursquare_bulk failed in its own code (run #1288)"') && str_contains($msg, 'Press that one too'), $msg);
$t('…and the planner still skips it (honestly said)', !$inPlan('overture_bulk'));
$t('…the trip stays open (it closes only when its run does its work)', $pdo->query("SELECT cleared_at FROM control_alarms WHERE id = $ov")->fetchColumn() === null);

[$ok, $msg] = ControlAlarms::resolve($pdo, $fs, 7, 'Jason', ''); Automation::forget();
$t('pressed on foursquare_bulk: "Started again …: W1 runs again, and its next run is this task\'s check"', $ok && str_starts_with($msg, 'Started again at ') && str_contains($msg, 'W1 runs again') && str_contains($msg, 'closes as Fixed when that run does its work, or trips again if it fails'), $msg);
$t('…no pause left on W1: the planner takes both tasks again', $inPlan('overture_bulk') && $inPlan('foursquare_bulk'));
$t('…both trips still open until their runs prove the fix', (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE id IN ($ov, $fs) AND cleared_at IS NULL")->fetchColumn() === 2);
$t('"Try it first on the next run" now: nothing holds it (null)', CrawlService::runNext($pdo, 'overture_bulk') === null);

// a good run clears the trip (the existing path: CrawlService's report clears task:failed:<task> on "done")
ControlAlarms::clear($pdo, 'task:failed:overture_bulk', 'It did its work in run #2101 (02:40 UTC).');
$t('its next good run clears it ("It did its work in run #…")', $pdo->query("SELECT cleared_at FROM control_alarms WHERE id = $ov")->fetchColumn() !== null);

// a trip that paused nothing: said so, never "started again"
ControlAlarms::raise($pdo, 'task:failed:x', 'wf:W1', 'trip', 'crash', 'x failed in its own code (run #1)', '', '', 'boom', [], true, false, 'task:x');
$x = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'task:failed:x'")->fetchColumn();
[$ok, $msg] = ControlAlarms::resolve($pdo, $x, 7, 'Jason', '');
$t('a trip that paused nothing: noted as before, "Its next run is its check" (never "started again")', $ok && str_contains($msg, 'Its next run is its check') && !str_contains($msg, 'Started again'), $msg);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
