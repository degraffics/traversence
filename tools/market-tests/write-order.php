<?php
// tools/market-tests/write-order.php <root>: MarketCheckout's copy of a sale in the business's walled data (decisions/0110),
// on a real MariaDB with the listings realm built by BusinessData::ensure (its tenant function, views and triggers).
// <root> holds app/ (the live mirror's sync-app with the build laid over it) and ../.env one level up from root's parent's
// parent, as Database::envPath() reads it. The test makes and drops its own databases (wo_main, wo_list).
//   1. two orders for two businesses in one request (the tick, the webhook) are both written, each in its own business;
//   2. after a write the request is ours again (owner 0, no business connection) and @tv_tenant is NULL on that connection;
//   3. a write that throws midway leaves the scope at 0, the tenant NULL, and the next business still written;
//   4. a request already inside a business (the portal) is refused before anything is written, its own scope untouched;
//   5. Scope::leave refuses any connection but the one that entered;
//   6. a copy that fails after its first line leaves nothing in the business's books and isn't linked (one transaction);
// (A direct-pay or card order whose copy fails: write-order-place.php.)
declare(strict_types=1);
$root = rtrim($argv[1] ?? '', '/'); $app = $root . '/app';
if (!is_file($app . '/lib/MarketCheckout.php')) { fwrite(STDERR, "usage: write-order.php <root with app/>\n"); exit(2); }
$n = 0; $bad = 0;
$t = function (string $what, bool $ok) use (&$n, &$bad) { $n++; if (!$ok) $bad++; echo ($ok ? '  ok  ' : 'FAIL  ') . $what . "\n"; };

$sys = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['wo_main', 'wo_list'] as $d) { $sys->exec("DROP DATABASE IF EXISTS $d"); $sys->exec("CREATE DATABASE $d CHARACTER SET utf8mb4"); }
$sys->exec("CREATE OR REPLACE USER 'wo'@'localhost' IDENTIFIED BY 'wo'"); $sys->exec("GRANT ALL ON wo_main.* TO 'wo'@'localhost'"); $sys->exec("GRANT ALL ON wo_list.* TO 'wo'@'localhost'");
$sys->exec('SET GLOBAL log_bin_trust_function_creators = 1');
file_put_contents(dirname($root, 2) . '/.env', "DB_HOST=localhost\nDB_DATABASE=wo_main\nDB_USERNAME=wo\nDB_PASSWORD=wo\nDB_LISTINGS_DATABASE=wo_list\nDB_LISTINGS_USERNAME=wo\nDB_LISTINGS_PASSWORD=wo\n");

require_once $app . '/lib/Database.php';
foreach (['Scope', 'BusinessData', 'BizQuotes', 'MarketCheckout'] as $c) require_once $app . '/lib/' . $c . '.php';
$main = Database::connection();
$main->exec("CREATE TABLE market_orders (id INT UNSIGNED PRIMARY KEY, entity_id INT UNSIGNED, biz_quote_id INT UNSIGNED DEFAULT NULL, note TEXT NOT NULL DEFAULT '')");
register_shutdown_function(function () use ($sys) { foreach (['wo_main', 'wo_list'] as $d) $sys->exec("DROP DATABASE IF EXISTS $d"); $sys->exec("DROP USER IF EXISTS 'wo'@'localhost'"); });   // also after a fatal
$main->exec("CREATE TABLE market_checkout (entity_id INT UNSIGNED PRIMARY KEY, settings TEXT, live TINYINT DEFAULT 1)");
$list = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=wo_list', 'wo', 'wo', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
BusinessData::ensure($list);

$order = function (int $id, int $e, string $email = 'buyer@example.test') use ($main) {
    $main->prepare('INSERT INTO market_orders (id, entity_id) VALUES (?, ?)')->execute([$id, $e]);
    return ['id' => $id, 'entity_id' => $e, 'biz_quote_id' => 0, 'email' => $email, 'name' => 'Test Buyer', 'method' => 'card', 'provider' => 'square',
        'lines' => [['product_id' => 0, 'title' => 'TEST · item', 'qty' => 2, 'unit_cents' => 1250, 'taxable' => 0]], 'ship_cents' => 0, 'ship_name' => 'Pickup',
        'ship_address' => '', 'note' => '', 'hold_until' => date('Y-m-d H:i:s', strtotime('+3 days')), 'total_cents' => 2500, 'pay_now_cents' => null];
};
$write = new ReflectionMethod('MarketCheckout', 'writeOrder'); $write->setAccessible(true);
$scopePdo = (new ReflectionClass('Scope'))->getProperty('pdo'); $scopePdo->setAccessible(true);
$quotes = fn (int $e) => (int) $list->query('SELECT COUNT(*) FROM biz_quotes WHERE owner_id = ' . $e)->fetchColumn();
$paid = fn (int $e) => (int) $list->query("SELECT COUNT(*) FROM biz_quotes WHERE owner_id = $e AND status = 'paid'")->fetchColumn();

