-- M-sections test seed (tools/market-tests/m-sections.cjs): the design harness only, never live. Drops and remakes market_items and market_posts.
DROP TABLE IF EXISTS market_items; DROP TABLE IF EXISTS market_posts;
CREATE TABLE market_items (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191), about TEXT, kind VARCHAR(40), kind_data TEXT, category VARCHAR(100), price_mode VARCHAR(20), price_cents INT NULL, price_qty INT DEFAULT 1, place_key VARCHAR(40), place_label VARCHAR(120), geo_hub_id INT NULL, hub_id INT NULL, buttons VARCHAR(191), draft_hash VARCHAR(64), check_due DATETIME NULL, published_by INT NULL, updated_at DATETIME NULL, entity_id INT, product_id INT, published_at DATETIME, status VARCHAR(20) DEFAULT 'published');
CREATE TABLE market_posts (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20), title VARCHAR(191), about TEXT, category VARCHAR(100), fields TEXT, item_condition VARCHAR(40), price_mode VARCHAR(20), price_cents INT NULL, place_key VARCHAR(40), place_label VARCHAR(120), geo_hub_id INT NULL, hub_id INT NULL, status VARCHAR(20), offer_days INT NULL, ends_on DATE NULL, check_due DATETIME NULL, hold_note TEXT, user_id INT, created_at DATETIME, listed_at DATETIME, is_test INT DEFAULT 0, asked_at DATETIME NULL);
UPDATE geo_hubs SET name='Round Valley' WHERE id=1;
INSERT IGNORE INTO geo_hubs (id, hub_id, name, slug) VALUES (1, 1, 'Round Valley', 'round-valley');
SET @e = (SELECT MIN(id) FROM entities);
UPDATE entities SET is_suppressed = 0 WHERE id = @e;
INSERT INTO market_items (title, about, kind, category, price_mode, price_cents, place_key, place_label, geo_hub_id, hub_id, buttons, entity_id, product_id, published_at) VALUES
 ('Harvest box: apples and squash', 'TEST seed', 'harvest', 'Food', 'fixed', 3500, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 901, NOW() - INTERVAL 1 HOUR),
 ('Fall barn dance', 'TEST seed', 'ticket', 'Events', 'fixed', 1500, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 902, NOW() - INTERVAL 2 HOUR),
 ('Cabin by the river, a night', 'TEST seed', 'booking', 'Stays', 'fixed', 12000, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 903, NOW() - INTERVAL 3 HOUR),
 ('Custom branded sign', 'TEST seed', 'madetoorder', 'Crafts', 'ask', NULL, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 904, NOW() - INTERVAL 4 HOUR),
 ('Resold camp chair', 'TEST seed', 'physical', 'Outdoor', 'fixed', 2500, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 905, NOW() - INTERVAL 5 HOUR),
 ('Fence repair, by the hour', 'TEST seed', 'service', 'Services', 'fixed', 4000, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 906, NOW() - INTERVAL 6 HOUR),
 ('Harvest box from nowhere', 'TEST seed: no geo hub', 'harvest', 'Food', 'fixed', 3000, 'cluster:99', 'Elsewhere', NULL, NULL, 'message', @e, 907, NOW() - INTERVAL 7 HOUR),
 ('Old dance ticket (legacy kind)', 'TEST seed: legacy class', 'class', 'Events', 'fixed', 1000, 'cluster:1', 'Springerville, AZ', 1, 1, 'message', @e, 908, NOW() - INTERVAL 8 HOUR);
INSERT INTO market_posts (kind, title, about, category, price_mode, price_cents, place_key, place_label, geo_hub_id, hub_id, status, user_id, created_at, listed_at) VALUES
 ('item', 'Used saddle', 'TEST seed', 'Outdoor', 'fixed', 30000, 'cluster:1', 'Springerville, AZ', 1, 1, 'active', 1, NOW(), NOW() - INTERVAL 30 MINUTE);
CREATE TABLE IF NOT EXISTS market_item_photos (entity_id INT, product_id INT, gallery_item_id INT, live INT, sort INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS market_photos (post_id INT, gallery_item_id INT, sort INT DEFAULT 0);
