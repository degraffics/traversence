DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main; USE lv_main;
CREATE TABLE crawl_jobs (id INT PRIMARY KEY);
CREATE TABLE crawl_runs (id INT AUTO_INCREMENT PRIMARY KEY, run_key VARCHAR(80), worker_v VARCHAR(20), started_at DATETIME, ended_at DATETIME, status VARCHAR(20), budget_s INT, seconds INT, fetches INT, searches INT, why TEXT);
CREATE TABLE crawl_run_items (id INT AUTO_INCREMENT PRIMARY KEY, run_id INT, wf VARCHAR(4), task VARCHAR(40), outcome VARCHAR(20), allotted_s INT, seconds INT, fetches INT, searches INT, counts TEXT, why TEXT, at DATETIME);
CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20), is_locked TINYINT DEFAULT 0, trust_score FLOAT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY (entity_id, meta_key));
INSERT INTO entity_metadata VALUES (502, 'zz_phone', '"0"', 'owner', 1, NULL, NOW(), NOW());
CREATE TABLE search_index_state (k VARCHAR(80) PRIMARY KEY, v MEDIUMTEXT);
