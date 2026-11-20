-- Personal Guides (decisions/0174 §1, release 4; 0058 §1): a trip its owner publishes, public and credited, checked like
-- a journey before it shows. Live with its trip; its own words (title, intro) are checked again when they change.
-- Safe to run twice. Named in app/lib/staging-tables.php (records).
CREATE TABLE IF NOT EXISTS personal_guides (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  intro TEXT,
  status VARCHAR(12) NOT NULL DEFAULT 'published',
  hidden_reason VARCHAR(300) NOT NULL DEFAULT '',
  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY personal_guides_trip (trip_id),
  KEY personal_guides_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
