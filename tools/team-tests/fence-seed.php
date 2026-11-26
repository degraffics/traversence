<?php
// For fence.cjs (P3-6): after tickets.cjs's seed, the decision tree approved and agents at Implementation behind the fence
// (Backend only), two Building tickets with their build briefs: one naming a library a public page reaches, one naming only
// /admin/ files. Prints their ids. Never live.
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/');
require_once $app . '/lib/Database.php'; require_once $app . '/lib/TeamRunner.php'; require_once $app . '/lib/ControlGauges.php'; require_once $app . '/admin/Access.php';
ControlGauges::$source = (string) getenv('GAUGES_FILE');
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); }
require_once $app . '/lib/DbUpdates.php';   // Piece 2's runner migration (build_brief), as live has run it
foreach (DbUpdates::statements((string) file_get_contents(dirname(__DIR__, 2) . '/handoffs/piece2-build/team-runner/build/app/migrations/2026-11-25_team_runs.sql')) as $q) { $st = $pdo->query($q); if ($st) $st->closeCursor(); }
$J = 'Jason (test)'; TeamRunner::ensure($pdo);
TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J); TeamRunner::setCeiling($pdo, 'implementation', $J);
$mk = function (string $what, string $brief) use ($pdo, $J) {
    $id = GaugeTasks::add($pdo, 'area:backlog', $what, 'why', 'src', $J, 1);
    GaugeTasks::edit($pdo, $id, ['goal' => 'g', 'done_when' => 'd'], $J); GaugeTasks::noDecision($pdo, $id, $J); GaugeTasks::assign($pdo, $id, 'system · System Architect', $J);
    GaugeTasks::move($pdo, $id, 'building', $J, 'test');
    $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = ? WHERE id = ?')->execute([$brief, $id]);
    return $id;
};
$front = $mk('Show the order count on the cart (app/lib/Orders.php)', "Task: the order count on the cart\nStep 1: change app/lib/Orders.php");
$back = $mk('Tidy the report console (website/admin/router.php)', "Task: tidy the report console\nStep 1: change app/admin/team-console.php");
echo $front . ' ' . $back;
