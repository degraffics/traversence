-- decisions/0134's note (#1589): businesses taking part in a host's event. Creates only; safe to run twice. Staging: RECORDS.
CREATE TABLE IF NOT EXISTS event_participants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  entity_id INT NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'invited',
  prev_status VARCHAR(12) NOT NULL DEFAULT '',
  offer_text VARCHAR(160) NOT NULL DEFAULT '',
  offer_code VARCHAR(40) NOT NULL DEFAULT '',
  offer_code_words VARCHAR(120) NOT NULL DEFAULT '',
  offer_code_starts DATE DEFAULT NULL,
  offer_code_ends DATE DEFAULT NULL,
  offer_code_min INT UNSIGNED NOT NULL DEFAULT 0,
  invited_by INT NOT NULL DEFAULT 0,
  invited_at DATETIME NOT NULL,
  answered_by INT NOT NULL DEFAULT 0,
  changed_at DATETIME NOT NULL,
  changed_tz VARCHAR(64) NOT NULL DEFAULT '',
  UNIQUE KEY event_entity (event_id, entity_id),
  KEY entity_status (entity_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
