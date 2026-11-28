<?php
// tools/orders-tests/f15_charge_then_ledger_acceptance.php: ACCEPTANCE test for f15. FAILS until the fix is written. Static only, no money.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
if (!method_exists('ClientOrders', 'pay')) { echo "FAIL: ClientOrders::pay not found (renamed?)\n"; exit(1); }
$r = new ReflectionMethod('ClientOrders', 'pay');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(str_contains($s, 'needs_books'), 'pay() logs needs_books (with the charge id) when the ledger write after a charge fails');
$ok((bool) preg_match('/catch\s*\(\s*Throwable/', $s), 'pay() catches a failure after the charge instead of losing it');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
