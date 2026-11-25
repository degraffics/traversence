<?php
// For tickets.cjs: the System Control Board as board-panels-router.php draws it, with Piece 2's Work queue actions answered
// as api.php answers them (qadd with its type, qmove, qnoadr, qbrief, qsend, qanswer). Everything else goes to that router.
// ROOT names the bench. Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path'];
if ($path === '/admin/api/config/control' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = (string) file_get_contents('php://input'); $in = json_decode($raw, true) ?: []; $act = (string) ($in['action'] ?? '');
    if (in_array($act, ['qadd', 'qmove', 'qnoadr', 'qbrief', 'qsend', 'qanswer'], true)) {
        require_once $app . '/lib/Database.php'; require_once $app . '/lib/Response.php'; require_once $app . '/lib/GaugeTasks.php';
        $pdo = Database::connection(); $in = Response::unwrap($in); $tid = (int) ($in['id'] ?? 0); $W = 'Jason (test)'; $tz = (string) ($in['tz'] ?? '');
        header('Content-Type: application/json');
        try {
            if ($act === 'qadd') { $nid = GaugeTasks::add($pdo, (string) $in['gauge'], (string) $in['what'], (string) $in['why'], (string) ($in['source'] ?? ''), $W, 1, $tz, (string) ($in['ticket'] ?? 'work') ?: 'work'); echo json_encode(['ok' => true, 'id' => $nid, 'message' => 'Added as task #' . $nid . '.']); return true; }
            $m = ['qmove' => fn () => GaugeTasks::move($pdo, $tid, (string) ($in['to'] ?? ''), $W, (string) ($in['why'] ?? ''), $tz), 'qnoadr' => fn () => GaugeTasks::noDecision($pdo, $tid, $W, $tz),
                'qbrief' => fn () => GaugeTasks::briefEdit($pdo, $tid, (string) ($in['field'] ?? ''), (string) ($in['value'] ?? ''), $W, $tz), 'qsend' => fn () => GaugeTasks::sendBrief($pdo, $tid, $W, $tz),
                'qanswer' => fn () => GaugeTasks::answer($pdo, $tid, (string) ($in['ans'] ?? ''), (string) ($in['words'] ?? ''), $W, $tz)][$act]();
            echo json_encode(['ok' => true, 'message' => $m]);
        } catch (Throwable $x) { echo json_encode(['ok' => false, 'error' => ['message' => $x->getMessage()]]); }
        return true;
    }
}
if ($path === '/api/auth/session.php') { header('Content-Type: application/json'); echo '{"csrf_token":"x"}'; return true; }
return require __DIR__ . '/../control-board-tests/board-panels-router.php';
