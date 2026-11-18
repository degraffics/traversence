-- decisions/0172 §3.3: messages left on unclaimed listings (ListingMessages). Add-only; safe to run twice.
-- guest_inquiries: who left it (a member), where it stands on its route, the guest's email confirmation and say-so.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN user_id INT NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'user_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN leave_state VARCHAR(12) NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'leave_state');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN phone_ok TINYINT NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'phone_ok');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN confirm_hash CHAR(64) NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'confirm_hash');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN email_ok_at DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'email_ok_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN sent_at DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'sent_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'delivered_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD COLUMN screen_why VARCHAR(160) NULL DEFAULT NULL', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND COLUMN_NAME = 'screen_why');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD INDEX gi_leave (entity_id, leave_state)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND INDEX_NAME = 'gi_leave');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE guest_inquiries ADD INDEX gi_user (user_id)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_inquiries' AND INDEX_NAME = 'gi_user');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
-- the pace of requests to one listing's public email (first at once, then a notice per 5 waiting or after 10 days)
CREATE TABLE IF NOT EXISTS listing_requests (
  entity_id INT NOT NULL PRIMARY KEY,
  addr_hash CHAR(64) NOT NULL,
  first_sent_at DATETIME NULL DEFAULT NULL,
  last_notice_at DATETIME NULL DEFAULT NULL,
  notices INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  KEY lr_addr (addr_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- every email of this kind, for message control (complaints over 0.1% of the last 1,000, bounce surges) and the email's links
CREATE TABLE IF NOT EXISTS listing_request_sends (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_id INT NOT NULL,
  inquiry_id INT NULL DEFAULT NULL,
  what VARCHAR(10) NOT NULL,
  addr_hash CHAR(64) NOT NULL,
  outcome VARCHAR(12) NOT NULL DEFAULT 'sent',
  sent_at DATETIME NOT NULL,
  KEY lrs_addr (addr_hash),
  KEY lrs_entity (entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
