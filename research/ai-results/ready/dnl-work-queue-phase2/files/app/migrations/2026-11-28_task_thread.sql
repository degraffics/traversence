-- app/migrations/2026-11-28_task_thread.sql — Work queue phase 2, release 1: a task's thread (handoffs/work-queue-phase2.md).
-- Additive only: one new table (0180 §4 item 2: additive tables stay Tier 2). Safe to run twice. No UPDATE, no DELETE.
-- TaskThread::ensure() makes the same table if this hasn't run.
CREATE TABLE IF NOT EXISTS control_gauge_task_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  at DATETIME NOT NULL,
  tz VARCHAR(64) NOT NULL DEFAULT '',
  kind VARCHAR(10) NOT NULL DEFAULT 'person',
  who VARCHAR(80) NOT NULL DEFAULT '',
  uid INT NOT NULL DEFAULT 0,
  body TEXT NOT NULL,
  edited_at DATETIME NULL,
  undone_at DATETIME NULL,
  undone_by VARCHAR(80) NOT NULL DEFAULT '',
  KEY task_at (task_id, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
