<?php
// tools/orders-tests/m5-destinations-tests.php — Orders M5 (decisions/0177): the per-destination arithmetic.
// Plain PHP, no database, no QuickBooks, no money: OrderDestinations is pure (checked below).
// Run: php tools/orders-tests/m5-destinations-tests.php   (prints PASS/FAIL per check; exit 1 on any FAIL)
declare(strict_types=1);

$lib = null;
foreach ([__DIR__ . '/../../app/lib/OrderDestinations.php', __DIR__ . '/../files/app/lib/OrderDestinations.php'] as $p) if (is_file($p)) { $lib = $p; break; }
if ($lib === null) { echo "FAIL OrderDestinations.php not found\n"; exit(1); }
require_once $lib;

$fails = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $fails++;
};
$M = 15; $D = 65;   // ClientOrders::DEPOSIT_MARKUP, DEPOSIT_DEFAULT (checked against the class in m5-deposit-parity-tests.php)

// ---- purity: no money can move from here --------------------------------------------------------------------------
$src = (string) file_get_contents($lib);
$check('lib never calls QuickBooks', strpos($src, 'QuickBooksPay') === false);
$check('lib takes no database', strpos($src, 'PDO') === false);

// ---- deposit: release A's rule, unchanged ---------------------------------------------------------------------------
$lines = [
    ['id' => 1, 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 30000, 'cost_cents' => 10000, 'ship_cost_cents' => 2000, 'status' => 'open'],
    ['id' => 2, 'title' => 'Stickers', 'qty' => 2, 'unit_cents' => 500, 'cost_cents' => 0, 'status' => 'open'],
    ['id' => 3, 'title' => 'Gone', 'qty' => 1, 'unit_cents' => 99999, 'cost_cents' => 99999, 'status' => 'cancelled'],
];
[$dep, $words, $open, $ids] = OrderDestinations::deposit(['deposit_pct' => 0], 50000, $lines, $M, $D);
$check('deposit = cost × 1.15 + 65% of an uncosted item', $dep === 14450, (string) $dep);   // 12000 × 1.15 = 13800, + 1000 × .65 = 650
$check('deposit words', $words === "items' cost + 15%; 1 item at 65% of price", $words);
$check('uncosted item named', $open === ['Stickers'] && $ids === [2]);
$check('deposit never above the total', OrderDestinations::deposit(['deposit_pct' => 0], 10000, $lines, $M, $D)[0] === 10000);
$check('staff % of the total', OrderDestinations::deposit(['deposit_pct' => 30], 33333, $lines, $M, $D)[0] === 10000);
$check('staff % per invoice: 50% of 101 is 51 (two invoices of 101 make 102, not 101)', OrderDestinations::deposit(['deposit_pct' => 50], 101, [], $M, $D)[0] === 51);

// ---- destinations ---------------------------------------------------------------------------------------------------
$check('own address wins', OrderDestinations::destKey(['delivery' => 'pickup'], ['ship_to' => '12'], false) === 'a12');
$check('ship_to 0 is no address', OrderDestinations::destKey(['delivery' => 'ship'], ['ship_to' => '0'], true) === 'order');
$check('pickup is ours', OrderDestinations::destKey(['delivery' => 'pickup'], ['ship_to' => ''], false) === 'ours');
$check('nothing to deliver is ours', OrderDestinations::destKey(['delivery' => 'none'], [], false) === 'ours');
$check("delivery '' without a shipping line is ours", OrderDestinations::destKey(['delivery' => ''], [], false) === 'ours');
$check("delivery '' with a shipping line is the order's Ship to", OrderDestinations::destKey(['delivery' => ''], [], true) === 'order');
$check('a GO Code is ours', OrderDestinations::destKey(['delivery' => 'ship'], ['product' => 'go_code'], true) === 'ours');

