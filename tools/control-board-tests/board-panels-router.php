<?php
// For board-panels.cjs: PHP's server router (php -S … board-panels-router.php) drawing what Jason opened (2026-11-23): the
// board at /admin/config/control?tab=alarms#open=config/alarms:<id>, the alarm's drawer (config/alarms' own get, through
// the admin API's shape), the part's own page (/admin/config/control/svc-google) and its register card
// (/admin/config/register#svc-google, the module card). ROOT names the bench (app/ and website/). Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path']; parse_str($u['query'] ?? '', $q);
if (str_starts_with($path, '/js/')) { $f = $root . '/website' . $path; if (!is_file($f)) { http_response_code(404); return true; } header('Content-Type: text/javascript'); readfile($f); return true; }
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start(); $_SESSION['csrf_token'] = 'x';
if ($path === '/admin/api/config/alarms' && ($q['action'] ?? '') === 'get') {
    $tools = require $app . '/admin/tools.php';
    $d = ($tools['config/alarms']['get'])($pdo, (int) $q['id']);
    $d = (array) $d + ['title' => '#' . $q['id'], 'subtitle' => '', 'fields' => [], 'links' => []];
    $d['fields'] = array_values(array_filter($d['fields'], fn ($f) => trim((string) $f[1]) !== '')); $d['id'] = (int) $q['id'];
    header('Content-Type: application/json'); echo json_encode(['ok' => true, 'item' => $d]); return true;
}
if ($path === '/admin/api/config/alarms' && ($q['action'] ?? 'list') === 'list') {   // the Alarms tab's table, as the admin API answers it
    $tools = require $app . '/admin/tools.php';
    [$rows, $total, $counts] = ($tools['config/alarms']['list'])($pdo, $q);
    header('Content-Type: application/json'); echo json_encode(['ok' => true, 'rows' => $rows, 'total' => $total, 'capped' => false, 'counts' => (object) $counts, 'per' => 25, 'sortable' => []]); return true;
}
if (str_starts_with($path, '/admin/api/')) { header('Content-Type: application/json'); echo '{"ok":true,"rows":[],"total":0,"counts":{}}'; return true; }
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$js = '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script><script src="/js/tv-copy.js"></script>';
$page = fn (string $title, string $body) => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>' . $css . '</head><body>' . $body . $js . '</body></html>';
if ($path === '/admin/config/register') {
    require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/module-cards.php';
    echo $page('Service register', '<h1>Service register</h1>' . tv_module_card($pdo, 'google', ['can' => true])); return true;
}
require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
if (preg_match('~^/admin/config/control/([a-z]+)-(.+)$~', $path, $m)) $_GET['proc'] = $m[1] . ':' . $m[2];
foreach ($q as $k => $v) if ($k !== 'proc') $_GET[$k] = $v;
echo $page('System Control Board', tv_admin_mod_control($pdo)); return true;
