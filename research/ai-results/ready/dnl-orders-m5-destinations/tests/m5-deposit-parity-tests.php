<?php
// tools/orders-tests/m5-deposit-parity-tests.php — ClientOrders::depositFor after M5's region-1 gives release A's numbers.
// The lines are passed in and no staff % path reads the database, so the PDO is an empty in-memory SQLite.
// No QuickBooks, no money. SKIP (not FAIL) when ClientOrders or pdo_sqlite isn't loadable here.
declare(strict_types=1);

$co = __DIR__ . '/../../app/lib/ClientOrders.php';
if (!is_file($co)) { echo "SKIP ClientOrders.php not found from here (run from tools/orders-tests/)\n"; exit(0); }
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "SKIP pdo_sqlite not available\n"; exit(0); }
require_once $co;
$pdo = new PDO('sqlite::memory:');

$fails = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $fails++;
};

$check('markup is 15', ClientOrders::DEPOSIT_MARKUP === 15);
$check('default is 65', ClientOrders::DEPOSIT_DEFAULT === 65);
$lines = [
    ['id' => 1, 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 30000, 'cost_cents' => 10000, 'ship_cost_cents' => 2000, 'status' => 'open'],
    ['id' => 2, 'title' => 'Stickers', 'qty' => 2, 'unit_cents' => 500, 'cost_cents' => 0, 'status' => 'open'],
];
$r = ClientOrders::depositFor($pdo, ['id' => 0, 'deposit_pct' => 0], 50000, $lines);
$check('cost rule unchanged', $r === [14450, "items' cost + 15%; 1 item at 65% of price", ['Stickers'], [2]], json_encode($r));
$r = ClientOrders::depositFor($pdo, ['id' => 0, 'deposit_pct' => 30], 33333);   // no lines: the % path never reads them
$check('staff % unchanged, no read', $r === [10000, '30% of the total, as set', [], []], json_encode($r));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
