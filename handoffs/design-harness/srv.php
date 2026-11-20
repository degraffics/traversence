<?php
// harness only: what the live .htaccess does for the admin (pretty paths to website/admin/router.php)
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); $f = __DIR__ . '/../root/website' . $p;
if (preg_match('#^/admin(/|$)#', $p) && !is_file($f) && !preg_match('#^/admin/api/#', $p)) { $_SERVER['SCRIPT_NAME'] = '/admin/router.php'; chdir(__DIR__ . '/../root/website/admin'); require __DIR__ . '/../root/website/admin/router.php'; return true; }
if (preg_match('#^/admin/api/#', $p) && !is_file($f)) { chdir(__DIR__ . '/../root/website/admin'); require __DIR__ . '/../root/website/admin/api.php'; return true; }
return false;
