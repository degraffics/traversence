-- The Team (decisions/0179's Piece 2, handoffs/piece2-team.md §3.2): one row per role in Jason's spec (§11). Its seed is
-- in its own file (2026-11-25_team_roster_seed.sql), so this file's table check never clears the seed unrun (0162's note).
-- Staging class: records (staging-tables.php). Safe to run twice.
CREATE TABLE IF NOT EXISTS team_roster (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  department VARCHAR(20) NOT NULL DEFAULT 'build',
  kind VARCHAR(12) NOT NULL DEFAULT 'operator',
  made_of VARCHAR(10) NOT NULL DEFAULT 'ai',
  spec_no VARCHAR(10) NOT NULL DEFAULT '',
  mandate TEXT NULL,
  rules TEXT NULL,
  stages VARCHAR(120) NOT NULL DEFAULT '',
  acts_level VARCHAR(12) NOT NULL DEFAULT '',
  acts_dept VARCHAR(8) NOT NULL DEFAULT '',
  tier_max TINYINT NOT NULL DEFAULT 1,
  test_account VARCHAR(30) NOT NULL DEFAULT '',
  reports_to VARCHAR(60) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'standby',
  sort INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(80) NOT NULL DEFAULT '',
  UNIQUE KEY slug (slug),
  KEY dept_status (department, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
