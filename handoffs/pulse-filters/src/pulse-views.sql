-- Pulse's saved views (design/table-standard.md, Jason's note and answers of 2026-11-20: "With my account"): a person's
-- own named mix of Pulse's filters, kept with their account across devices. Private to its owner. Safe to run twice.
-- Named in app/lib/staging-tables.php (records).
CREATE TABLE IF NOT EXISTS pulse_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  filters TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY pulse_views_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
