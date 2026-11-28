-- The way back for 2026-11-28_w14_adhs.sql (run by the release's Roll back, Releases.php): W14 paused again and its terms
-- task switched off, each only if this release's switch-on is still what holds (a person's later switch stands). The
-- ADHS source row and its columns stay, unused (they only add).
UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'Rolled back: the ADHS terms read (2026-11-28) undone', off_at = UTC_TIMESTAMP()
 WHERE task = 'w14_terms' AND enabled = 1 AND off_by IS NULL;
UPDATE crawl_workflows SET paused = 1, paused_at = NOW(), paused_why = 'Rolled back: the ADHS terms read (2026-11-28) undone'
 WHERE wf = 'W14' AND paused = 0 AND paused_by IS NULL;
