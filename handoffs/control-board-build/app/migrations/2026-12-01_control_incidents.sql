-- Incident Reports, IR-#### (handoffs/control-board-build.md §2.1, Migration B; the state model's P12). One per trip, or a
-- trip joining an open one with its signature inside 15 minutes. Each site's own (OWN in staging-tables.php). Kept 365 days.
CREATE TABLE IF NOT EXISTS control_incidents (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  opened_at DATETIME NOT NULL, closed_at DATETIME NULL, host VARCHAR(120) NOT NULL DEFAULT '', signature VARCHAR(160) NOT NULL DEFAULT '',
  title VARCHAR(200) NOT NULL DEFAULT '', radius TEXT NULL, evidence TEXT NULL, timeline MEDIUMTEXT NULL,
  root_cause VARCHAR(600) NULL, root_cause_state VARCHAR(12) NOT NULL DEFAULT 'unconfirmed', root_cause_by VARCHAR(80) NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'board', KEY open_sig (signature, closed_at), KEY opened (opened_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
