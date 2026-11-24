<?php
// For task-trip.cjs: W1's two task trips as live (each pausing W1) and a founder; then the presses for a state:
// php task-trip-seed.php <root>/app <before|one|waiting|failed>   before: as live, Overture's seen by Jason, nothing pressed;   one: overture pressed (W1 still held by Foursquare's trip);
// waiting: both pressed (W1 runs again); failed: both pressed, then Foursquare's next run failed. Prints the trips' ids.
declare(strict_types=1);
$app = $argv[1]; $state = $argv[2] ?? 'waiting';
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
foreach (['Database', 'ControlAlarms', 'Automation', 'ControlEngine'] as $c) require_once $app . '/lib/' . $c . '.php';
$pdo = Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'f@example.test', 'Jason', 'active', 'founder', '', '')");
Automation::ensurePauses($pdo);
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES ('overture_bulk', 'W1', 1, 'Overture Maps Places: load by town'), ('foursquare_bulk', 'W1', 1, 'Foursquare: load by town')");
ControlEngine::$checker = fn (string $c) => ['pass' => null, 'proof' => 'its next run is its check'];
$trip = function (string $task, int $run) use ($pdo): int {
    ControlAlarms::raise($pdo, 'task:failed:' . $task, 'wf:W1', 'trip', 'crash', $task . ' failed in its own code (run #' . $run . ')', 'Its work goes back and its next run tries it again.', 'Read its why in run #' . $run . ' (beside this) and fix its source or its settings; then Try it first on the next run. Or Pause the task.', "TimeoutError('The read operation timed out')",
        [['Run #' . $run, 'peek', '/admin/insights/crawler?run=' . $run], ['Try it first on the next run', 'ops', 'ops_runnext:' . $task], ['Pause the task', 'ops', 'ops_task_off:' . $task]], true, false, 'task:' . $task);
    $id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'task:failed:" . $task . "'")->fetchColumn();
    $pdo->prepare("UPDATE control_alarms SET level = 'trip', tripped_at = UTC_TIMESTAMP(), stopped = ? WHERE id = ?")->execute([json_encode([['pause', 'wf:W1', 'W1 (its tasks wait with it)']]), $id]);
    Automation::sysPause($pdo, 'wf', 'W1', $task . ' failed in its own code', $id);
    return $id;
};
$ov = $trip('overture_bulk', 1290); $fs = $trip('foursquare_bulk', 1288); Automation::forget();
if ($state === 'before') { ControlAlarms::ack($pdo, $ov, 7, 'Jason'); echo $ov . ' ' . $fs; exit; }
ControlAlarms::resolve($pdo, $ov, 7, 'Jason', ''); Automation::forget();
if ($state !== 'one') { ControlAlarms::resolve($pdo, $fs, 7, 'Jason', ''); Automation::forget(); }
if ($state === 'failed') ControlAlarms::raise($pdo, 'task:failed:foursquare_bulk', 'wf:W1', 'trip', 'crash', 'foursquare_bulk failed in its own code (run #2102)', 'Its work goes back and its next run tries it again.', 'Read its why.', "TimeoutError('The read operation timed out')", [], true, false, 'task:foursquare_bulk');
echo $ov . ' ' . $fs;
