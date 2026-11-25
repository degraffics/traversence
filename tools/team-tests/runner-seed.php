<?php
// For runner.cjs: over tickets.cjs's seed, the Team and its runner's log (their migrations), the runner switched on, and two runs
// as staging would make them (STAGING=1): the Project Manager moving a ready Work ticket to Building, and the Translator's
// proposal on a Resolution still at Brief. Prints the Work ticket's id and the Resolution's. php runner-seed.php <root>/app
putenv('STAGING=1');
$app = $argv[1]; require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php'; $pdo = Database::connection();
foreach (['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql'] as $f) foreach (DbUpdates::statements((string) file_get_contents($app . '/migrations/' . $f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); }
$pdo->exec("CREATE TABLE IF NOT EXISTS feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
require_once $app . '/lib/TeamRunner.php'; require_once $app . '/lib/Switches.php';
Switches::set($pdo, 'team_runner', 'on', 0, false, 'runner.cjs', 1);
$J = 'Jason (test)';
$pdo->exec("UPDATE control_gauge_tasks SET owner = 'Jason (test)' WHERE owner = '' AND cleared_at IS NULL AND undone_at IS NULL");   // the seed's other tasks are people's: the runner leaves them
$w = GaugeTasks::add($pdo, 'area:control-board', 'Ready for the Team', 'why', '0179', $J, 1, 'America/Phoenix');
GaugeTasks::edit($pdo, $w, ['goal' => 'Built', 'done_when' => 'It runs', 'priority' => '1'], $J); GaugeTasks::noDecision($pdo, $w, $J); GaugeTasks::assign($pdo, $w, 'system · Project Manager', $J);
$r = GaugeTasks::add($pdo, 'area:backlog', 'A second Tier 3', 'why', '', $J, 1, '', 'resolution'); GaugeTasks::briefEdit($pdo, $r, 'boundary', 'money', $J);
TeamRunner::tick($pdo); TeamRunner::tick($pdo);
echo $w, ' ', $r, "\n";
