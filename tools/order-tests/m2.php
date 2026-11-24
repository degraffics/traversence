<?php
// tools/order-tests/m2.php <root>: order management M2, money on the order page (decisions/0178 §1, §2, §5 and its money
// note; research/order-management-design-2026-11-23.md §4.3a) on the design harness's database (MariaDB tvh), QuickBooks
// in mock mode (QB_ENV=mock: nothing reaches QuickBooks; refundCard and refundReceipt answer MRF-/MRR-). Runs
// tools/order-tests/m1b-seed.php first (it empties and refills the orders tables only). <root> holds app/ (the live lib
// with the release's files over it). Each check names its source:
//   note     the money rule: each payment's correction (↗ read from QuickBooks, Cancel the record, Refund…); Cancel the
//            record is as if never recorded (paid and status back), append-only, once, with why, greyed once something
//            moved on; a payment received on its QuickBooks invoice keeps its payment id (processed: Refund…)
//   §1       a refund is prepared, never sent: one waiting per order, within what's left; only the Founder approves or
//            declines; Decline needs his reason; Withdraw before he decides; Approve sends it (mock) and does what was
//            ticked; QuickBooks refusing leaves it waiting with why; the Pulse case (Founder only), its steps and buttons
//   §2       the cancel window: open until the vendor accepts (the date in the reason); a paid order's cancel is a refund
//            for Jason's OK, cancelled on Approve; unaccepted work orders stop with it
//   setup    the migration twice, the staging register, the old page's forward
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
ob_start(); passthru('php ' . escapeshellarg(__DIR__ . '/m1b-seed.php')); ob_end_clean();
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/ClientOrders.php';
require_once $root . '/app/lib/QuickBooksPay.php';
require_once $root . '/app/lib/WorkOrders.php';
require_once $root . '/app/lib/Pulse.php';
require_once $root . '/app/lib/staging-tables.php';
require_once $root . '/app/admin/Access.php';
$pdo = Database::connection();
foreach (['order_refund_requests'] as $tb) { try { $pdo->exec("DELETE FROM $tb"); } catch (Throwable) {} }
$ok = 0; $bad = 0;
$t = function (string $what, bool $pass, $saw = '') use (&$ok, &$bad) { $pass ? $ok++ : $bad++; echo ($pass ? 'PASS ' : 'FAIL ') . $what . ($pass ? '' : "\n     saw: " . (is_string($saw) ? $saw : json_encode($saw))) . "\n"; };
$throws = function (callable $f, string $like = '') { try { $f(); return ''; } catch (Throwable $e) { return $like === '' || stripos($e->getMessage(), $like) !== false ? $e->getMessage() : 'OTHER: ' . $e->getMessage(); } };
$ids = []; foreach ($pdo->query('SELECT id, client_company FROM orders') as $r) $ids[$r['client_company']] = (int) $r['id'];
$ev = fn (int $oid, string $e) => $pdo->query("SELECT note FROM order_events WHERE order_id = $oid AND event = " . $pdo->quote($e) . ' ORDER BY id DESC LIMIT 1')->fetchColumn() ?: '';
// who is acting: the Founder (no staff login: an admin from before staff logins counts as one, Access::who) or a staff login
$find = function (array $items, string $key) { foreach ($items as $i) if (($i['key'] ?? '') === $key) return $i; return null; };   // Pulse::items is a list
$as = function (?array $staff) { $r = new ReflectionClass('Access'); $p = $r->getProperty('staff'); $p->setAccessible(true); $p->setValue(null, $staff); $l = $r->getProperty('loaded'); $l->setAccessible(true); $l->setValue(null, true); };
$exec = ['user_id' => 1, 'level' => 'executive', 'department' => 'fin', 'status' => 'active'];
$founder = ['user_id' => 1, 'level' => 'founder', 'department' => '', 'status' => 'active'];

