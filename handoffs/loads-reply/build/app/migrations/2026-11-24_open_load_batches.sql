-- A load's batch keys (IR-0001, 2026-11-24): each batch of a town's records the worker sends carries its number; one taken
-- again (its reply reached the worker too late, so it was sent once more) answers what it did then and does nothing twice.
-- Safe to run twice.
CREATE TABLE IF NOT EXISTS open_load_batches (
  dataset VARCHAR(20) NOT NULL,
  place_key VARCHAR(40) NOT NULL,
  release_id VARCHAR(40) NOT NULL,
  batch_no INT UNSIGNED NOT NULL,
  result VARCHAR(500) NOT NULL DEFAULT '',
  at DATETIME NOT NULL,
  PRIMARY KEY (dataset, place_key, release_id, batch_no),
  KEY (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
