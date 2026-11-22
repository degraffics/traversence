-- card-fixes' proof on the real Parks page (harness only, never shipped): a St. Johns area and ~30 parks, memorials and
-- landmarks: Commons, stock and no photo; two memorials, one with its own photo; distances unknown, a ZIP's middle, a real
-- point under half a mile, and normal; more landmarks than one page.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS hubs (id INT PRIMARY KEY, slug VARCHAR(80), name VARCHAR(120), hub_number INT, description TEXT, status VARCHAR(20));
CREATE TABLE IF NOT EXISTS geo_hubs (id INT PRIMARY KEY, hub_id INT, slug VARCHAR(80), name VARCHAR(120));
CREATE TABLE IF NOT EXISTS micro_clusters (id INT PRIMARY KEY, geo_hub_id INT, slug VARCHAR(80), name VARCHAR(120), center_lat DECIMAL(10,6), center_lon DECIMAL(10,6), primary_zip VARCHAR(10));
CREATE TABLE IF NOT EXISTS zip_coordinates (id INT AUTO_INCREMENT PRIMARY KEY, zip VARCHAR(10), city VARCHAR(80), state VARCHAR(2), county VARCHAR(80), latitude DECIMAL(10,6), longitude DECIMAL(10,6), population INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS categories (id INT PRIMARY KEY, name VARCHAR(120), slug VARCHAR(120), parent_id INT NULL, is_active TINYINT DEFAULT 1);
CREATE TABLE IF NOT EXISTS entities (id INT PRIMARY KEY, name VARCHAR(190), entity_type VARCHAR(20), category_id INT, latitude DECIMAL(10,6), longitude DECIMAL(10,6), primary_zip VARCHAR(10), status VARCHAR(30), is_suppressed TINYINT DEFAULT 0, micro_cluster_id INT NULL);
CREATE TABLE IF NOT EXISTS entity_metadata (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, meta_key VARCHAR(80), meta_value MEDIUMTEXT, updated_at DATETIME NULL);
CREATE TABLE IF NOT EXISTS rec_places (ridb_id VARCHAR(40) PRIMARY KEY, name VARCHAR(190), state VARCHAR(2), city VARCHAR(80), summary TEXT, lat DECIMAL(10,6), lon DECIMAL(10,6), hidden TINYINT DEFAULT 0, kind VARCHAR(20), org VARCHAR(190));
CREATE TABLE IF NOT EXISTS landmarks (gnis_id INT PRIMARY KEY, name VARCHAR(190), fclass VARCHAR(40), state VARCHAR(2), county VARCHAR(80), lat DECIMAL(10,6), lon DECIMAL(10,6), zip5 VARCHAR(5), near_town VARCHAR(80), nation VARCHAR(120) DEFAULT '', qid VARCHAR(20) DEFAULT '', wd_desc TEXT, wiki_title VARCHAR(190) DEFAULT '', extract TEXT, image_url VARCHAR(400) DEFAULT '', image_page VARCHAR(400) DEFAULT '', image_credit VARCHAR(200) DEFAULT '', image_license VARCHAR(60) DEFAULT '', status VARCHAR(20), why VARCHAR(190) DEFAULT '', photo_source VARCHAR(40) DEFAULT '');
CREATE TABLE IF NOT EXISTS place_photos (id INT AUTO_INCREMENT PRIMARY KEY, owner_kind VARCHAR(20), owner_ref VARCHAR(40), image_url VARCHAR(400), image_page VARCHAR(400) DEFAULT '', image_credit VARCHAR(200) DEFAULT '', image_license VARCHAR(60) DEFAULT '', source VARCHAR(20) DEFAULT 'commons', tried_at DATETIME NULL, found_at DATETIME NULL);
CREATE TABLE IF NOT EXISTS hero_media (id INT AUTO_INCREMENT PRIMARY KEY, owner_kind VARCHAR(20), owner_ref VARCHAR(40), kind VARCHAR(20), path VARCHAR(400), sort INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS stock_images (id INT PRIMARY KEY, status VARCHAR(20), path VARCHAR(400), remote_url VARCHAR(400) DEFAULT '', card_path VARCHAR(400) DEFAULT '', title VARCHAR(190), licence VARCHAR(80), licence_url VARCHAR(400) DEFAULT '', author VARCHAR(120), source VARCHAR(40), source_url VARCHAR(400) DEFAULT '', place_key VARCHAR(60) DEFAULT '', place_scope VARCHAR(20) DEFAULT 'region', subjects VARCHAR(400));
CREATE TABLE IF NOT EXISTS stock_uses (id INT AUTO_INCREMENT PRIMARY KEY, slot VARCHAR(60), owner_ref VARCHAR(120), stock_id INT);

DELETE FROM hubs; DELETE FROM geo_hubs; DELETE FROM micro_clusters; DELETE FROM zip_coordinates; DELETE FROM categories; DELETE FROM entities; DELETE FROM entity_metadata;
DELETE FROM rec_places; DELETE FROM landmarks; DELETE FROM place_photos; DELETE FROM hero_media; DELETE FROM stock_images; DELETE FROM stock_uses;
INSERT INTO hubs VALUES (1, 'white-mountains', 'White Mountains', 1, '', 'active');
INSERT INTO geo_hubs VALUES (1, 1, 'st-johns-area', 'St. Johns area');
INSERT INTO micro_clusters VALUES (1, 1, 'st-johns', 'Greater St. Johns', 34.505800, -109.360900, '85936');
INSERT INTO zip_coordinates (zip, city, state, county, latitude, longitude, population) VALUES ('85936', 'St Johns', 'AZ', 'Apache', 34.505800, -109.360900, 3500),
  ('85901', 'Show Low', 'AZ', 'Navajo', 34.254200, -110.029800, 11000), ('85938', 'Springerville', 'AZ', 'Apache', 34.133600, -109.285900, 2000);
INSERT INTO categories VALUES (1, 'State Parks', 'state-parks', NULL, 1), (2, 'Monuments', 'monuments', NULL, 1), (3, 'State Recreation Areas', 'state-rec', NULL, 1), (4, 'National Parks', 'national-parks', NULL, 1);
-- listings: lat/lon (a ZIP's middle = no point of its own), what the card should show in the comment
INSERT INTO entities VALUES
 (101, 'Lyman Lake State Park', 'merchant', 1, 34.360000, -109.380000, '85936', 'unclaimed', 0, 1),          -- 10 mi, stock 1
 (102, 'St. Johns Town Park', 'merchant', 3, 34.505800, -109.360900, '85936', 'unclaimed', 0, 1),            -- its ZIP's middle: no distance, stock 2
 (103, 'Veterans Memorial Park', 'merchant', 2, 34.510000, -109.360900, '85936', 'unclaimed', 0, 1),         -- 0.29 mi: under ½ mi; its own photo
 (104, 'Navajo Code Talkers Memorial', 'merchant', 2, 35.300000, -109.200000, '85936', 'unclaimed', 0, 1),   -- 55 mi; a memorial with no photo: pattern
 (105, 'Fool Hollow Lake Recreation Area', 'merchant', 3, 34.270000, -110.060000, '85901', 'unclaimed', 0, 1), -- 43 mi; Commons CC BY-SA on a pale sky
 (106, 'Show Low Lake Park', 'merchant', 3, 34.254200, -110.029800, '85901', 'unclaimed', 0, 1),            -- its ZIP's middle 41 mi away: no distance
 (107, 'Becker Lake Wildlife Area', 'merchant', 3, 34.150000, -109.300000, '85938', 'unclaimed', 0, 1),
 (108, 'Big Lake Recreation Area', 'merchant', 3, 33.880000, -109.410000, '85938', 'unclaimed', 0, 1),
 (109, 'Greer Lakes State Park', 'merchant', 1, 34.020000, -109.460000, '85938', 'unclaimed', 0, 1),
 (110, 'Concho Lake Park', 'merchant', 3, 34.450000, -109.620000, '85936', 'unclaimed', 0, 1),
 (111, 'Petrified Forest Gateway Park', 'merchant', 1, 34.900000, -109.800000, '85936', 'unclaimed', 0, 1),
 (112, 'Homolovi State Park', 'merchant', 1, 35.030000, -110.640000, '85901', 'unclaimed', 0, 1),
 (113, 'Woodland Lake Park', 'merchant', 3, 34.120000, -109.960000, '85901', 'unclaimed', 0, 1),
 (114, 'Sipe White Mountain Wildlife Area', 'merchant', 3, 34.060000, -109.240000, '85938', 'unclaimed', 0, 1);
INSERT INTO entity_metadata (entity_id, meta_key, meta_value) SELECT id, 'state', '"AZ"' FROM entities;
INSERT INTO entity_metadata (entity_id, meta_key, meta_value) SELECT id, 'city', IF(primary_zip = '85901', '"Show Low"', IF(primary_zip = '85938', '"Springerville"', '"St. Johns"')) FROM entities;
INSERT INTO hero_media (owner_kind, owner_ref, kind, path, sort) VALUES ('listing', '103', 'photo', '/uploads/h/p2.jpg', 0);
INSERT INTO place_photos (owner_kind, owner_ref, image_url, image_page, image_credit, image_license, found_at) VALUES
 ('listing', '105', '/uploads/h/sky-pale.jpg', '', 'Ken Lund', 'CC BY-SA 2.0', NOW()),
 ('rec', 'r1', '/uploads/h/p6.jpg', '', 'National Park Service', 'Public domain', NOW()),
 ('rec', 'r3', '/uploads/h/p7.jpg', '', 'Jean-Christophe Benoist with a very long name for the ellipsis', 'cc-by-3.0', NOW());
-- National Park Service units (Recreation.gov): real points
INSERT INTO rec_places VALUES
 ('r1', 'Petrified Forest National Park', 'AZ', 'Holbrook', 'Fossilized logs and the Painted Desert; a monument to the old Route 66 road.', 34.910000, -109.810000, 0, 'recarea', 'National Park Service'),
 ('r2', 'Canyon de Chelly National Monument', 'AZ', 'Chinle', 'Canyons on Navajo Nation land, homes of the Diné.', 36.130000, -109.480000, 0, 'recarea', 'National Park Service'),
 ('r3', 'El Morro National Monument', 'NM', 'Ramah', 'A sandstone bluff with inscriptions.', 35.040000, -108.330000, 0, 'recarea', 'National Park Service'),
 ('r4', 'Hubbell Trading Post National Historic Site', 'AZ', 'Ganado', 'The oldest operating trading post on the Navajo Nation.', 35.720000, -109.560000, 0, 'recarea', 'National Park Service'),
 ('r5', 'Zuni-Cibola Trail', 'NM', 'Zuni', 'A trail through the Zuni and Cibola country.', 35.070000, -108.850000, 0, 'recarea', 'National Park Service');
-- stock library: four photos for 'national park' (the hero takes one), so later cards would repeat them
INSERT INTO stock_images VALUES
 (1, 'live', '/uploads/h/p0.jpg', '', '', 'Desert road', 'Unsplash License', '', 'Ann Lee', 'unsplash', '', '', 'region', 'national park,landscape'),
 (2, 'live', '/uploads/h/p1.jpg', '', '', 'Mesa', 'Unsplash License', '', 'Bob Ray', 'unsplash', '', '', 'region', 'national park,landscape'),
 (3, 'live', '/uploads/h/p3.jpg', '', '', 'Lake', 'Pexels License', '', 'Cy Doe', 'pexels', '', '', 'region', 'national park,landscape'),
 (4, 'live', '/uploads/h/p4.jpg', '', '', 'Forest', 'Unsplash License', '', 'Di Fox', 'unsplash', '', '', 'region', 'national park,landscape'),
 (5, 'live', '/uploads/h/p5.jpg', '', '', 'Canyon', 'Unsplash License', '', 'Ed Gray', 'unsplash', '', '', 'region', 'national park,landscape');
-- landmarks near St. Johns (Commons photos with their licences, some without), more than the 24 a page shows
INSERT INTO landmarks (gnis_id, name, fclass, state, county, lat, lon, near_town, wd_desc, image_url, image_credit, image_license, status) VALUES
 (9001, 'Concho Butte', 'Summit', 'AZ', 'Apache', 34.470, -109.600, 'Concho', 'a volcanic butte', '/uploads/h/sky-pale.jpg', 'Ken Lund', 'CC BY-SA 2.0', 'shown'),
 (9002, 'Little Colorado River Falls', 'Falls', 'AZ', 'Apache', 34.400, -109.350, 'St. Johns', 'falls on the Little Colorado', '/uploads/h/p6.jpg', 'NPS Photo', 'Public domain', 'shown'),
 (9003, 'Twin Buttes', 'Summit', 'AZ', 'Apache', 34.550, -109.200, 'St. Johns', 'twin volcanic buttes', '/uploads/h/p7.jpg', 'Daniel Mayer', 'cc-by-sa-4.0', 'shown'),
 (9004, 'Salado Springs', 'Spring', 'AZ', 'Apache', 34.420, -109.500, 'St. Johns', 'a spring', '/uploads/h/p0.jpg', '', 'CC0', 'shown');
INSERT INTO landmarks (gnis_id, name, fclass, state, county, lat, lon, near_town, wd_desc, image_url, image_credit, image_license, status)
  SELECT 9100 + n, CONCAT('Mesa ', n), 'Summit', 'AZ', 'Apache', 34.505 + n * 0.01, -109.36 - n * 0.01, 'St. Johns', 'a mesa', IF(n % 3 = 0, '/uploads/h/p1.jpg', ''), IF(n % 3 = 0, 'A. Photographer', ''), IF(n % 3 = 0, 'CC BY 4.0', ''), 'shown'
  FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
        UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26) t;
