-- The control board's one engine (handoffs/control-board-build.md §2.1, Migration A): an alarm's component (P2), its tries on
-- the diagnostic clock (P3: try_n, next_try_at) and its Incident Report (P12). Add-only, safe to run twice. control_alarms is
-- created by ControlAlarms::ensure() on the main database (OWN in staging-tables.php).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_alarms ADD COLUMN component VARCHAR(80) NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_alarms' AND COLUMN_NAME = 'component');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_alarms ADD COLUMN try_n TINYINT UNSIGNED NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_alarms' AND COLUMN_NAME = 'try_n');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_alarms ADD COLUMN next_try_at DATETIME NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_alarms' AND COLUMN_NAME = 'next_try_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_alarms ADD COLUMN incident_id INT UNSIGNED NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_alarms' AND COLUMN_NAME = 'incident_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_alarms ADD INDEX next_try (next_try_at)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_alarms' AND INDEX_NAME = 'next_try');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
