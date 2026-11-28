<?php
// tools/orders-tests/f13_record_payment_lock_acceptance.php: ACCEPTANCE test for f13. FAILS until the fix is written. Static only.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
if (!method_exists('ClientOrders', 'recordPayment')) { echo "FAIL: ClientOrders::recordPayment not found (renamed?)\n"; exit(1); }
$r = new ReflectionMethod('ClientOrders', 'recordPayment');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(str_contains($s, 'GET_LOCK') && str_contains($s, 'tv-pay-'), 'recordPayment takes the tv-pay- lock');
$ok(str_contains($s, 'RELEASE_LOCK') && str_contains($s, 'finally'), 'recordPayment releases it in finally');
$gl = strpos($s, 'GET_LOCK'); $money = strrpos($s, 'Orders::money');
$ok($gl !== false && $money !== false && $money > $gl, 'the amount left is re-read inside the lock');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
