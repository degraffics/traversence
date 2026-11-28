-- The advocates' own audit (AdvocatesAudit): its findings' runs and its state. Add-only (decisions/0179's note: safe additive);
-- safe to run twice. AdvocatesAudit::ensure() makes the same tables.
CREATE TABLE IF NOT EXISTS advocate_audit_seen (akey CHAR(44) NOT NULL PRIMARY KEY, who VARCHAR(40) NOT NULL DEFAULT '', page VARCHAR(200) NOT NULL DEFAULT '',
  size VARCHAR(8) NOT NULL DEFAULT '', rule VARCHAR(20) NOT NULL DEFAULT '', what VARCHAR(300) NOT NULL DEFAULT '', runs INT NOT NULL DEFAULT 0,
  last_run VARCHAR(160) NOT NULL DEFAULT '', task_id INT UNSIGNED NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS advocate_audit_state (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
