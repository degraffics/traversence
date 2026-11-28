<?php
// tools/orders-tests/f16_deposit_checked_cogs.php: f16, the deposit's pure sum (no DB, no money).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$all = fn (array $l): bool => true;
$none = fn (array $l): bool => false;
$line = fn (array $x): array => $x + ['id' => 1, 'title' => 'Item', 'qty' => 1, 'unit_cents' => 0, 'ship_cents' => 0, 'cost_cents' => 0, 'ship_cost_cents' => 0, 'status' => 'open'];

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000, 'ship_cost_cents' => 200])], 100000, $all);
$ok($c === 1380, 'checked COGS + shipping we pay, +15%: (1000+200)×1.15 = 1380 (got ' . $c . ')');

[$c, , $open] = ClientOrders::depositFromLines([$line(['unit_cents' => 4000, 'ship_cost_cents' => 500])], 100000, $all);
$ok($c === 2600 && count($open) === 1, 'shipping cost only (no COGS) falls back to 65% of price = 2600 (got ' . $c . ')');

[$c, , $open, $ids] = ClientOrders::depositFromLines([$line(['id' => 7, 'unit_cents' => 5000, 'cost_cents' => 1000])], 100000, $none);
$ok($c === 3250 && $ids === [7], 'an unchecked COGS falls back to 65% of price = 3250 and is listed (got ' . $c . ')');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000])], 100, $all);
$ok($c === 100, 'never above the total');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000, 'status' => 'cancelled'])], 100000, $all);
$ok($c === 0, 'a cancelled item counts for nothing');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000]), $line(['id' => 2, 'unit_cents' => 2000])], 100000, fn (array $l): bool => (int) $l['id'] === 1);
$ok($c === 1150 + 1300, 'mixed: 1000×1.15 + 2000×65% = 2450 (got ' . $c . ')');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
