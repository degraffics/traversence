-- Release 3f's harness seed (handoffs/design-harness/README.md): three staff logins. Passwords: TRV-0001 owner-password-123
-- (owner), TRV-0002 sam-password-1234 (manager, no authenticator app), TRV-0003 ada-password-1234 (admin, an executive, not an owner: sees Security).
DELETE FROM staff_accounts; DELETE FROM staff_log; DELETE FROM staff_settings; DELETE FROM users WHERE id IN (1,3,4);
CREATE TABLE IF NOT EXISTS staff_mail_requests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, staff_account_id INT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL DEFAULT '', contact VARCHAR(190) NOT NULL DEFAULT '', ip_hash CHAR(64) NOT NULL DEFAULT '', device VARCHAR(120) NOT NULL DEFAULT '', tz VARCHAR(64) NOT NULL DEFAULT '', sends INT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(12) NOT NULL DEFAULT 'open', outcome VARCHAR(300) NOT NULL DEFAULT '', done_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, done_at DATETIME NULL);
DELETE FROM staff_mail_requests; DELETE FROM pulse_marks WHERE item_key LIKE 'staffmail:%'; DELETE FROM pulse_notes WHERE item_key LIKE 'staffmail:%';
INSERT INTO users (id, email, display_name, is_admin, session_version) VALUES (1, 'trv-0001@staff.traversence.invalid', 'Jason Owner', 1, 0), (3, 'trv-0002@staff.traversence.invalid', 'Sam Rivera', 1, 0), (4, 'trv-0003@staff.traversence.invalid', 'Ada Admin', 1, 0);
INSERT INTO staff_accounts (id, staff_id, name, email, role, status, user_id, password_hash, last_sign_in_at, level) VALUES
 (1, 'TRV-0001', 'Jason Owner', 'jason.owner@trvgo.com', 'owner', 'active', 1, '$2y$10$geX/UHRR70iLchSjOt0TWOrEBX3zUbkbavy7hEpDxE1ARFqB8fpOW', NOW() - INTERVAL 1 DAY, 'founder'),
 (2, 'TRV-0002', 'Sam Rivera', 'sam.rivera@trvgo.com', 'manager', 'active', 3, '$2y$10$C9o4/IjtxBbKguWsU4Vx.uut7jpFrzspIv7kM.FpjdjoKu2hmMYTa', '2026-11-13 16:05:00', 'manager'),
 (3, 'TRV-0003', 'Ada Admin', 'ada.admin@trvgo.com', 'admin', 'active', 4, '$2y$10$xusesCo8tlMwvXfVf0ZeAOeXLenCaiJRBL6giDdw/S9RRIG.XhZ7C', NOW() - INTERVAL 2 DAY, 'executive');
-- the owner already has an authenticator app (no notice for them); Sam and Ada don't
UPDATE staff_accounts SET totp_secret = 'harness-sealed' WHERE id = 1;
