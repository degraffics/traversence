<?php
// For config-rail.cjs: an admin page drawn by the real shell with the real side menu (tv_admin_rail), on the bench.
// ROOT names the root; AS=founder|staff picks who is signed in (Access's staff login set directly, as the team tests do).
$root = getenv('ROOT'); $app = $root . '/app';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(js|css|image)/#', $path)) return false;   // the site's own files from website/ (php -S -t)
if (!str_starts_with($path, '/admin/config/')) { http_response_code(404); echo 'not here'; return true; }
require_once $app . '/lib/Database.php'; require_once $app . '/admin/Access.php'; require_once $app . '/admin/AdminModules.php';
$as = getenv('AS') ?: 'founder';
$lv = ['founder' => ['founder', ''], 'engdir' => ['director', 'eng'], 'director' => ['director', 'ops'], 'staff' => ['staff', 'ops']][$as] ?? ['founder', ''];
foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => $as === 'founder' ? 'owner' : 'staff', 'level' => $lv[0], 'department' => $lv[1], 'name' => 'Test ' . $as, 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); }
require_once $app . '/ui/app-shell.php'; require_once $app . '/ui/section-rails.php';
tv_page_start('System Configuration', ['wide' => true, 'no_crumbs' => true, 'rail' => tv_admin_rail((string) ($_GET['area'] ?? 'config')), 'rail_label' => 'Admin', 'active' => (string) ($_GET['area'] ?? 'config')]);
echo '<main><h1>' . htmlspecialchars($path) . '</h1><p>The page.</p></main>';
tv_page_end();
return true;
