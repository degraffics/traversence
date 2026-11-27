-- J1 (Data & Content session, 2026-11-27): a region opens on its team-reviewed jurisdiction record, an attorney's review
-- advised, not required (Jason, 2026-11-27; decisions/0164 R8/R17 note). Add-only; safe to run twice.
-- 1. Who reviewed a record when a team role did it (decisions/0180 §5), beside reviewed_by (a person).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE jurisdictions ADD COLUMN reviewed_as VARCHAR(160) NOT NULL DEFAULT '''' AFTER reviewed_by', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jurisdictions' AND COLUMN_NAME = 'reviewed_as');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
-- 2. Colorado's and Utah's records, opened as drafts (0181 §3's launch area). A draft keeps the strictest rule.
INSERT IGNORE INTO jurisdictions (code, level, name, status) VALUES ('US-CO', 'state', 'Colorado', 'draft');
INSERT IGNORE INTO jurisdictions (code, level, name, status) VALUES ('US-UT', 'state', 'Utah', 'draft');
-- 3. Their laws, and New Mexico's missing one. Found by search on 2026-11-27, not yet read against their official text
--    (the statute sites weren't reachable from the build), so every record stays a draft until that read.
--    Each names its id (MAX(id)+1): on staging a records table copied by catch-up has its AUTO_INCREMENT behind its rows
--    (the session manager's rule until 0165's catch-up fix).
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-NM', 'Cultural Properties Act: confidentiality of site locations', 'NMSA 1978, § 18-6-11.1', 'Locations of archaeological resources held by public officials stay confidential unless releasing them serves the Act and puts no resource at risk.', 'Never publish a site location taken from state records. Found by search, not yet read against its official text.', 'site_location', 'https://law.justia.com/codes/new-mexico/2017/chapter-18/article-6/section-18-6-11.1/', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-NM' AND citation = 'NMSA 1978, § 18-6-11.1');
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-CO', 'Historical, Prehistoric, and Archaeological Resources', 'C.R.S. § 24-80-401 et seq.', 'Archaeological resources on state land, and the state archaeologist''s duties.', 'Never encourage digging or collecting, and no site locations from state records. Found by search, not yet read against its official text.', 'site_location', 'https://law.justia.com/codes/colorado/2016/title-24/state-history-archives-and-emblems/article-80/part-4/section-24-80-401', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-CO' AND citation = 'C.R.S. § 24-80-401 et seq.');
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-CO', 'Unmarked Human Graves', 'C.R.S. §§ 24-80-1301 to 1305', 'Unmarked human graves on all land that isn''t federal.', 'Never locate an unmarked grave. Found by search, not yet read against its official text.', 'burials', 'https://colorado.public.law/statutes/crs_24-80-1301', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-CO' AND citation = 'C.R.S. §§ 24-80-1301 to 1305');
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-UT', 'Antiquities', 'Utah Code §§ 9-8a-301 to 9-8a-308', 'Antiquities and archaeological sites on state land.', 'Never encourage digging, and no site locations. Found by search, not yet read against its official text.', 'site_location', 'https://le.utah.gov/xcode/Title9/Chapter8A/C9-8a-P3_2023050320230701.pdf', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-UT' AND citation = 'Utah Code §§ 9-8a-301 to 9-8a-308');
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-UT', 'Ancient human remains on non-federal land', 'Utah Code § 9-8a-309', 'Ancient human remains on land that is neither federal nor state land.', 'Never locate remains. Found by search, not yet read against its official text.', 'burials', 'https://le.utah.gov/xcode/Title9/Chapter8A/C9-8a-P3_2023050320230701.pdf', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-UT' AND citation = 'Utah Code § 9-8a-309');
INSERT INTO jurisdiction_laws (id, code, name, citation, protects, requires_us, effect, url, added_by)
  SELECT (SELECT COALESCE(MAX(l.id), 0) + 1 FROM jurisdiction_laws l), 'US-UT', 'Protected records: historic and prehistoric site locations', 'Utah Code § 63G-2-305', 'Records that would reveal the location of historic, prehistoric, paleontological or biological resources are protected.', 'Never publish a site location taken from state records. Found by search, not yet read against its official text (the paragraph number is not confirmed).', 'site_location', '', NULL
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM jurisdiction_laws WHERE code = 'US-UT' AND citation = 'Utah Code § 63G-2-305');
-- 4. Each new record says where its review stands (a note only; status stays draft).
UPDATE jurisdictions SET notes = 'Laws found by search on Nov 27, 2026 (the Data & Content session), and none is read against its official text yet, so the record stays a draft and the strictest rule applies. Still to confirm: the state''s consumer-privacy act and any public-records exemption for site locations.'
  WHERE code IN ('US-CO', 'US-UT') AND status = 'draft' AND (notes IS NULL OR notes = '');
