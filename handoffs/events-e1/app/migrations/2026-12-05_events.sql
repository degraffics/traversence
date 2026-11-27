-- decisions/0134's minimum (the note of 2026-11-27, option (a)): the events a business posts, and each change's old value.
-- Creates only; safe to run twice. Staging classes: RECORDS (app/lib/staging-tables.php).
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_id INT NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  about TEXT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT '',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  all_day TINYINT(1) NOT NULL DEFAULT 0,
  zone VARCHAR(64) NOT NULL DEFAULT '',
  age VARCHAR(4) NOT NULL DEFAULT 'all',
  showtimes TEXT NULL,
  ticket_item_id INT UNSIGNED NULL,
  gallery_item_id INT UNSIGNED NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  created_tz VARCHAR(64) NOT NULL DEFAULT '',
  updated_by INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  KEY host_start (entity_id, starts_at),
  KEY status_start (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS event_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  at DATETIME NOT NULL,
  tz VARCHAR(64) NOT NULL DEFAULT '',
  user_id INT NOT NULL DEFAULT 0,
  field VARCHAR(30) NOT NULL DEFAULT '',
  old_value TEXT NULL,
  KEY event_at (event_id, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
