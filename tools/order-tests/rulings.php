<?php
// tools/order-tests/rulings.php <root>: the Orders rulings release rebuilt on Jason's order model (decisions/0101's notes
// of 2026-11-20, "rulings on the order map", and 2026-11-23, "the order model"; research/order-model-2026-11-21.md), on
// MariaDB or MySQL. <root> holds app/ (the live lib with the release's files over it); Database::envPath() reads a .env
// two folders above <root> (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD; QB_ENV=mock). It makes its own
// small tables in that database and drops them before and after: give it a database of its own, never the design
// harness's (it drops orders, order_lines and users there). Mail goes to a stub Mailer in <root>/app/lib that
// only records ([to, subject, body]). Checks, each against its source:
//   B.2   a proof for every printed item before Send and approval; an exact reorder's reuse only with the same product and options
//   B.3   a part payment on QuickBooks' page recorded as it arrives; production at the deposit (or at full payment)
//   1.9a  the vendor work order waits for payment in full, a deposit order for its deposit; a draft can still be saved
//   1.9b  release for delivery (Shipped, Ready, an item out the door) waits for payment in full; invoice later no exception
//   1.9c  the override: admins only, with a reason recorded; the outside path isn't the override and needs no admin
//   1.9d  the vendor's own Shipped with the balance unpaid: kept on the work order, the order held, the client not told,
//         our team's Pulse step to collect it; the payment moves it all on
//   1.8 (0177 §3)   65% deposit default; invoice later not offered for new orders, still readable on old ones, a copy starts as full
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/Mailer.php';
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/ClientOrders.php';
require_once $root . '/app/lib/WorkOrders.php';
require_once $root . '/app/lib/QrIssue.php';
require_once $root . '/app/lib/Pulse.php';
require_once $root . '/app/admin/Workflow.php';
require_once $root . '/app/admin/Access.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
function t(string $name, bool $c) { global $ok, $bad; echo ($c ? 'ok   ' : 'FAIL ') . $name . "\n"; $c ? $ok++ : $bad++; }
function thrown(callable $f): string { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } }
/** Who's signed in, for Access::can(): a staff login at a level, or null (an admin from before staff logins). */
function as_level(?string $level) {
    $r = new ReflectionClass('Access');
    $p = $r->getProperty('loaded'); $p->setAccessible(true); $p->setValue(null, true);
    $p = $r->getProperty('staff'); $p->setAccessible(true); $p->setValue(null, $level === null ? null : ['id' => 1, 'level' => $level, 'department' => 'svc', 'role' => '']);
}
$mails = function (): string { return implode("\n----\n", array_map(fn ($m) => $m[1] . "\n" . $m[2], Mailer::$sent)); };
$tables = 'orders, order_lines, order_ledger, order_events, order_proofs, order_projects, order_work_orders, qr_issued, users, order_work_order_log, order_feedback, vendor_orders, admin_permissions, order_line_parts, pulse_marks, pulse_notes';
$pdo->exec("DROP TABLE IF EXISTS $tables");
$pdo->exec("CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, created_by INT NULL, user_id INT NULL, guest_key CHAR(64) NULL, status VARCHAR(20), stage VARCHAR(20) DEFAULT '',
  review VARCHAR(20) DEFAULT '', pay_mode VARCHAR(20) DEFAULT 'full', deposit_pct INT DEFAULT 0, deposit_cents INT DEFAULT 0, subtotal_cents INT DEFAULT 0,
  tax_cents INT DEFAULT 0, total_cents INT DEFAULT 0, invoice_id VARCHAR(20) NULL, invoice_no VARCHAR(20) DEFAULT '', qb_env VARCHAR(20) DEFAULT '', note VARCHAR(300) DEFAULT '',
  bill_name VARCHAR(120) DEFAULT 'Pat Client', bill_email VARCHAR(160) DEFAULT 'pat@example.com', client_company VARCHAR(120) DEFAULT '', client_phone VARCHAR(40) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  bill_street VARCHAR(160) DEFAULT '', bill_city VARCHAR(80) DEFAULT '', bill_state VARCHAR(40) DEFAULT '', bill_zip VARCHAR(12) DEFAULT '',
  ship_name VARCHAR(120) DEFAULT '', ship_street VARCHAR(160) DEFAULT '', ship_city VARCHAR(80) DEFAULT '', ship_state VARCHAR(40) DEFAULT '', ship_zip VARCHAR(12) DEFAULT '',
  paid_at DATETIME NULL, approved_at DATETIME NULL, approved_name VARCHAR(160) DEFAULT '', sent_at DATETIME NULL, expires_at DATETIME NULL, access_token CHAR(40) DEFAULT 'tok', notify_progress TINYINT DEFAULT 1,
  updated_at DATETIME NULL, created_at DATETIME NULL, order_no VARCHAR(20) NULL, pay_method VARCHAR(20) DEFAULT '', pay_report VARCHAR(80) DEFAULT '', due_terms VARCHAR(20) DEFAULT 'receipt', due_date DATE NULL, po_number VARCHAR(60) DEFAULT '')");
