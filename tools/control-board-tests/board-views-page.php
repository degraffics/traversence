<?php
// The board's views as the browser gets them, for board-views.cjs (as board-page.php does for the board): `php
// board-views-page.php <root>/app [page|ir <n>|report <scope> <range>|file]`. The gauges are read from the repo's own
// site-audit/results/gauges.json (ControlGauges::$source: a local copy, nothing fetched). Never live.
$app = $argv[1]; require $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
if (class_exists('ControlGauges')) ControlGauges::$source = getenv('GAUGES_FILE') ?: dirname(__DIR__, 2) . '/site-audit/results/gauges.json';
$what = $argv[2] ?? 'page';
if ($what === 'ir') $_GET['ir'] = (string) ($argv[3] ?? '1');
if ($what === 'report' || $what === 'file') { $_GET['report'] = (string) ($argv[3] ?? ''); $_GET['range'] = (string) ($argv[4] ?? 'week'); }
if ($what === 'file') { require_once $app . '/lib/ControlReport.php'; echo ControlReport::file(ControlReport::build($pdo, $_GET['report'], $_GET['range'])); exit; }
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$body = tv_admin_mod_control($pdo);
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '</head><body>' . $body
    . '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script></body></html>';
