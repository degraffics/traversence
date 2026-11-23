<?php
// For board-rendered.cjs: a trip on W1, with a founder to receive it, so the board shows "1 tripped" in its engine and not
// the dispatcher's own "reached no one" trip besides (02 P11: a bench with no founder raises it on every trip).
// Run after the start schema and 2a's migration: php board-rendered-seed.php <root>/app
$app = $argv[1]; require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; $pdo = Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
if (!(int) $pdo->query("SELECT COUNT(*) FROM staff_accounts WHERE level = 'founder'")->fetchColumn())
    $pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'founder@example.test', 'Jason', 'active', 'founder', '', '')");
ControlAlarms::raise($pdo, 'wf:W1:test', 'wf:W1', 'trip', 'crash', 'W1 Listings: its runs failed', 'Its work waits.', 'Check and start again.', 'TimeoutError after 600 s', [], false, false);
$n = (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE akey = 'dispatch:nobody' AND cleared_at IS NULL")->fetchColumn();
echo $n ? "FAIL: the dispatcher's own trip was raised ($n)\n" : "seeded (a W1 trip, a founder)\n";