$pdo->exec("CREATE TABLE order_lines (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, product VARCHAR(20), product_id INT NULL, ref_id INT NULL, title VARCHAR(160), qty INT DEFAULT 1,
  unit_cents INT DEFAULT 0, options TEXT, note VARCHAR(300) DEFAULT '', service_type VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'open', item_status VARCHAR(30) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  project_id INT NULL, collateral_id INT NULL, position INT DEFAULT 0, ship_to VARCHAR(40) DEFAULT '', approved_at DATETIME NULL, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE order_projects (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, title VARCHAR(120), position INT DEFAULT 0, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE order_ledger (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, kind VARCHAR(30), amount_cents INT, method VARCHAR(30), qb_ref VARCHAR(60), note VARCHAR(300),
  actor_user_id INT NULL, request_key VARCHAR(80), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (request_key))");
$pdo->exec("CREATE TABLE order_events (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT NULL, event VARCHAR(40), note TEXT, actor_user_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE order_proofs (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT, version INT DEFAULT 1, file_name VARCHAR(160), mime VARCHAR(60), status VARCHAR(20) DEFAULT 'waiting',
  kind VARCHAR(10) DEFAULT 'proof', reuse_of INT NULL, created_by INT NULL, decided_at DATETIME NULL)");
$pdo->exec("CREATE TABLE order_work_orders (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, wo_no VARCHAR(20), vendor VARCHAR(120), vendor_email VARCHAR(160), vendor_id INT NULL,
  line_ids VARCHAR(200) DEFAULT '', files TEXT, ship_to VARCHAR(400) DEFAULT '', due_on DATE NULL, due_time TIME NULL, note TEXT, token CHAR(40) DEFAULT '', expires_at DATETIME NULL,
  created_by INT NULL, status VARCHAR(20) DEFAULT 'draft', status_at DATETIME NULL, delivery VARCHAR(20) DEFAULT '', po_no VARCHAR(60) DEFAULT '', project VARCHAR(160) DEFAULT '',
  cost_cents INT NULL, cost_state VARCHAR(20) DEFAULT '', parts TEXT, sent_at DATETIME NULL, opened_at DATETIME NULL, carrier VARCHAR(30) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  job_no VARCHAR(60) DEFAULT '', accepted_by VARCHAR(120) DEFAULT '', promised_on DATE NULL, vendor_note TEXT, received_by VARCHAR(120) DEFAULT '', received_on DATE NULL, receive_note TEXT, reprint_of INT NULL)");
$pdo->exec("CREATE TABLE order_work_order_log (id INT AUTO_INCREMENT PRIMARY KEY, wo_id INT, status VARCHAR(20), note TEXT, by_vendor TINYINT DEFAULT 0, staff_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE qr_issued (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, status VARCHAR(20), issued_at DATETIME NULL, user_id INT NULL, guest_key CHAR(64) NULL)");
$pdo->exec("CREATE TABLE order_line_parts (id INT AUTO_INCREMENT PRIMARY KEY, line_id INT, title VARCHAR(160), qty INT DEFAULT 1, unit_cents INT DEFAULT 0, cost_cents INT DEFAULT 0, position INT DEFAULT 0, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(160), display_name VARCHAR(120), role VARCHAR(20))");
$pdo->exec("CREATE TABLE pulse_marks (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(120) UNIQUE, case_no INT NULL, assigned_to INT NULL, done_at DATETIME NULL, done_by INT NULL, esc_at DATETIME NULL, esc_level INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(120), user_id INT, kind VARCHAR(20), body TEXT, to_email VARCHAR(160), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO users VALUES (1, 'staff@example.com', 'Staff', 'admin')");

$order = function (array $f = []) use ($pdo): int {
    $f += ['created_by' => 1, 'status' => 'pending', 'total_cents' => 10000, 'subtotal_cents' => 10000, 'invoice_id' => '77', 'invoice_no' => '1077', 'approved_at' => date('Y-m-d H:i:s')];
    $pdo->prepare('INSERT INTO orders (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return (int) $pdo->lastInsertId();
};
$line = function (int $oid, array $f = []) use ($pdo): int {
    $f += ['order_id' => $oid, 'product' => 'product', 'product_id' => 5, 'title' => 'Business cards', 'options' => '{"Paper":"Matte"}', 'service_type' => 'print'];
    $pdo->prepare('INSERT INTO order_lines (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return (int) $pdo->lastInsertId();
};
$proof = function (int $oid, int $lid, array $f = []) use ($pdo): int {
    $f += ['order_id' => $oid, 'line_id' => $lid, 'file_name' => 'card.pdf', 'mime' => 'application/pdf'];
    $pdo->prepare('INSERT INTO order_proofs (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return (int) $pdo->lastInsertId();
};
$wo = function (int $oid, array $f = []) use ($pdo): array {
    $f += ['order_id' => $oid, 'wo_no' => 'WO-' . $oid, 'vendor' => 'Competition Printing', 'vendor_email' => 'orders@vendor.example', 'status' => 'draft', 'cost_cents' => 4000, 'files' => '[]', 'parts' => '[]', 'token' => bin2hex(random_bytes(8)), 'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400)];
    $pdo->prepare('INSERT INTO order_work_orders (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
    return WorkOrders::get($pdo, (int) $pdo->lastInsertId());
};
$ev = function (int $oid, string $e) use ($pdo): array { $st = $pdo->prepare('SELECT note FROM order_events WHERE order_id = ? AND event = ? ORDER BY id'); $st->execute([$oid, $e]); return $st->fetchAll(PDO::FETCH_COLUMN); };
as_level(null);

// ---- B.2: a proof for every printed item (kept as built)
$o1 = $order(['status' => 'draft', 'approved_at' => null]); $l1 = $line($o1); $line($o1, ['product' => 'shipping', 'title' => 'Shipping: Ground', 'service_type' => '']);
t('B.2 a printed item with no proof is a gap', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) === ['Business cards' => 'no proof yet']);
t('B.2 Send refuses it, naming the item', str_contains(thrown(fn () => ClientOrders::send($pdo, $o1, 14, 1, false)), 'Business cards: no proof yet'));
t('B.2 approving for the client refuses it too', str_contains(thrown(fn () => ClientOrders::approve($pdo, Orders::get($pdo, $o1), 'Pat', [], 1)), 'needs a proof first'));
$proof($o1, $l1);
t('B.2 with our proof (path 1): no gap; a shipping line never needs one', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) === []);
$pdo->exec("UPDATE order_proofs SET status = 'replaced' WHERE line_id = $l1");
t('B.2 a replaced proof doesn\'t count', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) !== []);
$src = $order(['status' => 'paid']); $sl = $line($src); $sp = $proof($src, $sl, ['status' => 'approved']);
$o2 = $order(['status' => 'draft', 'approved_at' => null]); $l2 = $line($o2); $proof($o2, $l2, ['reuse_of' => $sp]);
t('B.2 an exact reorder\'s approved art (path 2): no gap', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2)) === []);
$pdo->exec("UPDATE order_lines SET options = '{\"Paper\":\"Glossy\"}' WHERE id = $l2");
t('B.2 options changed since the art was approved: a fresh proof is needed', str_contains(ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2))['Business cards'] ?? '', 'fresh proof'));
$pdo->exec("UPDATE order_lines SET options = '{\"Paper\":\"Matte\"}', product_id = 6 WHERE id = $l2");
t('B.2 a different product: a fresh proof is needed', isset(ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2))['Business cards']));

