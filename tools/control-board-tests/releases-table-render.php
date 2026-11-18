<?php
$app = $argv[1]; $which = $argv[2];
require $app . '/lib/Database.php'; require $app . '/admin/Access.php';
$pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; require_once $app . '/lib/Releases.php';
$_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/sections.php';
$tools = require $app . '/admin/tools.php'; $t = $tools['config/releases'];
if ($which === 'json') { [$rows, $total] = ($t['list'])($pdo, ['state' => $argv[3] ?? ''], 1); echo json_encode(['ok' => true, 'rows' => $rows, 'total' => $total, 'counts' => new stdClass]); exit; }
ob_start(); include $app . '/admin/kit-css.php'; $css = ob_get_clean();
if ($which === 'item') $body = tv_admin_mod_releases($pdo, (int) ($argv[3] ?? 121));
else { $cfg = ['tool' => 'config/releases', 'title' => $t['title'], 'columns' => $t['columns'], 'filters' => array_map(fn ($f) => [$f[0], $f[1], array_map(fn ($k, $v) => [(string) $k, $v], array_keys($f[2]), $f[2])], $t['filters']), 'stages' => [], 'pipeline' => false, 'countBy' => '', 'open' => $t['open'], 'create' => null];
  $body = tv_admin_mod_releases($pdo) . '<section class="ak-list" data-ak-table="' . tv_w_e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"><div class="ak-load">Loading…</div></section>'; }
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '<style>body{margin:0;padding:0 .5rem;font:15px system-ui}*{box-sizing:border-box}</style></head><body>' . $body . '<script src="/js/tv-ask.js"></script><script src="/js/admin-kit.js"></script></body></html>';
