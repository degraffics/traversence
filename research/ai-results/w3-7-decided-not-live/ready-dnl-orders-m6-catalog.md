=== FILE: release.json ===
{
  "name": "orders-m6-catalog-categories-backend",
  "gate": "Lane session checks the Work queue first: M6 is 5th in the Orders queue (handoffs/decided-not-live.md), after M3's test fix, M2b (paused), M3, M4 and M5. The attached documents do not mark M6 itself as paused or waiting for Jason. Its Work-queue parent is in the live database, which isn't attached, so it is not seen here. Do not ship ahead of the queue unless the session manager says so.",
  "what": "DRAFT, partial M6 (research/order-management-design-2026-11-23.md §4.5 and §6 M6). It adds catalog Categories and a Services/Products split by kind as a new, self-contained library (app/lib/Catalog.php) and a twice-safe migration with two new tables. No attached live file is changed. Not included: the Back-Office Catalog tabs (Products · Services · Categories) and the category picker on New product or service. Also not included: Place an order's search grouping, the Shop grouping, and the 'cost checked' column. Those files are not attached (see verify).",
  "files": [
    "files/app/lib/Catalog.php",
    "files/app/migrations/2027-01-26_catalog_categories.sql",
    "tests/catalog-tests.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "SKIPPED FIRST, as the attached documents say they are paused or waiting for Jason: (1) M2b, how an order goes ('Paused by Jason 2026-11-24'). M6 does not depend on it, and nothing here touches it. (2) M7, credit terms ('granted by Jason', 0178). This is not part of M6 and is not touched. (3) Order #12's books ('I will address this after holiday', Jason, 2026-11-27). Not touched. (4) Release B / several destinations (0177 §1, M5). Not part of M6 and not touched.",
    "Review of the attached DRAFT (job 19): the attached file research/ai-results/job19-decided-not-live/dnl-orders-m6-catalog.md is empty. There was no draft code, test or FAQ text to review, so everything here was drafted fresh against the live files and the design doc.",
    "Scope source: research/order-management-design-2026-11-23.md §4.5. Products and Services are 'two lists of one catalog (Products, by kind)'. 'Categories: a new list; a product or service has one.' §2's Catalog table says services are 'Products with kind = service'.",
    "One category per product or service is enforced by catalog_category_items.product_id being the PRIMARY KEY. Setting a new category replaces the old one. Setting 0 clears it.",
    "Categories live in their own link table rather than in a new column on products. This avoids an ALTER on a table whose definition isn't attached, and keeps the migration safe to run twice with no DELIMITER.",
    "A category that still holds items can't be deleted (move them or hide it). Nothing is ever unlinked silently.",
    "No money paths are touched: no QuickBooksPay calls, no ledger, no order totals. The 0177 §3 deposit logic in ClientOrders::depositFor is unchanged."
  ],
  "tests": [
    "tests/catalog-tests.php: plain PHP that prints PASS/FAIL per check and exits 1 on any FAIL. It uses an in-memory SQLite PDO with a minimal products table plus the two new tables. Place it as tools/catalog-tests/run.php, or set TV_APP to the app folder. It covers: create a category; slug collision refused; empty name refused; assign, reassign (still one category), clear; assigning to a missing category or missing product refused; Services vs Products listing by kind; grouping with uncategorised items last as 'Other'; delete refused while it holds items; delete when empty; counts."
  ],
  "risks": [
    "NOTHING HERE WAS RUN OR VERIFIED. The PHP, the SQL and the test are drafts. The lane session runs php -l, the test, and the migration twice on staging, then zip-checks.",
    "The products table name ('products') and its columns 'id', 'name', 'kind', 'status' are assumed. 'name' and 'status' come from the attached code (ClientOrders::addProduct reads $p['name'] and $p['status'] === 'active'); 'kind' comes from the design doc only. Products.php is not attached.",
    "The test runs on SQLite, not MySQL/MariaDB. The library's SQL is written to be portable (no ON DUPLICATE KEY, no NOW(); times come from PHP), but the migration's DDL itself is untested.",
    "The migration filename date 2027-01-26 may clash with or misorder against migrations not shown. The latest attached reference is 2027-01-25_order_proofs_retired.sql.",
    "This is backend only. Until the Back-Office Catalog tabs are built, staff can't create categories from the UI. The FAQ entries therefore say 'coming'.",
    "Shipping ahead of M3–M5 would break the queue order in handoffs/decided-not-live.md. See gate."
  ],
  "verify": [
    "app/lib/Products.php: file not shown. Confirm the table name, the 'kind' column and its values (is a service exactly kind = 'service'?), and the 'status' values. Change Catalog::PRODUCTS / Catalog::SERVICE_KIND if they differ.",
    "admin/backoffice products page (Back-Office → Products, 'backoffice/products'): file not shown. The Catalog tabs (Products · Services · Categories), the header's one filled New button per tab, ⋯ per row, and at 390 the filter button on its own line under the search (§6 M6, Design reviewer's build note) are still to be built there.",
    "admin/client-order.php new_product (CO 218/221): file not shown. Add the category picker to New product or service, calling Catalog::setCategory.",
    "The Shop and Place an order's catalog search: files not shown. Group by Catalog::grouped when built (M4 may own Place an order).",
    "app/lib/Costing.php: file not shown. §4.5's 'whether its cost is checked' column needs it and is not drafted.",
    "The migrations runner and the convention for app/migrations/*.sql (engine, charset, naming): file not shown. Match it.",
    "tools/<area>-tests/ harness conventions: not shown. Adapt tests/catalog-tests.php to it.",
    "The Work queue's M6 task and its parents (live database, not attached): confirm M6 isn't paused and that its turn has come.",
    "No live file in this release is changed, so there is no whole-file or region replacement to byte-check. Only new files are added."
  ],
  "regions": false
}
=== END FILE ===
=== FILE: files/app/lib/Catalog.php ===
<?php
// app/lib/Catalog.php — the catalog's lists (research/order-management-design-2026-11-23.md §4.5, release M6): Products and
// Services as two lists of one catalog (the products table, by kind: a service is kind 'service'), and Categories, a new
// list: a product or service has one (catalog_category_items.product_id is its key), the Shop and Place an order's search
// group by it. 2027-01-26_catalog_categories.sql makes the two tables; until it has run, ready() is false and nothing here
// writes. DRAFT: the products table's name and its kind column are as the design doc says; check them against Products.php.
declare(strict_types=1);

