-- f15: server-side rate limit for resend-confirmation (per email + per IP).
-- Safe to run twice: CREATE TABLE IF NOT EXISTS only. No data is changed.
CREATE TABLE IF NOT EXISTS resend_confirmation_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kind       VARCHAR(8)      NOT NULL,
    key_hash   CHAR(64)        NOT NULL,
    created_at INT UNSIGNED    NOT NULL,
    KEY idx_rca_kind_key_time (kind, key_hash, created_at),
    KEY idx_rca_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
