<?php
// /user/api/pulse-views.php — a person's saved views in Pulse (app/lib/PulseViews.php; design/table-standard.md, Jason's
// note of 2026-11-20 and its answers: kept with the account). Signed in; only the person's own views. POSTs need X-CSRF-Token.
//   GET                                   -> {views: [{id, name, filters}], csrf}
//   POST {save: {name, filters}}          -> {view, views, message}   (the same name replaces that view's filters)
//   POST {delete: id}                     -> {view, views, message}   (the view removed, so Undo can save it again)
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/PulseViews.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
$me = Auth::currentUser();
if ($me === null) Response::error('Please sign in.', 401, 'unauthenticated');
$uid = (int) $me['id'];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Response::json(['ok' => true, 'ready' => PulseViews::ready($pdo), 'views' => PulseViews::of($pdo, $uid), 'csrf' => $_SESSION['csrf_token'] ?? null]);
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $b = Response::jsonBody();
    if (isset($b['save'])) {
        [$v, $replaced] = PulseViews::save($pdo, $uid, (string) ($b['save']['name'] ?? ''), (array) ($b['save']['filters'] ?? []));
        Response::json(['ok' => true, 'view' => $v, 'views' => PulseViews::of($pdo, $uid), 'message' => ($replaced ? 'Updated “' : 'Saved “') . $v['name'] . '”.']);
    }
    if (isset($b['delete'])) {
        $v = PulseViews::delete($pdo, $uid, (int) $b['delete']);
        Response::json(['ok' => true, 'view' => $v, 'views' => PulseViews::of($pdo, $uid), 'message' => 'Removed “' . $v['name'] . '”.']);
    }
    Response::error('Nothing to do.', 422, 'not_allowed');
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422, 'not_allowed');
} catch (Throwable $e) {
    error_log('pulse-views: ' . $e->getMessage());
    Response::error('That didn\'t save. Try again; still stuck? Write to support@traversence.com.', 500, 'failed');
}
