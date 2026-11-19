<?php
// For peek-anchor.cjs: `php peek-anchor-page.php <root>/app board` is a page with an alarm's "Open its card" link (the
// board's own tv_cb_evidence); `… register` is System Configuration → Service register as the side panel loads it.
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php'; require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$head = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '</head><body>';
$foot = '<script src="/js/tv-ask.js"></script><script src="/js/admin-kit.js"></script></body></html>';
if (($argv[2] ?? '') === 'register') { require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; echo $head . tv_admin_mod_register($pdo) . $foot; exit; }
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
[$label, $url] = tv_cb_evidence(['proc' => 'svc:' . ($argv[3] ?? 'aws'), 'akey' => 'x']);
echo $head . '<p><a id="go" href="' . htmlspecialchars($url) . '" data-peek-frame>' . htmlspecialchars($label) . '</a></p>' . $foot;
