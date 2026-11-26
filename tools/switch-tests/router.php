<?php
$root = getenv('ROOT'); $app = $root . '/app';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(js|css|image)/#', $path)) return false;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['csrf_token'] = 'tok';
require_once $app . '/lib/Database.php'; require_once $app . '/admin/Access.php'; require_once $app . '/admin/AdminModules.php';
foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'department' => '', 'name' => 'Test', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') file_put_contents('' . sys_get_temp_dir() . '/sw-posts.log', json_encode($_POST) . "\n", FILE_APPEND);
require_once $app . '/ui/app-shell.php'; require_once $app . '/ui/section-rails.php'; require_once $app . '/admin/sections.php';
$pdo = Database::connection();
tv_page_start('Switches', ['wide' => true, 'no_crumbs' => true, 'rail' => tv_admin_rail('config'), 'rail_label' => 'Admin', 'active' => 'config']);
require $app . '/admin/kit-css.php';
require_once $app . '/admin/widgets.php'; echo '<div class="ak-body" data-ak-section="config/switches">' . tv_w_section($pdo, Modules::module('config'), 'switches') . '</div>';
echo '<script src="/js/admin-kit.js"></script>';
tv_page_end();
return true;
