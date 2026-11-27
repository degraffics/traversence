<?php
// tools/market-tests/write-order-place.php <root>: placing a direct-pay order whose copy in the business's data fails
// (MarketCheckout::place, the !$card branch). The order is placed, the buyer still gets the payment instructions, the
// business is told its invoice wasn't written, and the order keeps a note; with a working copy, the usual words.
// Runs MarketCheckout, BusinessData, Scope and BizQuotes as built on a copy of <root>'s app/ in which the outside
// libraries the branch passes through (MarketBiz, KindCheckout, Standing, policies, messages, mail) are stand-ins that
// record what they're given. MariaDB: makes and drops wo_main and wo_list.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/') . '/app';
if (!is_file($src . '/lib/MarketCheckout.php')) { fwrite(STDERR, "usage: write-order-place.php <root with app/>\n"); exit(2); }
$base = sys_get_temp_dir() . '/wo-place-' . getmypid(); $root = $base . '/x/y/root'; @mkdir($root, 0777, true);
exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($root . '/app'));
$stub = [
  'Standing' => 'final class Standing { static function guard($p, $u): void {} }',
  'MarketBiz' => 'final class MarketBiz { static function onTeam($p, $u, $e, $r = ""): bool { return false; }
     static function get($p, $id): ?array { return ["id" => $id, "entity_id" => $id === 2 ? 202 : 101, "product_id" => 0, "title" => "TEST · candle", "price_qty" => 1, "price_cents" => 1800, "taxable" => 0, "kind" => "physical", "track_stock" => 0, "stock" => 0, "kind_data" => null, "status" => "published", "price_mode" => "fixed"]; }
     static function buttonsFor($p, $it): array { return ["buy"]; } static function tier($p, $e): string { return "free"; }
     static function entity($p, $e): array { return ["name" => "TEST · shop " . $e]; } }',
  'KindCheckout' => 'final class KindCheckout { const ALONE = []; static function ready($p): bool { return false; } static function buyable($p, $it): array { return [true, ""]; }
     static function seatsLeft(...$a) { return null; }
     static function prepare($p, $lines, $kx, $b, $g): array { return ["lines" => $lines, "extra" => [], "mode" => "payment", "ships" => false, "balance" => 0, "hold" => false, "house_rules" => ""]; }
     static function getting($kp): string { return "Pick up"; } static function fulfil($p, $o): array { return []; } }',
  'Acceptance' => 'final class Acceptance { static function record(...$a): void {} static function ready($p): bool { return false; } }',
  'StorePolicies' => 'final class StorePolicies { static function ready($p): bool { return false; } static function snapshot(...$a): array { return ["v" => 1]; } static function plain($s): string { return ""; } }',
  'LegalTexts' => 'final class LegalTexts { static function clause(...$a): array { return ["body" => "", "version" => "1"]; } static function current(...$a): array { return ["1"]; } static function fill($b, $i): string { return $b; } static function identity(): array { return []; } static function live(...$a) { return null; } }',
  'BizMessages' => 'final class BizMessages { static array $sent = []; static function personSend($p, $buyer, $e, $text, $tell): void { self::$sent[] = $text; } }',
  'Marketing' => 'final class Marketing {}',
  'Plan' => 'final class Plan { static function allows($p, $tier, $what): array { return [false, ""]; } static function upgradeNote($s): string { return "Card checkout comes with a paid plan."; } }',
  'Mailer' => 'final class Mailer { static array $sent = []; static function send($to, $subject, $body, $name = "", ...$r): bool { self::$sent[] = [$to, $subject, $body]; return true; } }',
  'MarketRules' => 'final class MarketRules { static function cardOk($it): bool { return true; } }',
];
foreach ($stub as $c => $code) file_put_contents("$root/app/lib/$c.php", "<?php\n$code\n");
$mp = file_get_contents("$root/app/lib/MarketPay.php");   // the real MarketPay's provider names; env() only says sandbox here
if (!preg_match('/const PROVIDERS = (\[[^;]*\]);/s', $mp, $m)) { fwrite(STDERR, "MarketPay::PROVIDERS not found\n"); exit(2); }
file_put_contents("$root/app/lib/MarketPay.php", "<?php\nfinal class MarketPay { const PROVIDERS = {$m[1]}; static function env(): string { return 'sandbox'; } }\n");

