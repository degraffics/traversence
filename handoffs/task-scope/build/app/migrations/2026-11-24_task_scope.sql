-- app/migrations/2026-11-24_task_scope.sql: a Work queue task's scope, priority and steps (Jason, 2026-11-24: "Each task must open
-- and allow me to scope and set priorities and work that needs to be done"). Each column added only when missing; safe to run twice.
CREATE TABLE IF NOT EXISTS control_gauge_task_steps (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL, pos INT NOT NULL DEFAULT 0, see VARCHAR(600) NOT NULL DEFAULT '', do_what VARCHAR(600) NOT NULL DEFAULT '', confirm VARCHAR(600) NOT NULL DEFAULT '', at DATETIME NOT NULL, who VARCHAR(80) NOT NULL DEFAULT '', done_at DATETIME NULL, done_by VARCHAR(80) NOT NULL DEFAULT '', tz VARCHAR(64) NOT NULL DEFAULT '', KEY task_pos (task_id, pos)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN goal TEXT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'goal');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN in_scope TEXT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'in_scope');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN out_scope TEXT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'out_scope');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN done_when TEXT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'done_when');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN priority TINYINT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'priority');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
