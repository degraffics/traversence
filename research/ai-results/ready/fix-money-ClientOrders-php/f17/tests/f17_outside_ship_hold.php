<?php
// tools/orders-tests/f17_outside_ship_hold.php: f17. Static checks (shipHold reads Orders::money from the DB; the DB test is in 'verify').
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$r = new ReflectionMethod('ClientOrders', 'shipHold');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(!str_contains($s, "'outside'"), "shipHold no longer lets 'outside' orders through");
$ok(str_contains($s, 'self::overridden('), "an admin's release with a reason still lets it through");
$ok(str_contains($s, "Orders::money("), 'it still checks what is paid');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
