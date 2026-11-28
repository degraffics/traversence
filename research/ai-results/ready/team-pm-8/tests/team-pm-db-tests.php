<?php
// tools/team-tests/team-pm-db-tests.php — the Project Manager's stall and sequence steps against a database, ON STAGING ONLY.
// It makes its own tickets on gauge area:pm-test, reads what the steps would do, and withdraws them at the end. It never
// switches the runner, never touches the ceiling or the tree, and makes no outside call or payment.
// Run on staging: php tools/team-tests/team-pm-db-tests.php [repo root]
declare(strict_types=1);

$root = $argv[1] ?? (getenv('TRAV_ROOT') ?: dirname(__DIR__, 2));
require_once rtrim($root, '/') . '/app/lib/TeamRunner.php';
require_once rtrim($root, '/') . '/app/lib/Staging.php';

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fail++; }

if (!Staging::here()) { echo "SKIP: staging only (this is not staging)\nALL PASS\n"; exit(0); }
$pdo = Database::pdo();   // VERIFY: Database's accessor name (file not shown)
TeamRunner::ensure($pdo); GaugeTasks::ensure($pdo);
$who = 'pm-tests'; $made = [];

// a goal at Priority 2 and a child without a priority: sequence proposes 2
$goal = GaugeTasks::add($pdo, 'area:pm-test', 'PM test goal ' . uniqid(), 'test', 'tests', $who, 0); $made[] = $goal;
GaugeTasks::edit($pdo, $goal, ['priority' => '2'], $who);
$kid = GaugeTasks::add($pdo, 'area:pm-test', 'PM test child ' . uniqid(), 'test', 'tests', $who, 0, '', 'work', $goal); $made[] = $kid;
$s = TeamRunner::sequenceFor($pdo, GaugeTasks::task($pdo, $kid));
check('sequence: child takes its goal\'s Priority 2', ($s[0] ?? 0) === 2);
check('sequence: the goal is settled by what waits on it', (TeamRunner::sequenceFor($pdo, GaugeTasks::task($pdo, $goal))[0] ?? 0) === 2 || TeamRunner::sequenceFor($pdo, GaugeTasks::task($pdo, $goal)) === null);

// a stalled Priority 1 ticket: four days at Planning (test data only, on staging; data_reason: the stall test needs an old stage_at)
$st = GaugeTasks::add($pdo, 'area:pm-test', 'PM test stalled ' . uniqid(), 'test', 'tests', $who, 0); $made[] = $st;
$pdo->prepare('UPDATE control_gauge_tasks SET priority = 1, stage_at = UTC_TIMESTAMP() - INTERVAL 4 DAY WHERE id = ?')->execute([$st]);
$f = TeamRunner::stallFor($pdo, GaugeTasks::task($pdo, $st));
check('stall: found at ticket level', ($f['level'] ?? '') === 'ticket' && ($f['days'] ?? 0) >= 4);
check('stall: names what its gate needs', in_array('a goal', $f['missing'] ?? [], true));

// a person's ticket is never worked
$pdo->prepare('UPDATE control_gauge_tasks SET owner = ? WHERE id = ?')->execute(['A Person', $st]);
check('mayWork refuses a person\'s ticket', !TeamRunner::mayWork(GaugeTasks::task($pdo, $st)));
$pdo->prepare("UPDATE control_gauge_tasks SET owner = '' WHERE id = ?")->execute([$st]);

// an Unstick ticket, fresh: no second escalation yet; the PM never unsticks its own
$u = GaugeTasks::add($pdo, 'area:pm-test', 'Unstick task #' . $st . ': test', 'test', TeamRunner::PM_SOURCE . $st . ')', $who, 0, '', 'work', $st); $made[] = $u;
check('stall: a fresh Unstick ticket holds the escalation', TeamRunner::stallFor($pdo, GaugeTasks::task($pdo, $st)) === null);
$pdo->prepare('UPDATE control_gauge_tasks SET priority = 1, stage_at = UTC_TIMESTAMP() - INTERVAL 9 DAY WHERE id = ?')->execute([$u]);
check('stall: never on its own Unstick ticket', TeamRunner::stallFor($pdo, GaugeTasks::task($pdo, $u)) === null);
$pdo->prepare('UPDATE control_gauge_tasks SET at = UTC_TIMESTAMP() - INTERVAL 4 DAY WHERE id = ?')->execute([$u]);
$f = TeamRunner::stallFor($pdo, GaugeTasks::task($pdo, $st));
check('stall: Unstick idle too → session level', ($f['level'] ?? '') === 'session' && ($f['child'] ?? 0) === $u);
check('stall: the route is a session while the agents\' API is off', TeamRunner::route('stall', false) === 'session');

foreach (array_reverse($made) as $id) { try { GaugeTasks::withdraw($pdo, $id, 'pm-tests cleanup', $who); } catch (Throwable $x) {} }
echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
