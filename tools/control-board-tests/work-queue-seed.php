<?php
// For work-queue.cjs and work-queue.php: the Work queue's migration run as the site runs it (DbUpdates::statements, one
// connection, twice: it must be safe to run again), then one task made a day and a half old with nobody on it (late), and
// a release named on another that's live (done by itself). php work-queue-seed.php <root>/app [migration file]
$app = $argv[1]; require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php'; $pdo = Database::connection();
$file = $argv[2] ?? $app . '/migrations/2026-11-24_work_queue.sql';
for ($i = 0; $i < 2; $i++) foreach (DbUpdates::statements((string) file_get_contents($file)) as $q) { $st = $pdo->query($q); if ($st) $st->closeCursor(); }
$pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (id INT AUTO_INCREMENT PRIMARY KEY, zip_name VARCHAR(190) NOT NULL DEFAULT '', state VARCHAR(20) NOT NULL DEFAULT '', live_at DATETIME NULL)");
$pdo->exec("INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source) VALUES (UTC_TIMESTAMP() - INTERVAL 36 HOUR, 'America/Phoenix', 'area:orders', 'Late test task', 'fix', 'Late test task', 'task', '', 'Jason (test)', 1, 'Nobody took it', 'work-queue-seed.php')");
$pdo->exec("INSERT INTO site_releases (zip_name, state, live_at) VALUES ('board-phone-header.zip', 'live', UTC_TIMESTAMP())");
echo (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE bucket = 'task'")->fetchColumn(), "\n";
