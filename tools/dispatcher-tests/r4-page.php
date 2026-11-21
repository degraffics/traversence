<?php
// For r4.cjs: an upload's staff threads as Pulse draws them inside their message (UploadThreads::pulseBody), after
// r4.php's kind of state: a place's photos with an open report, and an order's files (`thread`, the default), or Pulse's
// stream with that report's case (`pulse`). `php r4-page.php <root>/app [thread|pulse]`
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/ui/app-shell.php'; require_once $app . '/lib/UploadThreads.php'; require_once $app . '/lib/CommsBody.php';
require_once $app . '/ui/theme-tokens.php';
$pdo->exec("UPDATE journeys SET status = 'published' WHERE id = 40");
$pdo->exec("INSERT INTO content_reports (reporter_id, kind, target_id, reason, note, status, created_at) VALUES (6, 'journey', 40, 'spam', 'A very long note that keeps going so the line has to wrap on a phone without pushing anything sideways at all', 'open', NOW())");
if (($argv[2] ?? '') === 'pulse') {
    require_once $app . '/admin/widgets.php'; $_SESSION = ['csrf_token' => 'x']; require_once $app . '/admin/sections.php'; require_once $app . '/ui/admin-hub.php'; require_once $app . '/ui/pulse-ui.php';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . tv_theme_tokens() . '</head><body><script>window.appCsrfToken="x";</script><script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script>';
    echo @tv_pulse_board($pdo, ['id' => 1, 'role' => 'founder', 'level' => 'founder', 'department' => '', 'dept' => '', 'display_name' => 'J'], 1, 'stream'); exit;
}
$m = $pdo->query("SELECT * FROM comms_messages WHERE thing = 'journey:40' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$o = $pdo->query("SELECT * FROM comms_messages WHERE thing = 'order:9:files' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$a = UploadThreads::pulseBody($pdo, $m, (int) $m['to_user'])['html']; $b = UploadThreads::pulseBody($pdo, $o, (int) $o['to_user'])['html'];
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . tv_theme_tokens() . '<style>body{background:var(--tv-canvas);margin:0;padding:3px}</style></head><body>'
   . '<div id="m1">' . CommsBody::wrap($a) . '</div><div id="m2">' . CommsBody::wrap($b) . '</div>'
   . '<script>window.appCsrfToken="x";</script><script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/pulse-msg.js"></script></body></html>';
