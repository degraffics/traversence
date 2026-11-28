<?php
// tools/orders-tests/f01_paid_in_quickbooks_acceptance.php: ACCEPTANCE test for f01. It FAILS until the lane writes the fix.
// Static checks only: no database, no QuickBooks, no money (QuickBooksPay::guardReal() is never reached).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$src = function (string $m): string {
    $r = new ReflectionMethod('ClientOrders', $m);
    $lines = file($r->getFileName());
    return implode('', array_slice($lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
};

if (!method_exists('ClientOrders', 'paidInQuickBooks')) { echo "FAIL: ClientOrders::paidInQuickBooks not found (renamed?)\n"; exit(1); }
$s = $src('paidInQuickBooks');
$ok(str_contains($s, 'GET_LOCK') && str_contains($s, 'tv-pay-'), 'paidInQuickBooks takes the tv-pay- lock (as pay() and applyQuickBooks do)');
$ok(str_contains($s, 'RELEASE_LOCK'), 'paidInQuickBooks releases the lock');
$ok(!preg_match("/fromQuickBooks\\([^)]*\\)\\s*===\\s*''/", $s), "no fallback that records the whole balance when fromQuickBooks() returns ''");

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
