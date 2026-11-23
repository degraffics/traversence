<?php
// tools/order-tests/rulings-seed.php <root>: the orders tools/order-tests/rulings.cjs needs, in the design harness's
// database (handoffs/design-harness: root/ holds app/ and website/, the .env four folders above app/lib). Test only, never
// shipped. Empties (never drops) the order tables, so columns the harness's fix.py added stay. 101: approved, Pay in full,
// nothing paid, a work order saved; 102: a deposit order, its deposit in, the vendor's Shipped before the balance;
// 103: a draft; 104: an old draft on invoice-later terms; 105: sent on deposit terms (the client's page, t=e…e);
// 106: approved on invoice-later terms; 107: pulled from QuickBooks (pay_mode 'invoice').
declare(strict_types=1);
$root = $argv[1] ?? (__DIR__ . '/root');
putenv('QB_ENV=mock');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/Mailer.php';
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/ClientOrders.php';
require_once $root . '/app/lib/WorkOrders.php';
$pdo = Database::connection();
foreach (['orders', 'order_lines', 'order_ledger', 'order_events', 'order_proofs', 'order_projects', 'order_work_orders', 'order_work_order_log', 'qr_issued', 'order_line_parts'] as $t) { try { $pdo->exec("TRUNCATE TABLE $t"); } catch (Throwable) {} }
foreach (['pulse_marks', 'pulse_notes'] as $t) { try { $pdo->exec("TRUNCATE TABLE $t"); } catch (Throwable) {} }
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, created_by INT NULL, user_id INT NULL, guest_key CHAR(64) NULL, status VARCHAR(20), stage VARCHAR(20) DEFAULT '',
  review VARCHAR(20) DEFAULT '', pay_mode VARCHAR(20) DEFAULT 'full', deposit_pct INT DEFAULT 0, deposit_cents INT DEFAULT 0, subtotal_cents INT DEFAULT 0,
  tax_cents INT DEFAULT 0, total_cents INT DEFAULT 0, invoice_id VARCHAR(20) NULL, invoice_no VARCHAR(20) DEFAULT '', qb_env VARCHAR(20) DEFAULT '', note VARCHAR(300) DEFAULT '',
  bill_name VARCHAR(120) DEFAULT 'Pat Client', bill_email VARCHAR(160) DEFAULT 'pat@example.com', client_company VARCHAR(120) DEFAULT '', client_phone VARCHAR(40) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  bill_street VARCHAR(160) DEFAULT '', bill_city VARCHAR(80) DEFAULT '', bill_state VARCHAR(40) DEFAULT '', bill_zip VARCHAR(12) DEFAULT '',
  ship_name VARCHAR(120) DEFAULT '', ship_street VARCHAR(160) DEFAULT '', ship_city VARCHAR(80) DEFAULT '', ship_state VARCHAR(40) DEFAULT '', ship_zip VARCHAR(12) DEFAULT '',
  paid_at DATETIME NULL, approved_at DATETIME NULL, approved_name VARCHAR(160) DEFAULT '', sent_at DATETIME NULL, expires_at DATETIME NULL, access_token CHAR(40) DEFAULT 'tok', notify_progress TINYINT DEFAULT 1,
  updated_at DATETIME NULL, created_at DATETIME NULL, order_no VARCHAR(20) NULL, pay_method VARCHAR(20) DEFAULT '', pay_report VARCHAR(80) DEFAULT '', due_terms VARCHAR(20) DEFAULT 'receipt', due_date DATE NULL, po_number VARCHAR(60) DEFAULT '')");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_lines (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, product VARCHAR(20), product_id INT NULL, ref_id INT NULL, title VARCHAR(160), qty INT DEFAULT 1,
  unit_cents INT DEFAULT 0, options TEXT, note VARCHAR(300) DEFAULT '', service_type VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'open', item_status VARCHAR(30) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  project_id INT NULL, collateral_id INT NULL, position INT DEFAULT 0, ship_to VARCHAR(40) DEFAULT '', approved_at DATETIME NULL, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_projects (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, title VARCHAR(120), position INT DEFAULT 0, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_ledger (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, kind VARCHAR(30), amount_cents INT, method VARCHAR(30), qb_ref VARCHAR(60), note VARCHAR(300),
  actor_user_id INT NULL, request_key VARCHAR(80), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (request_key))");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_events (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT NULL, event VARCHAR(40), note TEXT, actor_user_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_proofs (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT, version INT DEFAULT 1, file_name VARCHAR(160), mime VARCHAR(60), status VARCHAR(20) DEFAULT 'waiting',
  kind VARCHAR(10) DEFAULT 'proof', reuse_of INT NULL, created_by INT NULL, decided_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_work_orders (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, wo_no VARCHAR(20), vendor VARCHAR(120), vendor_email VARCHAR(160), vendor_id INT NULL,
  line_ids VARCHAR(200) DEFAULT '', files TEXT, ship_to VARCHAR(400) DEFAULT '', due_on DATE NULL, due_time TIME NULL, note TEXT, token CHAR(40) DEFAULT '', expires_at DATETIME NULL,
  created_by INT NULL, status VARCHAR(20) DEFAULT 'draft', status_at DATETIME NULL, delivery VARCHAR(20) DEFAULT '', po_no VARCHAR(60) DEFAULT '', project VARCHAR(160) DEFAULT '',
  cost_cents INT NULL, cost_state VARCHAR(20) DEFAULT '', parts TEXT, sent_at DATETIME NULL, opened_at DATETIME NULL, carrier VARCHAR(30) DEFAULT '', tracking VARCHAR(160) DEFAULT '',
  job_no VARCHAR(60) DEFAULT '', accepted_by VARCHAR(120) DEFAULT '', promised_on DATE NULL, vendor_note TEXT, received_by VARCHAR(120) DEFAULT '', received_on DATE NULL, receive_note TEXT, reprint_of INT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_work_order_log (id INT AUTO_INCREMENT PRIMARY KEY, wo_id INT, status VARCHAR(20), note TEXT, by_vendor TINYINT DEFAULT 0, staff_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS qr_issued (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, status VARCHAR(20), issued_at DATETIME NULL, user_id INT NULL, guest_key CHAR(64) NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_line_parts (id INT AUTO_INCREMENT PRIMARY KEY, line_id INT, title VARCHAR(160), qty INT DEFAULT 1, unit_cents INT DEFAULT 0, cost_cents INT DEFAULT 0, position INT DEFAULT 0, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, email VARCHAR(160), display_name VARCHAR(120), role VARCHAR(20))");
$o = function (array $f) use ($pdo): int { $f += ['created_by' => 1, 'status' => 'pending', 'total_cents' => 48000, 'subtotal_cents' => 45000, 'tax_cents' => 3000, 'invoice_id' => '77', 'invoice_no' => '1077', 'approved_at' => date('Y-m-d H:i:s', time() - 86400), 'sent_at' => date('Y-m-d H:i:s', time() - 2 * 86400), 'created_at' => date('Y-m-d H:i:s', time() - 3 * 86400), 'bill_name' => 'Dana Ruiz', 'bill_email' => 'dana@example.com', 'client_company' => 'White Mountain Outfitters', 'bill_street' => '12 Main St', 'bill_city' => 'Springerville', 'bill_state' => 'AZ', 'bill_zip' => '85938', 'access_token' => bin2hex(random_bytes(20))];
    $pdo->prepare('INSERT INTO orders (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f)); return (int) $pdo->lastInsertId(); };
$l = function (int $oid, array $f = []) use ($pdo): int { $f += ['order_id' => $oid, 'product' => 'custom', 'title' => 'Trail map brochures · 2,500', 'qty' => 1, 'unit_cents' => 45000, 'options' => '', 'service_type' => 'print', 'created_at' => date('Y-m-d H:i:s')];
    $pdo->prepare('INSERT INTO order_lines (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f)); $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_proofs (order_id, line_id, file_name, mime, status) VALUES (?, ?, 'brochure.pdf', 'application/pdf', 'approved')")->execute([$oid, $id]); return $id; };
$w = function (int $oid, int $lid, array $f = []) use ($pdo): int { $f += ['order_id' => $oid, 'wo_no' => '20261122-CP0' . $oid, 'vendor' => 'Competition Printing', 'vendor_email' => 'orders@vendor.example', 'line_ids' => (string) $lid, 'status' => 'draft', 'cost_cents' => 21000, 'files' => '[]', 'parts' => '[]', 'token' => bin2hex(random_bytes(20)), 'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400), 'status_at' => date('Y-m-d H:i:s'), 'created_by' => 1];
    $pdo->prepare('INSERT INTO order_work_orders (' . implode(', ', array_keys($f)) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')')->execute(array_values($f)); return (int) $pdo->lastInsertId(); };
// 101: approved, paid in full on approval, nothing paid yet; a work order saved, not sent
$pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 101');
$a = $o([]); $la = $l($a); $w($a, $la);
// 102: a deposit order, its deposit in; the vendor shipped it before the balance
$b = $o(['pay_mode' => 'deposit', 'deposit_pct' => 65, 'deposit_cents' => 31200, 'stage' => 'ordered', 'bill_name' => 'Lee Begay', 'client_company' => 'Canyon Coffee', 'ship_name' => 'Lee Begay', 'ship_street' => '4 Rim Rd', 'ship_city' => 'Kayenta', 'ship_state' => 'AZ', 'ship_zip' => '86033']);
$lb = $l($b, ['title' => 'Menu boards · 6']); Orders::record($pdo, $b, 'deposit', 31200, 'seed-b', 'card', '', 'Deposit');
$wb = $w($b, $lb, ['status' => 'production', 'sent_at' => date('Y-m-d H:i:s', time() - 3600), 'job_no' => 'J-5521', 'accepted_by' => 'Ana']);
WorkOrders::vendorMove($pdo, WorkOrders::get($pdo, $wb), 'shipped', ['carrier' => 'UPS', 'tracking' => '1Z999AA10123456784']);
// 103: a draft, paid in full; 104: an old draft on invoice-later terms
$c = $o(['status' => 'draft', 'approved_at' => null, 'invoice_id' => null, 'invoice_no' => '', 'bill_name' => 'Sam Yazzie']); $l($c);
$d = $o(['status' => 'draft', 'approved_at' => null, 'invoice_id' => null, 'invoice_no' => '', 'pay_mode' => 'invoice', 'bill_name' => 'Kim Lopez']); $l($d);
// 105: sent to the client on deposit terms (the client page's note)
$e = $o(['status' => 'sent', 'approved_at' => null, 'invoice_id' => null, 'invoice_no' => '', 'pay_mode' => 'deposit', 'deposit_pct' => 65, 'bill_name' => 'Ruth Chee', 'access_token' => str_repeat('e', 40), 'expires_at' => date('Y-m-d H:i:s', time() + 10 * 86400)]); $l($e);
// 106: approved on invoice-later terms, unpaid; 107: pulled from QuickBooks (pay_mode 'invoice' too); with 104, the Pulse case's three
$f = $o(['pay_mode' => 'invoice', 'bill_name' => 'Ava Tso', 'client_company' => 'Mesa Tours']); $l($f);
$g = $o(['pay_mode' => 'invoice', 'bill_name' => 'Ben Ortiz', 'client_company' => 'Ortiz Feed', 'approved_name' => 'QuickBooks invoice 2201']); $l($g);
echo "seeded 101-107\n";
