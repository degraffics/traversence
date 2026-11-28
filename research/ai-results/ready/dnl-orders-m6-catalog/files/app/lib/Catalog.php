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
