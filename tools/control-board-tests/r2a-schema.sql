DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main; USE lv_main;
CREATE TABLE crawl_jobs (id INT PRIMARY KEY);
CREATE TABLE crawl_runs (id INT AUTO_INCREMENT PRIMARY KEY, run_key VARCHAR(80), worker_v VARCHAR(20), started_at DATETIME, ended_at DATETIME, status VARCHAR(20), budget_s INT, seconds INT, fetches INT, searches INT, why TEXT);
CREATE TABLE crawl_run_items (id INT AUTO_INCREMENT PRIMARY KEY, run_id INT, wf VARCHAR(4), task VARCHAR(40), outcome VARCHAR(20), allotted_s INT, seconds INT, fetches INT, searches INT, counts TEXT, why TEXT, at DATETIME);
CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20), is_locked TINYINT DEFAULT 0, trust_score FLOAT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY (entity_id, meta_key));
INSERT INTO entity_metadata VALUES (502, 'zz_phone', '"0"', 'owner', 1, NULL, NOW(), NOW());
CREATE TABLE search_index_state (k VARCHAR(80) PRIMARY KEY, v MEDIUMTEXT);
-- r2b (the board's Shut it down, Start it and the whole system in order) switches workflows and their tasks:
-- the switches and workflows as start-schema.sql has them (2026-11-19, so r2b runs on this schema alone)
CREATE TABLE automation_switches (kind VARCHAR(12), ref VARCHAR(40), paused TINYINT, why VARCHAR(255), by_uid INT, by_name VARCHAR(80), at DATETIME, PRIMARY KEY (kind, ref));
CREATE TABLE automation_log (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME, kind VARCHAR(12), ref VARCHAR(40), paused TINYINT, why VARCHAR(255), by_uid INT, by_name VARCHAR(80));
CREATE TABLE crawl_workflows (wf VARCHAR(4) PRIMARY KEY, name VARCHAR(80), paused TINYINT DEFAULT 0, paused_by INT, paused_at DATETIME, paused_why VARCHAR(255), share_pct INT DEFAULT 0, updated_at DATETIME);
CREATE TABLE crawl_capture_tasks (task VARCHAR(40) PRIMARY KEY, wf VARCHAR(4), mode VARCHAR(10) DEFAULT 'run', enabled TINYINT DEFAULT 1, every_minutes INT DEFAULT 60, per_run INT DEFAULT 5, max_seconds INT DEFAULT 60, max_fetches INT DEFAULT 0, sort INT DEFAULT 0, label VARCHAR(80) NOT NULL DEFAULT '', name VARCHAR(80) DEFAULT '', what VARCHAR(255) DEFAULT '', off_why VARCHAR(255), off_by INT, off_at DATETIME, last_served_at DATETIME, updated_at DATETIME);
INSERT INTO automation_switches VALUES ('master','all',1,'Paused by Jason, 2026-11-14, until the crawler review',NULL,'Jason',UTC_TIMESTAMP());
INSERT INTO crawl_workflows (wf, name, paused) VALUES ('W1','Listings',0),('W2','Keeping',0),('W3','Sources',1),('W4','Guides',0),('W5','On demand',0),('W6','Heritage',0),('W7','Journeys',0),('W8','Find it',0),('W9','Landmarks',0),('W10','Events',0),('W11','How-to',0),('W12','Search',0),('W13','Stories',0);
UPDATE crawl_workflows SET paused_why='Jason: old reason', paused_at=NOW() WHERE wf='W3';
INSERT INTO crawl_capture_tasks (task, wf, enabled) VALUES ('recheck','W2',0);
