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
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
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
$pdo->exec("UPDATE crawl_capture_tasks SET name = CASE task WHEN 'overture_bulk' THEN 'Overture Maps Places: load by town' ELSE 'Foursquare: load by town' END WHERE task IN ('overture_bulk', 'foursquare_bulk')");
$ov = $trip('overture_bulk', 1290); $fs = $trip('foursquare_bulk', 1288); Automation::forget();
$inPlan = fn (string $task) => Automation::sysAny($pdo, ['wf:W1', 'task:' . $task]) === null;
$t('as live: W1 paused by the system, so the planner skips overture_bulk', !$inPlan('overture_bulk'));
$held = CrawlService::runNext($pdo, 'overture_bulk');
$t('"Try it first on the next run" while W1 is paused: says it won\'t run and what lifts it (never "It runs first")', is_string($held) && str_contains($held, 'won\'t run while W1 is paused by a trip') && str_contains($held, 'Check and start again is beside this'), $held);

[$ok, $msg] = ControlAlarms::resolve($pdo, $ov, 7, 'Jason', ''); Automation::forget();
$t('pressed on overture_bulk: its own pause lifted', (int) $pdo->query("SELECT COUNT(*) FROM system_pauses WHERE alarm_id = $ov AND cleared_at IS NULL")->fetchColumn() === 0);
$t('…"This trip\'s pause is lifted (Jason, …). W1 is still paused by one more trip: Foursquare failed (run #1288)." in plain words, never "acknowledged"', $ok && str_starts_with($msg, 'This trip\'s pause is lifted (Jason, ') && str_contains($msg, 'W1 is still paused by one more trip: Foursquare failed (run #1288).') && str_contains($msg, 'Once both are cleared, W1\'s next run is this task\'s check') && !str_contains($msg, 'foursquare_bulk'), $msg);
$t('…and the planner still skips it (honestly said)', !$inPlan('overture_bulk'));
$t('…the trip stays open (it closes only when its run does its work)', $pdo->query("SELECT cleared_at FROM control_alarms WHERE id = $ov")->fetchColumn() === null);

$h0 = tv_admin_mod_control($pdo); $li0 = preg_match('~<li id="cb-alarm-' . $ov . '".*?</li>~s', $h0, $m0) ? $m0[0] : '';
$t('…the board: its card carries the other trip\'s own button, "Check and start again: Foursquare failed (run #1288)", in place', str_contains($li0, 'data-act="resolve"') ? (str_contains($li0, '>Check and start again: Foursquare failed (run #1288)<') && preg_match('/data-id="' . $fs . '"/', $li0) === 1) : str_contains($li0, 'Check and start again: Foursquare failed (run #1288)'), mb_substr(strip_tags($li0), 0, 400));
$f0 = ControlAlarms::facts($pdo, ControlAlarms::byId($pdo, $ov));
$t('…its card: Status the press\'s words, nothing held', str_starts_with($f0['status'], 'This trip\'s pause is lifted (') && $f0['holds'] === '', $f0['status']);
[$ok, $msg] = ControlAlarms::resolve($pdo, $fs, 7, 'Jason', ''); Automation::forget();
$t('pressed on foursquare_bulk: "W1 runs again (Jason, …). Its next run is this task\'s check: it closes as Fixed if it works, or trips again if not."', $ok && str_starts_with($msg, 'W1 runs again (Jason, ') && str_contains($msg, 'it closes as Fixed if it works, or trips again if not'), $msg);
$t('…and overture_bulk\'s card, pressed first, now says the same (never still "still paused")', str_starts_with((string) ControlAlarms::byId($pdo, $ov)['resolve_note'], 'W1 runs again (Jason, '), ControlAlarms::byId($pdo, $ov)['resolve_note']);
$t('…no pause left on W1: the planner takes both tasks again', $inPlan('overture_bulk') && $inPlan('foursquare_bulk'));
$t('…both trips still open until their runs prove the fix', (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE id IN ($ov, $fs) AND cleared_at IS NULL")->fetchColumn() === 2);
$t('"Try it first on the next run" now: nothing holds it (null)', CrawlService::runNext($pdo, 'overture_bulk') === null);

// the card: the press's state replaces the trip's (Jason, 2026-11-24: "every action … replace old states with new state")
$f = ControlAlarms::facts($pdo, ControlAlarms::byId($pdo, $fs));
$t('the card\'s Status is the press\'s result, never "Paused by the system" or "Acknowledged"', str_starts_with($f['status'], 'W1 runs again (') && !str_contains($f['status'], 'Paused by the system') && !str_contains($f['status'], 'Acknowledged'), $f['status']);
$t('…it holds nothing now ("Holds:" gone), and says what it\'s doing: its next run is the check', $f['holds'] === '' && str_contains($f['doing'], 'Its next run is the check'), [$f['holds'], $f['doing']]);
$steps = ControlAlarms::pulseSteps($pdo, $fs, fn ($l, $w, $a = null) => $l);
$t('Pulse: no Check and start again, no Try it first on the next run (it is first)', !in_array('Check and start again', $steps, true) && !in_array('Try it first on the next run', $steps, true), $steps);
$html = tv_admin_mod_control($pdo);
$li = preg_match('~<li id="cb-alarm-' . $fs . '".*?</li>~s', $html, $m) ? $m[0] : '';
$t('the board: its row is "started", amber, with no Check and start again, Try it first, Acknowledge or "Holds:"', $li !== '' && str_contains($li, 'data-astate="started"') && !str_contains($li, '>Check and start again<') && !str_contains($li, 'Try it first') && !str_contains($li, '>Acknowledge<') && !str_contains($li, 'Holds:</b>'), mb_substr(strip_tags($li), 0, 300));

// its run fails again: back to holding W1, in words (never still "Started again")
ControlAlarms::raise($pdo, 'task:failed:foursquare_bulk', 'wf:W1', 'trip', 'crash', 'foursquare_bulk failed in its own code (run #2102)', 'Its work goes back.', 'Read its why.', 'TimeoutError', [], true, false, 'task:foursquare_bulk'); Automation::forget();
$fa = ControlAlarms::byId($pdo, $fs); $f2 = ControlAlarms::facts($pdo, $fa);
$t('failed again: its pause is back (W1 skipped again)', !$inPlan('foursquare_bulk'));
$t('…its card says "Tried again at … it failed again", Check and start again back', ControlAlarms::startedAgain($fa) === '' && str_contains($f2['status'], 'Tried again at ') && str_contains($f2['status'], 'it failed again') && in_array('Check and start again', ControlAlarms::pulseSteps($pdo, $fs, fn ($l, $w, $a = null) => $l), true), $f2['status']);

// a good run clears the trip (the existing path: CrawlService's report clears task:failed:<task> on "done")
ControlAlarms::clear($pdo, 'task:failed:overture_bulk', 'It did its work in run #2101 (02:40 UTC).');
$t('its next good run clears it ("It did its work in run #…")', $pdo->query("SELECT cleared_at FROM control_alarms WHERE id = $ov")->fetchColumn() !== null);

// a trip that paused nothing: said so, never "started again"
ControlAlarms::raise($pdo, 'task:failed:x', 'wf:W1', 'trip', 'crash', 'x failed in its own code (run #1)', '', '', 'boom', [], true, false, 'task:x');
$x = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'task:failed:x'")->fetchColumn();
[$ok, $msg] = ControlAlarms::resolve($pdo, $x, 7, 'Jason', '');
$t('a trip that paused nothing: noted as before, "Its next run is its check" (never "started again")', $ok && str_contains($msg, 'Its next run is its check') && !str_contains($msg, 'runs again'), $msg);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
