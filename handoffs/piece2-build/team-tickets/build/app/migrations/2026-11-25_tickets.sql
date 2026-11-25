-- Piece 2's tickets (decisions/0179; handoffs/piece2-team.md §3.1, approved by Jason 2026-11-25): the Work queue's tasks get a
-- type (Work, Audit, Resolution), a stage, its time, a tier, an audit finding's key and the ticket it came from; each stage move
-- is kept (control_gauge_task_moves) and a Resolution ticket's Decision Brief has its own row (control_gauge_task_briefs).
-- Guarded, safe to run twice; no data changed here (the stages of today's tasks are set in 2026-11-25_tickets_stage.sql).

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN ticket VARCHAR(12) NOT NULL DEFAULT ''work''', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'ticket');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN stage VARCHAR(16) NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'stage');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN stage_at DATETIME NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'stage_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN tier TINYINT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'tier');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN audit_key VARCHAR(120) NOT NULL DEFAULT ''''', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'audit_key');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN parent_id INT UNSIGNED NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'parent_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD INDEX ticket_stage (ticket, stage)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND INDEX_NAME = 'ticket_stage');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
CREATE TABLE IF NOT EXISTS control_gauge_task_moves (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL, at DATETIME NOT NULL,
  tz VARCHAR(64) NOT NULL DEFAULT '', from_stage VARCHAR(16) NOT NULL DEFAULT '', to_stage VARCHAR(16) NOT NULL DEFAULT '', who VARCHAR(80) NOT NULL DEFAULT '', why VARCHAR(400) NOT NULL DEFAULT '',
  run_id INT UNSIGNED NULL, KEY task_at (task_id, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS control_gauge_task_briefs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
  decision TEXT NULL, why_you TEXT NULL, boundary VARCHAR(20) NOT NULL DEFAULT '', a_text TEXT NULL, a_pros TEXT NULL, a_cons TEXT NULL, b_text TEXT NULL, b_pros TEXT NULL, b_cons TEXT NULL,
  recommend CHAR(1) NOT NULL DEFAULT '', delay_impact TEXT NULL, cost VARCHAR(255) NOT NULL DEFAULT '', answer CHAR(1) NOT NULL DEFAULT '', answer_words TEXT NULL,
  answered_by VARCHAR(80) NOT NULL DEFAULT '', answered_at DATETIME NULL, answer_tz VARCHAR(64) NOT NULL DEFAULT '', precedent VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY task (task_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
