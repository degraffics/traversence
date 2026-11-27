<?php
// The daily goal check (GoalCheck, goal_check_tick; CLAUDE.md "Goals and records live with the roles", Jason 2026-11-27)
// on a real MySQL or MariaDB: a goal's ticket not moved in 3 days opens one Tier 3 ticket for its lane's manager; a goal's
// date at risk (the PM's (8) rule, GoalCheck::AT_RISK) opens one Tier 4 ticket for that manager's executive, or the CEO for a
// goal across lanes; nothing doubled on a later day; each closes itself, cleared by its own check, once the goal passes;
// a goal without a due date is checked only for stalls; our own tickets never count as a goal's work; the job is daily,
// automatic, live only and logs every run.
// Run: php goal-check.php <root>/app [r2's TeamRoster.php]   (it resets lv_goal; with R2's TeamRoster, lanes are real)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_file($app . '/lib/GoalCheck.php')) { fwrite(STDERR, "usage: php goal-check.php <root>/app [TeamRoster.php]\n"); exit(2); }
require_once $app . '/lib/Database.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_goal'); $pdo->exec('CREATE DATABASE lv_goal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_goal');
if (!empty($argv[2])) require_once $argv[2];   // R2's TeamRoster (lane()), before GoalCheck loads the live one
require_once $app . '/lib/GoalCheck.php'; require_once $app . '/lib/Scheduler.php';
GaugeTasks::ensure($pdo);
$pdo->exec("ALTER TABLE control_gauge_tasks ADD COLUMN due_on DATE NULL, ADD COLUMN measure VARCHAR(255) NULL, ADD COLUMN lane VARCHAR(40) NULL");   // task-seed-4's and R2's columns
$pdo->exec("CREATE TABLE team_roster (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(60) UNIQUE, name VARCHAR(120), reports_to VARCHAR(60) NOT NULL DEFAULT '', status VARCHAR(12) DEFAULT 'active', sort INT DEFAULT 0)");
$pdo->exec("INSERT INTO team_roster (slug, name, reports_to) VALUES ('ceo','CEO','founder'),('cto','CTO','ceo'),('coo','COO','ceo'),('it-manager','IT Manager','cto'),('project-manager','Project Manager','coo'),('editorial-director','Editorial Director','cmo'),('cmo','CMO','ceo')");
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$now = strtotime('2026-12-01 12:00:00 UTC'); $day = 86400;
$ago = fn (int $d) => gmdate('Y-m-d H:i:s', $now - $d * $day);
$goal = function (string $what, ?string $due, string $lane = '') use ($pdo, $ago) {
    $id = GaugeTasks::add($pdo, 'area:goals', $what, 'a goal', 'test', 'Jason (test)', 1);
    $pdo->prepare('UPDATE control_gauge_tasks SET due_on = ?, lane = ?, at = ?, stage_at = ? WHERE id = ?')->execute([$due, $lane ?: null, $ago(30), $ago(30), $id]); return $id; };
$kid = function (int $g, string $what, int $daysStill, string $stage = 'building', string $lane = '') use ($pdo, $ago) {
    $id = GaugeTasks::add($pdo, 'area:goals', $what, 'work for the goal', 'test', 'Jason (test)', 1, '', 'work', $g);
    $pdo->prepare('UPDATE control_gauge_tasks SET stage = ?, at = ?, stage_at = ?, lane = ? WHERE id = ?')->execute([$stage, $ago($daysStill), $ago($daysStill), $lane ?: null, $id]); return $id; };