// ---- B.3: part payments on QuickBooks' page (kept; production starts at what releases it)
$o3 = $order(['pay_mode' => 'deposit', 'deposit_pct' => 65, 'deposit_cents' => 6500]); $line($o3);
$pdo->exec("UPDATE orders SET note = 'mock-part:2000' WHERE id = $o3");
t('B.3 a part payment below the deposit is recorded', ClientOrders::fromQuickBooks($pdo, $o3) !== '' && Orders::money($pdo, $o3)['paid'] === 2000);
t('B.3 ... as a deposit entry, and production waits', Orders::get($pdo, $o3)['stage'] === '' && (int) $pdo->query("SELECT COUNT(*) FROM order_ledger WHERE order_id = $o3 AND kind = 'deposit'")->fetchColumn() === 1);
t('B.3 asking again records nothing twice', ClientOrders::fromQuickBooks($pdo, $o3) === '' && Orders::money($pdo, $o3)['paid'] === 2000);
t('1.9a the deposit order can\'t go to the vendor below its deposit', str_contains(ClientOrders::vendorHold($pdo, Orders::get($pdo, $o3)), 'once its deposit is paid ($45.00 still to pay)'));
$pdo->exec("UPDATE orders SET note = 'mock-part:7000' WHERE id = $o3");
ClientOrders::fromQuickBooks($pdo, $o3);
t('B.3 reaching the deposit releases production', Orders::money($pdo, $o3)['paid'] === 7000 && Orders::get($pdo, $o3)['stage'] === 'production' && Orders::get($pdo, $o3)['status'] === 'pending');
t('1.9a "a deposit overrides this statement": with its deposit paid it can go to the vendor', ClientOrders::vendorHold($pdo, Orders::get($pdo, $o3)) === '');
t('1.9b ... but it isn\'t released for delivery until the balance is paid', str_contains(ClientOrders::shipHold($pdo, Orders::get($pdo, $o3)), '$30.00 still to pay'));
t('1.9 the deposit receipt says when the rest is due', str_contains($mails(), 'it\'s due once production schedules your order, and it must be paid before your order is released for delivery'));
$pdo->exec("UPDATE orders SET note = 'mock-paid' WHERE id = $o3");
t('B.3 through the one check (the webhook\'s): the rest, paid in full', QrIssue::checkPaid($pdo, $o3) && Orders::get($pdo, $o3)['status'] === 'paid' && Orders::money($pdo, $o3)['paid'] === 10000);
$o4 = $order(); $line($o4); $pdo->exec("UPDATE orders SET note = 'mock-part:3000' WHERE id = $o4");
ClientOrders::fromQuickBooks($pdo, $o4);
t('B.3 paid in full on approval: a part is recorded, production waits for all of it', Orders::money($pdo, $o4)['paid'] === 3000 && Orders::get($pdo, $o4)['stage'] === '');
t('B.3 the receipt says how much more starts printing', str_contains($mails(), 'Printing starts once $70.00 more is paid'));
$o4b = $order(); $line($o4b); Mailer::$sent = [];
ClientOrders::recordPayment($pdo, $o4b, 2500, 'check', '1001', 1);
t('1.9a a part payment recorded by hand on a full-payment order doesn\'t start production either', Orders::get($pdo, $o4b)['stage'] === '' && str_contains($mails(), 'Production and the vendor wait until $75.00 more is paid'));

