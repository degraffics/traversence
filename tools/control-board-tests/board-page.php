<?php
// The board as the browser gets it, for board-rendered.cjs: `php board-page.php <root>/app page` draws the page (with the
// board's script and the table component); `php board-page.php <root>/app rows <q> <show>` answers the parts' table.
$app = $argv[1]; require $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
if (($argv[2] ?? '') === 'rows') {
    [$rows, $total, $counts] = tv_cb_table_rows($pdo, ['q' => $argv[3] ?? '', 'show' => $argv[4] ?? '']);
    echo json_encode(['ok' => true, 'rows' => $rows, 'total' => $total, 'counts' => (object) $counts, 'per' => 10, 'sortable' => []]); exit;
}
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
if (getenv('PROC')) $_GET['proc'] = (string) getenv('PROC');   // a part's own page (board-phone.cjs)
$body = tv_admin_mod_control($pdo);
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '</head><body>' . $body
    . '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script></body></html>';
