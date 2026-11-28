-- f20: counters for bearer-code rate limits on /listing/api/leave.php. Safe to run twice. No data changes.
CREATE TABLE IF NOT EXISTS listing_leave_throttle (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket VARCHAR(16) NOT NULL,
  subject CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_bucket_subject_time (bucket, subject, created_at),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
