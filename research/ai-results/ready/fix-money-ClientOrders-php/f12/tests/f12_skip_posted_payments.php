<?php
// tools/orders-tests/f12_skip_posted_payments.php: f12. Static checks (no DB, no QuickBooks, no money).
// A database test needs MySQL (GET_LOCK) and is listed in release.json 'verify' for the lane.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$body = function (string $m): string { $r = new ReflectionMethod('ClientOrders', $m); return implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)); };

$s = $body('applyQuickBooks');
$ok(str_contains($s, 'qb_payment_id'), 'applyQuickBooks checks order_ledger.qb_payment_id');
$ok(strpos($s, 'qb_payment_id = ?') !== false && strpos($s, 'order_id = ? AND qb_payment_id') !== false, 'the check is scoped to this order (sandbox and production ids can collide)');
$ok(strpos($s, '$posted') < strpos($s, 'self::received('), 'the skip comes before anything is recorded');
$ok(str_contains($s, "GET_LOCK") && str_contains($s, 'RELEASE_LOCK'), 'still under the tv-pay- lock');
$ok(method_exists('ClientOrders', 'hasLedgerQbId'), 'hasLedgerQbId exists');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
