-- W14 (decisions/0164 §7.1, Jason through the CEO, 2026-11-28): Arizona's licensed health facilities (ADHS, AZ Care Check)
-- staged as an authority source, switched off, and the crawler's terms read kept on each source for a person to decide.
-- Safe to run twice: each column is added only when missing, and the source row only when its state and url aren't there.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE authority_sources ADD COLUMN robots VARCHAR(12) DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'authority_sources' AND COLUMN_NAME = 'robots');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE authority_sources ADD COLUMN terms_fetch MEDIUMTEXT DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'authority_sources' AND COLUMN_NAME = 'terms_fetch');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE authority_sources ADD COLUMN fetched_at DATETIME DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'authority_sources' AND COLUMN_NAME = 'fetched_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
INSERT INTO authority_sources (state, kind, name, url, host, service, real_use, proves, terms_url, provenance, note, added_at)
SELECT 'AZ', 'health', 'Arizona Department of Health Services: licensed facilities (AZ Care Check)', 'https://azcarecheck.azdhs.gov/s/',
       'azcarecheck.azdhs.gov', 'azdhs', 'lookup', 'a health-care facility licence: its type (hospital, urgent care, clinic), status and address',
       'https://www.azdhs.gov/licensing/index.php', 'Jason through the CEO, 2026-11-28 (the listing audit, research/listing-audit-2026-11-27.md)',
       'W14 reads its robots.txt and terms first, and a person decides from them', UTC_TIMESTAMP()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM authority_sources WHERE state = 'AZ' AND url = 'https://azcarecheck.azdhs.gov/s/');
-- Switched on for ADHS's terms read only (Jason through the CEO, 2026-11-28: "use the crawler to scan those site if
-- allowed", and the session manager's ruling: reading robots.txt and the terms page is the "if allowed" check). The task
-- w14_terms is added on, a run task with 6 fetches (CrawlService gives a bulk task none). W14 comes off its shipped
-- pause only when no person paused it. The task reads only READER_SERVICES (azdhs), so W14's other sources stay as
-- they are. The way back is app/rollbacks/2026-11-28_w14_adhs.sql.
INSERT INTO crawl_capture_tasks (task, wf, name, what, from_where, mode, every_minutes, per_run, max_seconds, max_fetches, max_paid, enabled, sort, updated_at)
SELECT 'w14_terms', 'W14', 'W14: an authority source''s terms read',
       'A staged source''s robots.txt, terms page and own page read once by the crawler, for the IT Manager to decide (decisions/0164 7.1)',
       'the source''s own site (ADHS first)', 'run', 1440, 1, 600, 6, 0, 1, 90, NOW()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM crawl_capture_tasks WHERE task = 'w14_terms');
UPDATE crawl_workflows SET paused = 0, paused_why = NULL, paused_by = NULL, paused_at = NULL
 WHERE wf = 'W14' AND paused = 1 AND paused_by IS NULL;
