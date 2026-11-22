<?php
// The Workflows page as the browser gets it, for workflows-page.cjs: `php workflows-page-fixture.php <root>` draws the
// crawler's part of System Configuration → Workflows on the site's tokens and the admin kit's styles, with tv-ask.js and
// tv-time.js. Run after workflows-page.php's seed (it leaves the database as the page reads it).
$root = $argv[1]; $app = $root . '/app';
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php';
if (is_file($app . '/lib/ControlGauges.php')) { require_once $app . '/lib/ControlGauges.php'; $gf = dirname(__DIR__, 2) . '/site-audit/results/gauges.json'; if (is_file($gf)) ControlGauges::$source = $gf; }
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$body = tv_admin_mod_workflows_crawler($pdo);
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Workflows</title>' . $css
    . '<style>body{margin:0;padding:0 16px;background:var(--tv-bg,#FFFBF2);font:15px/1.45 system-ui,sans-serif}</style></head><body>' . $body
    . '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script></body></html>';
