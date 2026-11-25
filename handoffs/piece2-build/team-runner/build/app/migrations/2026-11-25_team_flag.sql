-- Piece 2, P2-4 (decisions/0179's note; handoffs/piece2-team.md §6): a ticket's flag when only its words suggest a Tier 3
-- boundary (TeamRunner::tier): the area's key, '' when none. Adds a column only; safe to run twice.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN flag VARCHAR(20) NOT NULL DEFAULT ''''', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'flag');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