// ---- 1.9a: the vendor waits for payment in full
$o5 = $order(['stage' => 'production']); $l5 = $line($o5, ['product' => 'custom', 'product_id' => null]);
$w5 = $wo($o5, ['line_ids' => (string) $l5]);
$why = thrown(fn () => WorkOrders::send($pdo, $w5, 1));
t('1.9a unpaid: Send to the vendor refused, saying why and what to do', str_contains($why, 'goes to the vendor once it\'s paid in full ($100.00 still to pay)') && str_contains($why, 'an admin can release'));
t('1.9a ... and nothing went: no email, still a draft', WorkOrders::get($pdo, (int) $w5['id'])['status'] === 'draft' && !array_filter(Mailer::$sent, fn ($m) => $m[0] === 'orders@vendor.example'));
t('1.9a handing the vendor its link is refused the same way', str_contains(thrown(fn () => WorkOrders::handLink($pdo, $w5, 1)), 'goes to the vendor once'));
$n0 = (int) $pdo->query("SELECT COUNT(*) FROM order_work_orders WHERE order_id = $o5")->fetchColumn();
t('1.9a Save and send refused before anything is saved', str_contains(thrown(fn () => WorkOrders::create($pdo, Orders::get($pdo, $o5), ['vendor' => 'Competition Printing', 'email' => 'orders@vendor.example', 'lines' => [$l5], 'cost' => '40.00'], 1, true)), 'Nothing was saved')
    && (int) $pdo->query("SELECT COUNT(*) FROM order_work_orders WHERE order_id = $o5")->fetchColumn() === $n0);
