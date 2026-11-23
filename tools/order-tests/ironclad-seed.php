<?php
// tools/order-tests/ironclad-seed.php <root>: orders for ironclad.cjs on the design harness (test only, never shipped).
// For each width: a sent order (full), one expired, one on deposit terms, one paid outside QuickBooks. Prints their
// private links' tokens as JSON.
declare(strict_types=1);
$root = $argv[1]; putenv('QB_ENV=mock');
foreach (['Database', 'Orders', 'Products', 'ClientOrders'] as $l) require_once $root . '/app/lib/' . $l . '.php';
$pdo = Database::connection();
$pid = (int) $pdo->query("SELECT id FROM products WHERE sku = 'IRON-CARDS'")->fetchColumn();
$mk = function (array $over) use ($pdo, $pid): string {
    $oid = ClientOrders::create($pdo, 1, ['name' => 'Dana Ruiz', 'email' => 'dana@example.com', 'company' => 'White Mountain Outfitters', 'phone' => '']);
    $lid = ClientOrders::addProduct($pdo, $oid, $pid, 500, [0 => 'Matte'], '', '', 1);
    $pdo->prepare("INSERT INTO order_proofs (order_id, line_id, file_name, mime, status, kind) VALUES (?, ?, 'p.pdf', 'application/pdf', 'approved', 'proof')")->execute([$oid, $lid]);
    foreach ($over as $k => $v) $pdo->prepare("UPDATE orders SET $k = ? WHERE id = ?")->execute([$v, $oid]);
    ClientOrders::send($pdo, $oid, 14, 1);
    if (!empty($over['_expired'])) {}
    return (string) Orders::get($pdo, $oid)['access_token'];
};
$out = [];
foreach ([1280, 390] as $w) {
    $exp = $mk([]); $pdo->prepare('UPDATE orders SET expires_at = ? WHERE access_token = ?')->execute([date('Y-m-d H:i:s', time() - 3600), $exp]);
    $out[$w] = ['full' => $mk([]), 'expired' => $exp, 'deposit' => $mk(['pay_mode' => 'deposit', 'deposit_pct' => 65]), 'outside' => $mk(['pay_mode' => 'outside'])];
}
echo json_encode($out);
