-- Trips you can save (decisions/0174 §1, §6, release 2): a member's own list of stops in order. Private: never shown,
-- never counted per person. Safe to run twice. Named in app/lib/staging-tables.php (records).
CREATE TABLE IF NOT EXISTS trips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  starts_on DATE DEFAULT NULL,
  days TINYINT UNSIGNED NOT NULL DEFAULT 1,
  tz VARCHAR(64) NOT NULL DEFAULT '',
  share_token VARCHAR(40) DEFAULT NULL,
  shared TINYINT(1) NOT NULL DEFAULT 0,
  guide_id INT UNSIGNED DEFAULT NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY trips_user (user_id, deleted_at, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS trip_stops (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  day TINYINT UNSIGNED NOT NULL DEFAULT 1,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  kind VARCHAR(12) NOT NULL,
  ref VARCHAR(80) NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  planned_at DATETIME DEFAULT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY trip_stops_trip (trip_id, day, position),
  KEY trip_stops_ref (kind, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