t('1.9a a draft can still be prepared and saved', thrown(fn () => WorkOrders::create($pdo, Orders::get($pdo, $o5), ['vendor' => 'Competition Printing', 'email' => 'orders@vendor.example', 'lines' => [$l5], 'cost' => '40.00'], 1, false)) === '');
t('1.9a "Sent to the printer" (Pulse, the workflow, the order page: setStage) refused too', str_contains(thrown(fn () => ClientOrders::setStage($pdo, $o5, 'ordered', '', 1)), 'goes to the vendor once'));
t('1.9a In production (our own staging) is never held', thrown(fn () => ClientOrders::setStage($pdo, $o5, 'production', '', 1)) === '');
Orders::record($pdo, $o5, 'charge', 10000, 'k5', 'card', '', 'Payment');
$pdo->exec("UPDATE orders SET status = 'paid' WHERE id = $o5");
t('1.9a paid in full: it goes', thrown(fn () => WorkOrders::send($pdo, WorkOrders::get($pdo, (int) $w5['id']), 1)) === '' && WorkOrders::get($pdo, (int) $w5['id'])['status'] === 'sent');

// ---- 1.9b: release for delivery waits for payment in full; invoice later is no exception
$o6 = $order(['stage' => 'ordered']); $l6 = $line($o6);
t('1.9b unpaid: Shipped refused, with what\'s left and what to do', str_contains(thrown(fn () => ClientOrders::setStage($pdo, $o6, 'shipped', '1Z', 1)), '$100.00 still to pay'));
t('1.9b unpaid: Ready refused', thrown(fn () => ClientOrders::setStage($pdo, $o6, 'ready', '', 1)) !== '');
t('1.9b an item set Shipped by hand is refused too (it would tell the client)', str_contains(thrown(fn () => ClientOrders::setItemStatus($pdo, $o6, $l6, 'shipped', '1Z', 1)), 'released for delivery'));
t('1.9b an item\'s production status is never held', thrown(fn () => ClientOrders::setItemStatus($pdo, $o6, $l6, 'production', '', 1)) === '');
t('1.9b the workflow\'s check says the same', is_string((Workflow::checks()['paid_in_full'][1])($pdo, $o6)));
$pdo->exec("UPDATE orders SET pay_mode = 'invoice' WHERE id = $o6");
t('§0 item 3: invoice later is no longer an exception (shipping)', str_contains(thrown(fn () => ClientOrders::setStage($pdo, $o6, 'shipped', '1Z', 1)), 'isn\'t paid in full'));
t('§0 item 3: ... nor at the vendor', ClientOrders::vendorHold($pdo, Orders::get($pdo, $o6)) !== '' && is_string((Workflow::checks()['paid_in_full'][1])($pdo, $o6)));
$o7 = $order(['pay_mode' => 'outside', 'stage' => 'ordered']); $line($o7);
t('B.4 paid outside QuickBooks: delivery allowed (outside terms, as confirmed)', ClientOrders::shipHold($pdo, Orders::get($pdo, $o7)) === '' && (Workflow::checks()['paid_in_full'][1])($pdo, $o7) === true);
t('1.9a paid outside QuickBooks, not handled outside: the vendor waits for the payment it records', ClientOrders::vendorHold($pdo, Orders::get($pdo, $o7)) !== '');
$o8 = $order(['stage' => 'ordered']); $line($o8); Orders::record($pdo, $o8, 'charge', 10000, 'k8', 'card', '', 'Payment');
t('1.9b paid in full: allowed', ClientOrders::shipHold($pdo, Orders::get($pdo, $o8)) === '');

// ---- 1.9c: the override is admins only, with a reason; the outside path isn't the override
$o9 = $order(); $line($o9);
as_level('staff');
t('1.9c a staff member can\'t release before payment, and is told who can', str_contains(thrown(fn () => ClientOrders::releaseHold($pdo, $o9, 1, 'Trusted client')), 'Only an admin') && !ClientOrders::canRelease()[0] && ClientOrders::canRelease()[1] === 'Needs an Executive or above');
t('1.9c ... nothing was recorded', !ClientOrders::overridden($pdo, $o9) && Orders::get($pdo, $o9)['stage'] === '');
as_level('manager');
t('1.9c a Manager can\'t either', str_contains(thrown(fn () => ClientOrders::releaseHold($pdo, $o9, 1, 'Trusted client')), 'Only an admin'));
as_level('executive');
t('1.9c an admin without a reason is asked for one', str_contains(thrown(fn () => ClientOrders::releaseHold($pdo, $o9, 1, ' ')), 'Say why'));
t('1.9c an admin with a reason: released', thrown(fn () => ClientOrders::releaseHold($pdo, $o9, 1, 'Net 30 agreed with the client by phone')) === '');
t('1.9c the reason is kept on the order, with who and what is still to come', ($ev($o9, 'released')[0] ?? '') === 'Released before payment by an admin: Net 30 agreed with the client by phone ($100.00 still to come)'
    && (int) $pdo->query("SELECT actor_user_id FROM order_events WHERE order_id = $o9 AND event = 'released'")->fetchColumn() === 1);