$ours = fn (int $g, string $kind) => $pdo->query("SELECT * FROM control_gauge_tasks WHERE audit_key = 'goal-$kind:$g' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$open = fn (array $rows) => array_values(array_filter($rows, fn ($r) => $r['cleared_at'] === null));

// goals: A stalled (a ticket 4 days still), due far off; B moving, due in 3 days with one at Planning (at risk); C past due
// with one open, its tickets in two lanes (the CEO); D no date, moving; E a goal with no tickets (not a goal yet)
$A = $goal('Launch the town paths', '2027-01-30', 'it-manager'); $a1 = $kid($A, 'Paths for 1A', 4); $a2 = $kid($A, 'Path checker sign-in', 1);
$B = $goal('Marketplace sections', '2026-12-04', 'project-manager'); $b1 = $kid($B, 'Tabs on /market/', 1, 'planning');
$C = $goal('Guides for 1A', '2026-11-28'); $c1 = $kid($C, 'Write the Springerville guide', 1, 'implementation', 'editorial-director'); $c2 = $kid($C, 'Guide pipeline job', 1, 'implementation', 'it-manager');
$D = $goal('A goal with no date', null, 'project-manager'); $d1 = $kid($D, 'Something moving', 1);
$E = $goal('Nothing under it yet', '2026-12-02');

$n = GoalCheck::tick($pdo, $now);
$t('counts: 4 goals (one with no tickets isn\'t a goal yet), 1 stalled, 2 at risk, 3 opened, 1 without a date', $n['goals'] === 4 && $n['stalled'] === 1 && $n['at_risk'] === 2 && $n['opened'] === 3 && $n['no_date'] === 1, $n);
$s = $open($ours($A, 'stall'));
$t('stalled: one ticket for goal A, naming the still ticket', count($s) === 1 && str_contains($s[0]['what'], '#' . $a1) && !str_contains($s[0]['what'], '#' . $a2), $s[0]['what'] ?? null);
$t('stalled: Tier 3, its lane\'s manager, a Work ticket at Planning under the goal', ($s[0]['tier'] ?? '') == 3 && $s[0]['owner'] === 'it-manager' && $s[0]['ticket'] === 'work' && $s[0]['stage'] === 'planning' && (int) $s[0]['parent_id'] === $A, $s[0] ?? null);
$t('A is not at risk (due far off, not past)', !$ours($A, 'risk'));
$r = $open($ours($B, 'risk'));
$t('at risk: goal B, due in 3 days with a ticket at Planning, Tier 4 for the manager\'s executive', count($r) === 1 && ($r[0]['tier'] ?? '') == 4 && $r[0]['owner'] === 'coo' && str_contains($r[0]['what'], 'at risk'), $r[0] ?? null);
$rc = $open($ours($C, 'risk'));
$t('at risk: goal C past its date with tickets open, across two lanes → the CEO', count($rc) === 1 && $rc[0]['owner'] === 'ceo' && str_contains($rc[0]['what'], 'still open past it'), $rc[0] ?? null);
$t('no date: goal D is checked for stalls only, nothing opened', !$ours($D, 'risk') && !$ours($D, 'stall'));
$t('no ticket for the goal with nothing under it', !$ours($E, 'risk') && !$ours($E, 'stall'));
$t('every run is logged', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'The daily goal check:%'")->fetchColumn() === 1);

// the next day: nothing doubled (our own ticket is never counted as the goal's work, though it sits under it)
$n2 = GoalCheck::tick($pdo, $now + $day);
$t('a day later: no duplicates (still one stall ticket for A, one risk ticket each for B and C)', count($ours($A, 'stall')) === 1 && count($ours($B, 'risk')) === 1 && count($ours($C, 'risk')) === 1 && $n2['opened'] === 0, $n2);
$t('our own ticket under goal A is not a stalled ticket of A', !str_contains($open($ours($A, 'stall'))[0]['what'], '#' . $open($ours($A, 'stall'))[0]['id']));

// cleared by its own check: A's still ticket moves; B's ticket leaves Planning
GaugeTasks::ensure($pdo);
$pdo->prepare('INSERT INTO control_gauge_task_moves (task_id, at, from_stage, to_stage, who) VALUES (?, ?, ?, ?, ?)')->execute([$a1, gmdate('Y-m-d H:i:s', $now + $day), 'building', 'implementation', 'test']);
$pdo->prepare("UPDATE control_gauge_tasks SET stage = 'implementation' WHERE id = ?")->execute([$b1]);
$n3 = GoalCheck::tick($pdo, $now + 2 * $day);
$sa = $ours($A, 'stall');
$t('cleared: A\'s stall ticket closes itself once the ticket moved', $sa[0]['cleared_at'] !== null && str_contains($sa[0]['done_how'], 'cleared by its own check') && !$open($sa), $sa[0] ?? null);
$t('cleared: B\'s risk ticket closes itself once nothing is at Planning or Building', !$open($ours($B, 'risk')) && str_contains($ours($B, 'risk')[0]['done_how'], 'no longer at risk'));
$t('C stays at risk: still past its date', count($open($ours($C, 'risk'))) === 1);
$t('the third run cleared 2', $n3['cleared'] === 2, $n3);
// a stall again later opens a new one (a closed one doesn't block the next)
$pdo->prepare('UPDATE control_gauge_tasks SET stage_at = ?, at = ? WHERE id = ?')->execute([$ago(1), $ago(1), $a1]); $pdo->exec('DELETE FROM control_gauge_task_moves WHERE task_id = ' . $a1);
GoalCheck::tick($pdo, $now + 6 * $day);
$t('stalled again later: a new ticket (the closed one doesn\'t block it)', count($open($ours($A, 'stall'))) === 1 && count($ours($A, 'stall')) === 2);
// the rule's own cases
$k = [['stage' => 'confirmation']];
$t('rule: due in 8 days with Planning work is not at risk', GoalCheck::atRisk('2026-12-09', [['stage' => 'planning']], '2026-12-01') === '');
$t('rule: due in 7 days with Building work is at risk', GoalCheck::atRisk('2026-12-08', [['stage' => 'building']], '2026-12-01') !== '');
$t('rule: due today with work at Confirmation is not at risk', GoalCheck::atRisk('2026-12-01', $k, '2026-12-01') === '');
$t('rule: a day past with any work open is at risk', GoalCheck::atRisk('2026-11-30', $k, '2026-12-01') !== '');
$t('rule: past with nothing open is not at risk', GoalCheck::atRisk('2026-11-30', [], '2026-12-01') === '');
$t('the rule carries the PM\'s note', str_contains((string) file_get_contents($app . '/lib/GoalCheck.php'), 'PM (8), 2026-11-27, adjustable by the PM'));
// the job
$t('the job: daily, automatic, live only, not quiet', (Scheduler::JOBS['goal_check_tick'][2] ?? '') === 'daily' && in_array('goal_check_tick', Scheduler::AUTO, true) && in_array('goal_check_tick', Scheduler::NOT_ON_STAGING, true) && !in_array('goal_check_tick', Scheduler::QUIET, true));
$t('the job\'s script exists', is_file($app . '/scripts/goal_check_tick.php'));
// before task-seed-4: no due_on, dates aren't checked, stalls still are
$pdo->exec('ALTER TABLE control_gauge_tasks DROP COLUMN due_on');
$n4 = GoalCheck::tick($pdo, $now + 6 * $day);
$t('before task-seed-4 (no due_on): no error, every goal counted as without a date', $n4['no_date'] === $n4['goals'] && $n4['at_risk'] === 0, $n4);
$pdo->exec('DROP DATABASE lv_goal');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
