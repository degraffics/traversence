-- f16: per-user counter for current-password checks.
-- Safe to run twice (IF NOT EXISTS). No DELIMITER. No data changes.
-- There is no foreign key to users because the users table was not shown.
-- user_id is BIGINT UNSIGNED so it covers either INT or BIGINT ids.
CREATE TABLE IF NOT EXISTS password_attempt_counters (
    user_id BIGINT UNSIGNED NOT NULL,
    scope VARCHAR(32) NOT NULL,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    window_start INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
