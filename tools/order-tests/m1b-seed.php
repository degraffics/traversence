<?php
// tools/order-tests/m1b-seed.php: M1b's orders on the design harness (MariaDB tvh), after m1-seed.php's 15 (it runs it
// first, so the M1 tests still see theirs). Test only, never shipped. QuickBooks runs in mock mode (QB_ENV=mock): invoice
// 112906 is paid (payment 7001, Oct 19, check 2231), 112907 is open (QuickBooksPay::invoiceByNumber's mock).
//   QB Paid Co   out the door (ready for pickup since 21 days), released before payment, its hand-made invoice 112906
//                named by number, not linked yet; work order 20261027-WO0001 at Competition Proofs (made up), $256.83
//                agreed, ready (Will Call), not billed: the Vendor line's case (mockups open, open-after)
//   QB Open Co   approved, owes $1,111.14, invoice 112907 named by hand (open in QuickBooks): a payment recorded here posts to it
//   Billed Co    in production, work order shipped, the vendor's bill recorded and in QuickBooks
//   Typed Co     paid, shipped; work order shipped, two work orders to two vendors, not billed
//   Picked Co    ready for pickup, owed (released); the same work order as QB Paid Co: Yes, picked up first, then the read
declare(strict_types=1);
ob_start(); require __DIR__ . '/m1-seed.php'; ob_end_clean();
$pdo = new PDO('mysql:host=127.0.0.1;dbname=tvh', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['order_vendor_bills', 'order_files', 'qb_settings_log'] as $t) { try { $pdo->exec("DELETE FROM $t"); } catch (Throwable) {} }
try { $pdo->exec("DELETE FROM qb_settings WHERE name = 'vendor_bill_account'"); } catch (Throwable) {}   // where vendor bills post: not chosen yet
$d = fn ($days) => date('Y-m-d H:i:s', time() + $days * 86400);
$mk = function (string $name, array $f, string $istat = '') use ($pdo, $d): int {
    $f += ['created_by' => 1, 'status' => 'pending', 'total_cents' => 111114, 'subtotal_cents' => 102120, 'tax_cents' => 8994, 'bill_name' => 'Ana Ortiz', 'bill_email' => 'ana@example.com', 'client_company' => $name,
           'created_at' => $d(-30), 'updated_at' => $d(-1), 'sent_at' => $d(-28), 'approved_at' => $d(-27), 'expires_at' => $d(4), 'access_token' => bin2hex(random_bytes(20))];
    $pdo->prepare('INSERT INTO orders (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_lines (order_id, product, title, qty, unit_cents, item_status, created_at) VALUES (?, 'custom', 'Gift certificates', 1, 102120, ?, ?)")->execute([$id, $istat, $d(-30)]);
    return $id;
};
$ev = fn ($id, $e, $n, $days) => $pdo->prepare('INSERT INTO order_events (order_id, event, note, created_at) VALUES (?, ?, ?, ?)')->execute([$id, $e, $n, $d($days)]);
$wo = function (int $oid, array $f) use ($pdo, $d): int {
    $f += ['order_id' => $oid, 'wo_no' => '20261027-WO0001', 'vendor' => 'Competition Proofs', 'vendor_email' => 'orders@vendor.example', 'line_ids' => '', 'files' => '[]', 'status' => 'ready', 'status_at' => $d(-21),
           'delivery' => 'will_call', 'cost_cents' => 25683, 'cost_state' => 'accepted', 'sent_at' => $d(-26), 'token' => bin2hex(random_bytes(20)), 'expires_at' => $d(30)];
    $pdo->prepare('INSERT INTO order_work_orders (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return (int) $pdo->lastInsertId();
};
$a = $mk('QB Paid Co', ['stage' => 'ready', 'pay_mode' => 'outside', 'invoice_no' => '112906'], 'ready');
$ev($a, 'released', 'Released before payment by an admin: handled outside', -27); $ev($a, 'stage', 'Ready for pickup', -21);
$wo($a, []);
$b = $mk('QB Open Co', ['invoice_no' => '112907', 'pay_mode' => 'full']);
$c = $mk('Billed Co', ['stage' => 'ordered', 'status' => 'paid', 'paid_at' => $d(-20)]);
$pdo->prepare("INSERT INTO order_ledger (order_id, kind, amount_cents, method, request_key, created_at) VALUES (?, 'charge', 111114, 'Check', ?, NOW())")->execute([$c, 'seed-c']);
$wc = $wo($c, ['wo_no' => '20261101-WO0002', 'status' => 'shipped', 'delivery' => 'ups']);
$pdo->prepare("INSERT INTO order_vendor_bills (order_id, wo_id, vendor, status, bill_no, bill_date, due_date, po, amount_cents, total_cents, source, qb_bill_id, qb_env, created_by, created_at) VALUES (?, ?, 'Competition Proofs', 'recorded', '130929', ?, ?, '20261101-WO0002', 25683, 25683, 'typed', 'MBL-1', 'mock', 1, NOW())")
    ->execute([$c, $wc, date('Y-m-d', time() - 6 * 86400), date('Y-m-d', time() + 24 * 86400)]);
$t = $mk('Typed Co', ['stage' => 'shipped', 'status' => 'paid', 'paid_at' => $d(-20), 'tracking' => '1Z1'], 'shipped');
$pdo->prepare("INSERT INTO order_ledger (order_id, kind, amount_cents, method, request_key, created_at) VALUES (?, 'charge', 111114, 'Check', ?, NOW())")->execute([$t, 'seed-t']);
$ev($t, 'stage', 'Shipped', -2);
$wo($t, ['wo_no' => '20261102-WO0003', 'status' => 'shipped', 'delivery' => 'ups', 'cost_cents' => 30000]);
$wo($t, ['wo_no' => '20261102-WO0004', 'vendor' => 'Mesa Bindery', 'status' => 'shipped', 'delivery' => 'ups', 'cost_cents' => 4500]);
// Picked Co: like QB Paid Co but money owed (released), so "Yes, picked up" leaves it open: the read finds pickup answered
$k = $mk('Picked Co', ['stage' => 'ready', 'pay_mode' => 'full'], 'ready');
$ev($k, 'released', 'Released before payment by an admin: agreed terms', -27); $ev($k, 'stage', 'Ready for pickup', -21);
$wo($k, []);
foreach (['QB Paid Co' => $a, 'QB Open Co' => $b, 'Billed Co' => $c, 'Typed Co' => $t, 'Picked Co' => $k] as $n => $id) echo "$n=$id\n";
