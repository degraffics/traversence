<?php
// tools/orders-tests/m3-money.php — Orders M3 (the Money section): plain checks, PASS/FAIL per line, exit 1 on a FAIL.
// The first part needs no database. The second runs only with TV_ORDERS_DB=1 and QB_ENV=mock (QuickBooksPay::env() must
// read 'mock': no network, no real money; it seeds sandbox orders with no invoice, so nothing reaches QuickBooks).
// DRAFT: never run.
declare(strict_types=1);

$root = rtrim((string) (getenv('TV_ROOT') ?: dirname(__DIR__, 2)), '/');
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/OrderMoney.php';

$fails = 0;
$ok = function (string $what, bool $pass) use (&$fails): void { echo ($pass ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$pass) $fails++; };

// ---- aging --------------------------------------------------------------------------------------------------------------
$ok('bucket: not past due is current', OrderMoney::bucket(0) === 'current' && OrderMoney::bucket(-5) === 'current');
$ok('bucket: 1 and 30 are 1–30', OrderMoney::bucket(1) === 'd1_30' && OrderMoney::bucket(30) === 'd1_30');
$ok('bucket: 31 and 60 are 31–60', OrderMoney::bucket(31) === 'd31_60' && OrderMoney::bucket(60) === 'd31_60');
$ok('bucket: 61 and 90 are 61–90', OrderMoney::bucket(61) === 'd61_90' && OrderMoney::bucket(90) === 'd61_90');
$ok('bucket: 91 is 90+', OrderMoney::bucket(91) === 'd90');
$ok('daysPast counts whole days', OrderMoney::daysPast('2026-11-01', '2026-11-27') === 26);
$ok('daysPast is negative before the due date', OrderMoney::daysPast('2026-12-10', '2026-11-27') < 0);

// ---- allocation (0177 §4: deposits first, then balances, oldest first) ---------------------------------------------------
$rows = [['id' => 1, 'deposit' => 0, 'left' => 500], ['id' => 2, 'deposit' => 300, 'left' => 1000]];
$a = OrderMoney::allocate(600, $rows);
$ok('allocate: the younger order\'s deposit before the older balance', ($a['parts'][2] ?? 0) === 300 && ($a['parts'][1] ?? 0) === 300 && $a['over'] === 0);
$a = OrderMoney::allocate(1500, $rows);
$ok('allocate: exactly what\'s owed, nothing over', ($a['parts'][1] ?? 0) === 500 && ($a['parts'][2] ?? 0) === 1000 && $a['over'] === 0);
$a = OrderMoney::allocate(2000, $rows);
$ok('allocate: more than owed says how much over', $a['over'] === 500);
$a = OrderMoney::allocate(200, [['id' => 7, 'deposit' => 900, 'left' => 150]]);
$ok('allocate: a deposit never above what\'s left', ($a['parts'][7] ?? 0) === 150 && $a['over'] === 50);
$a = OrderMoney::allocate(100, [['id' => 3, 'deposit' => 0, 'left' => 400], ['id' => 4, 'deposit' => 0, 'left' => 400]]);
$ok('allocate: balances oldest first', ($a['parts'][3] ?? 0) === 100 && !isset($a['parts'][4]));

// ---- credit lots (90 days, oldest used first) -----------------------------------------------------------------------------
$d = fn (int $day) => date('Y-m-d H:i:s', strtotime('2026-01-01 12:00:00 +' . $day . ' days'));
$t = fn (int $day) => strtotime('2026-01-01 12:00:00 +' . $day . ' days');
$credit = [['kind' => 'credit_issued', 'amount_cents' => 10000, 'created_at' => $d(0)], ['kind' => 'credit_used', 'amount_cents' => 3000, 'created_at' => $d(10)],
           ['kind' => 'credit_issued', 'amount_cents' => 5000, 'created_at' => $d(20)], ['kind' => 'credit_used', 'amount_cents' => 8000, 'created_at' => $d(30)]];
$sum = fn (array $lots) => (int) array_sum(array_column($lots, 0));
$ok('credit: used from the oldest credit first', $sum(Orders::creditLots($credit, $t(31))) === 4000);
$ok('credit: the first lot running out leaves the second', $sum(Orders::creditLots($credit, $t(95))) === 4000);
$ok('credit: past 90 days it\'s gone', $sum(Orders::creditLots($credit, $t(115))) === 0);
$late = [['kind' => 'credit_issued', 'amount_cents' => 1000, 'created_at' => $d(0)], ['kind' => 'credit_used', 'amount_cents' => 500, 'created_at' => $d(100)]];
$ok('credit: never below zero after it ran out', $sum(Orders::creditLots($late, $t(101))) === 0);

