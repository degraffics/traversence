<?php
// tools/orders-tests/f14_received_dedupe_acceptance.php: ACCEPTANCE test for f14. FAILS until the fix is written. Static only.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$body = function (string $m): string { $r = new ReflectionMethod('ClientOrders', $m); return implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)); };

if (!method_exists('ClientOrders', 'received') || !method_exists('ClientOrders', 'recordPayment')) { echo "FAIL: received()/recordPayment() not found\n"; exit(1); }
$t = (new ReflectionMethod('ClientOrders', 'received'))->getReturnType();
$ok($t !== null && (string) $t === 'bool', 'received() returns bool (false = already recorded)');
$s = $body('recordPayment');
$ok(!preg_match("/date\\(\\s*'[^']*i[^']*'\\s*\\)/", $s), 'the duplicate key no longer uses the minute');
$ok(str_contains($s, 'nonce'), 'the duplicate key comes from the form nonce');
$ok((bool) preg_match('/if\s*\(\s*!\s*self::received\(/', $s), 'the QuickBooks post and the event are skipped when received() says it was a duplicate');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
