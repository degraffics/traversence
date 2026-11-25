<?php
// tools/order-tests/m2b-seed.php <root>: M2b's orders on the design harness, after m2-seed.php (it runs it first). Test
// only, never shipped. Each order's one work order at Competition Printing, in one of how it goes (decisions/0101's note,
// 2026-11-24): leg 1 the work order's delivery until it's received, then leg 2 the item's ship style.
//   Leg Make Co      Will Call, in production
//   Leg Ready Co     Will Call, ready at the vendor since 22 days (Order #12's case): Receive
//   Leg Courier Co   Will Call, ready, a courier we book: Rapid Couriers · 55821
//   Leg ToUs Co      Ship UPS, no ship-to, shipped: on its way to us
//   Leg Blind Co     Blind Drop Ship, shipped by FedEx: straight to the client
//   Leg Direct Co    Ship UPS to the client's ship-to, shipped
//   Leg Pick Co      Will Call, received 2 days ago; the item is Pick Up
//   Leg Send Co      Will Call, received; the item goes by Courier, no number yet: Edit the shipping
//   Leg Out Co       Will Call, received; the item's courier booked and shipped
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
ob_start(); passthru('php ' . escapeshellarg(__DIR__ . '/m2-seed.php') . ' ' . escapeshellarg($root)); ob_end_clean();
$pdo = new PDO('mysql:host=127.0.0.1;dbname=tvh', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$d = fn ($days) => date('Y-m-d H:i:s', time() + $days * 86400);
$mk = function (string $name, array $f, string $istat = '', array $lf = []) use ($pdo, $d): int {
    $f += ['created_by' => 1, 'status' => 'paid', 'total_cents' => 80910, 'subtotal_cents' => 80910, 'tax_cents' => 0, 'bill_name' => 'Ana Ortiz', 'bill_email' => 'ana@example.com', 'client_company' => $name,
           'created_at' => $d(-30), 'updated_at' => $d(-1), 'sent_at' => $d(-28), 'approved_at' => $d(-27), 'paid_at' => $d(-27), 'expires_at' => $d(4), 'access_token' => bin2hex(random_bytes(20)), 'stage' => 'ordered', 'pay_mode' => 'full'];
    $pdo->prepare('INSERT INTO orders (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    $id = (int) $pdo->lastInsertId();
    $lf += ['order_id' => $id, 'product' => 'custom', 'title' => 'Gift Certificate Vouchers', 'qty' => 9000, 'unit_cents' => 80910, 'item_status' => $istat, 'created_at' => $d(-30)];
    $pdo->prepare('INSERT INTO order_lines (' . implode(',', array_keys($lf)) . ') VALUES (' . implode(',', array_fill(0, count($lf), '?')) . ')')->execute(array_values($lf));
    $pdo->prepare("INSERT INTO order_ledger (order_id, kind, amount_cents, method, request_key, created_at) VALUES (?, 'charge', 80910, 'Check', ?, ?)")->execute([$id, 'seed-leg-' . $id, $d(-27)]);
    return $id;
};
$wo = function (int $oid, array $f) use ($pdo, $d): int {
    $lid = (int) $pdo->query("SELECT id FROM order_lines WHERE order_id = $oid ORDER BY id LIMIT 1")->fetchColumn();
    $f += ['order_id' => $oid, 'wo_no' => '20261027-CP' . str_pad((string) $oid, 4, '0', STR_PAD_LEFT), 'vendor' => 'Competition Printing', 'vendor_email' => 'orders@vendor.example', 'line_ids' => (string) $lid, 'files' => '[]', 'status' => 'production', 'status_at' => $d(-5),
           'delivery' => 'will_call', 'cost_cents' => 45000, 'cost_state' => 'accepted', 'sent_at' => $d(-26), 'token' => bin2hex(random_bytes(20)), 'expires_at' => $d(30)];
    $pdo->prepare('INSERT INTO order_work_orders (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return (int) $pdo->lastInsertId();
};
$log = fn (int $w, string $st, string $note, int $days) => $pdo->prepare('INSERT INTO order_work_order_log (wo_id, status, note, by_vendor, staff_id, created_at) VALUES (?, ?, ?, 0, 1, ?)')->execute([$w, $st, $note, $d($days)]);
$out = [];
$out['Leg Make Co'] = $o = $mk('Leg Make Co', [], 'production'); $wo($o, []);
$out['Leg Ready Co'] = $o = $mk('Leg Ready Co', ['stage' => 'ready'], 'ready'); $wo($o, ['status' => 'ready', 'status_at' => $d(-22)]);
$out['Leg Courier Co'] = $o = $mk('Leg Courier Co', ['stage' => 'ready'], 'ready'); $w = $wo($o, ['status' => 'ready', 'status_at' => $d(-3), 'carrier' => 'Rapid Couriers', 'tracking' => '55821']); $log($w, 'ready', 'Courier booked: Rapid Couriers 55821', -1);
$out['Leg ToUs Co'] = $o = $mk('Leg ToUs Co', ['stage' => 'shipped'], 'shipped'); $wo($o, ['status' => 'shipped', 'status_at' => $d(-2), 'delivery' => 'ups', 'carrier' => 'UPS', 'tracking' => '1Z999AA10123456784']);
$out['Leg Blind Co'] = $o = $mk('Leg Blind Co', ['stage' => 'shipped'], 'shipped'); $wo($o, ['status' => 'shipped', 'status_at' => $d(-1), 'delivery' => 'blind_drop', 'carrier' => 'FedEx', 'tracking' => '7712 3456 7890']);
$out['Leg Direct Co'] = $o = $mk('Leg Direct Co', ['stage' => 'shipped'], 'shipped'); $wo($o, ['status' => 'shipped', 'status_at' => $d(-1), 'delivery' => 'ups', 'ship_to' => "Ana Ortiz\n12 Main St, Show Low AZ 85901", 'carrier' => 'UPS', 'tracking' => '1Z55']);
$out['Leg Pick Co'] = $o = $mk('Leg Pick Co', ['stage' => 'ready'], 'ready', ['ship_style' => 'pickup']); $wo($o, ['status' => 'received', 'status_at' => $d(-2), 'received_by' => 'Jason', 'received_on' => date('Y-m-d', time() - 2 * 86400)]);
$out['Leg Send Co'] = $o = $mk('Leg Send Co', ['stage' => 'ready'], 'ready', ['ship_style' => 'courier']); $wo($o, ['status' => 'received', 'status_at' => $d(-1), 'received_by' => 'Jason', 'received_on' => date('Y-m-d', time() - 86400)]);
$out['Leg Out Co'] = $o = $mk('Leg Out Co', ['stage' => 'shipped'], 'shipped', ['ship_style' => 'courier', 'ship_carrier' => 'other', 'tracking' => 'Rapid Couriers 55822']); $wo($o, ['status' => 'received', 'status_at' => $d(-2), 'received_by' => 'Jason', 'received_on' => date('Y-m-d', time() - 2 * 86400)]);
$pdo->prepare("INSERT INTO order_events (order_id, event, note, created_at) VALUES (?, 'item_status', 'Gift Certificate Vouchers: Shipped · Rapid Couriers 55822', ?)")->execute([$out['Leg Out Co'], $d(-1)]);
foreach ($out as $n => $id) echo "$n=$id\n";
