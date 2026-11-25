<?php
// For roster.cjs: PHP's server router (php -S … router.php) drawing System Configuration → Team as the admin draws a
// manifest's table (tv_w_table), and answering its tool as /admin/api/config/team does (list with group rows and paging,
// get, act). ROOT names the bench (app/ and website/). The Founder's view (no staff session: Access::who() is the Founder).
// Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path']; parse_str($u['query'] ?? '', $q);
if (str_starts_with($path, '/js/')) { $f = $root . '/website' . $path; if (!is_file($f)) { http_response_code(404); return true; } header('Content-Type: text/javascript'); readfile($f); return true; }
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/admin/Access.php'; require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start(); $_SESSION['csrf_token'] = 'x';
// FOUNDER=1: act as the Founder's own staff login (Access::isFounder), as when Jason is signed in
if (getenv('FOUNDER') === '1') { require_once $app . '/admin/Access.php';
    foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); } }
if ($path === '/api/auth/session.php') { header('Content-Type: application/json'); echo '{"csrf_token":"x"}'; return true; }   // the kit's token
if ($path === '/admin/api/config/team') {
    $tools = require $app . '/admin/tools.php'; $T = $tools['config/team']; header('Content-Type: application/json');
    $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode((string) file_get_contents('php://input'), true) ?: []) : $q;
    require_once $app . '/lib/Response.php'; $in = Response::unwrap($in);   // links travel wrapped (tvSafe), as api.php reads them
    $act = (string) ($in['action'] ?? 'list');
    if (in_array($act, ['teamtree', 'teamceiling'], true)) {   // as api.php: the Founder's alone
        require_once $app . '/lib/TeamRunner.php';
        if (!Access::isFounder()) { echo json_encode(['ok' => false, 'error' => ['message' => 'Only the Founder, signed in with the Founder\'s own staff login, approves the decision tree or changes how far agents work (decisions/0179).']]); return true; }
        try { TeamRunner::ensure($pdo); echo json_encode(['ok' => true, 'message' => $act === 'teamtree' ? TeamRunner::approveTree($pdo, 'Jason (test)', (string) ($in['tz'] ?? '')) : TeamRunner::setCeiling($pdo, (string) ($in['to'] ?? ''), 'Jason (test)', '', (string) ($in['tz'] ?? ''))]); }
        catch (Throwable $x) { echo json_encode(['ok' => false, 'error' => ['message' => $x->getMessage()]]); }
        return true;
    }
    try {
        if ($act === 'list') { [$rows, $total, $counts] = ($T['list'])($pdo, $in); $per = (int) ($in['per'] ?? 25) ?: 25; $page = (int) ($in['page'] ?? 0);
            echo json_encode(['ok' => true, 'rows' => array_slice($rows, $page * $per, $per), 'total' => $total, 'capped' => false, 'counts' => (object) $counts, 'per' => $per, 'sortable' => array_map(fn ($c) => $c[0], $T['columns'])]); return true; }
        $item = function (int $id) use ($pdo, $T) { $d = (array) ($T['get'])($pdo, $id) + ['title' => '#' . $id, 'subtitle' => '', 'fields' => [], 'links' => []]; $d['fields'] = array_values(array_filter($d['fields'], fn ($f) => trim((string) $f[1]) !== '')); $d['id'] = $id; return $d; };
        if ($act === 'get') { echo json_encode(['ok' => true, 'item' => $item((int) $in['id'])]); return true; }
        if ($act === 'act') { $m = ($T['act'])($pdo, (int) $in['id'], (string) $in['act'], (array) ($in['input'] ?? []), 0); echo json_encode(['ok' => true, 'item' => $item((int) $in['id']), 'message' => $m]); return true; }
        echo json_encode(['ok' => false, 'error' => ['message' => 'Unknown action.']]); return true;
    } catch (Throwable $x) { echo json_encode(['ok' => false, 'error' => ['message' => $x->getMessage()]]); return true; }
}
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$js = '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script>';
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Team</title>' . $css . '</head><body><main class="ak-main" style="padding:3px">'
    . (is_file($app . '/admin/team-console.php') ? (function () use ($app, $pdo) { require_once $app . '/admin/team-console.php'; return tv_admin_mod_team($pdo); })() : '') . tv_w_table($pdo, 'config/team', false) . '</main>' . $js . '</body></html>';
return true;
