<?php
// tools/domains-tests/domains.php: the domains-wallet release (decisions/0169 §1a, 0166 §6a) against a real MySQL or
// MariaDB in mock mode (no Name.com, no Fly, no card): php tools/domains-tests/domains.php <app folder> (its .env four
// folders up: DB_DATABASE=tvtest, login tv/tvpass, QB_ENV=mock, DOMAINS_MODE=mock). It drops and makes tvtest.
declare(strict_types=1);
$app = rtrim($argv[1], '/');
$c = new PDO('mysql:host=localhost', 'tv', 'tvpass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$c->exec('DROP DATABASE IF EXISTS tvtest'); $c->exec('CREATE DATABASE tvtest CHARACTER SET utf8mb4'); $c->exec('USE tvtest');
foreach ([
 "CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190), display_name VARCHAR(100), is_test TINYINT NOT NULL DEFAULT 0)",
 "CREATE TABLE entities (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), status VARCHAR(20) DEFAULT 'active', plan_tier VARCHAR(20) DEFAULT 'free', owner_user_id INT NULL)",
 "CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20))",
 "CREATE TABLE subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, owner_type ENUM('user','listing') NOT NULL, owner_id INT NOT NULL, plan VARCHAR(30), cycle VARCHAR(10) DEFAULT 'month', status VARCHAR(20) DEFAULT 'active', price_cents INT DEFAULT 0,
   period_start DATE NULL, period_end DATE NULL, trial_ends DATE NULL, payer_id INT NULL, bill_name VARCHAR(100) DEFAULT '', bill_email VARCHAR(190) DEFAULT '', wallet VARCHAR(40) DEFAULT '', card_id VARCHAR(80) DEFAULT '', card_label VARCHAR(60) DEFAULT '',
   cancel_at_end TINYINT DEFAULT 0, next_plan VARCHAR(30) NULL, fails TINYINT DEFAULT 0, next_try DATE NULL, notices VARCHAR(200) DEFAULT '', created_by INT NULL, ended_at DATETIME NULL, end_reason VARCHAR(40) DEFAULT '',
   promo_id INT NULL, promo_percent INT DEFAULT 0, promo_left INT DEFAULT 0, self_trial TINYINT DEFAULT 0, month_only TINYINT DEFAULT 0)",
 "CREATE TABLE subscription_addons (id INT AUTO_INCREMENT PRIMARY KEY, sub_id INT, addon VARCHAR(30), qty INT, month_cents INT, remove_at_end TINYINT DEFAULT 0)",
 "CREATE TABLE subscription_charges (id INT AUTO_INCREMENT PRIMARY KEY, sub_id INT, kind VARCHAR(20), amount_cents INT, items TEXT, status VARCHAR(20), error VARCHAR(300) DEFAULT '', charge_id VARCHAR(80) DEFAULT '', receipt_id VARCHAR(80) DEFAULT '', req_key VARCHAR(120) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE cart_plans (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, owner_type ENUM('user','listing','media','oneoff') NOT NULL, owner_id INT, item VARCHAR(40), qty INT DEFAULT 1, cycle VARCHAR(10), created_at DATETIME, UNIQUE KEY u (user_id, owner_type, owner_id, item))",
 "CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20), user_id INT NULL, guest_key VARCHAR(60) NULL, access_token VARCHAR(60), items INT DEFAULT 0, subtotal_cents INT DEFAULT 0, total_cents INT DEFAULT 0, bill_name VARCHAR(190), bill_email VARCHAR(190),
   client_company VARCHAR(190) DEFAULT '', approved_at DATETIME NULL, qb_env VARCHAR(12) DEFAULT '', charge_id VARCHAR(80) DEFAULT '', paid_at DATETIME NULL, note VARCHAR(300) DEFAULT '', stage VARCHAR(20) DEFAULT '', order_no INT NULL, test_no INT NULL, pay_mode VARCHAR(10) DEFAULT 'full')",
 "CREATE TABLE order_lines (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, product VARCHAR(30), product_id INT NULL, ref_id INT NULL, title VARCHAR(255), qty INT, unit_cents INT, list_cents INT DEFAULT 0, cost_cents INT DEFAULT 0, taxable TINYINT DEFAULT 0, approved_at DATETIME NULL, status VARCHAR(20) DEFAULT 'approved', vendor_id INT NULL)",
 "CREATE TABLE order_events (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT NULL, event VARCHAR(40), note VARCHAR(600), actor_user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE order_ledger (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, kind VARCHAR(30), amount_cents INT, method VARCHAR(40), qb_ref VARCHAR(60), note VARCHAR(400), actor_user_id INT NULL, request_key VARCHAR(80) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE order_line_costs (line_id INT PRIMARY KEY, order_id INT, recipe TEXT, run_qty INT, cogs_cents INT, vendor_cents INT, checked_cents INT NULL, checked_by INT NULL, checked_at DATETIME NULL, updated_by INT NULL, updated_at DATETIME)",
 "CREATE TABLE order_feedback (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, kind VARCHAR(20), rating TINYINT NULL, words TEXT, may_quote TINYINT DEFAULT 0, resolved_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(40), name VARCHAR(120), kind VARCHAR(20), description TEXT, options TEXT, tiers TEXT, taxable TINYINT, vendor VARCHAR(120), turnaround VARCHAR(80), status VARCHAR(10), qb_item_id VARCHAR(40) NULL, qb_item_env VARCHAR(12) NULL)",
 "CREATE TABLE vendors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120), email VARCHAR(190), contact VARCHAR(120), phone VARCHAR(40), website VARCHAR(190), account_no VARCHAR(60), notes TEXT, active TINYINT DEFAULT 1)",
 "CREATE TABLE age_confirmations (user_id INT PRIMARY KEY, min_age INT DEFAULT 18, confirmed_at DATETIME NULL)",
 "CREATE TABLE test_records (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20), ref INT)",
 "CREATE TABLE market_settings (k VARCHAR(60) PRIMARY KEY, v TEXT, updated_at DATETIME)",
 "CREATE TABLE pulse_marks (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(80) UNIQUE, case_no INT NULL, status VARCHAR(20) DEFAULT '')",
 "CREATE TABLE pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(80), user_id INT, kind VARCHAR(20), body TEXT, to_email VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
] as $q) $c->exec($q);
$c->exec("CREATE TRIGGER pm BEFORE INSERT ON pulse_marks FOR EACH ROW SET NEW.case_no = (SELECT COALESCE(MAX(case_no), 100) + 1 FROM pulse_marks)");
$c->exec("INSERT INTO users (email, display_name, is_test) VALUES ('check+ann@traversence.com','Ann Lee',0), ('check+bo@traversence.com','Bo Test',1), ('check+cy@traversence.com','Cy Owner',0), ('check+dee@traversence.com','Dee Clerk',0)");
$c->exec("INSERT INTO entities (name, owner_user_id) VALUES ('Pine Cafe', 3)");
$c->exec("INSERT INTO listing_access VALUES (1, 3, 'owner')");
// a running plan with a card on file (copied into the wallet by the migration)
$c->exec("INSERT INTO subscriptions (owner_type, owner_id, plan, status, price_cents, period_start, period_end, payer_id, bill_email, wallet, card_id, card_label) VALUES ('user', 1, 'personal_plus', 'active', 495, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 1, 'check+ann@traversence.com', 'TRV-U1', 'MCARD-old111', 'Visa ••1111')");