$o9r = Orders::get($pdo, $o9);
t('1.9c released: it can go to the vendor and be released for delivery', ClientOrders::vendorHold($pdo, $o9r) === '' && ClientOrders::shipHold($pdo, $o9r) === '' && $o9r['stage'] === 'production');
t('1.9c releasing twice is refused', str_contains(thrown(fn () => ClientOrders::releaseHold($pdo, $o9, 1, 'again')), 'already released'));
t('1.9c a paid order has nothing to release', str_contains(thrown(fn () => ClientOrders::releaseHold($pdo, $o8, 1, 'why not')), 'Only an approved order waiting on payment'));
as_level('staff');
$o10 = $order(); $line($o10);
ClientOrders::releaseHold($pdo, $o10, 1, '', true);
t('1.9c the outside path (processOutside) needs no admin, and says what it is', ClientOrders::overridden($pdo, $o10) && str_starts_with($ev($o10, 'released')[0] ?? '', 'Handled outside Traversence'));
t('1.9c processOutside calls it as the outside path', str_contains((string) file_get_contents($root . '/app/lib/WorkOrders.php'), "ClientOrders::releaseHold(\$pdo, \$oid, \$staff, '', true)"));
as_level(null);

// ---- 1.9d: the vendor's own Shipped on a deposit order with the balance unpaid
$o11 = $order(['pay_mode' => 'deposit', 'deposit_pct' => 65, 'deposit_cents' => 6500, 'stage' => 'ordered']); $l11 = $line($o11);
Orders::record($pdo, $o11, 'deposit', 6500, 'k11', 'card', '', 'Deposit');
$w11 = $wo($o11, ['line_ids' => (string) $l11, 'status' => 'production', 'sent_at' => date('Y-m-d H:i:s')]);
Mailer::$sent = [];
$say = WorkOrders::vendorMove($pdo, $w11, 'shipped', ['carrier' => 'UPS', 'tracking' => '1Z999']);
$w11 = WorkOrders::get($pdo, (int) $w11['id']); $o11r = Orders::get($pdo, $o11);
t('1.9d the vendor\'s Shipped and tracking are kept on the work order', $w11['status'] === 'shipped' && $w11['tracking'] === '1Z999' && $w11['carrier'] === 'UPS' && $say === 'Marked shipped. Thank you.');
t('1.9d the order\'s stage holds, and its item isn\'t marked shipped', $o11r['stage'] === 'ordered' && ClientOrders::itemStatus($o11r, ClientOrders::line($pdo, $o11, $l11)) !== 'shipped');
t('1.9d the client isn\'t told it shipped', !array_filter(Mailer::$sent, fn ($m) => $m[0] === 'pat@example.com'));
t('1.9d our team is told it waits for its balance', (bool) array_filter(Mailer::$sent, fn ($m) => str_contains($m[2], 'The order waits for its balance ($35.00)')) && $ev($o11, 'balance_hold') !== []);
$pulse = fn () => array_column(Pulse::items($pdo, null, 1), null, 'key');   // its list, by key
$items = $pulse();
$case = $items['order:' . $o11] ?? null;
t('1.9d Pulse: one case for the order, with the balance step first', $case !== null && ($case['order']['issue'] ?? '') === 'wobal' && str_contains((string) $case['why'], 'Balance due before delivery') && !isset($items['wobal:' . $o11]));
$steps = $case ? Pulse::playbook($case) : [];
$pay = array_values(array_filter($steps, fn ($s) => ($s['act'][0] ?? '') === 'form' && ($s['act'][1] ?? '') === 'pay'));
t('1.9d its step records the balance in place (a button, not a link), filled with what\'s left', $pay && $pay[0]['act'][3][0][3] === '35.00');
t('1.9d ... and asks the client for it by reply, with the amount', (bool) array_filter($steps, fn ($s) => ($s['act'][0] ?? '') === 'reply' && str_contains((string) $s['act'][1], '$35.00 is left to pay')));
Mailer::$sent = [];
Pulse::manage($pdo, 'order:' . $o11, 'pay', ['amount' => '35.00', 'method' => 'check', 'ref' => '2002'], 1);
$o11r = Orders::get($pdo, $o11);
t('1.9d the balance recorded from the case: paid, and the order moves on by itself', $o11r['status'] === 'paid' && $o11r['stage'] === 'shipped' && ClientOrders::itemStatus($o11r, ClientOrders::line($pdo, $o11, $l11)) === 'shipped');
t('1.9d ... now the client is told it shipped, with the tracking', (bool) array_filter(Mailer::$sent, fn ($m) => $m[0] === 'pat@example.com' && str_contains($m[2], 'has shipped') && str_contains($m[2], '1Z999')));
$after = $pulse();
t('1.9d ... and the case\'s step is gone', ($after['order:' . $o11]['order']['issue'] ?? '') !== 'wobal' && !isset($after['wobal:' . $o11]));
// our team marking it for the vendor: the same hold, not a refusal
$o12 = $order(['stage' => 'ordered']); $l12 = $line($o12); $w12 = $wo($o12, ['line_ids' => (string) $l12, 'status' => 'accepted', 'sent_at' => date('Y-m-d H:i:s')]);   // with the vendor, unpaid (an order from before this release)
$m12 = WorkOrders::staffMove($pdo, $w12, 'shipped', ['carrier' => 'FedEx', 'tracking' => '7788'], 1, 'Sam');
t('1.9d our team marking it shipped for the vendor: kept, and told the order waits', WorkOrders::get($pdo, (int) $w12['id'])['status'] === 'shipped' && str_contains($m12, 'The order waits for its balance ($100.00)') && Orders::get($pdo, $o12)['stage'] === 'ordered');
as_level('executive');
ClientOrders::releaseHold($pdo, $o12, 1, 'Paying on delivery, agreed');
as_level(null);
t('1.9d an admin\'s release moves what the vendor shipped on at once', Orders::get($pdo, $o12)['stage'] === 'shipped');

