<?php
// tools/catalog-tests/run.php — Catalog (M6): categories, one per item, Products vs Services, grouping.
// Plain PHP: prints PASS/FAIL per check, exits 1 on any FAIL. In-memory SQLite (no live data, no money, no QuickBooks).
// Set TV_APP to the app folder if this isn't at tools/catalog-tests/.
declare(strict_types=1);

$app = getenv('TV_APP') ?: __DIR__ . '/../../app';
require_once rtrim($app, '/') . '/lib/Catalog.php';

$fails = 0;
function check(string $what, bool $ok): void { global $fails; echo ($ok ? 'PASS ' : 'FAIL ') . $what . "\n"; if (!$ok) $fails++; }
function throws(callable $f): bool { try { $f(); return false; } catch (Throwable $x) { return true; } }

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "FAIL pdo_sqlite isn't available: run on staging with it on\n"; exit(1); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

check('not ready before the migration', Catalog::ready($pdo) === false);

$pdo->exec("CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, kind TEXT, status TEXT NOT NULL DEFAULT 'active')");
$pdo->exec('CREATE TABLE catalog_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, position INTEGER NOT NULL DEFAULT 0,
            shown INTEGER NOT NULL DEFAULT 1, created_by INTEGER NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
$pdo->exec('CREATE TABLE catalog_category_items (product_id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL, set_by INTEGER NULL, set_at TEXT NOT NULL)');
$ins = $pdo->prepare('INSERT INTO products (name, kind, status) VALUES (?, ?, ?)');
foreach ([['Business cards', 'product', 'active'], ['Flyers', 'product', 'active'], ['Banner', null, 'active'], ['Logo design', 'service', 'active'], ['Old poster', 'product', 'archived']] as $r) $ins->execute($r);
// ids: 1 cards, 2 flyers, 3 banner, 4 logo design (service), 5 old poster

check('ready after the migration', Catalog::ready($pdo) === true);
check('slug', Catalog::slug('  Business Cards & Flyers ') === 'business-cards-flyers');

$print = Catalog::saveCategory($pdo, 0, ['name' => 'Print', 'position' => 1], 9);
$design = Catalog::saveCategory($pdo, 0, ['name' => 'Design', 'position' => 2], 9);
check('two categories made', $print > 0 && $design > 0 && $print !== $design);
check('empty name refused', throws(fn () => Catalog::saveCategory($pdo, 0, ['name' => '  '], 9)));
check('symbols-only name refused', throws(fn () => Catalog::saveCategory($pdo, 0, ['name' => '&&&'], 9)));
check('same name again refused', throws(fn () => Catalog::saveCategory($pdo, 0, ['name' => 'print'], 9)));
check('renaming to itself is fine', Catalog::saveCategory($pdo, $print, ['name' => 'Print', 'position' => 1], 9) === $print);
check('changing a missing category refused', throws(fn () => Catalog::saveCategory($pdo, 999, ['name' => 'Ghost'], 9)));

Catalog::setCategory($pdo, 1, $print, 9);
Catalog::setCategory($pdo, 2, $print, 9);
Catalog::setCategory($pdo, 4, $design, 9);
check('business cards are in Print', (Catalog::categoryOf($pdo, 1)['id'] ?? 0) == $print);
Catalog::setCategory($pdo, 2, $design, 9);
$n = (int) $pdo->query('SELECT COUNT(*) FROM catalog_category_items WHERE product_id = 2')->fetchColumn();
check('a new category replaces the old (one per item)', $n === 1 && (Catalog::categoryOf($pdo, 2)['id'] ?? 0) == $design);
Catalog::setCategory($pdo, 2, 0, 9);
check('0 takes it out of its category', Catalog::categoryOf($pdo, 2) === null);
check('a missing category refused', throws(fn () => Catalog::setCategory($pdo, 1, 999, 9)));
check('a missing product refused', throws(fn () => Catalog::setCategory($pdo, 999, $print, 9)));
check('cards still in Print after the refusals', (Catalog::categoryOf($pdo, 1)['id'] ?? 0) == $print);

$services = Catalog::listing($pdo, 'service');
check('Services: only kind service', count($services) === 1 && $services[0]['name'] === 'Logo design' && $services[0]['category_name'] === 'Design');
$products = Catalog::listing($pdo, 'product');
check('Products: everything else, a null kind included', count($products) === 4);
check('Products: active only', count(Catalog::listing($pdo, 'product', 0, true)) === 3);
check('Products in Print', array_column(Catalog::listing($pdo, 'product', $print), 'name') === ['Business cards']);
check('an unknown list refused', throws(fn () => Catalog::listing($pdo, 'bundles')));

$g = Catalog::grouped($pdo, 'product', true);
check('grouped: Print first, Other last', count($g) === 2 && $g[0]['name'] === 'Print' && $g[1]['name'] === Catalog::NO_CATEGORY && $g[1]['id'] === 0);
check('grouped: Other holds flyers and banner', array_column($g[1]['items'], 'name') === ['Banner', 'Flyers']);

$cats = Catalog::categories($pdo);
check('counts per category', $cats[0]['name'] === 'Print' && $cats[0]['items'] === 1 && $cats[1]['items'] === 1);
check('a category with items can\'t be deleted', throws(fn () => Catalog::deleteCategory($pdo, $design)));
Catalog::setCategory($pdo, 4, 0, 9);
Catalog::deleteCategory($pdo, $design);
check('an empty category is deleted', Catalog::category($pdo, $design) === null);
check('deleting it again says it\'s gone', throws(fn () => Catalog::deleteCategory($pdo, $design)));

echo $fails ? "\n$fails FAIL\n" : "\nall PASS\n";
exit($fails ? 1 : 0);
