<?php
// For mirror-words.cjs: PHP's server router (php -S … page-router.php) drawing System Configuration → Staging on the live
// side as the admin draws it (tv_admin_mod_staging), as the Founder. ROOT names the bench (app/ and website/); its staging
// state is seeded by mirror-seed.php. Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path'];
if (str_starts_with($path, '/js/')) { $f = $root . '/website' . $path; if (!is_file($f)) { http_response_code(404); return true; } header('Content-Type: text/javascript'); readfile($f); return true; }
if ($path === '/api/auth/session.php') { header('Content-Type: application/json'); echo '{"csrf_token":"x"}'; return true; }
if ($path === '/admin/api/config/staging') { header('Content-Type: application/json'); echo '{"ok":true}'; return true; }   // the page's poll: nothing changes under the test
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/admin/Access.php'; require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start(); $_SESSION['csrf_token'] = 'x';
foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); }
require_once $app . '/admin/sections.php';
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$js = '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script>';
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Staging</title>' . $css . '</head><body><main class="ak-main" style="padding:3px">'
    . tv_admin_mod_staging($pdo) . '</main>' . $js . '</body></html>';