// ---- 1.8 as changed by decisions/0177 §3 (Jason, 2026-11-23: cost + 15% replaces the 65% default); invoice later not offered
$o13 = $order(['status' => 'draft', 'approved_at' => null]);
ClientOrders::setField($pdo, $o13, 'pay_mode', 'deposit', 1);
t('0177 §3 choosing a deposit starts figured from cost (no % set; 65% only for an item with no cost)', (int) Orders::get($pdo, $o13)['deposit_pct'] === 0 && ClientOrders::DEPOSIT_DEFAULT === 65 && ClientOrders::DEPOSIT_MARKUP === 15);
t('§0 item 1: invoice later can\'t be chosen for an order', str_contains(thrown(fn () => ClientOrders::setField($pdo, $o13, 'pay_mode', 'invoice', 1)), 'Invoice later isn\'t offered any more') && Orders::get($pdo, $o13)['pay_mode'] === 'deposit');
$o14 = $order(['status' => 'draft', 'approved_at' => null, 'pay_mode' => 'invoice']);
t('§0 item 1: an order that has it still saves its other terms', thrown(fn () => ClientOrders::setField($pdo, $o14, 'note', 'Same as last time', 1)) === '' && Orders::get($pdo, $o14)['pay_mode'] === 'invoice');
t('§0 item 1: it still reads', ClientOrders::PAY_MODES['invoice'] === 'Approve now, pay the invoice later' && !in_array('invoice', ClientOrders::OFFERED, true));
t('§0 item 1: a copy of it (duplicate, reorder) starts as Pay in full', ClientOrders::offeredMode('invoice') === 'full' && ClientOrders::offeredMode('deposit') === 'deposit' && ClientOrders::offeredMode('outside') === 'outside');
t('1.9 words: the deposit terms say when the rest is due', ClientOrders::PAY_MODES['deposit'] === 'A deposit on approval, the rest once production schedules it, before delivery');

