<?php
// tools/order-tests/rulings.php <root>: decisions/0101's note of 2026-11-20 ("rulings on the order map"), built in the
// Orders release after R0, on MariaDB or MySQL. <root> holds app/ (the live lib with the release's files over it) and a
// .env two folders above it (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD; QB_ENV=mock). It makes its own small
// tables in that database and drops them before and after. Mail goes to a stub Mailer in <root>/app/lib that only
// records. Checks: B.2 (a proof for every printed item before Send and approval; an exact reorder's reuse only with the
// same product and options), B.3 (a part payment on QuickBooks' page recorded as it arrives; production at the deposit),
// B.4 (one paid-in-full rule for Shipped and Ready, with its exceptions), the workflow's check on the same rule.
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
require $root . '/app/lib/Database.php';
require $root . '/app/lib/Orders.php';
require $root . '/app/lib/ClientOrders.php';
require $root . '/app/lib/WorkOrders.php';
require $root . '/app/lib/QrIssue.php';
require $root . '/app/admin/Workflow.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
function t(string $name, bool $c) { global $ok, $bad; echo ($c ? 'ok   ' : 'FAIL ') . $name . "\n"; $c ? $ok++ : $bad++; }
function thrown(callable $f): string { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } }
$tables = 'orders, order_lines, order_ledger, order_events, order_proofs, order_projects, order_work_orders, qr_issued, users, order_work_order_log';
$pdo->exec("DROP TABLE IF EXISTS $tables");
$pdo->exec("CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, created_by INT NULL, user_id INT NULL, status VARCHAR(20), stage VARCHAR(20) DEFAULT '',
  review VARCHAR(20) DEFAULT '', pay_mode VARCHAR(20) DEFAULT 'full', deposit_pct INT DEFAULT 0, deposit_cents INT DEFAULT 0, subtotal_cents INT DEFAULT 0,
  tax_cents INT DEFAULT 0, total_cents INT DEFAULT 0, invoice_id VARCHAR(20) NULL, invoice_no VARCHAR(20) DEFAULT '', qb_env VARCHAR(20) DEFAULT '', note VARCHAR(200) DEFAULT '',
  bill_name VARCHAR(120) DEFAULT 'Pat Client', bill_email VARCHAR(160) DEFAULT 'pat@example.com', client_company VARCHAR(120) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  paid_at DATETIME NULL, approved_at DATETIME NULL, sent_at DATETIME NULL, expires_at DATETIME NULL, access_token CHAR(40) DEFAULT '', notify_progress TINYINT DEFAULT 0,
  updated_at DATETIME NULL, created_at DATETIME NULL, order_no VARCHAR(20) NULL)");
$pdo->exec("CREATE TABLE order_lines (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, product VARCHAR(20), product_id INT NULL, title VARCHAR(160), qty INT DEFAULT 1,
  unit_cents INT DEFAULT 0, options TEXT, service_type VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'open', item_status VARCHAR(30) DEFAULT '', tracking VARCHAR(160) DEFAULT '', project_id INT NULL, collateral_id INT NULL)");
$pdo->exec("CREATE TABLE order_projects (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, title VARCHAR(120))");
$pdo->exec("CREATE TABLE order_ledger (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, kind VARCHAR(30), amount_cents INT, method VARCHAR(30), qb_ref VARCHAR(60), note VARCHAR(300),
  actor_user_id INT NULL, request_key VARCHAR(80), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (request_key))");
$pdo->exec("CREATE TABLE order_events (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT NULL, event VARCHAR(40), note TEXT, actor_user_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE order_proofs (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT, version INT DEFAULT 1, file_name VARCHAR(160), mime VARCHAR(60), status VARCHAR(20) DEFAULT 'waiting',
  kind VARCHAR(10) DEFAULT 'proof', reuse_of INT NULL, created_by INT NULL, decided_at DATETIME NULL)");