$c->exec("INSERT INTO subscriptions (owner_type, owner_id, plan, status, price_cents, period_start, period_end, payer_id, bill_email, wallet, card_id, card_label) VALUES ('user', 2, 'personal_plus', 'active', 495, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 2, 'check+bo@traversence.com', 'TRV-U2', 'MCARD-test222', 'Visa ••2222')");
$fails = 0; $n = 0;
function ok(bool $cond, string $what): void { global $fails, $n; $n++; echo ($cond ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$cond) $fails++; }
function throws(callable $f, string $what, string $has = ''): void { try { $f(); ok(false, $what . ' (no error)'); } catch (Throwable $x) { ok($has === '' || stripos($x->getMessage(), $has) !== false, $what . ': ' . $x->getMessage()); } }

// the migration, twice
require_once $app . '/lib/DbUpdates.php';
$sql = file_get_contents($app . '/migrations/2027-03-10_account_domains_wallet.sql');
foreach ([1, 2] as $run) foreach (DbUpdates::statements($sql) as $q) { $st = $c->query($q); if ($st) $st->closeCursor(); }
ok((int) $c->query('SELECT COUNT(*) FROM wallet_cards')->fetchColumn() === 2, 'migration (twice): each plan card copied into the wallet once');
ok($c->query("SELECT env FROM wallet_cards WHERE card_ref = 'MCARD-test222'")->fetchColumn() === 'sandbox' && $c->query("SELECT env FROM wallet_cards WHERE card_ref = 'MCARD-old111'")->fetchColumn() === '', 'migration: a test account\'s plan card goes into its test wallet, a member\'s into the live one');
ok((int) $c->query("SELECT COUNT(*) FROM wallet_assignments WHERE item_type = 'plan' AND item_id = 1 AND ended_at IS NULL")->fetchColumn() === 1, 'migration: the plan is assigned its card once');
ok($c->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'tvtest' AND TABLE_NAME = 'cart_plans' AND COLUMN_NAME = 'owner_type'")->fetchColumn() === 'enum', 'migration: add-only (cart_plans untouched; domains ride as oneoff lines)');
ok((int) $c->query("SELECT COUNT(*) FROM subscriptions WHERE card_id IN ('MCARD-old111','MCARD-test222')")->fetchColumn() === 2, 'migration: subscriptions untouched');

require_once $app . '/lib/Database.php';
require_once $app . '/lib/AccountDomains.php';
require_once $app . '/lib/CartPlans.php';
require_once $app . '/lib/Pulse.php';
$pdo = Database::connection();
$who = ['first' => 'Ann', 'last' => 'Lee', 'email' => 'check+ann@traversence.com', 'phone' => '928-555-1000', 'address1' => '1 Main St', 'city' => 'Springerville', 'state' => 'az', 'zip' => '85938'];

// ---- searching and the cart
ok(AccountDomains::open($pdo, 1)[0], 'open in mock mode');
$r = AccountDomains::search($pdo, 'Pine Cafe');
ok(count($r) === 7 && $r[0]['domain'] === 'pinecafe.com' && $r[0]['price'] === 1299 + 500, 'search: 7 endings, .com at cost + markup');
putenv('DOMAINS_MOCK_TAKEN=["pinecafe.net"]'); putenv('DOMAINS_MOCK_PREMIUM=["pinecafe.co"]');
$r = AccountDomains::search($pdo, 'pinecafe.shop');
ok($r[0]['domain'] === 'pinecafe.shop', 'search: the typed ending first');
$map = array_column($r, null, 'domain');
ok(!$map['pinecafe.net']['ok'] && $map['pinecafe.co']['premium'], 'search: taken and premium marked');
ok(!in_array('traversence.com', array_column(AccountDomains::search($pdo, 'traversence.com'), 'domain'), true), 'search: our own domain never offered');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.com', 1799, $who, false), 'cart: the 18+ tick is needed', '18');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.com', 1799, array_merge($who, ['phone' => '']), true), 'cart: the registrant\'s real details checked', 'phone');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.co', 99999, $who, true), 'cart: a premium name is referred to us', 'premium');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.net', 99999, $who, true), 'cart: a taken name refused', 'available');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.com', 1500, $who, true), 'cart: never more than shown', 'price changed');
AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.com', 1799, $who, true);
ok((bool) $c->query('SELECT 1 FROM age_confirmations WHERE user_id = 1')->fetchColumn(), 'cart: the 18+ confirmation recorded once');
throws(fn () => AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.com', 1799, $who, true), 'cart: not twice', 'already');
AccountDomains::addToCart($pdo, 1, 'user', 1, 'pinecafe.store', 2499, $who, true);
throws(fn () => AccountDomains::addToCart($pdo, 4, 'listing', 1, 'pinecafeaz.com', 1799, $who, true), 'cart: a clerk with no billing on the business refused', 'Domain');
$g = CartPlans::groups($pdo, 1);
$dg = array_values(array_filter($g, fn ($x) => $x['type'] === 'domain'))[0] ?? null;
ok($dg && count($dg['lines']) === 2 && $dg['due_now'] === 1799 + 2499 && $dg['pick'] === 'wallet:1' && $dg['card'] === 'wallet', 'cart: one domain group, the wallet\'s default card offered first');
ok(CartPlans::tokensNeeded($pdo, 1) === 0, 'cart: a wallet card needs no new token');
AccountDomains::setCartCard($pdo, 1, 'user', 1, 'new');
ok(CartPlans::tokensNeeded($pdo, 1) === 1, 'cart: a new card needs one token');

// ---- paying: one charges, the other fails at Name.com and is refunded
putenv('DOMAINS_MOCK_FAIL=["pinecafe.store"]');
$done = CartPlans::pay($pdo, 1, ['mock-4242']);
ok(count($done) === 1 && !(int) $c->query("SELECT COUNT(*) FROM cart_plans WHERE item LIKE 'domain%'")->fetchColumn(), 'pay: the cart\'s domain lines are paid and come out');
$o = $c->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
ok($o['total_cents'] == 1799 + 2499 && $o['status'] === 'part_refunded', 'pay: one order, charged once, the failed one refunded (part refunded)');
$lines = $c->query('SELECT * FROM order_lines WHERE order_id = ' . (int) $o['id'] . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$pP = AccountDomains::productId($pdo, 'purchase');
ok($lines[0]['product_id'] == $pP && $lines[0]['cost_cents'] == 1299 && $lines[0]['vendor_id'] == AccountDomains::vendorId($pdo) && AccountDomains::vendorId($pdo) > 0, 'pay: the line is Domain Purchase, with Name.com as vendor and its cost');
ok((int) $c->query('SELECT vendor_cents FROM order_line_costs WHERE line_id = ' . (int) $lines[0]['id'])->fetchColumn() === 1299, 'pay: Name.com\'s cost on the line\'s costing');
ok($lines[0]['status'] === 'done' && $lines[1]['status'] === 'cancelled', 'pay: registered done, not registered cancelled');
$d = $c->query("SELECT * FROM account_domains WHERE domain = 'pinecafe.com'")->fetch(PDO::FETCH_ASSOC);
ok($d && $d['owner_type'] === 'user' && (int) $d['owner_id'] === 1 && $d['source'] === 'bought' && (int) $d['auto_renew'] === 1 && $d['renew_cents'] == 1499 + 500, 'register: the domain is the member\'s, auto-renew on');
ok(!$c->query("SELECT 1 FROM account_domains WHERE domain = 'pinecafe.store'")->fetchColumn(), 'register: the failed one isn\'t listed');
$card = Wallet::assigned($pdo, 'domain', (int) $d['id']);
ok($card && $card['label'] === 'Visa ••4242', 'wallet: the new card was saved and assigned to its renewals');
ok(count(Wallet::cards($pdo, 'user', 1)) === 2, 'wallet: two cards now (the plan\'s and the new one)');
throws(fn () => Wallet::remove($pdo, 'user', 1, (int) $card['id']), 'wallet: a card paying for renewals can\'t be removed', 'Replace');

// ---- Renew now, auto-renew, declines
$did = (int) $d['id'];
$exp = (string) $d['expires_at'];
$oid = AccountDomains::renewNow($pdo, 1, $did, 'wallet:' . (int) $card['id']);
$d2 = AccountDomains::get($pdo, $did);
ok(strtotime((string) $d2['expires_at']) > strtotime($exp . ' +364 days'), 'Renew now: a year added');
$rl = $c->query('SELECT * FROM order_lines WHERE order_id = ' . $oid)->fetch(PDO::FETCH_ASSOC);
ok($rl['product_id'] == AccountDomains::productId($pdo, 'renewal') && $rl['cost_cents'] == 1499, 'Renew now: a Domain Renewal line with Name.com\'s renewal cost');
$c->exec("UPDATE account_domains SET expires_at = DATE_ADD(NOW(), INTERVAL 10 DAY), updated_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = $did");
$c->exec("DELETE FROM domain_events WHERE kind = 'renewed'");
putenv('QB_MOCK_DECLINE=1');
[$ren, $fail] = AccountDomains::tick($pdo);
$d3 = AccountDomains::get($pdo, $did);
ok($fail === 1 && (int) $d3['renew_fails'] === 1 && $d3['renew_next'] === date('Y-m-d', strtotime('+3 days')), 'auto-renew: declined, tried again in 3 days');
$c->exec("UPDATE account_domains SET renew_next = CURDATE(), updated_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = $did"); AccountDomains::tick($pdo);
$c->exec("UPDATE account_domains SET renew_next = CURDATE(), updated_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = $did"); AccountDomains::tick($pdo);
$d4 = AccountDomains::get($pdo, $did);
ok((int) $d4['renew_fails'] === 3 && $d4['renew_next'] === null && $d4['attention'] !== '', 'auto-renew: after 3 and 7 days, no more tries, needs attention');
ok(isset(AccountDomains::attention($pdo)['domrenew:' . $did]), 'Pulse: a case for the stopped renewal');
putenv('QB_MOCK_DECLINE');
// a new card: tried at once (only when retries remain: reset to one fail)
$c->exec("UPDATE account_domains SET renew_fails = 1, attention = '' WHERE id = $did");
[$new, $moved] = Wallet::replace($pdo, 1, 'user', 1, (int) $card['id'], 'mock-5555');
$d5 = AccountDomains::get($pdo, $did);
ok($moved && (int) $d5['renew_fails'] === 0 && Wallet::assigned($pdo, 'domain', $did)['label'] === 'Visa ••5555', 'replace: the new card pays, the declined renewal retried at once and renewed');
Wallet::remove($pdo, 'user', 1, (int) $card['id']);
ok(!Wallet::card($pdo, 'user', 1, (int) $card['id']), 'wallet: the replaced card unlocked and could be removed');
ok(str_contains(AccountDomains::setAutoRenew($pdo, 1, $did, false), 'won\'t renew') && !Wallet::uses($pdo, (int) $new['id']), 'auto-renew off: the card unlocks');
AccountDomains::setAutoRenew($pdo, 1, $did, true);
ok((bool) Wallet::assigned($pdo, 'domain', $did), 'auto-renew on: the default card assigned and locked again');

// ---- where it points, transfer, tickets, brought domains
ok(str_contains(AccountDomains::forward($pdo, 1, $did, 'example.org/menu'), 'https://example.org/menu'), 'forward: to another address');
throws(fn () => AccountDomains::forward($pdo, 1, $did, 'https://pinecafe.com'), 'forward: not to itself', 'itself');
throws(fn () => AccountDomains::saveRecord($pdo, 1, $did, ['type' => 'A', 'host' => '@', 'answer' => 'nope']), 'DNS: an A record checked', 'IPv4');
ok(str_contains(AccountDomains::saveRecord($pdo, 1, $did, ['type' => 'MX', 'host' => '', 'answer' => 'mail.example.org', 'priority' => 10]), 'Saved'), 'DNS: an MX record saved');
throws(fn () => AccountDomains::setNameservers($pdo, 1, $did, ['ns1.example.net']), 'nameservers: at least two', 'two');
AccountDomains::setNameservers($pdo, 1, $did, ['ns1.example.net', 'ns2.example.net']);
ok(AccountDomains::get($pdo, $did)['points'] === 'nameservers', 'nameservers: set elsewhere');
throws(fn () => AccountDomains::forward($pdo, 2, $did, 'example.org'), 'another member can\'t change it', 'can\'t');
$code = AccountDomains::transferCode($pdo, 1, $did);
ok(str_starts_with($code, 'MOCK-AUTH-') && !(int) AccountDomains::get($pdo, $did)['auto_renew'] && !$c->query("SELECT 1 FROM domain_events WHERE note LIKE '%$code%'")->fetchColumn(), 'transfer: code shown, not kept, auto-renew off');
$no = AccountDomains::ticket($pdo, 1, $did, 'My site doesn\'t open.');
ok($no > 0 && (int) $c->query("SELECT COUNT(*) FROM order_feedback WHERE kind = 'problem'")->fetchColumn() === 1, 'ticket: on its latest order, numbered');
$own = AccountDomains::addOwn($pdo, 1, 'user', 1, 'https://www.AnnLee.net/');
ok($own['domain'] === 'annlee.net' && $own['source'] === 'own', 'brought: listed for pointing only');
throws(fn () => AccountDomains::forward($pdo, 1, (int) $own['id'], 'example.org'), 'brought: its records are elsewhere', 'pointing only');
throws(fn () => AccountDomains::addOwn($pdo, 3, 'user', 3, 'annlee.net'), 'brought: another account can\'t list it too', 'Another account');
$no2 = AccountDomains::ticket($pdo, 1, (int) $own['id'], 'Help');
ok($no2 > 0, 'ticket: a brought domain\'s is its own case');
AccountDomains::removeOwn($pdo, 1, (int) $own['id']);
ok(AccountDomains::get($pdo, (int) $own['id'])['status'] === 'removed', 'brought: taken off the list');
throws(fn () => AccountDomains::removeOwn($pdo, 1, $did), 'bought: stays theirs (no remove)', 'Transfer away');

// ---- a business buys; a failed registration with a failed refund needs a person
AccountDomains::addToCart($pdo, 3, 'listing', 1, 'pinecafeaz.com', 1799, $who, true);
putenv('DOMAINS_MOCK_FAIL=["pinecafeaz.com"]');
$oid = (function () use ($pdo) { CartPlans::pay($pdo, 3, ['mock-1234']); return (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn(); })();
ok($pdo->query("SELECT client_company FROM orders WHERE id = $oid")->fetchColumn() === 'Pine Cafe' && (bool) Wallet::cards($pdo, 'listing', 1) && !Wallet::cards($pdo, 'user', 3), 'business: its own order and its own wallet (never mixed)');
// the retry from the Pulse case once Name.com works
$c->exec("INSERT INTO order_events (order_id, event, note) VALUES ($oid, 'needs_attention', 'test')");
ok(isset(AccountDomains::attention($pdo)['domorder:' . $oid]), 'Pulse: a case for the order');
putenv('DOMAINS_MOCK_FAIL=[]');
$c->exec("UPDATE order_lines SET status = 'approved' WHERE order_id = $oid"); $c->exec("DELETE FROM order_ledger WHERE order_id = $oid AND kind = 'refund'");
ok(str_contains(AccountDomains::pulseDo($pdo, 'domorder:' . $oid, 'dom_retry', [], 9), 'Done'), 'Pulse: Try again registers it, nothing charged again');
ok((bool) $c->query("SELECT 1 FROM account_domains WHERE domain = 'pinecafeaz.com' AND owner_type = 'listing' AND owner_id = 1")->fetchColumn() && !isset(AccountDomains::attention($pdo)['domorder:' . $oid]), 'Pulse: the business holds it; the case closes');

// ---- a test account: sandbox only
try { AccountDomains::addToCart($pdo, 2, 'user', 2, 'botest.com', 1799, $who, true); CartPlans::pay($pdo, 2, ['mock-9999']); } catch (Throwable $x) { echo 'test acct: ' . $x->getMessage() . "
"; }
ok($c->query("SELECT env FROM account_domains WHERE domain = 'botest.com'")->fetchColumn() === 'sandbox' && $c->query("SELECT qb_env FROM orders WHERE user_id = 2")->fetchColumn() === 'sandbox', 'test account: Name.com\'s sandbox and a sandbox order');
ok($c->query("SELECT env FROM wallet_cards WHERE owner_type = 'user' AND owner_id = 2")->fetchColumn() === 'sandbox', 'test account: its card is a sandbox card');

// ---- plans pay from the wallet
require_once $app . '/lib/Billing.php';
$s = Billing::setCard($pdo, 1, 'wallet:' . (int) $new['id']);
ok($s['card_id'] === $new['card_ref'] && Wallet::assigned($pdo, 'plan', 1)['id'] == $new['id'], 'plans: Change card picks a wallet card; the plan follows its assigned card');
$old = (int) $c->query("SELECT id FROM wallet_cards WHERE card_ref = 'MCARD-old111'")->fetchColumn();
Wallet::remove($pdo, 'user', 1, $old);
ok(!Wallet::card($pdo, 'user', 1, $old), 'plans: the plan\'s old card unlocked once replaced');
$s2 = Billing::start($pdo, 4, 'user', 4, 'personal_pro', 'month', [], 'mock-7777');
ok(Wallet::assigned($pdo, 'plan', (int) $s2['id'])['label'] === 'Visa ••7777', 'plans: a new plan saves its card into the wallet and is assigned it');
// ---- the test wallet and the live wallet never mix (Jason, 2026-11-17)
$liveCards = Wallet::cards($pdo, 'user', 1);
$tc = Wallet::asSandbox(true, fn () => Wallet::add($pdo, 1, 'user', 1, 'mock-4111'));
ok($tc['env'] === 'sandbox' && Wallet::cards($pdo, 'user', 1) == $liveCards, 'test wallet: a card saved while testing goes into the test wallet; the live wallet is unchanged');
ok(count(Wallet::asSandbox(true, fn () => Wallet::cards($pdo, 'user', 1))) === 1 && (int) Wallet::asSandbox(true, fn () => Wallet::cards($pdo, 'user', 1))[0]['is_default'] === 1, 'test wallet: only its own cards, with its own default');
ok((int) Wallet::cards($pdo, 'user', 1)[0]['is_default'] === 1, 'live wallet: keeps its default');
throws(fn () => Wallet::asSandbox(true, fn () => Wallet::resolve($pdo, 1, 'user', 1, 'wallet:' . (int) $liveCards[0]['id'])), 'test: a live card can\'t be picked', 'isn\'t in');
throws(fn () => Wallet::asSandbox(true, fn () => Wallet::charge($pdo, $liveCards[0], 100, 'x', 'k-live-in-test')), 'test: a live card is never charged', 'never charges a real card');
throws(fn () => Wallet::resolve($pdo, 1, 'user', 1, 'wallet:' . (int) $tc['id']), 'live: a test card can\'t be picked', 'isn\'t in');
ok(str_starts_with(Wallet::charge($pdo, $tc, 100, 'x', 'k-test'), 'MCH-S-'), 'a test card is charged in the sandbox (mock here)');
ok(Wallet::side($pdo, 'user', 2) === 'test' && !Wallet::liveAllowed($pdo, 'user', 2), 'a test account has only a test wallet');
// wallet expiry reminder
$c->exec("UPDATE wallet_cards SET exp = DATE_FORMAT(NOW(), '%Y-%m') WHERE owner_id = 4 AND owner_type = 'user'");
ok(Wallet::tick($pdo) === 1 && Wallet::tick($pdo) === 0, 'wallet: one reminder before a locked card expires');

echo "\n" . ($n - $fails) . " of $n passed" . ($fails ? ", $fails FAILED" : '') . "\n";
exit($fails ? 1 : 0);
