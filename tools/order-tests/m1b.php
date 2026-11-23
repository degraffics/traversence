<?php
// tools/order-tests/m1b.php <root>: order management M1b (research/order-management-design-2026-11-23.md §3.6;
// decisions/0178 notes of 2026-11-23) on the design harness's database, QuickBooks in mock mode (QB_ENV=mock). Runs
// tools/order-tests/m1b-seed.php first (it empties and refills the orders tables only). <root> holds app/ (the live lib
// with the release's files over it). Each check names its source:
//   §3.6.1 / (a)   a hand-made invoice named by number is linked (never merged: the order's lines and total stay), its
//                  payment applied once (twice is nothing), said as "Paid in QuickBooks Oct 19 · check 2231"; a part
//                  payment leaves the rest owed; one invoice never links two orders; our own posted payments skipped
//   (b)            a payment recorded here is received on the linked invoice (recordOtherPayment, mock)
//   §3.6.2–3 / (c) the Vendor line's state; a bill recorded (typed or read) is made in QuickBooks (createBill, mock);
//                  QuickBooks not answering keeps it here with why, Send it to QuickBooks tries again; a second record of
//                  the same invoice refused; ✎ edits with History, refused once in QuickBooks; Cancel the record only
//                  before QuickBooks; Not billed by them and back; Dispute… and settled
//   §3.6 board     the vendor's question is the Next step once the vendor has made it (Needs us)
//   setup          the hourly pull's job, its page, the staging table class, the upload target
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
ob_start(); passthru('php ' . escapeshellarg(__DIR__ . '/m1b-seed.php')); ob_end_clean();
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/ClientOrders.php';
require_once $root . '/app/lib/VendorBills.php';
require_once $root . '/app/lib/QuickBooksPay.php';
require_once $root . '/app/lib/WorkOrders.php';
require_once $root . '/app/lib/Scheduler.php';
require_once $root . '/app/lib/staging-tables.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
$t = function (string $what, bool $pass, $saw = '') use (&$ok, &$bad) { $pass ? $ok++ : $bad++; echo ($pass ? 'PASS ' : 'FAIL ') . $what . ($pass ? '' : "\n     saw: " . (is_string($saw) ? $saw : json_encode($saw))) . "\n"; };
$ids = []; foreach ($pdo->query('SELECT id, client_company FROM orders') as $r) $ids[$r['client_company']] = (int) $r['id'];
$ledger = fn (int $oid) => $pdo->query("SELECT kind, amount_cents, method, qb_ref, request_key, DATE(created_at) d FROM order_ledger WHERE order_id = $oid ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ev = fn (int $oid, string $e) => $pdo->query("SELECT note FROM order_events WHERE order_id = $oid AND event = " . $pdo->quote($e) . ' ORDER BY id DESC LIMIT 1')->fetchColumn() ?: '';

// ---- §3.6.1, (a): QuickBooks first ----
$a = $ids['QB Paid Co']; $before = Orders::get($pdo, $a);
$got = ClientOrders::readQuickBooks($pdo, $a);
$o = Orders::get($pdo, $a); $l = $ledger($a);
$t('(a) a hand-made invoice named by number is linked (its QuickBooks Id), not merged: no invoice_id, total and lines kept', $o['qb_link_id'] === '9112' && empty($o['invoice_id']) && (int) $o['total_cents'] === (int) $before['total_cents'] && $o['qb_link_customer'] === '58', $o);
$t('(a) its payment is applied at once: $1,111.14 by QuickBooks, check 2231, dated Oct 19, request key qbpay-<order>-7001', count($l) === 1 && (int) $l[0]['amount_cents'] === 111114 && $l[0]['method'] === 'QuickBooks' && $l[0]['qb_ref'] === 'check 2231' && $l[0]['request_key'] === 'qbpay-' . $a . '-7001' && $l[0]['d'] === '2026-10-19', $l);
$t('(a) the chain moves on: paid in full, status paid', $o['status'] === 'paid' && $got !== '', [$o['status'], $got]);
$t('(a) read again (the hourly pull, forced): nothing more is recorded', ClientOrders::readQuickBooks($pdo, $a, true) === '' && count($ledger($a)) === 1);
$t('(a) opened again within 5 minutes: QuickBooks isn\'t asked (qb_read_at)', ClientOrders::readQuickBooks($pdo, $a) === '' && !empty(Orders::get($pdo, $a)['qb_read_at']));
$bs = ClientOrders::boardState($pdo, Orders::get($pdo, $a));
$t('(a) the Money line: "Paid in QuickBooks" Oct 19 · check 2231 · $1,111.14 on invoice 112906', $bs['money']['word'] === 'Paid in QuickBooks' && $bs['money']['sentence'] === 'Oct 19 · check 2231 · $1,111.14 on invoice 112906.' && $bs['money']['via_qb'], $bs['money']);
$t('(a) the history says it was linked', str_contains($ev($a, 'qb_link'), 'Linked to QuickBooks invoice 112906 by its number'), $ev($a, 'qb_link'));
$t('↗ opens the linked invoice in QuickBooks (the sandbox\'s page for a test)', QuickBooksPay::webUrl('invoice', '9112') === 'https://app.sandbox.qbo.intuit.com/app/invoice?txnId=9112');
// a part payment: the same invoice on a bigger order leaves the rest owed (a fresh order, invoice 112906 freed first)
$pdo->exec("UPDATE orders SET qb_link_id = NULL, invoice_no = NULL WHERE id = $a");
$p = $ids['QB Open Co']; $pdo->exec("UPDATE orders SET invoice_no = '112906', total_cents = 200000, qb_link_id = NULL, qb_read_at = NULL WHERE id = $p");
ClientOrders::readQuickBooks($pdo, $p); $op = Orders::get($pdo, $p); $lp = $ledger($p);
$t('(a) a part payment: $1,111.14 recorded as a deposit, $888.86 still owed, the order still waiting', count($lp) === 1 && $lp[0]['kind'] === 'deposit' && $op['status'] === 'pending' && ClientOrders::boardState($pdo, $op)['money']['left'] === 88886, [$lp, $op['status']]);
// one invoice, one order
$pdo->exec("UPDATE orders SET invoice_no = '112906', qb_link_id = NULL, qb_read_at = NULL WHERE id = $a");
$t('one invoice never links two orders: the second order naming 112906 stays unlinked', ClientOrders::readQuickBooks($pdo, $a, true) === '' && empty(Orders::get($pdo, $a)['qb_link_id']));
// our own posted payments are skipped (their PrivateNote carries our mark)
$m = new ReflectionMethod('ClientOrders', 'applyQuickBooks'); $m->setAccessible(true);
$n0 = count($ledger($p));
$m->invoke(null, $pdo, $p, [['id' => '8001', 'date' => '2026-11-20', 'cents' => 5000, 'ref' => '', 'method' => '', 'ours' => true]]);
$t('a payment the site posted itself (ours) isn\'t applied again', count($ledger($p)) === $n0);
$m->invoke(null, $pdo, $p, [['id' => '8002', 'date' => '2026-11-21', 'cents' => 5000, 'ref' => '7781', 'method' => 'Check', 'ours' => false]]);
$t('a new payment someone entered in QuickBooks is applied', count($ledger($p)) === $n0 + 1);
// a new number drops the link (WorkOrders::setInvoiceNo)
WorkOrders::setInvoiceNo($pdo, Orders::get($pdo, $p), '112907', 1);
$t('a new invoice number drops the old link (read again on the next open)', empty(Orders::get($pdo, $p)['qb_link_id']) && empty(Orders::get($pdo, $p)['qb_read_at']));

// ---- (b): a payment recorded here is received on the linked invoice ----
ClientOrders::readQuickBooks($pdo, $p);
$t('(b) invoice 112907 (open in QuickBooks) is linked, nothing new applied', Orders::get($pdo, $p)['qb_link_id'] === '9113');
$qb = ClientOrders::recordPayment($pdo, $p, 10000, 'check', '5512', 1, false);
$t('(b) recording a $100 check also receives it on QuickBooks invoice 112907 (mock payment)', str_contains($qb, 'Received on QuickBooks invoice 112907 (payment MPY-' . $p . ')'), $qb);
$t('(b) the history says so', str_contains($ev($p, 'payment_recorded'), 'Received on QuickBooks invoice 112907'), $ev($p, 'payment_recorded'));

// ---- §3.6.2–3, (c): the Vendor line ----
$s = VendorBills::state($pdo, Orders::get($pdo, $a));
$t('Vendor: $256.83 to the print vendor (work order 20261027-WO0001), not billed yet; due (the vendor made it)', $s['key'] === 'unbilled' && $s['owed'] === 25683 && $s['due'] && $s['sentence'] === '$256.83 to the print vendor (work order 20261027-WO0001), not billed yet.', $s);
$bsA = ClientOrders::boardState($pdo, Orders::get($pdo, $a));
$t('the board: the vendor\'s question is the Next step (Record the vendor bill, Needs us)', ($bsA['next']['label'] ?? '') === 'Record the vendor bill' && $bsA['tile'] === 'needs', $bsA['next']);
$v = $ids['Vendor Co'];
$t('a work order still in production (accepted) asks nothing yet: Follow the work order', (ClientOrders::boardState($pdo, Orders::get($pdo, $v))['next']['label'] ?? '') === 'Follow the work order');
$wa = (int) $pdo->query("SELECT id FROM order_work_orders WHERE order_id = $a")->fetchColumn();
// QuickBooks not answering: kept here, with why
putenv('QB_ENV=sandbox');
$msg = VendorBills::record($pdo, $a, ['wo' => $wa, 'bill_no' => '130929', 'bill_date' => '11/17/2026', 'due_date' => '2026-12-17', 'po' => '20261027-WO0001', 'amount' => '256.83', 'tax' => '0', 'ship' => '0', 'total' => '256.83', 'ship_via' => 'USPS Priority Mail', 'answers' => json_encode([['q' => 'The vendor says it shipped by USPS Priority Mail; the work order says Will Call. Which is right?', 'a' => 'It was picked up']])], 1);
putenv('QB_ENV=mock');
$b = $pdo->query("SELECT * FROM order_vendor_bills WHERE order_id = $a")->fetch(PDO::FETCH_ASSOC);
$t('(c) QuickBooks not answering: the bill is recorded here, with why, and no QuickBooks id', $b && $b['status'] === 'recorded' && $b['qb_bill_id'] === null && $b['qb_error'] !== '' && str_contains($msg, 'Send it to QuickBooks'), [$b, $msg]);
$t('the answer to the ship-via question is kept on the bill and in the history', str_contains((string) $b['answers'], 'It was picked up') && str_contains($ev($a, 'vendor_bill'), 'It was picked up') || str_contains(implode('|', $pdo->query("SELECT note FROM order_events WHERE order_id = $a AND event = 'vendor_bill'")->fetchAll(PDO::FETCH_COLUMN)), 'It was picked up'));
VendorBills::edit($pdo, $a, (int) $b['id'], ['bill_no' => '130929', 'bill_date' => '2026-11-17', 'due_date' => '2026-12-18', 'total' => '256.83'], 1);
$t('✎ before QuickBooks: the due date changed, the old one in the history', $pdo->query('SELECT due_date FROM order_vendor_bills WHERE id = ' . (int) $b['id'])->fetchColumn() === '2026-12-18' && str_contains($ev($a, 'vendor_bill'), 'due 2026-12-17 → 2026-12-18'), $ev($a, 'vendor_bill'));
$msg = VendorBills::toQuickBooks($pdo, $a, (int) $b['id']);
$b = $pdo->query('SELECT * FROM order_vendor_bills WHERE id = ' . (int) $b['id'])->fetch(PDO::FETCH_ASSOC);
$t('(c) Send it to QuickBooks: made as a bill against the vendor (mock MBL-…), the error cleared', $b['qb_bill_id'] === 'MBL-' . $b['id'] && $b['qb_error'] === '' && str_contains($msg, 'in QuickBooks'), [$b, $msg]);
$s = VendorBills::state($pdo, Orders::get($pdo, $a));
$t('the line: Billed $256.83 (inv 130929, Nov 17) · due Dec 18', $s['key'] === 'billed' && $s['sentence'] === 'Billed $256.83 (inv 130929, Nov 17) · due Dec 18.', $s['sentence']);
$t('the board: once billed, the vendor asks nothing (Complete the order)', (ClientOrders::boardState($pdo, Orders::get($pdo, $a))['next']['label'] ?? '') !== 'Record the vendor bill');
$ex = function (callable $f): string { try { $f(); return ''; } catch (Throwable $x) { return $x->getMessage(); } };
$t('the same invoice twice is refused (✎ to edit it)', str_contains($ex(fn () => VendorBills::record($pdo, $a, ['wo' => $wa, 'bill_no' => '130929', 'total' => '256.83'], 1)), 'recorded on this order already'));
$t('✎ once in QuickBooks: refused, change it there', str_contains($ex(fn () => VendorBills::edit($pdo, $a, (int) $b['id'], ['total' => '1'], 1)), 'change it there'));
$t('Cancel the record once in QuickBooks: refused, void it there (money never has Undo)', str_contains($ex(fn () => VendorBills::cancel($pdo, $a, (int) $b['id'], 1)), 'void it there'));
VendorBills::dispute($pdo, $a, (int) $b['id'], 'Billed for shipping we picked up', true, 1);
$s = VendorBills::state($pdo, Orders::get($pdo, $a));
$t('Dispute…: the bill is disputed, with why, on the line', $s['key'] === 'disputed' && str_contains($s['sentence'], 'Billed for shipping we picked up'), $s['sentence']);
VendorBills::dispute($pdo, $a, (int) $b['id'], '', false, 1);
$t('Dispute settled: back to billed', VendorBills::state($pdo, Orders::get($pdo, $a))['key'] === 'billed');
// typed, a dispute answered, then Cancel the record before QuickBooks
$ty = $ids['Typed Co']; $w3 = (int) $pdo->query("SELECT id FROM order_work_orders WHERE order_id = $ty AND wo_no = '20261102-WO0003'")->fetchColumn();
$s = VendorBills::state($pdo, Orders::get($pdo, $ty));
$t('two work orders to two vendors: $345.00 to the vendors, not billed yet', $s['key'] === 'unbilled' && str_contains($s['sentence'], '$345.00 to the vendors (work orders 20261102-WO0003, 20261102-WO0004)'), $s['sentence']);
putenv('QB_ENV=sandbox');
VendorBills::record($pdo, $ty, ['wo' => $w3, 'bill_no' => 'A-77', 'total' => '320.00', 'dispute' => 1, 'why' => 'billed $320.00; the work order agreed $300.00'], 1);
putenv('QB_ENV=mock');
$bt = $pdo->query("SELECT * FROM order_vendor_bills WHERE order_id = $ty")->fetch(PDO::FETCH_ASSOC);
$t('typed, the amount disputed: recorded as disputed with why', $bt['status'] === 'disputed' && $bt['source'] === 'typed' && str_contains($bt['why'], 'agreed $300.00'));
$t('a disputed bill is what the line says first (Disputed: their invoice A-77 for $320.00 …)', VendorBills::state($pdo, Orders::get($pdo, $ty))['key'] === 'disputed' && str_starts_with(VendorBills::state($pdo, Orders::get($pdo, $ty))['sentence'], 'Disputed: their invoice A-77 for $320.00'));
VendorBills::cancel($pdo, $ty, (int) $bt['id'], 1);
$t('Cancel the record before QuickBooks: gone as if never recorded, the history keeps a line', $pdo->query('SELECT status FROM order_vendor_bills WHERE id = ' . (int) $bt['id'])->fetchColumn() === 'cancelled' && str_contains($ev($ty, 'vendor_bill'), 'cancelled as a record') && VendorBills::state($pdo, Orders::get($pdo, $ty))['key'] === 'unbilled');
VendorBills::notBilled($pdo, $ty, 'A remake on them', 1);
$s = VendorBills::state($pdo, Orders::get($pdo, $ty));
$t('Not billed by them: said with why; the board asks nothing of it', $s['key'] === 'not_billed' && str_contains($s['sentence'], 'A remake on them') && (ClientOrders::boardState($pdo, Orders::get($pdo, $ty))['next']['label'] ?? '') !== 'Record the vendor bill', $s['sentence']);
$t('Not billed by them needs why', str_contains($ex(fn () => VendorBills::notBilled($pdo, $ids['Billed Co'], '', 1)), 'Say why'));
$nid = (int) $pdo->query("SELECT id FROM order_vendor_bills WHERE order_id = $ty AND status = 'none'")->fetchColumn();
VendorBills::cancel($pdo, $ty, $nid, 1);
$t('They billed after all: their bill is expected again', VendorBills::state($pdo, Orders::get($pdo, $ty))['key'] === 'unbilled');
$t('an order with no work order has no Vendor line', ClientOrders::boardState($pdo, Orders::get($pdo, $ids['Paid Co']))['vendor']['key'] === '');
$t('Billed Co (seeded with its bill in QuickBooks): Billed $256.83', VendorBills::state($pdo, Orders::get($pdo, $ids['Billed Co']))['key'] === 'billed');
$t('PO match: our work order number in any form', (VendorBills::woForPo(VendorBills::workOrders($pdo, $ty), '20261102 wo0004')['vendor'] ?? '') === 'Mesa Bindery');

// ---- setup: the hourly pull, staging, the uploader ----
$t('the hourly pull is a scheduled job, set up by itself (Scheduler::JOBS and AUTO)', isset(Scheduler::JOBS['qb_orders_tick']) && in_array('qb_orders_tick', Scheduler::AUTO, true) && is_file($root . '/app/scripts/qb_orders_tick.php'));
$pdo->exec("UPDATE orders SET qb_read_at = NULL, invoice_no = '112907', qb_link_id = NULL WHERE id = " . $ids['Mesa Ridge Lodge']);
$t('the hourly pull reads every open order naming an invoice (runs without error)', is_int(ClientOrders::qbOrdersTick($pdo)) && Orders::get($pdo, $ids['Mesa Ridge Lodge'])['qb_read_at'] !== null);
$t('order_vendor_bills is named in staging-tables.php as money (zip check R14)', in_array('order_vendor_bills', StagingTables::MONEY, true));
$src = file_get_contents($root . '/app/lib/upload-targets.php');
$t('the drop zone is a target of the one uploader (decisions/0127), kept as the order\'s invoice file, with no team thread', str_contains($src, "Uploads::target('order_vendor_bill'") && str_contains($src, "fileFromUpload(\$pdo, (int) \$ctx['order'], 0, \$u, \$uid, 'invoice')"));
$q = file_get_contents($root . '/app/lib/QuickBooksPay.php');
$t('createBill asks StagingGate::hold() first and guards real money (R13, guardReal)', (bool) preg_match('/function createBill.*?guardReal.*?StagingGate::hold/s', $q));
echo "\n$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
