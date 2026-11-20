<?php
// For r3.cjs: after r3.php's state, the Service register (`register`) or the board's alarms (`alarms`) as the browser gets
// them, or Pulse's stream (`pulse`). `php r3-page.php <root>/app register|alarms|pulse`
$app = $argv[1]; $mode = $argv[2] ?? 'register'; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/control-console.php';
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
if ($mode === 'alarms') {
    $al = array_map(function ($a) { $a['fix'] = json_decode((string) $a['fix'], true) ?: []; return $a; }, array_values(array_filter(ControlAlarms::list($pdo, 50), fn ($a) => $a['cleared_at'] === null)));
    $btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button class="ak-b cb-xs" data-cb-act="' . $act . '">' . htmlspecialchars($label) . '</button>';
    $body = '<div id="cb">' . @tv_cb_alarms($al, ControlAlarms::counts($pdo), [], fn ($p) => 'p', true, '', $btn) . '</div>';
} elseif ($mode === 'pulse') { require_once $app . '/ui/pulse-ui.php'; $body = @tv_pulse_board($pdo, ['id' => 1, 'role' => 'founder', 'dept' => '', 'display_name' => 'J'], 1, 'stream'); }
else $body = @tv_admin_mod_register($pdo);
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '</head><body>' . $body
    . '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script><script src="/js/admin-outside.js"></script></body></html>';