// 1-2: two businesses in one request
$write->invoke(null, $main, $order(1, 101), 'sq-1');
$t('2. after the first write the request is ours again (owner 0, no business connection)', Scope::owner() === 0 && !Scope::isBusiness() && $scopePdo->getValue() === null);
$write->invoke(null, $main, $order(2, 202), 'sq-2');
$t('1. two orders for two businesses in one request: both written', $quotes(101) === 1 && $quotes(202) === 1);
$t('1. each in its own business, paid (101 and 202 one each, nothing under the other)', $paid(101) === 1 && $paid(202) === 1 && (int) $list->query('SELECT COUNT(*) FROM biz_quotes')->fetchColumn() === 2);
$t('1. each order points at its own copy', (int) $main->query('SELECT COUNT(DISTINCT biz_quote_id) FROM market_orders WHERE id IN (1,2) AND biz_quote_id > 0')->fetchColumn() === 2);

// the tenant on the connection a write used is NULL after: watched through the connection BusinessData::open returns
$seen = null;
BusinessData::scoped(303, function (PDO $p) use (&$seen) { $seen = $p; });
$t('2. @tv_tenant is NULL on that connection after the run', $seen && $seen->query('SELECT @tv_tenant')->fetchColumn() === null);
$t('2. and a write on it now is refused by the trigger (no tenant)', (function () use ($seen) { try { $seen->exec("INSERT INTO biz_codes (owner_id, code, created_at) VALUES (303, 'X', NOW())"); return false; } catch (PDOException $x) { return str_contains($x->getMessage(), 'another business'); } })());

// 3: a throw midway
$threw = '';
try { BusinessData::scoped(404, function (PDO $p) { $p->exec("INSERT INTO biz_codes (owner_id, code, created_at) VALUES (0, 'MID', NOW())"); throw new RuntimeException('midway'); }); } catch (RuntimeException $x) { $threw = $x->getMessage(); }
$t('3. a throw midway comes back to the caller', $threw === 'midway');
$t('3. the scope is at 0 after it', Scope::owner() === 0 && !Scope::isBusiness());
$bad3 = $order(5, 505); $bad3['email'] = 'not an email';   // BizQuotes::create refuses it inside the run
$why = ''; try { $write->invoke(null, $main, $bad3, 'sq-5'); } catch (Throwable $x) { $why = $x->getMessage(); }
$t('3. writeOrder throwing inside the run (a refused email) comes back, scope 0', $why !== '' && Scope::owner() === 0 && !Scope::isBusiness());
$write->invoke(null, $main, $order(6, 606), 'sq-6');
$t('3. the next business is still written after a failed one', $quotes(606) === 1 && $quotes(505) === 0);

// 4: a request already inside a business (the portal)
$portal = BusinessData::open(707);
$before = (int) $list->query('SELECT COUNT(*) FROM biz_quotes')->fetchColumn();
$why = ''; try { $write->invoke(null, $main, $order(8, 808), 'sq-8'); } catch (Throwable $x) { $why = $x->getMessage(); }
$t('4. a portal request is refused with why', str_contains($why, 'already working for a business'));
$t('4. nothing was written by it', (int) $list->query('SELECT COUNT(*) FROM biz_quotes')->fetchColumn() === $before);
$t('4. the portal\'s own scope is untouched (still its business, its connection, its tenant)', Scope::owner() === 707 && $scopePdo->getValue() === $portal && (int) $portal->query('SELECT @tv_tenant')->fetchColumn() === 707);

// 5: leave only with the connection that entered
$t('5. Scope::leave refuses another connection', (function () use ($main) { try { Scope::leave($main); return false; } catch (LogicException $x) { return true; } })());
Scope::leave($portal);
$t('5. and leaves with its own', Scope::owner() === 0 && !Scope::isBusiness());

// 6: a copy that fails after its first line leaves nothing in the business's books (one transaction), and isn't linked
$half = $order(9, 909); $half['lines'][] = ['product_id' => 0, 'title' => '', 'qty' => 1, 'unit_cents' => 500, 'taxable' => 0];   // BizQuotes::addLine refuses a line with no name
$why = ''; try { $write->invoke(null, $main, $half, null); } catch (Throwable $x) { $why = $x->getMessage(); }
$t('6. a copy failing after its first line comes back as a failure', $why !== '');
$t('6. nothing half-written: no invoice, no line, no customer in that business', $quotes(909) === 0 && (int) $list->query('SELECT COUNT(*) FROM biz_quote_lines WHERE owner_id = 909')->fetchColumn() === 0 && (int) $list->query('SELECT COUNT(*) FROM crm_customers WHERE owner_id = 909')->fetchColumn() === 0);
$t('6. and the order isn\'t linked to anything', (int) $main->query('SELECT IFNULL(biz_quote_id, 0) FROM market_orders WHERE id = 9')->fetchColumn() === 0);
$t('6. the scope is at 0 after it', Scope::owner() === 0 && !Scope::isBusiness());

echo "\n$n checks, $bad failed\n";
foreach (['wo_main', 'wo_list'] as $d) $sys->exec("DROP DATABASE IF EXISTS $d");
exit($bad ? 1 : 0);