$pdo->exec("CREATE TABLE order_work_orders (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, wo_no VARCHAR(20), vendor VARCHAR(120), vendor_email VARCHAR(160), status VARCHAR(20), sent_at DATETIME NULL, cost_cents INT NULL, parts TEXT)");
$pdo->exec("CREATE TABLE order_work_order_log (id INT AUTO_INCREMENT PRIMARY KEY, work_order_id INT)");
$pdo->exec("CREATE TABLE qr_issued (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, status VARCHAR(20), issued_at DATETIME NULL)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(160), display_name VARCHAR(120), role VARCHAR(20))");
$pdo->exec("INSERT INTO users VALUES (1, 'staff@example.com', 'Staff', 'admin')");

$order = function (array $f = []) use ($pdo): int {
    $f += ['created_by' => 1, 'status' => 'pending', 'total_cents' => 10000, 'invoice_id' => '77', 'invoice_no' => '1077'];
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

// ---- B.2: a proof for every printed item
$o1 = $order(['status' => 'draft']); $l1 = $line($o1); $line($o1, ['product' => 'shipping', 'title' => 'Shipping: Ground', 'service_type' => '']);
t('B.2 a printed item with no proof is a gap', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) === ['Business cards' => 'no proof yet']);
t('B.2 Send refuses it, naming the item', str_contains(thrown(fn () => ClientOrders::send($pdo, $o1, 14, 1, false)), 'Business cards: no proof yet'));
t('B.2 approving for the client refuses it too', str_contains(thrown(fn () => ClientOrders::approve($pdo, Orders::get($pdo, $o1), 'Pat', [], 1)), 'needs a proof first'));
$proof($o1, $l1);
t('B.2 with our proof (path 1): no gap; a shipping line never needs one', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) === []);
$pdo->exec("UPDATE order_proofs SET status = 'replaced' WHERE line_id = $l1");
t('B.2 a replaced proof doesn\'t count', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o1)) !== []);
// path 2: an exact reorder
$src = $order(['status' => 'paid']); $sl = $line($src); $sp = $proof($src, $sl, ['status' => 'approved']);
$o2 = $order(['status' => 'draft']); $l2 = $line($o2); $proof($o2, $l2, ['reuse_of' => $sp]);
t('B.2 an exact reorder\'s approved art (path 2): no gap', ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2)) === []);
$pdo->exec("UPDATE order_lines SET options = '{\"Paper\":\"Glossy\"}' WHERE id = $l2");
t('B.2 options changed since the art was approved: a fresh proof is needed', str_contains(ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2))['Business cards'] ?? '', 'fresh proof'));
$pdo->exec("UPDATE order_lines SET options = '{\"Paper\":\"Matte\"}', product_id = 6 WHERE id = $l2");
t('B.2 a different product: a fresh proof is needed', isset(ClientOrders::proofGaps($pdo, Orders::lines($pdo, $o2))['Business cards']));

// ---- B.3: part payments on QuickBooks' page
$o3 = $order(['pay_mode' => 'deposit', 'deposit_pct' => 50, 'deposit_cents' => 5000]); $line($o3);
$pdo->exec("UPDATE orders SET note = 'mock-part:2000' WHERE id = $o3");
t('B.3 a part payment below the deposit is recorded', ClientOrders::fromQuickBooks($pdo, $o3) !== '' && Orders::money($pdo, $o3)['paid'] === 2000);
t('B.3 ... as a deposit entry, and production waits', Orders::get($pdo, $o3)['stage'] === '' && (int) $pdo->query("SELECT COUNT(*) FROM order_ledger WHERE order_id = $o3 AND kind = 'deposit'")->fetchColumn() === 1);
t('B.3 asking again records nothing twice', ClientOrders::fromQuickBooks($pdo, $o3) === '' && Orders::money($pdo, $o3)['paid'] === 2000);
$pdo->exec("UPDATE orders SET note = 'mock-part:6000' WHERE id = $o3");
ClientOrders::fromQuickBooks($pdo, $o3);
t('B.3 reaching the deposit releases production', Orders::money($pdo, $o3)['paid'] === 6000 && Orders::get($pdo, $o3)['stage'] === 'production' && Orders::get($pdo, $o3)['status'] === 'pending');
$pdo->exec("UPDATE orders SET note = 'mock-paid' WHERE id = $o3");
t('B.3 through the one check (the webhook\'s): the rest, paid in full', QrIssue::checkPaid($pdo, $o3) && Orders::get($pdo, $o3)['status'] === 'paid' && Orders::money($pdo, $o3)['paid'] === 10000);
$o4 = $order(); $line($o4); $pdo->exec("UPDATE orders SET note = 'mock-part:3000' WHERE id = $o4");
ClientOrders::fromQuickBooks($pdo, $o4);
t('B.3 paid in full on approval: a part is recorded, production waits for all of it', Orders::money($pdo, $o4)['paid'] === 3000 && Orders::get($pdo, $o4)['stage'] === '');
t('B.3 the receipt says printing waits', str_contains(implode("\n", array_column(Mailer::$sent, 2)), 'Printing starts once $100.00 is paid'));