// ---- Order #12 is held (its books wait for Jason) ---------------------------------------------------------------------------
$ok('held: real order #12 is held', OrderMoney::held(['id' => 12, 'order_no' => 12, 'qb_env' => 'production']) !== '');
$ok('held: sandbox S-12 isn\'t', OrderMoney::held(['id' => 12, 'test_no' => 12, 'qb_env' => 'sandbox']) === '');
$ok('held: another real order isn\'t', OrderMoney::held(['id' => 40, 'order_no' => 13, 'qb_env' => 'production']) === '');

// ---- with the database (opt-in) ---------------------------------------------------------------------------------------------
if (getenv('TV_ORDERS_DB') !== '1') { echo "SKIP  database checks (set TV_ORDERS_DB=1 and QB_ENV=mock on the test harness)\n"; exit($fails ? 1 : 0); }
putenv('QB_ENV=mock');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/QuickBooksPay.php';
if (QuickBooksPay::env() !== 'mock') { echo "SKIP  database checks: QuickBooks isn't in mock mode here, so nothing runs (no real money, ever)\n"; exit($fails ? 1 : 0); }
$pdo = Database::connection();
$email = 'm3-test-' . bin2hex(random_bytes(4)) . '@example.invalid';
$ids = [];
$seed = function (string $status, int $total) use ($pdo, $email, &$ids): int {
    $pdo->prepare("INSERT INTO orders (status, bill_name, bill_email, created_by, pay_mode, total_cents, subtotal_cents, qb_env, access_token) VALUES (?, 'TEST · M3', ?, 1, 'full', ?, ?, 'sandbox', ?)")
        ->execute([$status, $email, $total, $total, bin2hex(random_bytes(20))]);
    return $ids[] = (int) $pdo->lastInsertId();
};
try {
    $p = $seed('pending', 10000);
    Orders::record($pdo, $p, 'charge', 10000, 'm3t-pay-' . $p, 'Check');
    Orders::record($pdo, $p, 'credit_issued', 3000, 'm3t-cr-' . $p, 'account credit');
    $a = $seed('pending', 5000); $b = $seed('pending', 4000);
    $ok('db: the credit shows on the client\'s sandbox side', Orders::creditBalance($pdo, null, $email, true) === 3000);
    $ok('db: and not on the real side', Orders::creditBalance($pdo, null, $email, false) === 0);
    OrderMoney::applyCredit($pdo, $a, 2000, 0);
    $ok('db: applied credit counts as paid', Orders::money($pdo, $a)['paid'] === 2000);
    $ok('db: applied credit can\'t be refunded to a card', Orders::refundable($pdo, $a) === 0);
    $ok('db: the credit left is 1000', Orders::creditBalance($pdo, null, $email, true) === 1000);
    $threw = false; try { OrderMoney::applyCredit($pdo, $b, 2000, 0); } catch (RuntimeException) { $threw = true; }
    $ok('db: more credit than there is is refused', $threw && Orders::money($pdo, $b)['paid'] === 0);
    $lid = (int) $pdo->query("SELECT id FROM order_ledger WHERE order_id = $a AND kind = 'credit_used' ORDER BY id DESC LIMIT 1")->fetchColumn();
    OrderMoney::cancelCredit($pdo, $a, $lid, 'Applied to the wrong order', 0);
    $ok('db: cancelling the record gives the credit back', Orders::creditBalance($pdo, null, $email, true) === 3000 && Orders::money($pdo, $a)['paid'] === 0);
    $threw = false; try { OrderMoney::takePayment($pdo, [$a, $b], 9500, 'check', '1001', 0); } catch (RuntimeException) { $threw = true; }
    $ok('db: an overpayment is refused, nothing recorded', $threw && Orders::money($pdo, $a)['paid'] === 0 && Orders::money($pdo, $b)['paid'] === 0);
    $done = OrderMoney::takePayment($pdo, [$b, $a], 9000, 'check', '1001', 0);
    $ok('db: one payment pays both orders', array_sum(array_column($done, 1)) === 9000 && Orders::get($pdo, $a)['status'] === 'paid' && Orders::get($pdo, $b)['status'] === 'paid');
    $again = OrderMoney::takePayment(...[$pdo, [$a, $b], 1, 'check', '1002', 0]) ?? null;
} catch (Throwable $e) {
    $ok('db: no error (' . get_class($e) . ': ' . $e->getMessage() . ')', $e instanceof RuntimeException && str_contains($e->getMessage(), 'isn\'t waiting for payment'));
} finally {
    // data_reason: removes only the rows this test seeded (its own sandbox orders, by id)
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach (['order_payment_cancels', 'order_ledger', 'order_events', 'order_lines'] as $tb) { try { $pdo->exec("DELETE FROM $tb WHERE order_id IN ($in)"); } catch (Throwable) {} }
        $pdo->exec("DELETE FROM orders WHERE id IN ($in)");
    }
}
exit($fails ? 1 : 0);
