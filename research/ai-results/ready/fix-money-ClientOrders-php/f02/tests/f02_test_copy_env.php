<?php
// tools/orders-tests/f02_test_copy_env.php: f02's guard. No database, no QuickBooks call, no money.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };

$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'production']) !== '', 'a test copy set to production is refused');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => '']) !== '', 'a test copy with no mode recorded is refused');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'sandbox']) === '', 'a test copy in sandbox is allowed');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'mock']) === '', 'a test copy in mock is allowed');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'Ann Lee', 'qb_env' => 'production']) === '', 'a real order in production is not touched');

$threw = false;
try { ClientOrders::inOrderEnv(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'production'], fn () => 'charged'); } catch (RuntimeException $x) { $threw = true; }
$ok($threw, 'inOrderEnv refuses before running anything for a test copy in production');

$src = file_get_contents($root . '/app/lib/ClientOrders.php');
$ok(str_contains($src, 'testEnvBlock(') && substr_count($src, 'testEnvBlock(') >= 3, 'testEnvBlock is called from more than its own definition (lane: wire into cantInvoice)');
$ok(substr_count($src, 'inOrderEnv(') >= 2, 'inOrderEnv is used (lane: wrap invoiceFor and the charge)');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