final class Catalog
{
    /** The catalog's table (Products.php) and the kind that makes an item a service (design §2: "kind = service"). */
    public const PRODUCTS = 'products';
    public const SERVICE_KIND = 'service';
    /** The two lists of one catalog. */
    public const LISTS = ['product' => 'Products', 'service' => 'Services'];
    /** What items with no category are grouped under, always last. */
    public const NO_CATEGORY = 'Other';

    /** Has 2027-01-26_catalog_categories.sql run? */
    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM catalog_categories LIMIT 0'); $pdo->query('SELECT 1 FROM catalog_category_items LIMIT 0'); return true; }
        catch (Throwable $x) { return false; }
    }

    private static function need(PDO $pdo): void
    {
        if (!self::ready($pdo)) throw new RuntimeException('Categories wait on their database update (2027-01-26_catalog_categories.sql).');
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    /** "Business Cards & Flyers" → "business-cards-flyers". */
    public static function slug(string $name): string
    {
        $s = strtolower(trim($name));
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        return substr(trim($s, '-'), 0, 64);
    }

    // ---- categories ----------------------------------------------------------------------------------------------------

    /** Every category, in order, each with how many items it holds ('items'). */
    public static function categories(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM catalog_category_items i WHERE i.category_id = c.id) AS items FROM catalog_categories c ORDER BY c.position, c.name, c.id');
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) { $c['items'] = (int) $c['items']; $out[] = $c; }
        return $out;
    }

    public static function category(PDO $pdo, int $id): ?array
    {
        if ($id <= 0 || !self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM catalog_categories WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Adds ($id 0) or changes a category: name, position, shown. Returns its id. */
    public static function saveCategory(PDO $pdo, int $id, array $in, int $staff): int
    {
        self::need($pdo);
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 60);
        if ($name === '') throw new RuntimeException('Name the category.');
        $slug = self::slug($name);
        if ($slug === '') throw new RuntimeException('Give the category a name with letters or numbers in it.');
        $pos = (int) ($in['position'] ?? 0);
        $shown = (string) ($in['shown'] ?? '1') === '0' ? 0 : 1;
        $dup = $pdo->prepare('SELECT id FROM catalog_categories WHERE slug = ? AND id <> ?'); $dup->execute([$slug, $id]);
        if ($dup->fetchColumn()) throw new RuntimeException('There\'s already a category called ' . $name . '.');
        $now = self::now();
        if ($id) {
            if (!self::category($pdo, $id)) throw new RuntimeException('That category isn\'t here.');
            $pdo->prepare('UPDATE catalog_categories SET name = ?, slug = ?, position = ?, shown = ?, updated_at = ? WHERE id = ?')->execute([$name, $slug, $pos, $shown, $now, $id]);
            return $id;
        }
        $pdo->prepare('INSERT INTO catalog_categories (name, slug, position, shown, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $slug, $pos, $shown, $staff ?: null, $now, $now]);
        return (int) $pdo->lastInsertId();
    }

    /** A category taken off the list: only once it holds nothing (move its items, or hide it instead). */
    public static function deleteCategory(PDO $pdo, int $id): void
    {
        self::need($pdo);
        $c = self::category($pdo, $id);
        if (!$c) throw new RuntimeException('That category is already gone.');
        $st = $pdo->prepare('SELECT COUNT(*) FROM catalog_category_items WHERE category_id = ?'); $st->execute([$id]);
        $n = (int) $st->fetchColumn();
        if ($n > 0) throw new RuntimeException($c['name'] . ' holds ' . $n . ' item' . ($n === 1 ? '' : 's') . ': move ' . ($n === 1 ? 'it' : 'them') . ' to another category first, or hide it.');
        $pdo->prepare('DELETE FROM catalog_categories WHERE id = ?')->execute([$id]);
    }

    // ---- a product's or service's category -----------------------------------------------------------------------------

    /** Puts a product or service in a category (one at a time: a new one replaces the old); $categoryId 0 takes it out. */
    public static function setCategory(PDO $pdo, int $productId, int $categoryId, int $staff): void
    {
        self::need($pdo);
        $p = $pdo->prepare('SELECT id FROM ' . self::PRODUCTS . ' WHERE id = ?'); $p->execute([$productId]);
        if (!$p->fetchColumn()) throw new RuntimeException('That product or service isn\'t in the catalog.');
        if ($categoryId && !self::category($pdo, $categoryId)) throw new RuntimeException('Choose a category from the list.');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM catalog_category_items WHERE product_id = ?')->execute([$productId]);
            if ($categoryId) $pdo->prepare('INSERT INTO catalog_category_items (product_id, category_id, set_by, set_at) VALUES (?, ?, ?, ?)')->execute([$productId, $categoryId, $staff ?: null, self::now()]);
            $pdo->commit();
        } catch (Throwable $x) { $pdo->rollBack(); throw $x; }
    }

    /** The category a product or service is in, or null. */
    public static function categoryOf(PDO $pdo, int $productId): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT c.* FROM catalog_category_items i JOIN catalog_categories c ON c.id = i.category_id WHERE i.product_id = ?'); $st->execute([$productId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ---- the lists -----------------------------------------------------------------------------------------------------

    /**
     * One of the catalog's two lists ('product' or 'service'), each item with category_id and category_name (null when it
     * has none), by category then name. $categoryId: that category only. $activeOnly: status 'active' only.
     */
    public static function listing(PDO $pdo, string $list, int $categoryId = 0, bool $activeOnly = false): array
    {
        if (!isset(self::LISTS[$list])) throw new InvalidArgumentException('list');
        $where = $list === 'service' ? 'p.kind = ?' : '(p.kind IS NULL OR p.kind <> ?)';
        $args = [self::SERVICE_KIND];
        if ($activeOnly) { $where .= ' AND p.status = ?'; $args[] = 'active'; }
        if (!self::ready($pdo)) {
            $st = $pdo->prepare('SELECT p.*, NULL AS category_id, NULL AS category_name FROM ' . self::PRODUCTS . ' p WHERE ' . $where . ' ORDER BY p.name, p.id'); $st->execute($args);
            return $categoryId ? [] : $st->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($categoryId) { $where .= ' AND i.category_id = ?'; $args[] = $categoryId; }
        $st = $pdo->prepare('SELECT p.*, c.id AS category_id, c.name AS category_name FROM ' . self::PRODUCTS . ' p
            LEFT JOIN catalog_category_items i ON i.product_id = p.id LEFT JOIN catalog_categories c ON c.id = i.category_id
            WHERE ' . $where . ' ORDER BY (c.id IS NULL), c.position, c.name, p.name, p.id');
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A list grouped by category, for the Shop and Place an order's search: [['id' => category id or 0, 'name' => …,
     * 'items' => [...]], …] in the categories' order, items with no category last under NO_CATEGORY. Empty groups are left out.
     */
    public static function grouped(PDO $pdo, string $list, bool $activeOnly = false): array
    {
        $groups = []; $none = [];
        foreach (self::listing($pdo, $list, 0, $activeOnly) as $p) {
            if ($p['category_id'] === null) { $none[] = $p; continue; }
            $k = (int) $p['category_id'];
            if (!isset($groups[$k])) $groups[$k] = ['id' => $k, 'name' => (string) $p['category_name'], 'items' => []];
            $groups[$k]['items'][] = $p;
        }
        $out = array_values($groups);
        if ($none) $out[] = ['id' => 0, 'name' => self::NO_CATEGORY, 'items' => $none];
        return $out;
    }
}
=== END FILE ===
=== FILE: files/app/migrations/2027-01-26_catalog_categories.sql ===
-- 2027-01-26_catalog_categories.sql — catalog Categories (research/order-management-design-2026-11-23.md §4.5, M6).
-- A product or service has one category: catalog_category_items.product_id is the key. Safe to run twice: only
-- CREATE TABLE IF NOT EXISTS, no ALTER, no UPDATE or DELETE, no DELIMITER. No foreign keys: the products table's
-- definition isn't attached (check it against Products.php); app/lib/Catalog.php keeps the links right.

CREATE TABLE IF NOT EXISTS catalog_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(64) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  shown TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_catalog_categories_slug (slug),
  KEY idx_catalog_categories_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_category_items (
  product_id INT UNSIGNED NOT NULL PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  set_by INT UNSIGNED NULL,
  set_at DATETIME NOT NULL,
  KEY idx_catalog_category_items_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
=== END FILE ===
=== FILE: tests/catalog-tests.php ===
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
=== END FILE ===
=== FILE: faq/faq-entries.md ===
# FAQ entries (M6, the catalog). DRAFT, as the visitor sees them

## Do you offer services as well as printed products?
Coming. The catalog will list our services, such as design work, on their own, next to our printed products. Until then, ask us for a service and we'll add it to your order.

## Can I browse the catalog by category?
Coming. Each product and service will sit in one category, and the shop will group them that way so you can find what you need faster. For now, search the shop by name, or ask us.
=== END FILE ===