// ---- the money rule (0178's note) ----
$d = $ids['Deposit Co'];   // approved, a deposit order, nothing paid, no QuickBooks invoice
$pdo->prepare("UPDATE orders SET stage = '' WHERE id = ?")->execute([$d]);   // live's default (the harness's column has none)
ClientOrders::recordPayment($pdo, $d, 41200, 'check', '2231', 1, false);
$pays = Orders::payments($pdo, Orders::get($pdo, $d));
$t('note: a check recorded here, with no QuickBooks invoice, is "here" (Cancel the record)', count($pays) === 1 && $pays[0]['how'] === 'here', $pays);
$t('note: Cancel the record is open for it', Orders::cancelRecordBlock($pdo, Orders::get($pdo, $d), $pays[0]) === '');
$t('note: Cancel the record needs why', $throws(fn () => Orders::cancelRecord($pdo, $d, (int) $pays[0]['id'], '  ', 1), 'say why') !== '');
$t('note: Refund… is refused for it (nothing was processed)', $throws(fn () => Orders::requestRefund($pdo, $d, 1000, 'x', (int) $pays[0]['id'], [], [], false, 1), 'Cancel the record instead') !== '');
$before = Orders::money($pdo, $d)['paid'];
Orders::cancelRecord($pdo, $d, (int) $pays[0]['id'], 'Recorded on the wrong order', 1);
$o = Orders::get($pdo, $d); $m = Orders::money($pdo, $d);
$t('note: cancelled, it no longer counts as paid ($412.00 → $0.00) and the order still waits for payment', $before === 41200 && $m['paid'] === 0 && $o['status'] === 'pending' && (string) $o['stage'] === '', [$before, $m['paid'], $o['status'], $o['stage']]);
$rows = $pdo->query("SELECT kind, amount_cents, request_key FROM order_ledger WHERE order_id = $d ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$t('note: the ledger is only added to: the payment stays, a payment_cancelled row keyed cancel-<its row> reverses it', count($rows) === 2 && $rows[1]['kind'] === 'payment_cancelled' && $rows[1]['request_key'] === 'cancel-' . $pays[0]['id'], $rows);
$t('note: the History says so, with why', str_contains($ev($d, 'payment_cancelled'), 'Recorded on the wrong order') && str_contains($ev($d, 'payment_cancelled'), '$412.00'), $ev($d, 'payment_cancelled'));
$t('note: the cancelled record is gone from the payments; cancelling it again is refused', Orders::payments($pdo, Orders::get($pdo, $d)) === [] && $throws(fn () => Orders::cancelRecord($pdo, $d, (int) $pays[0]['id'], 'again', 1)) !== '');
// a full payment that started production, cancelled: production waits again
ClientOrders::recordPayment($pdo, $d, 178600, 'check', '2232', 1, false);
$o = Orders::get($pdo, $d); $p2 = Orders::payments($pdo, $o)[0];
$t('note: (set-up) paid in full by check: paid, in production', $o['status'] === 'paid' && $o['stage'] === 'production', [$o['status'], $o['stage']]);
Orders::cancelRecord($pdo, $d, (int) $p2['id'], 'The check bounced', 1);
$o = Orders::get($pdo, $d);
$t('note: its record cancelled: waiting for payment again, production waits (stage back), paid_at cleared', $o['status'] === 'pending' && (string) $o['stage'] === '' && $o['paid_at'] === null, [$o['status'], $o['stage'], $o['paid_at']]);
// greyed once something moved on: a work order sent after it
ClientOrders::recordPayment($pdo, $d, 41200, 'cash', '', 1, false);
$p3 = Orders::payments($pdo, Orders::get($pdo, $d))[0];
$pdo->prepare("INSERT INTO order_work_orders (order_id, wo_no, vendor, status, sent_at, token) VALUES (?, 'WO-D1', 'Print Vendor', 'sent', NOW() + INTERVAL 1 SECOND, 'tokd1')")->execute([$d]);
$blk = Orders::cancelRecordBlock($pdo, Orders::get($pdo, $d), $p3);
$t('note: greyed once a work order went to the vendor after it, naming it', str_contains($blk, 'WO-D1'), $blk);
$t('note: and refused if pressed anyway', $throws(fn () => Orders::cancelRecord($pdo, $d, (int) $p3['id'], 'x', 1), 'WO-D1') !== '');
// processed: a payment received on the order's QuickBooks invoice keeps its id
$q = $ids['QB Open Co'];   // invoice 112907 named by hand, open in QuickBooks: a payment recorded here posts to it
ClientOrders::readQuickBooks($pdo, $q, true);
ClientOrders::recordPayment($pdo, $q, 50000, 'check', '3001', 1, false);
$pq = array_values(array_filter(Orders::payments($pdo, Orders::get($pdo, $q)), fn ($p) => str_starts_with((string) $p['request_key'], 'out-')));
$t('note: a payment received on its QuickBooks invoice is "posted" (processed: Refund…), its QuickBooks payment id kept', count($pq) === 1 && $pq[0]['how'] === 'posted' && $pq[0]['posted_id'] !== '', $pq);
$t('note: Cancel the record is greyed for it: processed, Refund… instead', str_contains(Orders::cancelRecordBlock($pdo, Orders::get($pdo, $q), $pq[0]), 'Refund'));
$a = $ids['QB Paid Co']; ClientOrders::readQuickBooks($pdo, $a, true);
$pa = Orders::payments($pdo, Orders::get($pdo, $a));
$t('note: a payment read from QuickBooks is "qb" (↗ there), and Cancel the record says to correct it there', count($pa) === 1 && $pa[0]['how'] === 'qb' && str_contains(Orders::cancelRecordBlock($pdo, Orders::get($pdo, $a), $pa[0]), 'QuickBooks'), $pa);

// ---- §1: refunds wait for Jason ----
$p = $ids['Paid Co']; $pp = Orders::payments($pdo, Orders::get($pdo, $p));
$as($exec);
$t('§1: (set-up) Paid Co has one processed payment of $809.10', count($pp) === 1 && $pp[0]['how'] !== 'here' && (int) $pp[0]['amount_cents'] === 80910, $pp);
$t('§1: more than was paid is refused', $throws(fn () => Orders::requestRefund($pdo, $p, 90000, 'Too much', (int) $pp[0]['id'], [], [], false, 1), 'more than') !== '');
$t('§1: a refund needs why', $throws(fn () => Orders::requestRefund($pdo, $p, 10000, ' ', (int) $pp[0]['id'], [], [], false, 1), 'why') !== '');
$rid = Orders::requestRefund($pdo, $p, 20000, 'Two cartons arrived crushed', (int) $pp[0]['id'], [], [], false, 1, 'America/Phoenix');
$r = Orders::refundRequest($pdo, $rid);
$t('§1: prepared, never sent: waiting, nothing in the ledger, the order still paid', $r['status'] === 'waiting' && Orders::money($pdo, $p)['refunded'] === 0 && Orders::get($pdo, $p)['status'] === 'paid' && $r['asked_tz'] === 'America/Phoenix', [$r, Orders::money($pdo, $p)]);
$t('§1: the History says it waits for Jason\'s OK', str_contains($ev($p, 'refund_asked'), 'waiting for Jason\'s OK'), $ev($p, 'refund_asked'));
$t('§1: one waiting per order: a second is refused, naming the first', $throws(fn () => Orders::requestRefund($pdo, $p, 5000, 'Another', (int) $pp[0]['id'], [], [], false, 1), '$200.00') !== '');
$t('§1: an Executive can\'t approve it', $throws(fn () => Orders::approveRefund($pdo, $rid, 1), 'Only Jason') !== '' && Orders::refundRequest($pdo, $rid)['status'] === 'waiting');
$t('§1: nor decline it', $throws(fn () => Orders::declineRefund($pdo, $rid, 'No', 1), 'Only Jason') !== '');
$items = Pulse::items($pdo, $exec, 1);
$t('§1: the Pulse case isn\'t in an Executive\'s Pulse', $find($items, 'refundok:' . $rid) === null);
$as($founder);
$items = Pulse::items($pdo, $founder, 1);
$it = $find($items, 'refundok:' . $rid);
$t('§1: the Founder\'s Pulse has the case: "Refund to approve: $200.00 on order …", its reason, the order\'s #money', $it && str_starts_with($it['title'], 'Refund to approve: $200.00 on order') && str_contains($it['why'], 'Two cartons arrived crushed') && str_ends_with($it['url'], '/admin/backoffice/orders/' . $p . '#money'), $it);
$steps = $it ? Pulse::playbook($it) : [];
$acts = array_map(fn ($s) => $s['act'][0] ?? '', $steps);
$t('§1: its steps: what\'s asked (the payment it comes from), Approve (a button that does it), Decline (a form with why)', count($steps) === 3 && str_contains($steps[0]['h'], '$809.10 paid by check') && ($steps[1]['act'][1] ?? '') === 'refund_approve' && ($steps[2]['act'][0] ?? '') === 'form' && ($steps[2]['act'][1] ?? '') === 'refund_decline', $steps);
$t('§1: Decline needs his reason', $throws(fn () => Pulse::manage($pdo, 'refundok:' . $rid, 'refund_decline', ['why' => ''], 1), 'why') !== '');
// Withdraw before he decides
Orders::withdrawRefund($pdo, $rid, 1);
$t('§1: withdrawn before he decided: nothing refunded, the case is gone from Pulse', Orders::refundRequest($pdo, $rid)['status'] === 'withdrawn' && $find(Pulse::items($pdo, $founder, 1), 'refundok:' . $rid) === null && Orders::money($pdo, $p)['refunded'] === 0);
$t('§1: a withdrawn one can\'t be approved', $throws(fn () => Orders::approveRefund($pdo, $rid, 1), 'isn\'t waiting') !== '');
// Decline
$rid2 = Orders::requestRefund($pdo, $p, 20000, 'Two cartons arrived crushed', (int) $pp[0]['id'], [], [], false, 1);
$said = Pulse::manage($pdo, 'refundok:' . $rid2, 'refund_decline', ['why' => 'Reprint them instead'], 1);
$r2 = Orders::refundRequest($pdo, $rid2);
$t('§1: declined from the case: his reason kept, nothing refunded, the History says so', $r2['status'] === 'declined' && $r2['decided_note'] === 'Reprint them instead' && Orders::money($pdo, $p)['refunded'] === 0 && str_contains($ev($p, 'refund_declined'), 'Reprint them instead') && str_contains($said, 'Declined'), [$r2, $said]);
// Approve, with a work order ticked to stop
$pdo->prepare("INSERT INTO order_work_orders (order_id, wo_no, vendor, vendor_email, status, sent_at, token) VALUES (?, 'WO-P1', 'Print Vendor', '', 'sent', NOW(), 'tokp1')")->execute([$p]);
$wo = (int) $pdo->lastInsertId();
$rid3 = Orders::requestRefund($pdo, $p, 20000, 'Two cartons arrived crushed', (int) $pp[0]['id'], [], [$wo], false, 1);
$said = Pulse::manage($pdo, 'refundok:' . $rid3, 'refund_approve', [], 1);
$r3 = Orders::refundRequest($pdo, $rid3); $o = Orders::get($pdo, $p); $m = Orders::money($pdo, $p);
$t('§1: approved from the case: refunded through QuickBooks (mock), $200.00 in the ledger, part refunded', $r3['status'] === 'approved' && $m['refunded'] === 20000 && $o['status'] === 'part_refunded' && str_contains($said, 'Refunded $200.00'), [$r3, $m, $o['status'], $said]);
$t('§1: the ticked work order stopped with it', (string) $pdo->query("SELECT status FROM order_work_orders WHERE id = $wo")->fetchColumn() === 'cancelled' && str_contains($said, 'WO-P1'), $said);
$t('§1: the payment now has $609.10 left to refund; more than that is refused', (int) Orders::payments($pdo, $o)[0]['refunded'] === 20000 && $throws(fn () => Orders::requestRefund($pdo, $p, 70000, 'Rest', (int) $pp[0]['id'], [], [], false, 1), '$609.10') !== '');
$t('§1: approving it again is refused', $throws(fn () => Orders::approveRefund($pdo, $rid3, 1), 'isn\'t waiting') !== '');
// QuickBooks refusing: waiting, with why
putenv('QB_ENV=production');
$s2 = $ids['Shipped Paid Co']; $ps = Orders::payments($pdo, Orders::get($pdo, $s2));
$rid4 = Orders::requestRefund($pdo, $s2, 10000, 'Late', (int) $ps[0]['id'], [], [], false, 1);
$no = $throws(fn () => Orders::approveRefund($pdo, $rid4, 1));
$r4 = Orders::refundRequest($pdo, $rid4);
$t('§1: QuickBooks not taking it: nothing refunded, still waiting with why, Approve can be pressed again', $no !== '' && str_starts_with($no, 'Nothing was refunded') && $r4['status'] === 'waiting' && str_starts_with((string) $r4['result'], 'Not refunded') && Orders::money($pdo, $s2)['refunded'] === 0, [$no, $r4]);
putenv('QB_ENV=mock');
Orders::withdrawRefund($pdo, $rid4, 1);

// ---- §2: the cancel window ----
$v = $ids['Vendor Co'];   // paid, its work order accepted (WorkOrders::vendorMove writes this event)
$pdo->prepare("INSERT INTO order_events (order_id, event, note, created_at) VALUES (?, 'work_order_accepted', 'Work order WO-1 (Print Vendor): Accepted by Sam', '2026-11-03 10:00:00')")->execute([$v]);
$cb = ClientOrders::cancelBlock($pdo, Orders::get($pdo, $v));
$t('§2: the vendor accepted: greyed with the date, "a credit or a refund instead"', str_starts_with($cb, 'The vendor accepted the work order on Nov 3') && str_ends_with($cb, 'a credit or a refund instead'), $cb);
$t('§2: and refused if pressed', $throws(fn () => ClientOrders::cancel($pdo, $v, 'x', 1), 'accepted') !== '');
$pdo->prepare("UPDATE order_work_orders SET status = 'sent', job_no = 'J-88' WHERE order_id = ?")->execute([$v]);
$t('§2: a job number counts as accepted', str_contains(ClientOrders::cancelBlock($pdo, Orders::get($pdo, $v)), 'job J-88'));
$x = $ids['Deposit Paid Co'];   // a deposit paid, in production, no work order
$t('§2: not accepted yet: open, even with the deposit paid', ClientOrders::cancelBlock($pdo, Orders::get($pdo, $x)) === '');
$t('§2: with money paid, the plain cancel is refused, saying it goes to Jason', $throws(fn () => ClientOrders::cancel($pdo, $x, 'x', 1), 'Jason') !== '');
$pdo->prepare("INSERT INTO order_work_orders (order_id, wo_no, vendor, vendor_email, status, sent_at, token) VALUES (?, 'WO-X1', 'Print Vendor', '', 'sent', NOW(), 'tokx1')")->execute([$x]);
$wx = (int) $pdo->lastInsertId();
$rid5 = Orders::requestRefund($pdo, $x, Orders::refundable($pdo, $x), 'The client changed their plans', 0, [], [], true, 1);
$t('§2: a paid order\'s cancel is a refund of everything paid, waiting; nothing changed yet', Orders::refundRequest($pdo, $rid5)['status'] === 'waiting' && (int) Orders::refundRequest($pdo, $rid5)['amount_cents'] === 41200 && Orders::get($pdo, $x)['status'] === 'pending');
$t('§2: while it waits, Cancel says the refund decides it first', str_contains(ClientOrders::cancelBlock($pdo, Orders::get($pdo, $x)), 'waiting for Jason'));
Orders::approveRefund($pdo, $rid5, 1);
$o = Orders::get($pdo, $x);
$t('§2: on Approve: refunded, the order cancelled, its unaccepted work order stopped', $o['status'] === 'void' && Orders::money($pdo, $x)['refunded'] === 41200 && (string) $pdo->query("SELECT status FROM order_work_orders WHERE id = $wx")->fetchColumn() === 'cancelled', [$o['status'], Orders::money($pdo, $x)]);
$u = $ids['Past Due Co'];   // approved, nothing paid
$pdo->prepare("INSERT INTO order_work_orders (order_id, wo_no, vendor, vendor_email, status, sent_at, token) VALUES (?, 'WO-U1', 'Print Vendor', '', 'sent', NOW(), 'toku1')")->execute([$u]);
$wu = (int) $pdo->lastInsertId();
ClientOrders::cancel($pdo, $u, 'Duplicate order', 1);
$t('§2: an unpaid order with a work order not yet accepted cancels at once, and the work order stops', Orders::get($pdo, $u)['status'] === 'void' && (string) $pdo->query("SELECT status FROM order_work_orders WHERE id = $wu")->fetchColumn() === 'cancelled');

// ---- setup ----
$t('setup: order_refund_requests is a money table on staging (R14)', in_array('order_refund_requests', StagingTables::MONEY, true));
$t('setup: the ledger has qb_payment_id', (bool) $pdo->query("SHOW COLUMNS FROM order_ledger LIKE 'qb_payment_id'")->fetch());
$t('setup: "Record cancelled" is a ledger kind', (Orders::KINDS['payment_cancelled'] ?? '') === 'Record cancelled');
echo "\n$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
