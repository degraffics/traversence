<?php
// One case for who.php, in its own process (Access caches who's acting): php case.php <root>/app <case> [web|cli]
// Cases: member (signed in, no staff login), staff (a staff login, Service), broken (a staff login whose lookup fails),
// admin_switchover (no staff login, an admin page, before an owner's staff login is active). Prints JSON: who, can, and the
// order_proof and order_file targets' check (the message it stops with, or "passed the staff check").
declare(strict_types=1);
[$app, $case, $mode] = [rtrim($argv[1], '/'), $argv[2], $argv[3] ?? 'web'];
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Uploads.php'; require_once $app . '/admin/Access.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (property_exists('Access', 'cliTest')) Access::$cliTest = $mode === 'cli';
$_SERVER['REQUEST_URI'] = $case === 'admin_switchover' ? '/admin/backoffice/orders' : '/api/upload.php';
$_SESSION = ['user_id' => 501];
if ($case === 'staff' || $case === 'broken') $_SESSION['tv_staff'] = 7;
$out = ['who' => Access::who(), 'can' => Access::can('backoffice/orders.edit', ['min' => 'staff', 'depts' => ['fin', 'svc', 'sal']])];
$def = (new ReflectionMethod('Uploads', 'def')); $def->setAccessible(true);
foreach (['order_proof', 'order_file'] as $tg) {
    try { ($def->invoke(null, $tg)['check'])($pdo, 501, ['order' => 99, 'line' => 0], ['name' => 'p.pdf', 'ext' => 'pdf', 'size' => 10]); $out[$tg] = 'passed everything'; }
    catch (Throwable $x) { $m = $x->getMessage(); $out[$tg] = preg_match('/staff login|role can|Needs /i', $m) ? 'refused: ' . $m : 'passed the staff check (stopped later: ' . mb_substr($m, 0, 60) . ')'; }
}
echo json_encode($out);
