-- The Team's runner (decisions/0179's Piece 2, P2-3; handoffs/piece2-team.md §3.3, §5): its log of runs, staging's own (each
-- with what it saw and did, its Undo, a proposal when it only proposes, and what it touched for piece 3's check), and a Work
-- ticket's build brief (what the System Architect writes for the session manager). Guarded, safe to run twice; no data.
CREATE TABLE IF NOT EXISTS team_runs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, role VARCHAR(60) NOT NULL DEFAULT '',
  task_id INT UNSIGNED NULL, step VARCHAR(20) NOT NULL DEFAULT '', seen VARCHAR(400) NOT NULL DEFAULT '', did VARCHAR(400) NOT NULL DEFAULT '',
  state VARCHAR(10) NOT NULL DEFAULT 'done', why VARCHAR(400) NOT NULL DEFAULT '', proposal TEXT NULL, undo_how VARCHAR(400) NOT NULL DEFAULT '', undo_data TEXT NULL,
  undone_at DATETIME NULL, undone_by VARCHAR(80) NOT NULL DEFAULT '', settled_at DATETIME NULL, settled_by VARCHAR(80) NOT NULL DEFAULT '',
  cost_micros INT NOT NULL DEFAULT 0, touches VARCHAR(400) NOT NULL DEFAULT '', KEY task_at (task_id, at), KEY at (at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE control_gauge_tasks ADD COLUMN build_brief TEXT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME = 'build_brief');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
-- The Team's settings (P2-3, Jason's ceiling, 2026-11-25): how far agents work and the decision tree's approval (its rules'
-- fingerprint, who, when). Records: set by the Founder on live, read by the runner on staging after catch-up.
CREATE TABLE IF NOT EXISTS team_settings (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', who VARCHAR(80) NOT NULL DEFAULT '', at DATETIME NULL, tz VARCHAR(64) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
