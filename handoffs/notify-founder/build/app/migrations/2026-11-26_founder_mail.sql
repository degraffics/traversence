-- The site emails the Founder itself (Jason, 2026-11-25; FounderMail): what was sent, so each batch, decision and message
-- goes once. Each site its own (staging-tables: own); staging sends nothing. Safe to run twice.
CREATE TABLE IF NOT EXISTS founder_mail_sent (k VARCHAR(120) NOT NULL PRIMARY KEY, at DATETIME NOT NULL, users VARCHAR(120) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