$n = 0; $bad = 0; $t = function (string $w, bool $ok) use (&$n, &$bad) { $n++; if (!$ok) $bad++; echo ($ok ? '  ok  ' : 'FAIL  ') . $w . "\n"; };
$sys = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['wo_main', 'wo_list'] as $d) { $sys->exec("DROP DATABASE IF EXISTS $d"); $sys->exec("CREATE DATABASE $d CHARACTER SET utf8mb4"); }
$sys->exec("CREATE OR REPLACE USER 'wo'@'localhost' IDENTIFIED BY 'wo'"); $sys->exec("GRANT ALL ON wo_main.* TO 'wo'@'localhost'"); $sys->exec("GRANT ALL ON wo_list.* TO 'wo'@'localhost'");
$sys->exec('SET GLOBAL log_bin_trust_function_creators = 1');
file_put_contents($base . '/x/.env', "DB_HOST=localhost\nDB_DATABASE=wo_main\nDB_USERNAME=wo\nDB_PASSWORD=wo\nDB_LISTINGS_DATABASE=wo_list\nDB_LISTINGS_USERNAME=wo\nDB_LISTINGS_PASSWORD=wo\n");
require_once "$root/app/lib/Database.php"; require_once "$root/app/lib/Plan.php"; require_once "$root/app/lib/MarketCheckout.php"; require_once "$root/app/lib/BusinessData.php";
$main = Database::connection();
$main->exec("CREATE TABLE market_orders (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token CHAR(32), entity_id INT UNSIGNED, buyer_id INT UNSIGNED, email VARCHAR(190), name VARCHAR(160), order_lines TEXT,
  ship_name VARCHAR(120), ship_address VARCHAR(400), subtotal_cents INT, ship_cents INT, tax_cents INT, total_cents INT, method VARCHAR(20), provider VARCHAR(20), env VARCHAR(20), status VARCHAR(20),
  hold_until DATETIME, note TEXT, created_at DATETIME, updated_at DATETIME, biz_quote_id INT UNSIGNED DEFAULT NULL, pay_now_cents INT DEFAULT NULL, pay_mode VARCHAR(20) DEFAULT NULL, policy_json TEXT)");
$main->exec("CREATE TABLE market_checkout (entity_id INT UNSIGNED PRIMARY KEY, settings TEXT, live TINYINT DEFAULT 1)");
$main->exec("CREATE TABLE market_cart (user_id INT, entity_id INT)");
$main->exec("CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, email VARCHAR(190), display_name VARCHAR(160))");
$main->exec("CREATE TABLE market_gateways (entity_id INT, provider VARCHAR(20), status VARCHAR(20), account_id VARCHAR(80), env VARCHAR(20))");
$ways = json_encode(['methods' => ['zelle' => ['label' => 'Zelle', 'text' => 'Send it to shop@example.test']], 'hold_days' => 3]);
$main->prepare('INSERT INTO market_checkout (entity_id, settings, live) VALUES (101, ?, 1), (202, ?, 1)')->execute([$ways, $ways]);
$main->exec("INSERT INTO users VALUES (9, 'buyer@example.test', 'Test Buyer'), (10, 'not an email', 'Test Bad')");
BusinessData::ensure(new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=wo_list', 'wo', 'wo', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
$list = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=wo_list', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// a working copy: the usual words, its invoice written
$o = MarketCheckout::place($main, 9, 101, ['item' => 1, 'qty' => 1, 'method' => 'zelle']);
$t('a direct-pay order is placed (awaiting)', ($o['status'] ?? '') === 'awaiting');
$t('its invoice is written in the business\'s data', (int) $list->query('SELECT COUNT(*) FROM biz_quotes WHERE owner_id = 101')->fetchColumn() === 1 && (int) $o['biz_quote_id'] > 0);
$t('the business is told to mark it paid in Quotes & invoices', str_contains(end(BizMessages::$sent), 'Mark it paid in Quotes & invoices'));
$t('the buyer gets how to pay', str_contains(end(Mailer::$sent)[1], 'how to pay') && str_contains(end(Mailer::$sent)[2], 'by Zelle'));
$t('the request is ours again after', Scope::owner() === 0 && !Scope::isBusiness());

// a failing copy (BizQuotes refuses the buyer's email inside the run): placed all the same
$mails = count(Mailer::$sent); $threw = '';
try { $o2 = MarketCheckout::place($main, 10, 101, ['item' => 1, 'qty' => 1, 'method' => 'zelle']); } catch (Throwable $x) { $threw = $x->getMessage(); $o2 = []; }
$t('a failed copy never reaches the buyer as an error', $threw === '' && ($o2['status'] ?? '') === 'awaiting');
$t('the buyer still gets how to pay', count(Mailer::$sent) === $mails + 1 && str_contains(end(Mailer::$sent)[2], 'by Zelle'));
$t('the business is told its invoice wasn\'t written, and why', str_contains(end(BizMessages::$sent), 'Its invoice in Quotes & invoices wasn\'t written (Add the customer') && !str_contains(end(BizMessages::$sent), 'Mark it paid'));
$t('the order keeps a note of it', str_contains((string) $main->query('SELECT note FROM market_orders WHERE id = ' . (int) ($o2['id'] ?? 0))->fetchColumn(), 'Placed, but the business\'s own order wasn\'t written'));
$t('nothing half-written in the business\'s data', (int) $list->query('SELECT COUNT(*) FROM biz_quotes WHERE owner_id = 101')->fetchColumn() === 1);
$t('the scope is at 0 after the failure', Scope::owner() === 0 && !Scope::isBusiness());

// and the next business's order in the same request is written
$o3 = MarketCheckout::place($main, 9, 202, ["item" => 2, 'qty' => 1, 'method' => 'zelle']);
$t('the next business\'s order in the same request is written in its own data', (int) $list->query('SELECT COUNT(*) FROM biz_quotes WHERE owner_id = 202')->fetchColumn() === 1);

echo "\n$n checks, $bad failed\n";
foreach (['wo_main', 'wo_list'] as $d) $sys->exec("DROP DATABASE IF EXISTS $d");
exec('rm -rf ' . escapeshellarg($base));
exit($bad ? 1 : 0);
