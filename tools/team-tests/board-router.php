<?php
// For tickets.cjs: the System Control Board as board-panels-router.php draws it, with Piece 2's Work queue actions answered
// as api.php answers them (qadd with its type, qmove, qnoadr, qbrief, qsend, qanswer; the Team's runs: qrunundo, qrunaccept, qrundismiss). Everything else goes to that router.
// ROOT names the bench. Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path'];
// FOUNDER=1: act as the Founder's own staff login (Access::isFounder), as when Jason is signed in; else a staff member's
if (getenv('FOUNDER') === '1') { require_once $app . '/lib/Database.php'; require_once $app . '/admin/Access.php';
    foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); } }
if ($path === '/admin/api/config/control' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = (string) file_get_contents('php://input'); $in = json_decode($raw, true) ?: []; $act = (string) ($in['action'] ?? '');
    if (in_array($act, ['qadd', 'qmove', 'qnoadr', 'qbrief', 'qsend', 'qanswer', 'qrunundo', 'qrunaccept', 'qrundismiss'], true)) {
        require_once $app . '/lib/Database.php'; require_once $app . '/lib/Response.php'; require_once $app . '/lib/GaugeTasks.php';
        $pdo = Database::connection(); $in = Response::unwrap($in); $tid = (int) ($in['id'] ?? 0); $W = 'Jason (test)'; $tz = (string) ($in['tz'] ?? '');
        header('Content-Type: application/json');
        try {
            if ($act === 'qadd') { $nid = GaugeTasks::add($pdo, (string) $in['gauge'], (string) $in['what'], (string) $in['why'], (string) ($in['source'] ?? ''), $W, 1, $tz, (string) ($in['ticket'] ?? 'work') ?: 'work'); echo json_encode(['ok' => true, 'id' => $nid, 'message' => 'Added as task #' . $nid . '.']); return true; }
            if (in_array($act, ['qanswer'], true)) { require_once $app . '/admin/Access.php'; if (!Access::isFounder()) { echo json_encode(['ok' => false, 'error' => ['message' => 'Only the Founder, signed in with the Founder\'s own staff login, answers a Tier 3 decision (decisions/0179).']]); return true; } }   // as api.php
            $m = ['qmove' => fn () => GaugeTasks::move($pdo, $tid, (string) ($in['to'] ?? ''), $W, (string) ($in['why'] ?? ''), $tz), 'qnoadr' => fn () => GaugeTasks::noDecision($pdo, $tid, $W, $tz),
                'qbrief' => fn () => GaugeTasks::briefEdit($pdo, $tid, (string) ($in['field'] ?? ''), (string) ($in['value'] ?? ''), $W, $tz), 'qsend' => fn () => GaugeTasks::sendBrief($pdo, $tid, $W, $tz),
                'qanswer' => fn () => GaugeTasks::answer($pdo, $tid, (string) ($in['ans'] ?? ''), (string) ($in['words'] ?? ''), $W, $tz),
                'qrunundo' => function () use ($pdo, $in, $W, $tz, $app) { require_once $app . '/lib/TeamRunner.php'; return TeamRunner::undo($pdo, (int) $in['rid'], $W, $tz); },
                'qrunaccept' => function () use ($pdo, $in, $W, $tz, $app) { require_once $app . '/lib/TeamRunner.php'; return TeamRunner::accept($pdo, (int) $in['rid'], $W, $tz); },
                'qrundismiss' => function () use ($pdo, $in, $W, $app) { require_once $app . '/lib/TeamRunner.php'; return TeamRunner::dismiss($pdo, (int) $in['rid'], $W); }][$act]();
            echo json_encode(['ok' => true, 'message' => $m]);
        } catch (Throwable $x) { echo json_encode(['ok' => false, 'error' => ['message' => $x->getMessage()]]); }
        return true;
    }
}
if ($path === '/api/auth/session.php') { header('Content-Type: application/json'); echo '{"csrf_token":"x"}'; return true; }
return require __DIR__ . '/../control-board-tests/board-panels-router.php';
