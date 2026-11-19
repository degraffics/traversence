<?php
// The Control Board's 4d (the session manager, 2026-11-19): before the Founder decides on the unused tables and columns,
// how many rows hold a value in each: SchemaUpkeep::filled counts (never values), only for names on the unused list,
// and health.php's unused_schema carries them. Run: mariadb < ../release-b-tests/start-schema.sql && php r4d.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/SchemaUpkeep.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$db = Database::mainName();
$pdo->exec('DROP TABLE IF EXISTS zz_orders'); $pdo->exec('CREATE TABLE zz_orders (id INT PRIMARY KEY, cost INT NULL, note VARCHAR(20) NULL, gone VARCHAR(5) NULL)');
$pdo->exec("INSERT INTO zz_orders VALUES (1, 500, '', NULL), (2, NULL, 'x', NULL), (3, 0, NULL, '')");
$pdo->exec('DROP TABLE IF EXISTS zz_old'); $pdo->exec('CREATE TABLE zz_old (id INT)'); $pdo->exec('INSERT INTO zz_old VALUES (1),(2),(3),(4)');
$f = dirname($app) . '/app/storage/schema/unused.json'; @mkdir(dirname($f), 0775, true); $was = @file_get_contents($f);
file_put_contents($f, json_encode(['at' => gmdate('c'), 'files' => 900, 'tables' => [['key' => 'table zz_old', 'db' => $db, 'table' => 'zz_old', 'rows' => 0, 'bytes' => 1]],
    'columns' => [['key' => 'column zz_orders.cost', 'db' => $db, 'table' => 'zz_orders', 'column' => 'cost'], ['key' => 'column zz_orders.note', 'db' => $db, 'table' => 'zz_orders', 'column' => 'note'], ['key' => 'column zz_orders.gone', 'db' => $db, 'table' => 'zz_orders', 'column' => 'gone']]]));
$t('a column with values: counted (a zero is a value; NULL and empty aren\'t)', SchemaUpkeep::filled($pdo, $db, 'zz_orders', 'cost') === 2);
$t('…a text column: empty strings aren\'t values', SchemaUpkeep::filled($pdo, $db, 'zz_orders', 'note') === 1);
$t('…a column empty in every row: 0, so it can be safe to remove', SchemaUpkeep::filled($pdo, $db, 'zz_orders', 'gone') === 0);
$t('a table\'s exact row count', SchemaUpkeep::filled($pdo, $db, 'zz_old') === 4);
$t('a name that isn\'t on the unused list is never read', SchemaUpkeep::filled($pdo, $db, 'zz_orders', 'id') === null && SchemaUpkeep::filled($pdo, $db, 'zz_orders') === null);
$t('a name that isn\'t a plain identifier is refused', SchemaUpkeep::filled($pdo, $db, 'zz_orders`; DROP TABLE x; --', 'cost') === null);
$hp = file_get_contents(dirname($app) . '/website/api/health.php');
$t('health.php carries each column\'s count and each table\'s exact rows (counts only)', str_contains($hp, "'filled' => SchemaUpkeep::filled(") && str_contains($hp, "'rows_exact' => SchemaUpkeep::filled("));
if ($was === false) @unlink($f); else file_put_contents($f, $was);
$pdo->exec('DROP TABLE zz_orders'); $pdo->exec('DROP TABLE zz_old');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
