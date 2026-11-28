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