// ---- §0 item 1, live orders: one Pulse case lists the orders still on invoice later; nothing changes until chosen
$pdo->exec("UPDATE orders SET pay_mode = 'full' WHERE pay_mode = 'invoice'");   // the earlier checks' orders out of the way
t('no open invoice-later orders: no case', !isset($pulse()['invlater:open']));
$i1 = $order(['pay_mode' => 'invoice', 'bill_name' => 'Ours Pending']); $line($i1);
$i2 = $order(['pay_mode' => 'invoice', 'approved_name' => 'QuickBooks invoice 2201', 'bill_name' => 'From QB']); $line($i2);
$i3 = $order(['pay_mode' => 'invoice', 'status' => 'draft', 'approved_at' => null, 'bill_name' => 'Ours Draft']); $line($i3);
$order(['pay_mode' => 'invoice', 'qb_env' => 'sandbox']); $order(['pay_mode' => 'invoice', 'status' => 'paid']);
$i6 = $order(['pay_mode' => 'invoice']); $pdo->exec("INSERT INTO order_events (order_id, event, note) VALUES ($i6, 'released', 'old release')");
$il = ClientOrders::invoiceLaterOpen($pdo);
t('§0.1 the open ones listed (a sandbox, a paid one and one already released aren\'t)', array_map(fn ($x) => (int) $x['id'], $il) === [$i1, $i2, $i3]);
t('§0.1 the QuickBooks-pulled one is marked so', array_column($il, 'pulled', 'id') === [$i1 => false, $i2 => true, $i3 => false]);
$ilCase = $pulse()['invlater:open'] ?? null;
t('§0.1 one Pulse case, with the count and what it means', $ilCase !== null && str_contains($ilCase['title'], 'choose for each (3)') && str_contains($ilCase['why'], '1 came from QuickBooks'));
as_level('staff');
$st = Pulse::playbook($ilCase);
$labels = array_column($st, 't');
t('§0.1 one step per order, ours first, then those from QuickBooks, named so', count($st) === 4 && str_contains($labels[0], 'Ours Pending') && str_contains($labels[1], 'Ours Draft') && str_starts_with($labels[2], 'From QuickBooks: order') && str_contains($labels[2], 'From QB'));
t('§0.1 each step is a form that does it (a choice and a reason)', ($st[0]['act'][0] ?? '') === 'form' && ($st[0]['act'][1] ?? '') === 'inv_choose:' . $i1);
t('§0.1 Staff level: release isn\'t offered, and the step says why', count($st[0]['act'][3][0][4]) === 1 && str_contains($st[0]['h'], 'Needs an Executive or above'));
t('§0.1 Staff level: choosing release anyway is refused', str_contains(thrown(fn () => Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i1, ['choice' => 'release', 'why' => 'x y z'], 1)), 'Only an admin'));
t('§0.1 nothing changed by looking', Orders::get($pdo, $i1)['pay_mode'] === 'invoice' && !ClientOrders::overridden($pdo, $i1));
as_level('executive');
t('§0.1 an admin is offered the release', count(Pulse::playbook($ilCase)[0]['act'][3][0][4]) === 2);
$m = Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i2, ['choice' => 'release', 'why' => 'Net 30 on the QuickBooks invoice'], 1);
t('§0.1 Release before payment, with its reason: released, terms kept', str_contains($m, 'released before payment') && ClientOrders::overridden($pdo, $i2) && Orders::get($pdo, $i2)['pay_mode'] === 'invoice');
Mailer::$sent = [];
$m = Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i1, ['choice' => 'invoice'], 1);
t('§0.1 Send invoice on an approved one: Pay in full, and the client is emailed it\'s due, with the link', Orders::get($pdo, $i1)['pay_mode'] === 'full' && str_contains($m, 'was emailed') && (bool) array_filter(Mailer::$sent, fn ($x) => str_contains($x[1], 'your invoice is due now') && str_contains($x[2], '$100.00')));
t('§0.1 a release on a draft is refused, saying what to do', str_contains(thrown(fn () => Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i3, ['choice' => 'release', 'why' => 'reason'], 1)), 'isn\'t approved yet'));
$m = Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i3, ['choice' => 'invoice'], 1);
t('§0.1 Send invoice on a draft: Pay in full, nothing emailed yet', Orders::get($pdo, $i3)['pay_mode'] === 'full' && str_contains($m, 'when it\'s sent'));
t('§0.1 every one chosen: the case is gone', ClientOrders::invoiceLaterOpen($pdo) === [] && !isset($pulse()['invlater:open']));
t('§0.1 choosing one twice says it\'s been chosen for', str_contains(thrown(fn () => Pulse::manage($pdo, 'invlater:open', 'inv_choose:' . $i1, ['choice' => 'invoice'], 1)), 'chosen for'));
as_level(null);

$pdo->exec("DROP TABLE IF EXISTS $tables");
echo "$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
