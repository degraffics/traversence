-- The trip page (decisions/0174 §3, release 3): when each day starts, how long at each stop, and the place a trip was
-- planned for (its ready-made journeys offered as a start). Safe to run twice: each column is added only when missing.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE trips ADD COLUMN start_min SMALLINT UNSIGNED NOT NULL DEFAULT 540', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trips' AND COLUMN_NAME = 'start_min');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE trips ADD COLUMN place_key VARCHAR(40) NOT NULL DEFAULT \'\'', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trips' AND COLUMN_NAME = 'place_key');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE trip_stops ADD COLUMN stay SMALLINT UNSIGNED DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trip_stops' AND COLUMN_NAME = 'stay');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q
