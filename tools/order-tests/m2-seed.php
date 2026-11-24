<?php
// tools/order-tests/m2-seed.php <root>: M2's states on the design harness, after m1b-seed.php (it runs it first). Test
// only, never shipped; QuickBooks in mock mode. Deposit Co: a $412.00 check recorded here, never processed (Cancel the
// record). Vendor Co: its work order accepted on Nov 3 (Cancel greyed with the date). Paid Co and Deposit Paid Co: paid,
// processed (Refund…; a cancel before acceptance is a refund for Jason's OK).
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
ob_start(); passthru('php ' . escapeshellarg(__DIR__ . '/m1b-seed.php')); ob_end_clean();
require_once $root . '/app/lib/Database.php'; require_once $root . '/app/lib/Orders.php'; require_once $root . '/app/lib/ClientOrders.php';
$pdo = Database::connection();
try { $pdo->exec('DELETE FROM order_refund_requests'); } catch (Throwable) {}
$id = fn (string $n) => (int) $pdo->query('SELECT id FROM orders WHERE client_company = ' . $pdo->quote($n))->fetchColumn();
$pdo->exec("UPDATE orders SET stage = '' WHERE stage IS NULL");
ob_start(); ClientOrders::recordPayment($pdo, $id('Deposit Co'), 41200, 'check', '2231', 1, false); ob_end_clean();
$pdo->prepare("INSERT INTO order_events (order_id, event, note, created_at) VALUES (?, 'work_order_accepted', 'Work order WO-1 (Print Vendor): Accepted by Sam', '2026-11-03 10:00:00')")->execute([$id('Vendor Co')]);
// QB Open Co: a $500.00 check received on its QuickBooks invoice (mock), $100.00 of it refunded with Jason's OK: the Refund
// Receipt made, the money still to send back by a person (Send it back, ✓ Sent)
$q = $id('QB Open Co'); ob_start(); ClientOrders::readQuickBooks($pdo, $q, true); ClientOrders::recordPayment($pdo, $q, 50000, 'check', '3001', 1, false); ob_end_clean();
$lid = (int) $pdo->query("SELECT id FROM order_ledger WHERE order_id = $q AND request_key LIKE 'out-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
Orders::record($pdo, $q, 'refund', 10000, 'rr-seed-' . $q, 'quickbooks', 'Refund Receipt MRR-' . $q . '-10000', 'Overcharged for shipping', 1);
$pdo->prepare("INSERT INTO order_refund_requests (order_id, ledger_id, amount_cents, reason, status, asked_by, decided_by, decided_at, result, payout) VALUES (?, ?, 10000, 'Overcharged for shipping', 'approved', 1, 1, NOW(), 'Approved. Refund Receipt MRR-1 is in QuickBooks; send $100.00 back to the client by check, then ✓ Sent.', 'to_send')")->execute([$q, $lid]);
foreach (['QB Open Co', 'Paid Co', 'Deposit Co', 'Vendor Co', 'Deposit Paid Co', 'QB Paid Co'] as $n) echo $n . '=' . $id($n) . "\n";