// ---- the plan: one invoice per destination, oldest first ----------------------------------------------------------
$o = ['id' => 9, 'delivery' => 'ship', 'deposit_pct' => 0];
$ls = [
    ['id' => 11, 'product' => 'go_code', 'title' => 'GO Code: Menu', 'qty' => 1, 'unit_cents' => 3000, 'cost_cents' => 0, 'ship_to' => ''],
    ['id' => 12, 'product' => 'custom', 'title' => 'Signs', 'qty' => 2, 'unit_cents' => 5000, 'cost_cents' => 2000, 'ship_cost_cents' => 1000, 'ship_to' => '7'],
    ['id' => 13, 'product' => 'product', 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 20000, 'cost_cents' => 8000, 'ship_to' => ''],
    ['id' => 14, 'product' => 'shipping', 'title' => 'Shipping', 'qty' => 1, 'unit_cents' => 1500, 'cost_cents' => 1200, 'ship_to' => ''],
    ['id' => 15, 'product' => 'custom', 'title' => 'Cancelled', 'qty' => 1, 'unit_cents' => 7777, 'cost_cents' => 1, 'ship_to' => '', 'status' => 'cancelled'],
];
$plan = OrderDestinations::plan($o, $ls, ['order' => 1720, 'a7' => 800], $M, $D);
$check('three invoices, oldest first, ours last', array_column($plan, 'key') === ['order', 'a7', 'ours'], implode(',', array_column($plan, 'key')));
$check("order's Ship to holds the banner and its shipping", $plan[0]['lines'] === [13, 14]);
$check('cancelled line on no invoice', !in_array(15, array_merge(...array_column($plan, 'lines')), true));
$check('totals carry QuickBooks\' tax per invoice', array_column($plan, 'total') === [23220, 10800, 3000], implode(',', array_column($plan, 'total')));
$check('deposit per invoice', array_column($plan, 'deposit') === [10580, 5750, 1950], implode(',', array_column($plan, 'deposit')));
$check("order's deposit is the sum", OrderDestinations::orderDeposit($plan) === 18280);
$check('one destination, one invoice', count(OrderDestinations::plan($o, [$ls[2], $ls[3]], [], $M, $D)) === 1);

// ---- an estimate's deposit, in proportion ---------------------------------------------------------------------------
$s = OrderDestinations::spread(1000, ['order' => 4000, 'a7' => 3000]);
$check('spread in proportion, exact cents', $s === ['order' => 571, 'a7' => 429], json_encode($s));
$s = OrderDestinations::spread(1000, ['order' => 1, 'a7' => 1, 'ours' => 1]);
$check('spread ties go to the older destination', $s === ['order' => 334, 'a7' => 333, 'ours' => 333], json_encode($s));
$s = OrderDestinations::spread(500, ['order' => 0, 'a7' => 0]);
$check('no deposits to weigh: all on the oldest', $s === ['order' => 500, 'a7' => 0], json_encode($s));

// ---- a payment against the invoices -------------------------------------------------------------------------------
$inv = [['key' => 'order', 'total' => 10000, 'deposit' => 4000, 'paid' => 0], ['key' => 'a7', 'total' => 6000, 'deposit' => 3000, 'paid' => 0]];
$a = OrderDestinations::allocate(8000, $inv);
$check('deposits first, then the oldest balance', $a['applied'] === ['order' => 5000, 'a7' => 3000] && $a['left'] === 0, json_encode($a));
$a = OrderDestinations::allocate(20000, $inv);
$check('over the order: the rest is left over', $a['applied'] === ['order' => 10000, 'a7' => 6000] && $a['left'] === 4000, json_encode($a));
$a = OrderDestinations::allocate(5000, [['key' => 'order', 'total' => 10000, 'deposit' => 4000, 'paid' => 4000], ['key' => 'a7', 'total' => 6000, 'deposit' => 3000, 'paid' => 0]]);
$check('a deposit already in is skipped', $a['applied'] === ['order' => 2000, 'a7' => 3000], json_encode($a));
$a = OrderDestinations::allocate(8000, $inv, false);
$check('pay in full: balances only, oldest first', $a['applied'] === ['order' => 8000, 'a7' => 0], json_encode($a));

// ---- the vendor waits on the whole order -----------------------------------------------------------------------------
$check('deposit order needs every deposit', OrderDestinations::releaseNeed('deposit', $plan) === 18280);
$check('pay in full needs the whole total', OrderDestinations::releaseNeed('full', $plan) === 37020);
$check('one invoice\'s deposit alone releases nothing', !OrderDestinations::releaseMet('deposit', $plan, 10580, false));
$check('the whole deposit releases the order', OrderDestinations::releaseMet('deposit', $plan, 18280, false));
$check('an admin\'s release still works', OrderDestinations::releaseMet('full', $plan, 0, true));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
