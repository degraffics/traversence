<?php
// harness only: orders in every state the board and the order page show (M1)
$pdo = new PDO('mysql:host=127.0.0.1;dbname=tvh', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['orders', 'order_lines', 'order_ledger', 'order_events', 'order_proofs', 'order_work_orders', 'order_feedback'] as $t) $pdo->exec("DELETE FROM $t");
$pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1');
$d = fn ($days) => date('Y-m-d H:i:s', time() + $days * 86400);
$ids = [];
$o = function (string $name, array $f, array $line = []) use ($pdo, $d, &$ids) {
    $f += ['created_by' => 1, 'status' => 'pending', 'total_cents' => 80910, 'subtotal_cents' => 80910, 'bill_name' => 'Ana Ortiz', 'bill_email' => 'ana@example.com', 'client_company' => $name, 'created_at' => $d(-20), 'updated_at' => $d(-1), 'sent_at' => $d(-10), 'approved_at' => $d(-8), 'expires_at' => $d(4), 'access_token' => bin2hex(random_bytes(20))];
    $pdo->prepare('INSERT INTO orders (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_lines (order_id, product, title, qty, unit_cents, item_status, created_at) VALUES (?, 'product', 'Door hangers', 5000, 16, ?, ?)")->execute([$id, $line['item_status'] ?? '', $d(-20)]);
    $ids[$name] = $id; return $id;
};
$pay = fn ($id, $c, $kind = 'charge') => $pdo->prepare("INSERT INTO order_ledger (order_id, kind, amount_cents, method, request_key, created_at) VALUES (?, ?, ?, 'Check', ?, NOW())")->execute([$id, $kind, $c, 'k' . $id . $kind . $c]);
$ev = fn ($id, $e, $n, $days = -5) => $pdo->prepare('INSERT INTO order_events (order_id, event, note, created_at) VALUES (?, ?, ?, ?)')->execute([$id, $e, $n, $d($days)]);
$o('Draft Co', ['status' => 'draft', 'sent_at' => null, 'approved_at' => null]);
$i = $o('Sent Co', ['status' => 'sent', 'approved_at' => null, 'sent_at' => $d(-2)]);
$pdo->prepare("INSERT INTO order_proofs (order_id, line_id, version, status, file_name, mime) SELECT ?, id, 1, 'waiting', 'proof.pdf', 'application/pdf' FROM order_lines WHERE order_id = ?")->execute([$i, $i]);
$i = $o('Expired Co', ['status' => 'sent', 'approved_at' => null, 'sent_at' => $d(-19), 'expires_at' => $d(-5)]);
$pdo->prepare("INSERT INTO order_proofs (order_id, line_id, version, status, file_name, mime) SELECT ?, id, 1, 'waiting', 'proof.pdf', 'application/pdf' FROM order_lines WHERE order_id = ?")->execute([$i, $i]);
$c = $o('Changes Co', ['status' => 'sent', 'approved_at' => null, 'review' => 'changes']);
$pdo->prepare("INSERT INTO order_proofs (order_id, line_id, version, status, decided_at) SELECT ?, id, 2, 'changes', ? FROM order_lines WHERE order_id = ?")->execute([$c, $d(-1), $c]);
$o('Deposit Co', ['pay_mode' => 'deposit', 'deposit_cents' => 41200, 'total_cents' => 178600, 'subtotal_cents' => 178600, 'approved_at' => $d(0)]);
$i = $o('Deposit Paid Co', ['pay_mode' => 'deposit', 'deposit_cents' => 41200, 'total_cents' => 178600, 'subtotal_cents' => 178600, 'stage' => 'production']); $pay($i, 41200, 'deposit');
$i = $o('Paid Co', ['status' => 'paid', 'stage' => 'production', 'paid_at' => $d(-3)]); $pay($i, 80910);
$i = $o('Vendor Co', ['status' => 'paid', 'stage' => 'ordered', 'paid_at' => $d(-3)]); $pay($i, 80910);
$pdo->prepare("INSERT INTO order_work_orders (order_id, wo_no, vendor, status, sent_at) VALUES (?, 'WO-1', 'Print Vendor', 'accepted', NOW())")->execute([$i]);
$i = $o('Mesa Ridge Lodge', ['stage' => 'ready', 'pay_mode' => 'outside', 'invoice_no' => '1044', 'delivery' => 'pickup'], ['item_status' => 'ready']); $ev($i, 'released', 'Released before payment by an admin: agreed terms', -27); $ev($i, 'stage', 'Ready for pickup', -21);
$i = $o('Released Co', ['stage' => 'ready', 'pay_mode' => 'full'], ['item_status' => 'ready']); $ev($i, 'released', 'Released before payment by an admin: agreed terms', -27); $ev($i, 'stage', 'Ready for pickup', -2);
$i = $o('Shipped Paid Co', ['status' => 'paid', 'stage' => 'shipped', 'tracking' => '1Z999'], ['item_status' => 'shipped']); $pay($i, 80910); $ev($i, 'stage', 'Shipped', -2);
$i = $o('Delivered Owed Co', ['stage' => 'shipped', 'received_at' => $d(-1)], ['item_status' => 'shipped']); $ev($i, 'released', 'Released before payment by an admin: net terms', -9); $ev($i, 'stage', 'Shipped', -3);
$i = $o('Complete Co', ['status' => 'paid', 'stage' => 'complete'], ['item_status' => 'complete']); $pay($i, 80910);
$o('Cancelled Co', ['status' => 'void', 'approved_at' => null]);
$i = $o('Past Due Co', ['approved_at' => $d(-40), 'due_terms' => 'net15']);
foreach ($ids as $n => $id) echo "$n=$id\n";
