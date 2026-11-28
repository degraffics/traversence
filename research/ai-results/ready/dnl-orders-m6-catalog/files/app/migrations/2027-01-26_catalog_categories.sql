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
