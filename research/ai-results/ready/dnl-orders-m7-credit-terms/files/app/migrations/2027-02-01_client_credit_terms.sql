-- Orders M7: credit terms on a client's account (decisions/0178 §4). Safe to run twice: it only creates a table that
-- isn't there yet. No UPDATE or DELETE. Rename the date to the next in the live migrations' sequence before shipping.
CREATE TABLE IF NOT EXISTS client_credit_terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  terms VARCHAR(10) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'asked',
  credit_note VARCHAR(400) NOT NULL DEFAULT '',
  asked_by INT UNSIGNED NULL,
  asked_at DATETIME NOT NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decided_note VARCHAR(400) NOT NULL DEFAULT '',
  ended_by INT UNSIGNED NULL,
  ended_at DATETIME NULL,
  ended_note VARCHAR(400) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_cct_user (user_id),
  KEY idx_cct_email (email),
  KEY idx_cct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