// ---- B.4: one paid-in-full rule
$o5 = $order(['stage' => 'ordered']); $line($o5);
t('B.4 unpaid: Shipped refused, with what\'s left and what to do', str_contains(thrown(fn () => ClientOrders::setStage($pdo, $o5, 'shipped', '1Z', 1)), '$100.00 still to pay'));
t('B.4 unpaid: Ready refused', thrown(fn () => ClientOrders::setStage($pdo, $o5, 'ready', '', 1)) !== '');
t('B.4 the workflow\'s check says the same', is_string((Workflow::checks()['paid_in_full'][1])($pdo, $o5)));
t('B.4 In production is never held', thrown(fn () => ClientOrders::setStage($pdo, $o5, 'production', '', 1)) === '');
$pdo->exec("UPDATE orders SET pay_mode = 'invoice' WHERE id = $o5");
t('B.4 invoice later: allowed', thrown(fn () => ClientOrders::setStage($pdo, $o5, 'shipped', '1Z', 1)) === '' && Orders::get($pdo, $o5)['stage'] === 'shipped');
$o6 = $order(['pay_mode' => 'outside', 'stage' => 'ordered']); $line($o6);
t('B.4 paid outside: allowed', ClientOrders::shipHold($pdo, Orders::get($pdo, $o6)) === '' && (Workflow::checks()['paid_in_full'][1])($pdo, $o6) === true);
$o7 = $order(['stage' => '']); $line($o7);
ClientOrders::releaseHold($pdo, $o7, 1);
t('B.4 released to production before payment: allowed', ClientOrders::shipHold($pdo, Orders::get($pdo, $o7)) === '' && (Workflow::checks()['paid_in_full'][1])($pdo, $o7) === true);
$o8 = $order(['stage' => 'ordered']); $line($o8); Orders::record($pdo, $o8, 'charge', 10000, 'k8', 'card', '', 'Payment');
t('B.4 paid in full: allowed', ClientOrders::shipHold($pdo, Orders::get($pdo, $o8)) === '');
$pdo->exec("INSERT INTO order_work_orders (order_id, wo_no, vendor, vendor_email, status) VALUES ($o5, 'WO-1', 'Vendor', '', 'accepted')");
$o9 = $order(['stage' => 'ordered']); $line($o9);
$pdo->exec("INSERT INTO order_work_orders (order_id, wo_no, vendor, vendor_email, status) VALUES ($o9, 'WO-2', 'Vendor', '', 'accepted')");
$w = WorkOrders::get($pdo, (int) $pdo->lastInsertId());
t('B.4 our team moving a work order to Shipped for the vendor: refused while unpaid', str_contains(thrown(fn () => WorkOrders::staffMove($pdo, $w, 'shipped', [], 1)), 'isn\'t paid in full'));

$pdo->exec("DROP TABLE IF EXISTS $tables");
echo "$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
